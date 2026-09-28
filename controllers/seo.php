<?php
/**
 * SEO endpoints: XML sitemap + robots.txt. Generated live from the DB so
 * they always reflect real published content (no fake URLs).
 */

declare(strict_types=1);

function sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $pdo = db();
    $urls = [];
    $urls[] = ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'];
    $urls[] = ['loc' => url('/shop'), 'priority' => '0.9', 'changefreq' => 'daily'];
    foreach (Category::all() as $c) {
        $urls[] = ['loc' => url('/category/' . $c['slug']), 'priority' => '0.7', 'changefreq' => 'weekly'];
    }
    // Published products, newest first, capped for performance
    $st = $pdo->query("SELECT slug, updated_at FROM products WHERE status='published' ORDER BY updated_at DESC LIMIT 5000");
    foreach ($st as $p2) {
        $urls[] = ['loc' => url('/product/' . $p2['slug']), 'lastmod' => date('Y-m-d', strtotime((string)$p2['updated_at'])), 'priority' => '0.8', 'changefreq' => 'weekly'];
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        echo '  <url><loc>' . e($u['loc']) . '</loc>';
        if (!empty($u['lastmod'])) echo '<lastmod>' . $u['lastmod'] . '</lastmod>';
        echo '<changefreq>' . $u['changefreq'] . '</changefreq><priority>' . $u['priority'] . '</priority></url>' . "\n";
    }
    echo '</urlset>';
    exit;
}

function robots_txt(): void
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\n";
    echo 'Sitemap: ' . url('/sitemap.xml') . "\n";
    exit;
}
