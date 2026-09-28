<?php
declare(strict_types=1);

class Coupon
{
    public static function all(): array
    {
        return db()->query('SELECT * FROM coupons ORDER BY created_at DESC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM coupons WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Server-side validation. Returns ['ok'=>bool,'error'=>?string,'discount'=>float] */
    public static function validate(string $code, array $cartItems, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?: db();
        $st = $pdo->prepare('SELECT * FROM coupons WHERE code=? AND is_active=1 LIMIT 1');
        $st->execute([strtoupper(trim($code))]);
        $c = $st->fetch();
        if (!$c) return ['ok'=>false,'error'=>'Invalid coupon code.','discount'=>0];

        $now = date('Y-m-d H:i:s');
        if ($c['starts_at'] && $now < $c['starts_at'])
            return ['ok'=>false,'error'=>'Coupon not active yet.','discount'=>0];
        if ($c['expires_at'] && $now > $c['expires_at'])
            return ['ok'=>false,'error'=>'Coupon has expired.','discount'=>0];
        if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit'])
            return ['ok'=>false,'error'=>'Coupon usage limit reached.','discount'=>0];

        $uid = current_user()['id'] ?? null;
        if ($c['per_user_limit'] !== null && $uid) {
            $u = $pdo->prepare('SELECT COUNT(*) FROM coupon_usage WHERE coupon_id=? AND user_id=?');
            $u->execute([$c['id'], $uid]);
            if ((int)$u->fetchColumn() >= (int)$c['per_user_limit'])
                return ['ok'=>false,'error'=>'You have already used this coupon.','discount'=>0];
        }

        // Eligible subtotal (respect product/category restriction)
        $eligible = 0.0;
        foreach ($cartItems as $it) {
            if ($c['product_id'] && (int)$c['product_id'] !== (int)$it['product_id']) continue;
            if ($c['category_id']) {
                $p = Product::find((int)$it['product_id']);
                if (!$p || (int)$p['category_id'] !== (int)$c['category_id']) continue;
            }
            $eligible += (float)$it['line_total'];
        }
        if ($eligible <= 0) return ['ok'=>false,'error'=>'Coupon does not apply to cart items.','discount'=>0];
        if ($eligible < (float)$c['min_order_amount'])
            return ['ok'=>false,'error'=>'Minimum order of ' . money((float)$c['min_order_amount']) . ' required.','discount'=>0];

        $discount = $c['discount_type'] === 'percent'
            ? round($eligible * ((float)$c['discount_value'] / 100), 2)
            : round(min((float)$c['discount_value'], $eligible), 2);
        if ($c['max_discount'] !== null && $c['discount_type'] === 'percent') {
            $discount = min($discount, (float)$c['max_discount']);
        }
        return ['ok'=>true,'error'=>null,'discount'=>round($discount,2),'coupon'=>$c];
    }

    public static function save(array $d, ?int $id = null): void
    {
        $fields = [
            strtoupper(trim($d['code'])), trim($d['description'] ?? '') ?: null,
            $d['discount_type'], (float)$d['discount_value'],
            (float)($d['min_order_amount'] ?: 0),
            $d['max_discount'] !== '' ? (float)$d['max_discount'] : null,
            $d['usage_limit'] !== '' ? (int)$d['usage_limit'] : null,
            $d['per_user_limit'] !== '' ? (int)$d['per_user_limit'] : null,
            $d['starts_at'] ?: null, $d['expires_at'] ?: null,
            $d['category_id'] ? (int)$d['category_id'] : null,
            $d['product_id'] ? (int)$d['product_id'] : null,
            (int)$d['is_active'],
        ];
        $pdo = db();
        if ($id) {
            $st = $pdo->prepare(
                'UPDATE coupons SET code=?,description=?,discount_type=?,discount_value=?,min_order_amount=?,
                 max_discount=?,usage_limit=?,per_user_limit=?,starts_at=?,expires_at=?,category_id=?,product_id=?,is_active=?
                 WHERE id=?');
            $st->execute(array_merge($fields, [$id]));
        } else {
            $st = $pdo->prepare(
                'INSERT INTO coupons (code,description,discount_type,discount_value,min_order_amount,max_discount,usage_limit,per_user_limit,starts_at,expires_at,category_id,product_id,is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute($fields);
        }
    }

    public static function delete(int $id): void
    {
        db()->prepare('DELETE FROM coupons WHERE id=?')->execute([$id]);
    }
}
