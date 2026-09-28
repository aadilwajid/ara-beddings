<?php
/**
 * Bootstrap: loads config, defines constants/helpers, connects DB.
 * Every entry script (index.php, api/*, admin/*) requires this file first.
 */

declare(strict_types=1);

$CONFIG = require dirname(__DIR__) . '/config/config.php';

define('APP_ENV',  $CONFIG['app']['env']);
define('APP_URL',  $CONFIG['app']['url']);
define('APP_KEY',  $CONFIG['app']['key']);
define('IS_PRODUCTION', APP_ENV === 'production');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart_lib.php';
require_once __DIR__ . '/shipping_lib.php';
require_once __DIR__ . '/ratelimit.php';

if (IS_PRODUCTION) {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');          // never show errors to users
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

/** Shared PDO instance (config injected once). */
function db(): PDO
{
    global $CONFIG;
    static $pdo = null;
    if ($pdo === null) $pdo = \db($CONFIG);
    return $pdo;
}

// Models
require_once dirname(__DIR__) . '/models/Product.php';
require_once dirname(__DIR__) . '/models/Category.php';
require_once dirname(__DIR__) . '/models/Cart.php';
require_once dirname(__DIR__) . '/models/Order.php';
require_once dirname(__DIR__) . '/models/Coupon.php';
require_once dirname(__DIR__) . '/models/Review.php';
require_once dirname(__DIR__) . '/models/User.php';
require_once dirname(__DIR__) . '/includes/mailer.php';
