<?php
/**
 * PDO singleton for MySQL. Works with any externally hosted MySQL
 * (PlanetScale, Railway, Aiven, RDS, ...) reachable from Vercel.
 * All queries in the app use prepared statements exclusively.
 */

declare(strict_types=1);

function db(array $config): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db']['host'],
        $config['db']['port'],
        $config['db']['database'],
        $config['db']['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='STRICT_TRANS_TABLES'",
        ]);
    } catch (PDOException $e) {
        // Never leak credentials/DSN to the client.
        error_log('DB connection failed: ' . $e->getMessage());
        if ($config['app']['env'] === 'production') {
            http_response_code(500);
            render_error_page(500, 'Service temporarily unavailable. Please try again later.');
        }
        throw new RuntimeException('Database connection failed. Check DB_* environment variables.');
    }
    return $pdo;
}

/** Small helper: run a prepared query and return the statement. */
function q(PDO $pdo, string $sql, array $params = []): PDOStatement
{
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st;
}
