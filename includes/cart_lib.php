<?php
/**
 * Cart token management for serverless: a signed cookie holds a random
 * cart token; the actual cart lives in MySQL (`carts` + `cart_items`).
 * On login the guest cart is merged into the user's existing cart.
 */

declare(strict_types=1);

function cart_token(): string
{
    $data = SecureCookie::read('cart');
    if (!empty($data['tok'])) return $data['tok'];
    $tok = bin2hex(random_bytes(16));
    SecureCookie::set('cart', ['tok' => $tok], 60 * 86400, IS_PRODUCTION);
    return $tok;
}

/** Ensure a DB cart row exists for this token (+user), return cart id. */
function ensure_cart(?int $userId = null): int
{
    $tok = cart_token();
    $pdo = db();
    $st = $pdo->prepare('SELECT id FROM carts WHERE session_token=? LIMIT 1');
    $st->execute([$tok]);
    if ($id = $st->fetchColumn()) {
        if ($userId) {
            $pdo->prepare('UPDATE carts SET user_id=? WHERE id=?')->execute([$userId, $id]);
        }
        return (int)$id;
    }
    $pdo->prepare('INSERT INTO carts (user_id, session_token) VALUES (?,?)')
        ->execute([$userId, $tok]);
    return (int)$pdo->lastInsertId();
}

/** Merge guest cart into the logged-in user's persistent cart after login. */
function merge_guest_cart(int $userId): void
{
    $pdo = db();
    $tok = cart_token();
    $guest = $pdo->prepare('SELECT id FROM carts WHERE session_token=? AND user_id IS NULL');
    $guest->execute([$tok]);
    $guestId = (int)($guest->fetchColumn() ?: 0);
    if (!$guestId) return;

    $own = $pdo->prepare('SELECT id FROM carts WHERE user_id=? AND session_token<>? ORDER BY id LIMIT 1');
    $own->execute([$userId, $tok]);
    $ownId = (int)($own->fetchColumn() ?: 0);

    if ($ownId) {
        // Move items; combine quantities on duplicate lines.
        $items = $pdo->prepare('SELECT product_id, variant_id, quantity FROM cart_items WHERE cart_id=?');
        $items->execute([$guestId]);
        foreach ($items as $it) {
            $pdo->prepare(
                'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
            )->execute([$ownId, $it['product_id'], $it['variant_id'], $it['quantity']]);
        }
        $pdo->prepare('DELETE FROM carts WHERE id=?')->execute([$guestId]);
    } else {
        $pdo->prepare('UPDATE carts SET user_id=? WHERE id=?')->execute([$userId, $guestId]);
    }
}
