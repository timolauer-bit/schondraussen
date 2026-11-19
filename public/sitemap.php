<?php
// Wird per .htaccess als /sitemap.xml ausgeliefert. Solange die Seite nicht öffentlich ist: 404.
// bootstrap.php zuerst laden, vor jeder Ausgabe (wie überall sonst).
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/seo.php';
require_once __DIR__ . '/lib/showcase.php';
if (!seo_public()) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); echo 'Not found'; exit; }

$paths = ['/', '/kontakt.php', '/impressum.php', '/datenschutz.php'];
if (showcase_public()) {
    $paths = array_merge($paths, ['/einblick/kalender', '/einblick/serien', '/einblick/community']);
    // Serienseiten aus beiden Quellen: aktuelle Wochenauswahl (wie auf /einblick/serien verlinkt) und alle gemerkten Serienseiten früherer Wochen
    $ser = [];
    try {
        $pdo = db();
        $snap = showcase_get($pdo);
        foreach (showcase_series_pages($pdo) as $r) $ser[(int) $r['id']] = (string) $r['name'];
        $ids = array_values(array_filter(array_map('intval', (array) ($snap['ids']['series'] ?? [])), fn($i) => $i > 0 && !isset($ser[$i])));
        if ($ids) foreach ($pdo->query('SELECT id,name FROM subjects WHERE id IN (' . implode(',', $ids) . ')')->fetchAll() as $r) $ser[(int) $r['id']] = (string) $r['name'];
    } catch (Throwable $e) {
        error_log('Schondraussen sitemap.php: Serienseiten fehlen, ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    }
    asort($ser, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($ser as $id => $name) $paths[] = showcase_series_path($id, $name);
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach (array_unique($paths) as $p) echo '<url><loc>' . htmlspecialchars(seo_base() . $p, ENT_XML1) . '</loc></url>' . "\n";
echo '</urlset>';
