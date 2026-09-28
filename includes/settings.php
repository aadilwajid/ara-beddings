<?php
/** Settings store backed by the `settings` table (cached per request). */

declare(strict_types=1);

function all_settings(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable) {
            // DB not yet installed — safe defaults keep error pages rendering.
        }
    }
    return $cache;
}

function setting(string $key, ?string $default = ''): ?string
{
    $v = all_settings()[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function set_setting(PDO $pdo, string $key, ?string $value): void
{
    $st = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $st->execute([$key, $value]);
}

/** Effective selling price of a base product row. */
function effective_price(array $p): float
{
    $sale = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
    $price = (float)$p['price'];
    return ($sale !== null && $sale > 0 && $sale < $price) ? $sale : $price;
}
