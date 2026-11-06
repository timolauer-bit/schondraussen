<?php
// Wird per .htaccess als /robots.txt ausgeliefert.
require_once __DIR__ . '/lib/seo.php';
header('Content-Type: text/plain; charset=utf-8');
if (seo_public()) {
    echo "User-agent: *\nDisallow: /api/\nDisallow: /approve.php\nDisallow: /unsubscribe.php\nDisallow: /migrate.php\n\nSitemap: " . seo_base() . "/sitemap.xml\n";
} else {
    // Bewusst KEIN Disallow: sonst kann Google das noindex nicht lesen.
    echo "User-agent: *\nAllow: /\n";
}
