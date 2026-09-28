<?php
/**
 * REST-style JSON API. Stateless: cart identity comes from the signed
 * cart cookie; every mutation verifies CSRF (double-submit token).
 */

declare(strict_types=1);

function api_handle(string $endpoint): never
{
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
        verify_csrf();
    }
    try {
        switch ($endpoint) {

            case 'cart':
                json_out(Cart::get());

            case 'cart/add': {
                $body = api_body();
                [$ok, $msg] = Cart::add(
                    (int)($body['product_id'] ?? 0),
                    !empty($body['variant_id']) ? (int)$body['variant_id'] : null,
                    (int)($body['quantity'] ?? 1)
                );
                $cart = Cart::get();
                json_out(['ok' => $ok, 'message' => $msg, 'cart_count' => $cart['count']], $ok ? 200 : 422);
            }

            case 'cart/update': {
                $body = api_body();
                [$ok, $msg] = Cart::setQty((int)($body['item_id'] ?? 0), (int)($body['quantity'] ?? 1));
                json_out(['ok' => $ok, 'message' => $msg, 'cart' => Cart::get()]);
            }

            case 'cart/remove': {
                $body = api_body();
                Cart::remove((int)($body['item_id'] ?? 0));
                json_out(['ok' => true, 'cart' => Cart::get()]);
            }

            case 'cart/clear':
                Cart::clear();
                json_out(['ok' => true, 'cart' => Cart::get()]);

            case 'cart/coupon': {
                $body = api_body();
                [$ok, $msg] = Cart::applyCoupon(isset($body['code']) ? (string)$body['code'] : '');
                json_out(['ok' => $ok, 'message' => $msg, 'cart' => Cart::get()], $ok ? 200 : 422);
            }

            case 'variants': {
                // Dynamic variant data for product page (prices/stock from DB)
                $pid = (int)($_GET['product_id'] ?? 0);
                $p = Product::find($pid);
                if (!$p || !$p['is_variable']) json_out(['variants' => []]);
                $out = [];
                foreach (Product::variants($pid) as $v) {
                    $out[] = [
                        'id' => (int)$v['id'], 'sku' => $v['sku'], 'name' => $v['name'],
                        'opts' => json_decode((string)$v['option_json'], true) ?: [],
                        'price' => effective_price(['price' => $v['price'], 'sale_price' => $v['sale_price']]),
                        'regular' => (float)$v['price'],
                        'sale' => $v['sale_price'] !== null ? (float)$v['sale_price'] : null,
                        'stock' => (int)$v['stock_quantity'], 'image' => $v['image'],
                    ];
                }
                json_out(['variants' => $out]);
            }

            case 'shipping-quote': {
                // Live checkout estimate — server computes, client never sets prices
                $subtotal = max(0, (float)($_GET['subtotal'] ?? 0));
                $city = (string)($_GET['city'] ?? '');
                $province = (string)($_GET['province'] ?? '');
                json_out([
                    'shipping' => Shipping::cost($subtotal, $city ?: null, $province ?: null),
                    'free_threshold' => (float)setting('free_shipping_threshold', '0'),
                ]);
            }

            case 'whatsapp': {
                // Build a support deep link without exposing private customer info
                $wa = preg_replace('/\D+/', '', (string)setting('whatsapp_number', ''));
                if (!$wa) json_out(['ok' => false, 'error' => 'WhatsApp not configured'], 422);
                $topic = mb_substr(trim((string)($_GET['topic'] ?? 'Hello')), 0, 100);
                $product = trim((string)($_GET['product'] ?? ''));
                $text = "Hi " . setting('store_name') . ", $topic";
                if ($product !== '' && preg_match('/^\/product\/[a-z0-9-]+$/i', $product)) {
                    $text .= ' — ' . url($product);
                }
                json_out(['ok' => true, 'link' => 'https://wa.me/' . $wa . '?text=' . rawurlencode($text)]);
            }

            case 'newsletter': {
                $body = api_body();
                $email = strtolower(trim((string)($body['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'Invalid email'], 422);
                if (!rate_limit('nl:' . ($_SERVER['REMOTE_ADDR'] ?? 'na'), 5, 3600)) {
                    json_out(['ok' => false, 'error' => 'Too many requests'], 429);
                }
                db()->prepare('INSERT INTO newsletter_subscribers (email) VALUES (?) ON DUPLICATE KEY UPDATE email=VALUES(email)')
                    ->execute([$email]);
                json_out(['ok' => true, 'message' => 'Subscribed!']);
            }

            default:
                json_out(['error' => 'Unknown endpoint'], 404);
        }
    } catch (Throwable $ex) {
        error_log('API error: ' . $ex->getMessage());
        json_out(['ok' => false, 'error' => IS_PRODUCTION ? 'Server error' : $ex->getMessage()], 500);
    }
}

function api_body(): array
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $j = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($j) ? $j : [];
    }
    return $_POST;
}
