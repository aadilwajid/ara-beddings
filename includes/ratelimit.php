<?php
/**
 * Front-controller router. On Vercel, vercel.json rewrites every dynamic
 * path to public/index.php; locally use:  php -S localhost:8000 public/index.php
 */

declare(strict_types=1);

function route_path(): string
{
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $p = rawurldecode($p);
    $p = '/' . trim($p, '/');
    return $p === '/' ? '/' : rtrim($p, '/');
}

/** Simple rate limiter using MySQL (stateless-safe): key + window count. */
function rate_limit(string $key, int $max, int $windowSec): bool
{
    // Use a dedicated table created on demand so schema stays clean for prod import too.
    db()->exec('CREATE TABLE IF NOT EXISTS rate_limits (
        rl_key VARCHAR(190) PRIMARY KEY, hits INT NOT NULL DEFAULT 1,
        first_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
    $pdo = db();
    $st = $pdo->prepare('SELECT hits, UNIX_TIMESTAMP(first_at) t FROM rate_limits WHERE rl_key=?');
    $st->execute([$key]);
    $row = $st->fetch();
    $now = time();
    if (!$row || ($now - (int)$row['t']) > $windowSec) {
        $pdo->prepare('REPLACE INTO rate_limits (rl_key,hits,first_at) VALUES (?,1,FROM_UNIXTIME(?))')->execute([$key,$now]);
        return true;
    }
    if ((int)$row['hits'] >= $max) return false;
    $pdo->prepare('UPDATE rate_limits SET hits=hits+1 WHERE rl_key=?')->execute([$key]);
    return true;
}
