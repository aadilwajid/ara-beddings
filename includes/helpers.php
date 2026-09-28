<?php
/** Tiny utility helpers used across views/controllers. */

declare(strict_types=1);

/** HTML-escape output (XSS protection). */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format money in store currency (PKR by default). */
function money(float|string|null $amount): string
{
    $symbol = setting('currency_symbol', 'Rs.');
    return $symbol . ' ' . number_format((float)$amount, 0);
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_api_request(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/api/');
}

function redirect(string $to, int $code = 302): never
{
    header('Location: $to', true, $code);
    exit;
}

function flash(string $msg, string $type = 'success'): void
{
    SecureCookie::set('flash', ['m' => $msg, 't' => $type], 120, IS_PRODUCTION);
}

function get_flash(): ?array
{
    $f = SecureCookie::read('flash');
    if ($f) SecureCookie::delete('flash', IS_PRODUCTION);
    return $f;
}

/** URL helper — absolute when given a path. */
function url(string $path = '/'): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/** Simple slugify for product/category names. */
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9\x{4e00}-\x{9fff}\s-]/u', '', $s) ?? '';
    $s = preg_replace('/[\s-]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item-' . substr(md5($s . microtime()), 0, 6);
}

/** Render an error page (used by router + exception handler). */
function render_error_page(int $code, string $message): void
{
    $file = dirname(__DIR__) . "/views/errors/{$code}.php";
    if (is_readable($file)) {
        require $file;
    } else {
        http_response_code($code);
        echo '<h1>Error ' . $code . '</h1><p>' . e($message) . '</p>';
    }
}

/** Validate Pakistani mobile-ish numbers: 03xx… or +923xx… */
function is_pk_phone(string $p): bool
{
    $p = preg_replace('/[\s-]/', '', $p) ?? '';
    return (bool)preg_match('/^(?:\+?92|0)3\d{9}$/', $p);
}

function old(string $key, string $default = ''): string
{
    return e($_POST[$key] ?? $default);
}

/** Pagination links renderer */
function paginate(int $total, int $perPage, int $current, string $baseUrl): string
{
    $pages = (int)ceil($total / max(1, $perPage));
    if ($pages <= 1) return '';
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $out = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        if ($pages > 9 && $i > 2 && $i < $pages - 1 && abs($i - $current) > 2) {
            if ($i === 3 || $i === $pages - 2) $out .= '<span class="dots">…</span>';
            continue;
        }
        $cls = $i === $current ? ' class="active"' : '';
        $out .= '<a href="' . e("{$baseUrl}{$sep}page={$i}") . '"' . $cls . '>' . $i . '</a>';
    }
    return $out . '</nav>';
}
