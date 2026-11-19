<?php
// Öffentliche Seite „Neuerscheinungen“: alle Termine einer Kalenderwoche aus den Listen der Nutzer (ohne Angaben zu Nutzern).
// Adressen (.htaccess): /neuerscheinungen = laufende Woche, /neuerscheinungen/2026-kw48 = bestimmte Woche (Archiv und die nächsten Wochen).
// Reines HTML ohne JavaScript, damit Suchmaschinen alles lesen können. Logik in lib/newreleases.php.
require __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/newreleases.php';
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$notFound = function () {
    http_response_code(404); header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>Nicht gefunden</title></head><body><p>Für diese Woche gibt es keine Seite. <a href="/neuerscheinungen">Zu den Neuerscheinungen dieser Woche</a></p></body></html>'; exit;
};
$u = null; try { $u = current_user(); } catch (Throwable $ex) {}
[$cy, $cw] = nr_current();
$wk = strtolower(trim((string) ($_GET['w'] ?? '')));
if ($wk === '') { $rp = strtok((string) ($_SERVER['REQUEST_URI'] ?? ''), '?'); if (preg_match('#^/neuerscheinungen/(\d{4}-kw\d{1,2})/?$#i', $rp, $m)) $wk = strtolower($m[1]); } // falls die Umschreibung fehlt (MultiViews)
if ($wk === '') { $y = $cy; $w = $cw; }
elseif (preg_match('/^(\d{4})-kw(\d{1,2})$/', $wk, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 53) {
    $y = (int) $m[1]; $w = (int) $m[2];
    [$a] = nr_bounds($y, $w); $chk = new DateTime($a, nr_tz()); if ((int) $chk->format('o') !== $y || (int) $chk->format('W') !== $w) $notFound(); // KW 53 gibt es nicht in jedem Jahr
    if ($y === $cy && $w === $cw) { header('Location: /neuerscheinungen', true, 301); exit; } // laufende Woche hat eine feste Adresse
    if ($wk !== nr_key($y, $w)) { header('Location: ' . nr_path($y, $w), true, 301); exit; } // 2026-kw8 → 2026-kw08
} else $notFound();

$pdo = db();
$rows = nr_rows($pdo, $y, $w);
if ($rows === null) $notFound();
$isCur = $y === $cy && $w === $cw; $off = nr_offset($y, $w);
$path = $isCur ? '/neuerscheinungen' : nr_path($y, $w);
[$from, $to] = nr_bounds($y, $w);
$MON = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
$DAY = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
$range = function (string $a, string $b) use ($MON): string {
    [$ay, $am, $ad] = array_map('intval', explode('-', $a)); [$by, $bm, $bd] = array_map('intval', explode('-', $b));
    if ($ay !== $by) return $ad . '. ' . $MON[$am] . ' ' . $ay . ' bis ' . $bd . '. ' . $MON[$bm] . ' ' . $by;
    if ($am !== $bm) return $ad . '. ' . $MON[$am] . ' bis ' . $bd . '. ' . $MON[$bm] . ' ' . $by;
    return $ad . '. bis ' . $bd . '. ' . $MON[$bm] . ' ' . $by;
};
$rangeTxt = $range($from, $to);

// Zählen und gruppieren: Tag → Kategorie → Eintrag → Titel
$cnt = array_fill_keys(array_keys(NR_TYPES), 0); $by = [];
foreach ($rows as $r) { $cnt[$r['ty']] = ($cnt[$r['ty']] ?? 0) + 1; $by[$r['d']][$r['ty']][$r['sid']]['n'] = $r['n']; $by[$r['d']][$r['ty']][$r['sid']]['x'][] = $r; }
ksort($by);
$serPages = []; try { $serPages = showcase_series_map($pdo); } catch (Throwable $ex) {}
$NOUN = ['author' => ['Buch', 'Bücher'], 'artist' => ['Musik-Veröffentlichung', 'Musik-Veröffentlichungen'], 'film' => ['Film', 'Filme'], 'series' => ['Serienfolge', 'Serienfolgen'], 'podcast' => ['Podcast-Folge', 'Podcast-Folgen']];
$parts = []; foreach (NR_TYPES as $t => $tn) if ($cnt[$t]) $parts[] = $cnt[$t] . ' ' . $NOUN[$t][$cnt[$t] === 1 ? 0 : 1];
$sumTxt = $parts ? implode(', ', $parts) : '';

$h1 = $isCur ? 'Neuerscheinungen diese Woche' : 'Neuerscheinungen KW ' . $w . ' ' . $y;
$title = ($isCur ? 'Neuerscheinungen diese Woche (KW ' . $w . ')' : 'Neuerscheinungen KW ' . $w . '/' . $y) . ': Bücher, Musik, Filme, Serien – Schondraussen.de';
$desc = 'Neuerscheinungen vom ' . $rangeTxt . ($sumTxt ? ': ' . $sumTxt : '') . '. Alle Termine der Woche auf einen Blick.';
if (mb_strlen($desc) > 160) { $desc = mb_substr($desc, 0, 157); $desc = mb_substr($desc, 0, (int) mb_strrpos($desc, ' ')) . ' …'; } // nie mitten im Wort abschneiden
$seo = seo_head($path, $title, $desc, !$u && count($rows) > 0); // leere Wochen nicht in den Index
$wl = function (int $yy, int $ww) use ($cy, $cw): string { return ($yy === $cy && $ww === $cw) ? '/neuerscheinungen' : nr_path($yy, $ww); };
[$py, $pw] = nr_shift($y, $w, -1); [$ny, $nw] = nr_shift($y, $w, 1);
$hasPrev = nr_offset($py, $pw) >= 0 || nr_snap_get($pdo, $py, $pw) !== null; $hasNext = nr_offset($ny, $nw) <= NR_FUTURE_WEEKS;
$css = __DIR__ . '/app.css';
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $e($title) ?></title>
<?= $seo ?>
<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="icon" type="image/png" sizes="32x32" href="/appicons/favicon-32.png"><link rel="shortcut icon" href="/favicon.ico"><meta name="theme-color" content="#f3ead6">
<link rel="stylesheet" href="/app.css?v=<?= filemtime($css) ?>">
<style>
.nrnav{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;margin:12px 0 4px}.nrnav a{text-decoration:none}
.nrday{margin-top:20px;padding-top:12px;border-top:1px solid var(--line)}.nrday:first-of-type{border-top:0;padding-top:0}
.nrday h2{font-size:19px;margin:0 0 4px}.nrcat{margin:10px 0 2px;font-size:15px;display:flex;align-items:center;gap:7px}
.nrlist{list-style:none;margin:2px 0;padding:0}.nrlist li{margin:3px 0;padding-left:15px}.nrlist .k{color:var(--muted);font-size:14px}
.nrbrand{font:800 clamp(24px,2.4vw,30px)/1.02 var(--font-display);letter-spacing:-.035em}.nrbrand .tld{color:var(--accent)}
.demopre a:not(.btn){color:var(--accent)}
.nrcta{margin:22px 0 4px}.nrcta .btn{display:inline-block;text-decoration:none;white-space:normal;height:auto;line-height:1.35;padding:10px 16px;text-align:center}
@media(max-width:760px){.masthead.demohead{flex-wrap:wrap}}.nrmore{margin-top:22px;font-size:14px}.nrmore a{white-space:nowrap}
</style>
</head><body class="demo">
<div class="wrap">
  <header class="masthead demohead"><div><a href="/" class="homelink nrbrand">Schondraussen<span class="tld">.de</span></a></div>
  <div class="whobox"><?php if ($u): ?><a class="btn" href="/">Zur App</a><?php else: ?><a class="btn" href="/#registrieren">Kostenlos registrieren</a><a class="btn ghost" href="/">Anmelden</a><?php endif; ?></div></header>
  <main class="demopre" style="margin-top:18px">
    <h1 style="margin:0 0 4px;font-size:clamp(24px,3.4vw,32px)"><?= $e($h1) ?></h1>
    <p style="margin:0"><b>KW <?= $w ?>: <?= $e($rangeTxt) ?></b><?= $sumTxt ? ' · ' . $e($sumTxt) : '' ?></p>
    <nav class="nrnav" aria-label="Wochen">
      <?php if ($hasPrev): ?><a class="btn ghost small" href="<?= $e($wl($py, $pw)) ?>">← KW <?= $pw ?></a><?php endif; ?>
      <?php if (!$isCur): ?><a class="btn ghost small" href="/neuerscheinungen">Diese Woche</a><?php endif; ?>
      <?php if ($hasNext): ?><a class="btn ghost small" href="<?= $e($wl($ny, $nw)) ?>">KW <?= $nw ?> →</a><?php endif; ?>
    </nav>
    <p style="color:var(--muted);font-size:14px;margin:6px 0 0">Alles, was Nutzerinnen und Nutzer von Schondraussen.de verfolgen, nach Tag sortiert. <?= $off < 0 ? 'Stand am Ende der Woche.' : 'Wird alle zwei Stunden aktualisiert.' ?></p>
<?php if (!$rows): ?>
    <p style="margin-top:18px">Für diese Woche sind noch keine Termine bekannt.</p>
<?php else: foreach ($by as $d => $cats):
    $dt = new DateTime($d, nr_tz()); $dn = (int) $dt->format('N'); ?>
    <section class="nrday">
      <h2><time datetime="<?= $e($d) ?>"><?= $DAY[$dn] ?>, <?= (int) $dt->format('j') ?>. <?= $MON[(int) $dt->format('n')] ?></time><?= $d === date('Y-m-d') ? ' <span class="pill" style="font-size:12px;vertical-align:middle">Heute</span>' : '' ?></h2>
<?php foreach (NR_TYPES as $t => $tn): if (empty($cats[$t])) continue; ?>
      <h3 class="nrcat"><i class="cdot c-<?= $t ?>" aria-hidden="true"></i><?= $e($tn) ?></h3>
      <ul class="nrlist">
<?php foreach ($cats[$t] as $sid => $g):
        $nm = isset($serPages[$sid]) && $t === 'series' ? '<a href="' . $e(showcase_series_path($sid, $g['n'])) . '">' . $e($g['n']) . '</a>' : $e($g['n']);
        $kOf = fn($r) => $r['k'] !== '' && !in_array($r['k'], ['Folge', 'Film', 'Buch'], true) ? ' <span class="k">(' . $e($r['k']) . ')</span>' : '';
        $ti = []; $tail = '';
        foreach ($g['x'] as $r) { if ($r['t'] !== '' && $r['t'] !== $r['n']) $ti[] = $e($r['t']) . $kOf($r); elseif ($tail === '') $tail = $kOf($r); } // Titel gleich Name (z. B. Kinostart): nur die Art hinter den Namen
        if ($t === 'podcast' && count($ti) > 3) $ti = array_merge(array_slice($ti, 0, 3), ['und ' . (count($ti) - 3) . ' weitere Folgen']); ?>
        <li><b><?= $nm ?></b><?= $tail ?><?= $ti ? ': ' . implode(' · ', $ti) : '' ?></li>
<?php endforeach; ?>
      </ul>
<?php endforeach; ?>
    </section>
<?php endforeach; endif; ?>
<?php if (!$u): ?>
    <p class="nrcta"><a class="btn" href="/#registrieren">Kostenlos registrieren und keine Neuerscheinung mehr verpassen</a></p>
    <p style="margin:6px 0 0;font-size:14px;color:var(--muted)">Mit Konto: eigene Liste mit Musiker:innen, Autor:innen, Filmen, Serien und Podcasts, Kalender, Erinnerung per Mail. <a href="/einblick/kalender">So sieht das aus</a>.</p>
<?php endif; ?>
<?php
$more = [];
for ($i = 1; $i <= NR_FUTURE_WEEKS; $i++) { [$yy, $ww] = nr_shift($cy, $cw, $i); $more[] = [$yy, $ww]; }
$past = array_values(array_filter(nr_archive($pdo), fn($x) => nr_offset($x[0], $x[1]) < 0));
?>
    <p class="nrmore">Weitere Wochen: <?php if (!$isCur) echo '<a href="/neuerscheinungen">Diese Woche</a> · '; echo implode(' · ', array_map(fn($x) => $x[0] === $y && $x[1] === $w ? '<b>KW ' . $x[1] . '</b>' : '<a href="' . $e(nr_path($x[0], $x[1])) . '">KW ' . $x[1] . ($x[0] !== $cy ? '/' . $x[0] : '') . '</a>', $more)); ?>
<?php if ($past): ?><br>Archiv: <?= implode(' · ', array_map(fn($x) => $x[0] === $y && $x[1] === $w ? '<b>KW ' . $x[1] . '/' . $x[0] . '</b>' : '<a href="' . $e(nr_path($x[0], $x[1])) . '">KW ' . $x[1] . '/' . $x[0] . '</a>', array_slice($past, 0, 60))) ?><?php endif; ?></p>
  </main>
  <footer>
    <p>Werke und Termine stammen aus <a href="https://musicbrainz.org" target="_blank" rel="noopener noreferrer">MusicBrainz</a> (Musik), der <a href="https://www.dnb.de" target="_blank" rel="noopener noreferrer">Deutschen Nationalbibliothek</a> und <a href="https://openlibrary.org" target="_blank" rel="noopener noreferrer">Open Library</a> (Bücher). Folgen und Sendetermine von Serien stammen von <a href="https://www.tvmaze.com" target="_blank" rel="noopener noreferrer">TVmaze</a> (<a href="https://creativecommons.org/licenses/by-sa/4.0/deed.de" target="_blank" rel="noopener noreferrer">CC BY-SA 4.0</a>). Filmdaten von <a href="https://www.themoviedb.org" target="_blank" rel="noopener noreferrer">TMDB</a> (dieses Produkt nutzt die TMDB-API, wird aber von TMDB weder unterstützt noch zertifiziert). Termine bitte vor dem Kauf prüfen.</p>
    <p><a href="/">Startseite</a> · <a href="/neuerscheinungen">Neuerscheinungen</a> · <a href="/impressum.php">Impressum</a> · <a href="/datenschutz.php">Datenschutz</a></p>
  </footer>
</div>
</body></html>
