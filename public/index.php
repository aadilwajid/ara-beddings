<?php
/**
 * Front controller — the ONLY PHP entry point routed on Vercel.
 * vercel.json rewrites all non-asset paths here; locally run:
 *   php -S localhost:8000 public/index.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/controllers/storefront.php';
require dirname(__DIR__) . '/controllers/account.php';
require dirname(__DIR__) . '/controllers/admin.php';
require dirname(__DIR__) . '/controllers/api.php';
require dirname(__DIR__) . '/controllers/seo.php';

set_exception_handler(function (Throwable $ex) {
    error_log('Unhandled: ' . $ex->getMessage() . ' @ ' . $ex->getFile() . ':' . $ex->getLine());
    if (is_api_request()) json_out(['ok' => false, 'error' => IS_PRODUCTION ? 'Server error' : $ex->getMessage()], 500);
    http_response_code(500);
    render_error_page(500, 'Something went wrong on our side. Please try again.');
});

$path = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$segs = $path === '/' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'];

/* ---------- API ---------- */
if ($segs && $segs[0] === 'api') {
    api_handle(implode('/', array_slice($segs, 1)));
}

/* ---------- SEO files ---------- */
if ($path === '/sitemap.xml') sitemap();
if ($path === '/robots.txt') robots_txt();

/* ---------- Storefront ---------- */
switch ($path) {
    case '/':            home(); exit;
    case '/shop':        shop([]); exit;
    case '/cart':        cart_page(); exit;
    case '/checkout':    $method === 'POST' ? place_order() : checkout_page(); exit;
    case '/order-placed':order_placed_page(); exit;
    case '/track':       track_page(); exit;
    case '/contact':     contact_page(); exit;
    case '/returns':     policy_page('returns'); exit;
    case '/terms':       policy_page('terms'); exit;
    case '/privacy':     policy_page('privacy'); exit;

    /* Auth */
    case '/login':            $method === 'POST' ? do_login() : login_page(); exit;
    case '/register':         $method === 'POST' ? do_register() : register_page(); exit;
    case '/logout':           logout(); exit;
    case '/forgot-password':  $method === 'POST' ? do_forgot() : forgot_page(); exit;
    case '/reset-password':   $method === 'POST' ? do_reset() : reset_page(); exit;

    /* Account */
    case '/account':            $method === 'POST' ? do_profile_update() : account_page(); exit;
    case '/account/addresses':  $method === 'POST' ? do_address_add() : addresses_page(); exit;
    case '/account/addresses/delete': do_address_delete(); exit;
    case '/wishlist':           wishlist_page(); exit;
    case '/wishlist/toggle':    do_wishlist_toggle(); exit;
}

/* Dynamic storefront routes */
if (($segs[0] ?? '') === 'product' && isset($segs[1]) && count($segs) === 2) {
    product_page($segs[1]); exit;
}
if (($segs[0] ?? '') === 'category' && isset($segs[1]) && count($segs) === 2) {
    category_page($segs[1]); exit;
}
if (($segs[0] ?? '') === 'search') { shop([]); exit; }
if (($segs[0] ?? '') === 'order' && isset($segs[1]) && count($segs) === 2) {
    order_detail_page($segs[1]); exit;
}

/* ---------- Admin ---------- */
if (($segs[0] ?? '') === 'admin') {
    $a = array_slice($segs, 1);
    $key = implode('/', $a);
    switch ($key) {
        case '': case 'dashboard': admin_dashboard(); exit;
        case 'login': view('admin/login', ['title' => 'Admin Login']); exit; // handled below too
        case 'products': admin_products(); exit;
        case 'products/add': admin_product_form(null); exit;
        case 'inventory': admin_inventory(); exit;
        case 'inventory/adjust': admin_stock_adjust(); exit;
        case 'categories': admin_categories(); exit;
        case 'categories/add': admin_category_form(null); exit;
        case 'attributes': admin_attributes(); exit;
        case 'attributes/save': admin_attribute_save(); exit;
        case 'attributes/value-save': admin_attribute_value_save(); exit;
        case 'orders': admin_orders(); exit;
        case 'customers': admin_customers(); exit;
        case 'coupons': $method === 'POST' ? admin_coupon_save() : admin_coupons(); exit;
        case 'coupons/add': admin_coupon_form(null); exit;
        case 'reviews': admin_reviews(); exit;
        case 'settings': $method === 'POST' ? admin_settings_save() : admin_settings(); exit;
        case 'bulk': admin_bulk(); exit;
        case 'product/save': admin_product_save(); exit;
    }
    // Patterned admin routes
    if (preg_match('/^products\/edit\/(\d+)$/', $key, $m)) { admin_product_form((int)$m[1]); exit; }
    if (preg_match('/^products\/delete\/(\d+)$/', $key, $m) && $method === 'POST') { admin_product_delete((int)$m[1]); exit; }
    if (preg_match('/^categories\/edit\/(\d+)$/', $key, $m)) { admin_category_form((int)$m[1]); exit; }
    if (preg_match('/^categories\/delete\/(\d+)$/', $key, $m) && $method === 'POST') { admin_category_delete((int)$m[1]); exit; }
    if (preg_match('/^attributes\/value-delete\/(\d+)$/', $key, $m) && $method === 'POST') { admin_attribute_value_delete((int)$m[1]); exit; }
    if (preg_match('/^orders\/(\d+)$/', $key, $m)) {
        $method === 'POST' ? admin_order_update((int)$m[1]) : admin_order_detail((int)$m[1]); exit;
    }
    if (preg_match('/^orders\/(\d+)\/invoice$/', $key, $m)) { admin_invoice((int)$m[1]); exit; }
    if (preg_match('/^customers\/(\d+)$/', $key, $m)) { admin_customer_detail((int)$m[1]); exit; }
    if (preg_match('/^coupons\/edit\/(\d+)$/', $key, $m)) { admin_coupon_form((int)$m[1]); exit; }
    if (preg_match('/^coupons\/delete\/(\d+)$/', $key, $m) && $method === 'POST') { admin_coupon_delete((int)$m[1]); exit; }
    if (preg_match('/^reviews\/(\d+)\/(approve|delete)$/', $key, $m) && $method === 'POST') {
        admin_review_action((int)$m[1], $m[2]); exit;
    }
    // Admin login uses the same /login handler but scoped path
    if ($key === 'login' && $method === 'POST') { do_login(); exit; }
}

/* ---------- 404 ---------- */
http_response_code(404);
render_error_page(404, 'The page you are looking for could not be found.');
