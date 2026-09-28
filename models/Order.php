<?php
/**
 * Order creation with atomic stock deduction (prevents overselling),
 * server-computed totals, inventory history and status timeline.
 */

declare(strict_types=1);

class Order
{
    public const STATUSES = [
        'pending','confirmed','processing','packed','shipped',
        'out_for_delivery','delivered','cancelled','returned','refunded',
    ];

    public static function label(string $s): string
    {
        return ucwords(str_replace('_', ' ', $s));
    }

    /**
     * Place an order from the current DB cart. Recomputes EVERYTHING
     * server-side inside a transaction with row locks.
     * Returns ['ok'=>bool,'error'=>?string,'order'=>array]
     */
    public static function place(array $data): array
    {
        $pdo = db();
        $user = current_user();
        $cartId = ensure_cart($user['id'] ?? null);

        foreach (['full_name','phone','province','city','address'] as $f) {
            if (empty(trim((string)($data[$f] ?? '')))) {
                return ['ok' => false, 'error' => 'Please fill all required fields.'];
            }
        }
        if (!is_pk_phone($data['phone'])) {
            return ['ok' => false, 'error' => 'Please enter a valid Pakistani mobile number (e.g. 03XXXXXXXXX).'];
        }
        $email = trim((string)($data['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email address looks invalid.'];
        }
        $methods = Shipping::methodsEnabled();
        $pm = (string)($data['payment_method'] ?? '');
        if (!isset($methods[$pm])) {
            return ['ok' => false, 'error' => 'Selected payment method is not available.'];
        }

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'SELECT ci.product_id, ci.variant_id, ci.quantity, p.name, p.slug, p.is_variable, p.status,
                        COALESCE(v.image, (SELECT image FROM product_images pi WHERE pi.product_id=p.id ORDER BY sort_order,id LIMIT 1)) AS image,
                        v.option_json, v.name AS variant_name,
                        COALESCE(v.sku, p.sku) AS sku,
                        COALESCE(v.price, p.price) AS price,
                        COALESCE(v.sale_price, p.sale_price) AS sale_price,
                        COALESCE(v.stock_quantity, p.stock_quantity) AS stock
                 FROM cart_items ci
                 JOIN products p ON p.id=ci.product_id
                 LEFT JOIN product_variants v ON v.id=ci.variant_id
                 WHERE ci.cart_id=? FOR UPDATE'
            );
            $st->execute([$cartId]);
            $lines = $st->fetchAll();
            if (!$lines) { $pdo->rollBack(); return ['ok'=>false,'error'=>'Your cart is empty.']; }

            $items = [];
            $subtotal = 0.0;
            foreach ($lines as $l) {
                if ($l['status'] !== 'published') {
                    throw new RuntimeException('"' . $l['name'] . '" is no longer available.');
                }
                if ($l['is_variable'] && !$l['variant_id']) {
                    throw new RuntimeException('Invalid cart line.');
                }
                // Re-check stock under row lock — prevents overselling.
                $sel = $pdo->prepare($l['variant_id']
                    ? 'SELECT stock_quantity FROM product_variants WHERE id=? FOR UPDATE'
                    : 'SELECT stock_quantity FROM products WHERE id=? FOR UPDATE');
                $sel->execute([(int)($l['variant_id'] ?: $l['product_id'])]);
                $stockNow = (int)$sel->fetchColumn();
                $qty = max(1, (int)$l['quantity']);
                if ($stockNow < $qty) {
                    throw new RuntimeException("Not enough stock for {$l['name']}"
                        . ($l['variant_name'] ? " ({$l['variant_name']})" : '') . ". Only $stockNow left.");
                }
                $regular = (float)$l['price'];
                $sale = $l['sale_price'] !== null ? (float)$l['sale_price'] : 0.0;
                $unit = ($sale > 0 && $sale < $regular) ? $sale : $regular;
                $lineTotal = round($unit * $qty, 2);
                $subtotal += $lineTotal;
                $opts = json_decode((string)$l['option_json'], true) ?: [];
                $items[] = [
                    'product_id' => (int)$l['product_id'],
                    'variant_id' => $l['variant_id'] ? (int)$l['variant_id'] : null,
                    'name'   => $l['name'],
                    'vname'  => $l['variant_name'] ?: ($opts ? implode(' / ', $opts) : null),
                    'sku'    => $l['sku'],
                    'image'  => $l['image'],
                    'unit'   => $unit,
                    'qty'    => $qty,
                    'total'  => $lineTotal,
                ];
            }

            // Coupon re-validation at order time
            $couponCodeRow = $pdo->prepare('SELECT coupon_code FROM carts WHERE id=?');
            $couponCodeRow->execute([$cartId]);
            $couponCode = $couponCodeRow->fetchColumn() ?: null;
            $discount = 0.0; $coupon = null;
            if ($couponCode) {
                $cartLike = array_map(fn($i) => ['product_id'=>$i['product_id'],'line_total'=>$i['total']], $items);
                $res = Coupon::validate((string)$couponCode, $cartLike, $pdo);
                if ($res['ok']) { $discount = $res['discount']; $coupon = $res['coupon']; }
                else { $couponCode = null; }
            }

            $afterDiscount = max(0.0, round($subtotal - $discount, 2));
            $shipping = Shipping::cost($afterDiscount, $data['city'], $data['province']);
            $tax = setting('tax_enabled','0') === '1'
                ? round($afterDiscount * ((float)setting('tax_rate','0') / 100), 2) : 0.0;
            $grand = round($afterDiscount + $shipping + $tax, 2);

            $orderNumber = self::generateOrderNumber($pdo);

            $ins = $pdo->prepare(
                'INSERT INTO orders (order_number,user_id,customer_name,customer_phone,customer_email,
                    province,city,area,address,landmark,postal_code,
                    subtotal,discount,shipping_cost,tax_amount,grand_total,coupon_code,
                    payment_method,payment_status,status,order_notes,billing_info)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',?,?)'
            );
            $ins->execute([
                $orderNumber, $user['id'] ?? null, mb_substr(trim($data['full_name']), 0, 120),
                preg_replace('/[^0-9+ -]/', '', $data['phone']), $email ?: null,
                trim($data['province']), trim($data['city']),
                $data['area'] ? trim($data['area']) : null,
                trim($data['address']),
                $data['landmark'] ? trim($data['landmark']) : null,
                $data['postal_code'] ? trim($data['postal_code']) : null,
                $subtotal, $discount, $shipping, $tax, $grand, $couponCode ?: null,
                $pm,
                // Manual methods are NEVER auto-marked paid; COD stays pending until collected.
                'pending',
                $data['notes'] ? mb_substr(trim($data['notes']), 0, 2000) : null,
                $data['billing_info'] ?? null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $oi = $pdo->prepare(
                'INSERT INTO order_items (order_id,product_id,variant_id,product_name,variant_name,sku,image,unit_price,quantity,line_total)
                 VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            $inv  = $pdo->prepare('UPDATE products SET stock_quantity = GREATEST(stock_quantity - ?, 0), sold_count = sold_count + ? WHERE id = ?');
            $invV = $pdo->prepare('UPDATE product_variants SET stock_quantity = GREATEST(stock_quantity - ?, 0) WHERE id = ?');
            $tx   = $pdo->prepare('INSERT INTO inventory_transactions (product_id,variant_id,change_qty,reason,reference,actor) VALUES (?,?,?,?,?,?)');

            $actor = $user ? ('user:' . $user['email']) : 'guest';
            foreach ($items as $it) {
                $oi->execute([$orderId, $it['product_id'], $it['variant_id'], $it['name'], $it['vname'],
                              $it['sku'], $it['image'], $it['unit'], $it['qty'], $it['total']]);
                if ($it['variant_id']) {
                    $invV->execute([$it['qty'], $it['variant_id']]);
                } else {
                    $inv->execute([$it['qty'], $it['qty'], $it['product_id']]);
                }
                $tx->execute([$it['product_id'], $it['variant_id'], -$it['qty'], 'sale', $orderNumber, $actor]);
                Product::recalcStockStatus((int)$it['product_id']);
            }

            $pdo->prepare("INSERT INTO payments (order_id,method,amount,status) VALUES (?,?,?,'pending')")
                ->execute([$orderId, $pm, $grand]);
            $pdo->prepare("INSERT INTO order_status_history (order_id,status,note) VALUES (?,'pending','Order placed')")
                ->execute([$orderId]);

            if ($coupon) {
                $pdo->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id=?')->execute([$coupon['id']]);
                $pdo->prepare('INSERT INTO coupon_usage (coupon_id,order_id,user_id) VALUES (?,?,?)')
                    ->execute([$coupon['id'], $orderId, $user['id'] ?? null]);
            }

            $pdo->prepare('DELETE FROM cart_items WHERE cart_id=?')->execute([$cartId]);
            $pdo->prepare('UPDATE carts SET coupon_code=NULL WHERE id=?')->execute([$cartId]);

            $pdo->commit();

            $order = self::find($orderId);
            self::sendNotifications($order);
            return ['ok' => true, 'error' => null, 'order' => $order];
        } catch (Throwable $ex) {
            $pdo->rollBack();
            error_log('Order placement failed: ' . $ex->getMessage());
            return ['ok' => false, 'error' => $ex->getMessage(), 'order' => null];
        }
    }

    private static function generateOrderNumber(PDO $pdo): string
    {
        do {
            $num = 'PK-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $st = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE order_number=?');
            $st->execute([$num]);
        } while ($st->fetchColumn());
        return $num;
    }

    public static function find(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM orders WHERE id=?');
        $st->execute([$id]);
        $o = $st->fetch();
        if ($o) $o['items'] = self::items((int)$o['id']);
        return $o ?: null;
    }

    public static function findByNumber(string $num): ?array
    {
        $st = db()->prepare('SELECT * FROM orders WHERE order_number=?');
        $st->execute([trim($num)]);
        $o = $st->fetch();
        if ($o) $o['items'] = self::items((int)$o['id']);
        return $o ?: null;
    }

    public static function items(int $orderId): array
    {
        $st = db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
        $st->execute([$orderId]);
        return $st->fetchAll();
    }

    public static function history(int $orderId): array
    {
        $st = db()->prepare('SELECT * FROM order_status_history WHERE order_id=? ORDER BY changed_at,id');
        $st->execute([$orderId]);
        return $st->fetchAll();
    }

    public static function forUser(int $userId, int $limit = 50): array
    {
        $st = db()->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT ?');
        $st->execute([$userId, $limit]);
        return $st->fetchAll();
    }

    /** Public tracking lookup: order number + phone OR email. */
    public static function track(string $orderNumber, string $identifier): ?array
    {
        $st = db()->prepare(
            'SELECT * FROM orders WHERE order_number=? AND (customer_phone=? OR customer_email=?) LIMIT 1'
        );
        $st->execute([
            trim($orderNumber),
            preg_replace('/[^0-9+ -]/', '', $identifier),
            strtolower(trim($identifier)),
        ]);
        $o = $st->fetch();
        if ($o) {
            $o['items'] = self::items((int)$o['id']);
            $o['history'] = self::history((int)$o['id']);
        }
        return $o ?: null;
    }

    /* ---------------- Admin operations ---------------- */

    public static function adminList(array $f = []): array
    {
        $where = ['1=1']; $params = [];
        if (!empty($f['status'])) { $where[] = 'status=?'; $params[] = $f['status']; }
        if (!empty($f['search'])) {
            $like = '%' . str_replace(['%','_'], ['\%','\_'], $f['search']) . '%';
            $where[] = '(order_number LIKE ? OR customer_phone LIKE ? OR customer_name LIKE ? OR customer_email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $w = implode(' AND ', $where);
        $limit  = max(1, min(100, (int)($f['per_page'] ?? 20)));
        $offset = max(0, (((int)($f['page'] ?? 1)) - 1) * $limit);
        $cnt = db()->prepare("SELECT COUNT(*) FROM orders WHERE $w"); $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();
        $st = db()->prepare("SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT $limit OFFSET $offset");
        $st->execute($params);
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    public static function setStatus(int $orderId, string $status, ?string $note, string $actor): bool
    {
        if (!in_array($status, self::STATUSES, true)) return false;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $st->execute([$orderId]);
            $o = $st->fetch();
            if (!$o) { $pdo->rollBack(); return false; }
            $old = $o['status'];
            $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$status, $orderId]);
            $pdo->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)')
                ->execute([$orderId, $status, $note ?: ("$actor changed status $old → $status")]);

            // Stock restoration on cancel/return/refund (only once per transition)
            if (in_array($status, ['cancelled','returned','refunded'], true)
                && !in_array($old, ['cancelled','returned','refunded'], true)) {
                self::restoreStock($orderId, $status, (string)$o['order_number'], $actor);
            }
            $pdo->commit();
            $order = self::find($orderId);
            if ($order) self::notifyStatusChange($order, $status);
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('setStatus: ' . $e->getMessage());
            return false;
        }
    }

    private static function restoreStock(int $orderId, string $status, string $orderNumber, string $actor): void
    {
        $pdo = db();
        $reason = $status === 'cancelled' ? 'cancel_restore' : 'return';
        $st = $pdo->prepare('SELECT * FROM order_items WHERE order_id=?');
        $st->execute([$orderId]);
        foreach ($st as $it) {
            if ($it['variant_id']) {
                $pdo->prepare('UPDATE product_variants SET stock_quantity = stock_quantity + ? WHERE id=?')
                    ->execute([(int)$it['quantity'], (int)$it['variant_id']]);
            } elseif ($it['product_id']) {
                $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity + ?, sold_count = GREATEST(sold_count - ?, 0) WHERE id=?')
                    ->execute([(int)$it['quantity'], (int)$it['quantity'], (int)$it['product_id']]);
            }
            if ($it['product_id']) {
                $pdo->prepare('INSERT INTO inventory_transactions (product_id,variant_id,change_qty,reason,reference,actor) VALUES (?,?,?,?,?,?)')
                    ->execute([(int)$it['product_id'],
                               $it['variant_id'] ? (int)$it['variant_id'] : null,
                               (int)$it['quantity'], $reason, $orderNumber, $actor]);
                Product::recalcStockStatus((int)$it['product_id']);
            }
        }
    }

    public static function setPayment(int $orderId, string $payStatus, ?string $reference, string $actor): bool
    {
        if (!in_array($payStatus, ['pending','paid','failed','refunded'], true)) return false;
        $pdo = db();
        $pdo->prepare('UPDATE orders SET payment_status=?, payment_reference=COALESCE(?,payment_reference) WHERE id=?')
            ->execute([$payStatus, $reference, $orderId]);
        $pdo->prepare('UPDATE payments SET status=?, reference=COALESCE(?,reference) WHERE order_id=? ORDER BY id DESC LIMIT 1')
            ->execute([$payStatus, $reference, $orderId]);
        $st = $pdo->prepare('SELECT order_number FROM orders WHERE id=?');
        $st->execute([$orderId]);
        $num = $st->fetchColumn() ?: ('order#' . $orderId);
        $cur = $pdo->prepare('SELECT status FROM orders WHERE id=?');
        $cur->execute([$orderId]);
        $pdo->prepare('INSERT INTO order_status_history (order_id,status,note) VALUES (?,?,?)')
            ->execute([$orderId, (string)($cur->fetchColumn() ?: 'pending'), "$actor marked payment $payStatus ($num)"]);
        return true;
    }

    public static function setTracking(int $orderId, ?string $tracking, ?string $courier): void
    {
        db()->prepare('UPDATE orders SET tracking_number=?, courier=? WHERE id=?')
            ->execute([$tracking ?: null, $courier ?: null, $orderId]);
    }

    /* ---------------- Notifications ---------------- */

    private static function sendNotifications(array $order): void
    {
        $store = setting('store_name', 'Store');
        $html = '<p>Thank you for your order! Summary:</p>'
              . '<p><b>Order:</b> ' . e($order['order_number']) . '<br>'
              . '<b>Total:</b> ' . money((float)$order['grand_total']) . '<br>'
              . '<b>Payment:</b> ' . e(Shipping::methodsEnabled()[$order['payment_method']] ?? $order['payment_method']) . '</p>'
              . '<p>We will contact you on ' . e($order['customer_phone']) . ' to confirm.</p>';
        if (!empty($order['customer_email'])) {
            Mailer::make()->send((string)$order['customer_email'],
                "$store — Order {$order['order_number']} received",
                mail_template('Order Received', $html));
        }
        $adminMail = setting('contact_email');
        if ($adminMail) {
            Mailer::make()->send((string)$adminMail, "$store — New order {$order['order_number']}",
                mail_template('New Order', '<p>' . e($order['customer_name']) . ' placed order '
                    . e($order['order_number']) . ' worth ' . money((float)$order['grand_total']) . '.</p>'));
        }
    }

    public static function notifyStatusChange(array $order, string $status): void
    {
        if (empty($order['customer_email'])) return;
        $store = setting('store_name', 'Store');
        $html = '<p>Your order <b>' . e($order['order_number']) . '</b> status is now: <b>' . e(self::label($status)) . '</b>.</p>'
              . ($order['tracking_number'] ? '<p>Tracking: ' . e((string)$order['tracking_number']) . ' (' . e((string)$order['courier']) . ')</p>' : '');
        Mailer::make()->send((string)$order['customer_email'],
            "$store — Order update: " . self::label($status),
            mail_template('Order Update', $html));
    }

    /** Dashboard stats — computed live from data, never faked. */
    public static function stats(): array
    {
        $pdo = db();
        $one = function (string $sql, array $p = []) use ($pdo) {
            $st = $pdo->prepare($sql); $st->execute($p); return $st->fetchColumn();
        };
        $today = date('Y-m-d');
        return [
            'sales_today'    => (float)$one("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE DATE(placed_at)=? AND status NOT IN ('cancelled','refunded')", [$today]),
            'revenue_total'  => (float)$one("SELECT COALESCE(SUM(grand_total),0) FROM orders WHERE status NOT IN ('cancelled','refunded')"),
            'orders_total'   => (int)$one('SELECT COUNT(*) FROM orders'),
            'orders_today'   => (int)$one('SELECT COUNT(*) FROM orders WHERE DATE(placed_at)=?', [$today]),
            'orders_pending' => (int)$one("SELECT COUNT(*) FROM orders WHERE status='pending'"),
            'customers'      => (int)$one("SELECT COUNT(*) FROM users WHERE role='customer'"),
            'products'       => (int)$one('SELECT COUNT(*) FROM products'),
            'low_stock'      => (int)$one("SELECT COUNT(*) FROM products WHERE status='published' AND stock_quantity <= low_stock_threshold"),
            'recent_orders'  => $pdo->query('SELECT id,order_number,customer_name,grand_total,status,payment_method,placed_at FROM orders ORDER BY id DESC LIMIT 8')->fetchAll(),
            'top_products'   => $pdo->query('SELECT p.id,p.name,p.sku,SUM(oi.quantity) q FROM order_items oi JOIN products p ON p.id=oi.product_id GROUP BY p.id ORDER BY q DESC LIMIT 5')->fetchAll(),
            'sales_7d'       => $pdo->query("SELECT DATE(placed_at) d, SUM(grand_total) t, COUNT(*) c FROM orders WHERE placed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status NOT IN ('cancelled','refunded') GROUP BY DATE(placed_at) ORDER BY d")->fetchAll(),
        ];
    }
}
