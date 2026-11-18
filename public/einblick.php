<?php
// Einblick für Gäste: Kalender, Serien und Community aus Sicht eines Beispielnutzers (ohne Anmeldung, nur ansehen).
// Adressen: /einblick/kalender, /einblick/serien, /einblick/community (.htaccess). Die Oberfläche ist dieselbe wie in der App (app.js im Einblick-Modus);
// zusätzlich steht der Inhalt schon im HTML, damit Suchmaschinen ihn ohne JavaScript lesen können.
require __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/showcase.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
$nonce = base64_encode(random_bytes(16));
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; manifest-src 'self'; worker-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$u = null; try { $u = current_user(); } catch (Throwable $e) {}
if (!showcase_viewer_ok($u)) {
    http_response_code(404); header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Nicht gefunden</title></head><body><p>Diese Seite gibt es nicht. <a href="/">Zur Startseite</a></p></body></html>'; exit;
}
$PAGES = [
    'kalender' => ['cal', 'Kalender', 'Neuerscheinungen im Kalender: Bücher, Musik, Filme, Serien und Podcasts', 'Kommende Bücher, Alben, Filmstarts, Serienfolgen und Podcast-Folgen im Kalender von Schondraussen.de. Ein Einblick, wie die Übersicht mit eigenem Konto aussieht.'],
    'serien' => ['series', 'Serien', 'Serien mit Staffeln, Folgen und Sendeterminen', 'Serien mit allen Staffeln, Folgen und kommenden Sendeterminen. Ein Einblick, wie die Serienseite von Schondraussen.de mit eigenem Konto aussieht.'],
    'community' => ['comm', 'Community', 'Community: die beliebtesten Bücher, Alben, Filme, Serien und Podcasts', 'Was die Community von Schondraussen.de am häufigsten verfolgt, neu entdeckt und schon gesehen, gelesen oder gehört hat.'],
];
$slug = (string) ($_GET['s'] ?? 'kalender'); if (!isset($PAGES[$slug])) $slug = 'kalender';
[$view, $navName, $h1, $desc] = $PAGES[$slug];
$pub = showcase_public();
$pdo = db(); $snap = showcase_get($pdo);
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$title = $h1 . ' – Schondraussen.de';
$seo = seo_head('/einblick/' . $slug, $title, $desc, !$u);
$ap = $pub ? '' : '/dev'; // bis zur Freigabe mit der Entwicklungsversion
if (!is_file(__DIR__ . $ap . '/app.js')) $ap = '';
$TN = ['author' => 'Bücher', 'artist' => 'Musik', 'film' => 'Filme', 'series' => 'Serien', 'podcast' => 'Podcasts'];
$MON = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
function sc_date(string $d, array $MON): string {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m)) return (int) $m[3] . '. ' . $MON[(int) $m[2]] . ' ' . $m[1];
    if (preg_match('/^(\d{4})-(\d{2})$/', $d, $m)) return $MON[(int) $m[2]] . ' ' . $m[1];
    return $d !== '' ? $d : 'Termin offen';
}
// Inhalt als einfaches HTML (wird im Browser sofort durch die echte Oberfläche ersetzt)
ob_start();
try {
    if ($view === 'cal') {
        $rels = showcase_calendar($pdo, $snap); $today = date('Y-m-d');
        $rels = array_values(array_filter($rels, fn($r) => (string) $r['release_date'] >= substr($today, 0, 7)));
        echo '<h2>Kommende Veröffentlichungen</h2><p>Angekündigte Bücher, Alben, Filme, Serienfolgen und Podcast-Folgen aus den Listen eines Beispielnutzers, nach Datum sortiert.</p>';
        $cur = null;
        foreach (array_slice($rels, 0, 200) as $r) {
            $d = (string) $r['release_date']; $mk = preg_match('/^\d{4}-\d{2}/', $d) ? substr($d, 0, 7) : 'offen';
            if ($mk !== $cur) { if ($cur !== null) echo '</ul>'; $cur = $mk; echo '<h3>' . $e($mk === 'offen' ? 'Termin noch offen' : $MON[(int) substr($mk, 5, 2)] . ' ' . substr($mk, 0, 4)) . '</h3><ul>'; }
            echo '<li><time datetime="' . $e($d) . '">' . $e(sc_date($d, $MON)) . '</time>: <b>' . $e($r['name']) . '</b> – ' . $e($r['title']) . ($r['kind'] && $r['kind'] !== 'Folge' ? ' (' . $e($r['kind']) . ')' : '') . ' · ' . $e($TN[$r['type']] ?? '') . '</li>';
        }
        if ($cur !== null) echo '</ul>'; else echo '<p>Gerade sind keine Termine bekannt.</p>';
    } elseif ($view === 'series') {
        $rows = array_values(array_filter(showcase_list($pdo, $snap), fn($r) => $r['type'] === 'series'));
        echo '<h2>Serien</h2><p>Serien aus der Liste eines Beispielnutzers mit Staffeln, Folgen und kommenden Sendeterminen (Daten von TVmaze).</p><ul>';
        foreach ($rows as $r) {
            $sum = trim(strip_tags((string) $r['summary'])); if (mb_strlen($sum) > 220) $sum = mb_substr($sum, 0, 217) . '…';
            echo '<li><b>' . $e($r['name']) . '</b>: ' . (int) $r['works'] . ' ' . ((int) $r['works'] === 1 ? 'Staffel' : 'Staffeln') . ((int) $r['episodes'] ? ', ' . (int) $r['episodes'] . ' Folgen' : '') . ($r['next_date'] ? ', nächster Termin ' . $e(sc_date((string) $r['next_date'], $MON)) : '') . ($sum !== '' ? '<br>' . $e($sum) : '') . '</li>';
        }
        echo '</ul>';
    } else {
        $c = $snap['comm'] ?? null;
        echo '<h2>Community</h2><p>Am häufigsten verfolgt, neu hinzugefügt und gesehen, gelesen oder gehört. Gezählt werden nur Nutzer, die der Community-Nutzung zugestimmt haben; Namen sind hier anonymisiert.</p>';
        if (is_array($c)) foreach ([['top', 'Top'], ['new', 'Newcomer der letzten 14 Tage'], ['seen', 'Meistgesehen, gelesen, gehört']] as [$k, $lbl]) {
            echo '<h3>' . $e($lbl) . '</h3>';
            foreach ($TN as $t => $tn) { $l = array_slice((array) ($c[$k][$t] ?? []), 0, 10); if (!$l) continue; echo '<h4>' . $e($tn) . '</h4><ol>'; foreach ($l as $r) echo '<li>' . $e($r['name']) . '</li>'; echo '</ol>'; }
        }
    }
} catch (Throwable $ex) { echo '<p>Der Einblick ist gerade nicht verfügbar.</p>'; }
$pre = ob_get_clean();
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $e($title) ?></title>
<?= $seo ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="icon" type="image/png" sizes="32x32" href="/appicons/favicon-32.png"><link rel="shortcut icon" href="/favicon.ico"><meta name="theme-color" content="#f3ead6">
<link rel="stylesheet" href="<?= $ap ?>/app.css?v=<?= filemtime(__DIR__ . $ap . '/app.css') ?>">
</head><body class="demo">
<div class="wrap">
<?php if (!$pub): ?>  <div class="warnbox previewbar demobar"><b>Vorschau für Admin und Alphatester:</b> So sehen Gäste den Einblick. Noch nicht öffentlich und für Suchmaschinen unsichtbar. <a href="/?gast=1">Startseite als Gast ansehen</a> · <a href="/">Zurück zur App</a></div>
<?php endif; ?>
  <header class="masthead demohead"><div><h1><a href="/" class="homelink">Schondraussen<span class="tld">.de</span></a></h1><p><b>Einblick:</b> So sieht die Seite mit eigenem Konto aus, hier aus Sicht eines Beispielnutzers. Die Daten sind echt, ansehen geht, ändern nur mit Konto.</p></div>
  <div class="whobox"><a class="btn" href="/#registrieren">Kostenlos registrieren</a><a class="btn ghost" href="/">Anmelden</a></div></header>
  <nav class="topbar" id="topbar" aria-label="Bereiche"></nav>
  <div id="appview" class="demopre"><h1 class="sr-title"><?= $e($h1) ?></h1><?= $pre ?>
    <p>Weitere Einblicke: <?php foreach ($PAGES as $k => $p) if ($k !== $slug) echo '<a href="/einblick/' . $k . '">' . $e($p[1]) . '</a> '; ?></p></div>
  <footer>
    <p>Werke und Termine stammen aus <a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a> (Musik) und <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> (Bücher). Folgen und Sendetermine von Serien stammen von <a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>). Filmdaten von <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">TMDB</a> (dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert). Termine bitte vor dem Kauf prüfen.</p>
    <p>Einblick: <a href="/einblick/kalender">Kalender</a> · <a href="/einblick/serien">Serien</a> · <a href="/einblick/community">Community</a></p>
    <p><a href="/">Startseite</a> · <a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></p>
  </footer>
</div>
<script nonce="<?= $nonce ?>">window.SD = <?= json_encode(showcase_sd($view), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= $ap ?>/app.js?v=<?= filemtime(__DIR__ . $ap . '/app.js') ?>"></script>
</body></html>
