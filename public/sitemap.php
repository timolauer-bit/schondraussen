<?php
// Wird per .htaccess als /sitemap.xml ausgeliefert. Solange die Seite nicht öffentlich ist: 404.
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/showcase.php';
if (!seo_public()) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Not found'; exit; }
header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (array_merge(['/', '/kontakt.php', '/impressum.php', '/datenschutz.php'], showcase_public() ? ['/einblick/kalender', '/einblick/serien', '/einblick/community'] : []) as $p) echo '<url><loc>' . htmlspecialchars(seo_base() . $p, ENT_XML1) . '</loc></url>';
if (showcase_public()) { try { require_once __DIR__ . '/lib/bootstrap.php'; foreach (showcase_series_pages(db()) as $r) echo '<url><loc>' . htmlspecialchars(seo_base() . showcase_series_path((int) $r['id'], (string) $r['name']), ENT_XML1) . '</loc></url>'; } catch (Throwable $e) {} }
echo '</urlset>';
