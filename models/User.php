<?php
/** Authentication + account data access. Passwords via password_hash(). */

declare(strict_types=1);

class User
{
    public static function findByEmail(string $email): ?array
    {
        $st = db()->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
        $st->execute([strtolower(trim($email))]);
        return $st->fetch() ?: null;
    }

    public static function register(string $name, string $email, string $phone, string $password): array
    {
        $name = mb_substr(trim($name), 0, 120);
        $email = strtolower(trim($email));
        if ($name === '' || mb_strlen($name) < 2) return [false, 'Please enter your full name.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return [false, 'Please enter a valid email address.'];
        if ($phone !== '' && !is_pk_phone($phone)) return [false, 'Phone number format looks invalid (e.g. 03XXXXXXXXX).'];
        if (strlen($password) < 8) return [false, 'Password must be at least 8 characters.'];
        if (self::findByEmail($email)) return [false, 'An account with this email already exists.'];

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo = db();
        $pdo->prepare('INSERT INTO users (name,email,phone,password_hash,role) VALUES (?,?,?,?,\'customer\')')
            ->execute([$name, $email, $phone ?: null, $hash]);
        $uid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO customers (user_id, whatsapp) VALUES (?,?)')
            ->execute([$uid, $phone ?: null]);
        return [true, 'Account created.', $uid];
    }

    public static function attempt(string $email, string $password): ?array
    {
        $u = self::findByEmail($email);
        if (!$u || !$u['is_active'] || !password_verify($password, $u['password_hash'])) {
            return null;
        }
        // Transparent rehash if algorithm cost changed
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db()->prepare('UPDATE users SET password_hash=? WHERE id=?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
        }
        return $u;
    }

    public static function createAdmin(string $name, string $email, string $password, string $role = 'super_admin'): array
    {
        if (!in_array($role, ['staff','admin','super_admin'], true)) return [false, 'Bad role'];
        if (strlen($password) < 10) return [false, 'Admin password must be at least 10 characters.'];
        if (self::findByEmail($email)) return [false, 'Email already in use.'];
        db()->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)')
            ->execute([trim($name), strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role]);
        return [true, 'Admin created: ' . $email];
    }

    public static function setResetToken(int $userId): string
    {
        $tok = bin2hex(random_bytes(24));
        db()->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?')
            ->execute([hash('sha256', $tok), date('Y-m-d H:i:s', time() + 3600), $userId]);
        return $tok;
    }

    public static function byResetToken(string $tok): ?array
    {
        $st = db()->prepare('SELECT * FROM users WHERE reset_token=? AND reset_expires>NOW() LIMIT 1');
        $st->execute([hash('sha256', $tok)]);
        return $st->fetch() ?: null;
    }

    public static function setPassword(int $userId, string $password): array
    {
        if (strlen($password) < 8) return [false, 'Password must be at least 8 characters.'];
        db()->prepare('UPDATE users SET password_hash=?, reset_token=NULL, reset_expires=NULL WHERE id=?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        return [true, 'Password updated.'];
    }

    public static function updateProfile(int $userId, string $name, string $phone): array
    {
        $name = mb_substr(trim($name), 0, 120);
        if ($name === '') return [false, 'Name is required.'];
        if ($phone !== '' && !is_pk_phone($phone)) return [false, 'Invalid phone format.'];
        db()->prepare('UPDATE users SET name=?, phone=? WHERE id=?')
            ->execute([$name, $phone ?: null, $userId]);
        return [true, 'Profile updated.'];
    }

    /* Addresses */
    public static function addresses(int $userId): array
    {
        $st = db()->prepare('SELECT * FROM addresses WHERE user_id=? ORDER BY is_default DESC, id DESC');
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function addAddress(int $userId, array $a): array
    {
        foreach (['full_name','phone','province','city','address'] as $f) {
            if (empty(trim((string)($a[$f] ?? '')))) return [false, 'Please fill all required address fields.'];
        }
        $pdo = db();
        if (!empty($a['is_default'])) {
            $pdo->prepare('UPDATE addresses SET is_default=0 WHERE user_id=?')->execute([$userId]);
        }
        $pdo->prepare(
            'INSERT INTO addresses (user_id,label,full_name,phone,province,city,area,address,landmark,postal_code,is_default)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            $userId, mb_substr(trim($a['label'] ?? 'Home'),0,60), mb_substr(trim($a['full_name']),0,120),
            trim($a['phone']), trim($a['province']), trim($a['city']),
            $a['area'] ? trim($a['area']) : null, trim($a['address']),
            $a['landmark'] ? trim($a['landmark']) : null,
            $a['postal_code'] ? trim($a['postal_code']) : null,
            !empty($a['is_default']) ? 1 : 0,
        ]);
        return [true, 'Address saved.'];
    }

    public static function deleteAddress(int $id, int $userId): void
    {
        db()->prepare('DELETE FROM addresses WHERE id=? AND user_id=?')->execute([$id, $userId]);
    }

    public static function listCustomers(int $page = 1, int $perPage = 25): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
        $st = db()->prepare(
            "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) order_count,
                    (SELECT COALESCE(SUM(o.grand_total),0) FROM orders o WHERE o.user_id=u.id AND o.status NOT IN ('cancelled','refunded')) spent
             FROM users u WHERE u.role='customer' ORDER BY u.id DESC LIMIT $perPage OFFSET $offset"
        );
        $st->execute();
        return ['items' => $st->fetchAll(), 'total' => $total];
    }

    /* Wishlist */
    public static function wishlistToggle(int $userId, int $productId): string
    {
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM wishlists WHERE user_id=? AND product_id=?');
        $st->execute([$userId, $productId]);
        if ($id = $st->fetchColumn()) {
            $pdo->prepare('DELETE FROM wishlists WHERE id=?')->execute([$id]);
            return 'removed';
        }
        $pdo->prepare('INSERT INTO wishlists (user_id,product_id) VALUES (?,?)')->execute([$userId, $productId]);
        return 'added';
    }

    public static function wishlistIds(int $userId): array
    {
        $st = db()->prepare('SELECT product_id FROM wishlists WHERE user_id=?');
        $st->execute([$userId]);
        return array_map('intval', array_column($st->fetchAll(), 'product_id'));
    }
}
