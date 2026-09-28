<?php
/**
 * Global configuration — reads everything from environment variables.
 * NEVER hard-code credentials here. On Vercel these come from Project
 * Environment Variables; locally from a .env file (git-ignored).
 */

declare(strict_types=1);

// ---- Minimal .env loader for local development (skipped on Vercel where
//      env vars are already present in $_ENV / getenv) ----
if (PHP_SAPI === 'cli' || getenv('VERCEL') !== '1') {
    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim($k); $v = trim(trim($v), "\"'");
            if (getenv($k) === false) putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

$config = [
    'app' => [
        'name'     => env('APP_NAME', 'Pakistani Store'),
        'url'      => rtrim((string)env('APP_URL', 'http://localhost:8000'), '/'),
        'env'      => env('APP_ENV', 'development'),          // development|production
        'key'      => env('APP_KEY', ''),                     // used for CSRF/session signing
        'timezone' => env('APP_TIMEZONE', 'Asia/Karachi'),
    ],
    'db' => [
        'host'     => env('DB_HOST', '127.0.0.1'),
        'port'     => (int)env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'ecommerce_pk'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset'  => 'utf8mb4',
    ],
    'mail' => [
        'host'     => env('MAIL_HOST', ''),
        'port'     => (int)env('MAIL_PORT', '587'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'from'     => env('MAIL_FROM', 'no-reply@example.com'),
    ],
];

// Fail fast if APP_KEY missing (needed for secure cookies/CSRF)
if (empty($config['app']['key'])) {
    if ($config['app']['env'] === 'production') {
        http_response_code(500);
        exit('Server configuration error: APP_KEY is not set.');
    }
    $config['app']['key'] = 'dev-only-insecure-key';
}

date_default_timezone_set($config['app']['timezone']);

return $config;
