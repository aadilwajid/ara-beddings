<?php
/**
 * Stateless "session" for Vercel serverless PHP.
 *
 * Vercel's PHP runtime is stateless — files written by one invocation may
 * not exist in the next, and /tmp is ephemeral. Therefore we do NOT rely on
 * filesystem session storage. Instead, auth state is kept in a signed,
 * HttpOnly cookie (HMAC with APP_KEY). Cart state lives in MySQL keyed by a
 * cart token cookie. This is production-safe on serverless.
 */

declare(strict_types=1);

final class SecureCookie
{
    public static function sign(string $payload, string $key): string
    {
        return hash_hmac('sha256', $payload, $key);
    }

    /** Set a signed cookie: value = base64(json).sig */
    public static function set(string $name, array $data, int $ttl, bool $production): void
    {
        $json  = json_encode($data);
        $b64   = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        $sig   = self::sign($b64, APP_KEY);
        setcookie($name, "$b64.$sig", [
            'expires'  => time() + $ttl,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $production,
        ]);
    }

    /** Read & verify a signed cookie. Returns array|null. */
    public static function read(string $name): ?array
    {
        $raw = $_COOKIE[$name] ?? '';
        if (!$raw || substr_count($raw, '.') !== 1) return null;
        [$b64, $sig] = explode('.', $raw, 2);
        if (!hash_equals(self::sign($b64, APP_KEY), $sig)) return null;
        $json = base64_decode(strtr($b64, '-_', '+/'), true);
        if ($json === false) return null;
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public static function delete(string $name, bool $production): void
    {
        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $production,
        ]);
    }
}

/** Current authenticated user (from signed auth cookie) or null. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) return $user;

    $data = SecureCookie::read('auth');
    if (!$data || empty($data['uid']) || empty($data['exp']) || $data['exp'] < time()) {
        return $user = null;
    }
    $stmt = db()->prepare('SELECT id,name,email,phone,role,is_active FROM users WHERE id=? LIMIT 1');
    $stmt->execute([(int)$data['uid']]);
    $row = $stmt->fetch();
    if (!$row || !$row['is_active']) return $user = null;
    return $user = $row;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: /login?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
    return $u;
}

function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        render_error_page(403, 'You are not authorized to access this area.');
        exit;
    }
    return $u;
}

/** CSRF helpers — token bound to user/session via signed cookie. */
function csrf_token(): string
{
    $t = SecureCookie::read('csrf');
    if (!$t || empty($t['tok'])) {
        $t = ['tok' => bin2hex(random_bytes(16))];
        SecureCookie::set('csrf', $t, 86400, IS_PRODUCTION);
    }
    return $t['tok'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $sent = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $stored = SecureCookie::read('csrf')['tok'] ?? '';
        if (!$sent || !$stored || !hash_equals($stored, $sent)) {
            http_response_code(419);
            if (is_api_request()) json_out(['error' => 'CSRF token mismatch'], 419);
            render_error_page(419, 'Session expired or invalid request. Go back, refresh and try again.');
            exit;
        }
    }
}
