<?php
/** Storefront page controllers (render views). */

declare(strict_types=1);

function view(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $template . '.php';
}

function home(): void
{
    $per = max(4, min(24, (int)setting('products_per_page', '12')));
    $featured = Product::browse(['featured' => 1, 'per_page' => $per]);
    $new      = Product::browse(['sort' => 'new', 'per_page' => $per]);
    $best     = Product::browse(['sort' => 'best', 'per_page' => $per]);
    view('storefront/home', [
        'sections'   => explode(',', (string)setting('show_home_sections', '')),
        'featured'   => $featured['items'],
        'newItems'   => $new['items'],
        'bestsellers'=> $best['items'],
        'categories' => Category::all(),
        'reviews'    => Review::latestFeatured(6),
        'title'      => setting('store_name') . ' — ' . setting('store_description'),
        'description'=> setting('store_description'),
    ]);
}

function shop(array $f): void
{
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(4, min(24, (int)setting('products_per_page', '12')));
    $res = Product::browse([
        'category_id' => $f['category_id'] ?? null,
        'search'      => trim((string)($_GET['q'] ?? '')),
        'sort'        => $_GET['sort'] ?? 'new',
        'page'        => $page,
        'per_page'    => $per,
        'min_price'   => $_GET['min'] ?? null,
        'max_price'   => $_GET['max'] ?? null,
    ]);
    view('storefront/shop', [
        'items'  => $res['items'],
        'total'  => $res['total'],
        'page'   => $page,
        'per'    => $per,
        'cats'   => Category::all(),
        'title'  => 'Shop — ' . setting('store_name'),
    ]);
}

function category_page(string $slug): void
{
    $cat = Category::bySlug($slug);
    if (!$cat) { render_error_page(404, 'Category not found.'); return; }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per  = max(4, min(24, (int)setting('products_per_page', '12')));
    $res = Product::browse(['category_id' => $cat['id'], 'page' => $page, 'per_page' => $per,
                            'sort' => $_GET['sort'] ?? 'new']);
    view('storefront/shop', [
        'items' => $res['items'], 'total' => $res['total'], 'page' => $page, 'per' => $per,
        'cats' => Category::all(), 'category' => $cat,
        'title' => ($cat['meta_title'] ?: $cat['name']) . ' — ' . setting('store_name'),
        'description' => $cat['meta_description'] ?: mb_substr((string)$cat['description'], 0, 160),
    ]);
}

function product_page(string $slug): void
{
    $p = Product::findBySlug($slug);
    if (!$p) { render_error_page(404, 'Product not found.'); return; }

    $variants = $p['is_variable'] ? Product::variants((int)$p['id']) : [];
    $options  = $p['is_variable'] ? Product::variantOptions((int)$p['id']) : [];
    $images   = Product::images((int)$p['id']);
    $related  = Product::related((int)$p['id'], $p['category_id'] ? (int)$p['category_id'] : null);
    $reviews  = Review::forProduct((int)$p['id']);
    $recently = recently_viewed_ids();

    // Track "recently viewed" in a signed cookie (stateless-safe)
    mark_recently_viewed((int)$p['id']);

    $cat = $p['category_id'] ? Category::byId((int)$p['category_id']) : null;

    // JSON payload for dynamic variant switching (prices/stock from DB)
    $variantPayload = [];
    foreach ($variants as $v) {
        $variantPayload[] = [
            'id'    => (int)$v['id'],
            'sku'   => $v['sku'],
            'name'  => $v['name'],
            'opts'  => $v['options'],
            'price' => effective_price(['price' => $v['price'], 'sale_price' => $v['sale_price']]),
            'regular' => (float)$v['price'],
            'sale'  => $v['sale_price'] !== null ? (float)$v['sale_price'] : null,
            'stock' => (int)$v['stock_quantity'],
            'image' => $v['image'],
        ];
    }

    view('storefront/product', [
        'p' => $p, 'images' => $images, 'variants' => $variantPayload, 'options' => $options,
        'related' => $related, 'reviews' => $reviews, 'category' => $cat,
        'recentlyIds' => $recently,
        'title' => $p['meta_title'] ?: ($p['name'] . ' — ' . setting('store_name')),
        'description' => $p['meta_description'] ?: mb_substr((string)$p['short_description'], 0, 160),
    ]);
}

function mark_recently_viewed(int $productId): void
{
    $data = SecureCookie::read('recent') ?: ['ids' => []];
    $ids = array_values(array_filter(array_unique(array_merge([$productId], (array)$data['ids']))));
    $ids = array_slice($ids, 0, 8);
    SecureCookie::set('recent', ['ids' => $ids], 30 * 86400, IS_PRODUCTION);
}

function recently_viewed_ids(): array
{
    $data = SecureCookie::read('recent');
    return $data['ids'] ?? [];
}

function cart_page(): void
{
    view('storefront/cart', ['cart' => Cart::get(), 'title' => 'Shopping Cart — ' . setting('store_name')]);
}

function checkout_page(): void
{
    $cart = Cart::get();
    if (!$cart['items']) redirect('/cart');
    $u = current_user();
    view('storefront/checkout', [
        'cart' => $cart,
        'methods' => Shipping::methodsEnabled(),
        'provinces' => pk_provinces(),
        'user' => $u,
        'addresses' => $u ? User::addresses((int)$u['id']) : [],
        'title' => 'Checkout — ' . setting('store_name'),
    ]);
}

function place_order(): void
{
    verify_csrf();
    if (!rate_limit('order:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 10, 60)) {
        flash('Too many attempts. Please wait a minute.', 'error');
        redirect('/checkout');
    }
    $res = Order::place([
        'full_name'  => $_POST['full_name'] ?? '',
        'phone'      => $_POST['phone'] ?? '',
        'email'      => $_POST['email'] ?? '',
        'province'   => $_POST['province'] ?? '',
        'city'       => $_POST['city'] ?? '',
        'area'       => $_POST['area'] ?? '',
        'address'    => $_POST['address'] ?? '',
        'landmark'   => $_POST['landmark'] ?? '',
        'postal_code'=> $_POST['postal_code'] ?? '',
        'notes'      => $_POST['notes'] ?? '',
        'payment_method' => $_POST['payment_method'] ?? '',
    ]);
    if (!$res['ok']) {
        flash((string)$res['error'], 'error');
        redirect('/checkout');
    }
    redirect('/order-placed?n=' . urlencode($res['order']['order_number']));
}

function order_placed_page(): void
{
    $num = trim((string)($_GET['n'] ?? ''));
    $o = $num ? Order::findByNumber($num) : null;
    // Only show if this visitor placed it (logged-in owner) — otherwise generic thanks page.
    $u = current_user();
    if ($o && $o['user_id'] && (!$u || (int)$o['user_id'] !== (int)$u['id'])) $o = null;
    if ($o && !$o['user_id'] && $u) $o = null; // guest order but logged-in viewer: don't leak
    view('storefront/order_placed', ['order' => $o, 'num' => $num, 'title' => 'Order Placed']);
}

function track_page(): void
{
    $order = null; $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (!rate_limit('track:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 15, 60)) {
            $error = 'Too many lookups. Please try again in a minute.';
        } else {
            $num = trim((string)($_POST['order_number'] ?? ''));
            $id  = trim((string)($_POST['identifier'] ?? ''));
            if ($num === '' || $id === '') $error = 'Please enter both your order number and phone/email.';
            else {
                $order = Order::track($num, $id);
                if (!$order) $error = 'No matching order found. Check the order number and phone/email used at checkout.';
            }
        }
    }
    view('storefront/track', ['order' => $order, 'error' => $error, 'title' => 'Track Your Order']);
}

function policy_page(string $which): void
{
    $map = [
        'returns'  => ['Return Policy',  setting('return_policy') ? str_replace('{days}', setting('return_policy_days','7'), (string)setting('return_policy')) : null],
        'terms'    => ['Terms & Conditions', setting('terms_conditions')],
        'privacy'  => ['Privacy Policy', setting('privacy_policy')],
    ];
    if (!isset($map[$which])) { render_error_page(404, 'Page not found.'); return; }
    view('storefront/policy', ['heading' => $map[$which][0], 'body' => $map[$which][1], 'title' => $map[$which][0]]);
}

function contact_page(): void
{
    view('storefront/contact', ['title' => 'Contact Us — ' . setting('store_name')]);
}

function pk_provinces(): array
{
    return ['Punjab','Sindh','Khyber Pakhtunkhwa','Balochistan','Gilgit-Baltistan','Azad Jammu & Kashmir','Islamabad Capital Territory'];
}
