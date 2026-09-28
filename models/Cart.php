<?php
/**
 * Server-side cart math. Prices & stock are ALWAYS re-read from the DB —
 * nothing sent by the browser is trusted.
 */

declare(strict_types=1);

class Cart
{
    /** Return normalized cart with server-computed totals. */
    public static function get(): array
    {
        $cartId = ensure_cart(current_user()['id'] ?? null);
        $pdo = db();
        $st = $pdo->prepare(
            'SELECT ci.*, p.name, p.slug, p.is_variable, p.status AS pstatus,
                    v.sku, v.option_json, v.name AS variant_name, v.price AS vprice, v.sale_price AS vsale,
                    v.stock_quantity AS vstock, v.image AS vimage
             FROM cart_items ci
             JOIN products p ON p.id = ci.product_id
             LEFT JOIN product_variants v ON v.id = ci.variant_id
             WHERE ci.cart_id=? ORDER BY ci.id DESC'
        );
        $st->execute([$cartId]);

        $items = [];
        $subtotal = 0.0;
        foreach ($st as $row) {
            // Drop unpublished products / deleted variants defensively
            if ($row['pstatus'] !== 'published') continue;
            if ($row['is_variable'] && !$row['variant_id']) continue;

            if ($row['variant_id']) {
                $price = self::pickPrice($row['vprice'], $row['vsale']);
                $stock = (int)$row['vstock'];
                $opts  = json_decode((string)$row['option_json'], true) ?: [];
                $label = $row['variant_name'] ?: implode(' / ', $opts);
            } else {
                $price = self::pickPrice($row['price'], $row['sale_price']);
                $stock = (int)$row['stock_quantity'];
                $label = null;
            }

            $qty = max(1, min((int)$row['quantity'], max($stock, 1)));
            if ($qty !== (int)$row['quantity']) {   // clamp to available stock
                $pdo->prepare('UPDATE cart_items SET quantity=? WHERE id=?')->execute([$qty, $row['id']]);
            }

            $line = round($price * $qty, 2);
            $subtotal += $line;

            $items[] = [
                'item_id'    => (int)$row['id'],
                'product_id' => (int)$row['product_id'],
                'variant_id' => $row['variant_id'] ? (int)$row['variant_id'] : null,
                'slug'       => $row['slug'],
                'name'       => $row['name'],
                'variant'    => $label,
                'sku'        => $row['sku'] ?? null,
                'image'      => $row['vimage'] ?: null,
                'price'      => $price,
                'quantity'   => $qty,
                'max_qty'    => $stock,
                'stock'      => $stock,
                'line_total' => $line,
            ];
        }

        // Coupon (stored on the cart row, validated live)
        $couponRow = $pdo->prepare('SELECT coupon_code FROM carts WHERE id=?');
        $couponRow->execute([$cartId]);
        $couponCode = $couponRow->fetchColumn() ?: null;
        $discount = 0.0; $couponMsg = null;
        if ($couponCode) {
            $res = Coupon::validate($couponCode, $items, $pdo);
            if ($res['ok']) { $discount = $res['discount']; }
            else { $couponMsg = $res['error']; }
        }

        $afterDiscount = max(0, $subtotal - $discount);
        $shipping = Shipping::cost($afterDiscount);
        $tax = 0.0;
        if (setting('tax_enabled', '0') === '1') {
            $tax = round($afterDiscount * ((float)setting('tax_rate', '0') / 100), 2);
        }
        $total = round($afterDiscount + $shipping + $tax, 2);

        return [
            'cart_id'   => $cartId,
            'items'     => $items,
            'count'     => array_sum(array_column($items, 'quantity')),
            'subtotal'  => round($subtotal, 2),
            'discount'  => round($discount, 2),
            'coupon'    => $discount > 0 ? $couponCode : ($couponCode ? null : $couponCode),
            'coupon_error' => $couponMsg,
            'shipping'  => $shipping,
            'tax'       => $tax,
            'total'     => $total,
        ];
    }

    private static function pickPrice($regular, $sale): float
    {
        $regular = (float)$regular;
        $sale = $sale !== null ? (float)$sale : 0.0;
        return ($sale > 0 && $sale < $regular) ? $sale : $regular;
    }

    /** Add line with server-side stock validation. Returns [ok,message]. */
    public static function add(int $productId, ?int $variantId, int $qty): array
    {
        $qty = max(1, min(99, $qty));
        $pdo = db();
        $p = Product::find($productId);
        if (!$p || $p['status'] !== 'published') return [false, 'Product not available.'];

        if ($p['is_variable']) {
            if (!$variantId) return [false, 'Please choose options first.'];
            $st = $pdo->prepare('SELECT * FROM product_variants WHERE id=? AND product_id=? AND is_active=1');
            $st->execute([$variantId, $productId]);
            $v = $st->fetch();
            if (!$v) return [false, 'Selected variant not available.'];
            $stock = (int)$v['stock_quantity'];
        } else {
            if ($variantId) return [false, 'This product has no variants.'];
            $stock = (int)$p['stock_quantity'];
        }
        if ($stock <= 0) return [false, 'Out of stock.'];

        $cartId = ensure_cart(current_user()['id'] ?? null);
        $st = $pdo->prepare('SELECT quantity FROM cart_items WHERE cart_id=? AND product_id=? AND variant_id <=> ?');
        $st->execute([$cartId, $productId, $variantId]);
        $existing = (int)($st->fetchColumn() ?: 0);

        if ($existing + $qty > $stock) {
            $room = $stock - $existing;
            if ($room <= 0) return [false, "Only $stock in stock (already in cart)."];
            $qty = $room;
        }
        $pdo->prepare(
            'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
        )->execute([$cartId, $productId, $variantId, $qty]);

        return [true, 'Added to cart.'];
    }

    public static function setQty(int $itemId, int $qty): array
    {
        $pdo = db();
        $cartId = ensure_cart(current_user()['id'] ?? null);
        $st = $pdo->prepare(
            'SELECT ci.variant_id, COALESCE(v.stock_quantity, p.stock_quantity) stock
             FROM cart_items ci JOIN products p ON p.id=ci.product_id
             LEFT JOIN product_variants v ON v.id=ci.variant_id
             WHERE ci.id=? AND ci.cart_id=?'
        );
        $st->execute([$itemId, $cartId]);
        $row = $st->fetch();
        if (!$row) return [false, 'Item not in cart.'];

        $qty = (int)$qty;
        if ($qty <= 0) {
            $pdo->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?')->execute([$itemId, $cartId]);
            return [true, 'Removed.'];
        }
        $stock = (int)$row['stock'];
        if ($qty > $stock) {
            $pdo->prepare('UPDATE cart_items SET quantity=? WHERE id=?')->execute([$stock, $itemId]);
            return [true, "Only $stock available."];
        }
        $pdo->prepare('UPDATE cart_items SET quantity=? WHERE id=? AND cart_id=?')->execute([$qty, $itemId, $cartId]);
        return [true, 'Updated.'];
    }

    public static function remove(int $itemId): void
    {
        $cartId = ensure_cart(current_user()['id'] ?? null);
        db()->prepare('DELETE FROM cart_items WHERE id=? AND cart_id=?')->execute([$itemId, $cartId]);
    }

    public static function clear(): void
    {
        $cartId = ensure_cart(current_user()['id'] ?? null);
        db()->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);
    }

    public static function applyCoupon(?string $code): array
    {
        $cartId = ensure_cart(current_user()['id'] ?? null);
        $pdo = db();
        if ($code === null || $code === '') {
            $pdo->prepare('UPDATE carts SET coupon_code=NULL WHERE id=?')->execute([$cartId]);
            return [true, 'Coupon removed.'];
        }
        $code = strtoupper(trim($code));
        $data = self::get();
        $res = Coupon::validate($code, $data['items'], $pdo);
        if (!$res['ok']) return [false, $res['error']];
        $pdo->prepare('UPDATE carts SET coupon_code=? WHERE id=?')->execute([$code, $cartId]);
        return [true, 'Coupon applied.'];
    }
}
