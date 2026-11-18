<?php
// Einblick für Gäste (/einblick/kalender, /einblick/serien, /einblick/community): die Seite aus Sicht eines ausgedachten Beispielnutzers.
// Die Auswahl der Einträge (je Kategorie 10) und die Community-Übersicht werden einmal pro Woche neu berechnet und in news_cache gespeichert;
// Termine, Folgen und Werke der ausgewählten Einträge kommen bei jedem Aufruf frisch aus der Datenbank.
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/watchdog.php';

// Solange false: nur Admin und Gruppen mit dem Recht „Entwicklerversion nutzen“ (Alphatester) sehen die Seiten, für Google bleiben sie unsichtbar.
// Zur Freigabe auf true setzen (dann auch Startseite-Links, Sitemap und robots.txt aktiv).
const SHOWCASE_PUBLIC = false;
const SHOWCASE_KEY = 'showcase';
const SHOWCASE_TYPES = ['author', 'artist', 'film', 'series', 'podcast'];

function showcase_public(): bool { return SHOWCASE_PUBLIC; }
/** Darf der aktuelle Besucher die Einblick-Seiten sehen? Öffentlich: alle. Vorher: Admin und Recht „Entwicklerversion nutzen“. */
function showcase_viewer_ok(?array $u): bool { return showcase_public() || ($u && !empty($u['approved']) && perm($u, 'devmode')); }

/** Gespeicherte Auswahl lesen; fehlt sie oder ist sie älter als 8 Tage, neu berechnen (sonst wählt der Cron montags neu). */
function showcase_get(PDO $pdo, bool $force = false): array {
    $row = null;
    try { $s = $pdo->prepare('SELECT data, TIMESTAMPDIFF(HOUR, fetched_at, NOW()) AS age FROM news_cache WHERE k=?'); $s->execute([SHOWCASE_KEY]); $row = $s->fetch(); } catch (Throwable $e) {}
    $d = $row ? json_decode((string) $row['data'], true) : null;
    if (!$force && is_array($d) && (int) $row['age'] < 8 * 24) return $d; // der Cron wählt montags neu, das hier ist nur das Sicherheitsnetz
    try { $d = showcase_build($pdo); $pdo->prepare('REPLACE INTO news_cache (k,data) VALUES (?,?)')->execute([SHOWCASE_KEY, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]); }
    catch (Throwable $e) { if (!is_array($d)) $d = ['ids' => array_fill_keys(SHOWCASE_TYPES, []), 'comm' => null, 'built' => '']; }
    return $d;
}
/** Für den Cron: einmal pro Woche (montags ab 4 Uhr) neu auswählen. */
function showcase_weekly(PDO $pdo): string {
    $now = new DateTime('now', new DateTimeZone('Europe/Berlin')); $week = $now->format('o-W');
    $cur = state_get($pdo, 'showcase_week');
    if ($cur && $cur['v'] === $week) return 'diese Woche schon ausgewählt';
    if ($cur && ($now->format('N') !== '1' || (int) $now->format('G') < 4)) return 'nicht fällig';
    $d = showcase_get($pdo, true); state_set($pdo, 'showcase_week', $week);
    $n = 0; foreach ($d['ids'] ?? [] as $l) $n += count($l);
    return 'neu ausgewählt (' . $n . ' Einträge)';
}

/** Auswahl: je Kategorie bis zu 10 Einträge, davon möglichst 5 mit kommenden Terminen (Podcasts: Folgen der letzten 5 Tage),
 *  der Rest beliebt (auf vielen Listen) oder umfangreich (viele Bücher, Folgen, Filme). Aus je 15 Kandidaten wird pro Woche zufällig gezogen, damit sich die Seiten ändern. */
function showcase_build(PDO $pdo): array {
    $week = (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('o-W');
    $today = date('Y-m-d'); $d5 = date('Y-m-d', strtotime('-5 days'));
    $pick = function (array $ids, int $n, string $seed): array { mt_srand(crc32($seed)); for ($i = count($ids) - 1; $i > 0; $i--) { $j = mt_rand(0, $i); [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]]; } return array_slice($ids, 0, $n); };
    $out = ['ids' => [], 'built' => date('Y-m-d H:i')];
    foreach (SHOWCASE_TYPES as $t) {
        $size = $t === 'series' ? '(SELECT COUNT(*) FROM episodes x WHERE x.subject_id=s.id)' : '(SELECT COUNT(*) FROM works x WHERE x.subject_id=s.id)';
        $base = "FROM subjects s JOIN user_entries ue ON ue.subject_id=s.id WHERE s.type=? AND s.fetched_at IS NOT NULL";
        $recent = $t === 'podcast'
            ? "EXISTS (SELECT 1 FROM releases r WHERE r.subject_id=s.id AND LENGTH(r.release_date)=10 AND r.release_date BETWEEN ? AND ?)"
            : "EXISTS (SELECT 1 FROM releases r WHERE r.subject_id=s.id AND r.release_date >= ?)";
        $p = $t === 'podcast' ? [$t, $d5, $today] : [$t, $today];
        $ids = [];
        try { $q = $pdo->prepare("SELECT s.id $base AND $recent GROUP BY s.id ORDER BY COUNT(DISTINCT ue.user_id) DESC, $size DESC LIMIT 15"); $q->execute($p); $ids = $pick(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), 5, $week . $t . 'a'); } catch (Throwable $e) {}
        try {
            $ex = $ids ? ' AND s.id NOT IN (' . implode(',', $ids) . ')' : '';
            $q = $pdo->prepare("SELECT s.id $base$ex GROUP BY s.id ORDER BY COUNT(DISTINCT ue.user_id) DESC, $size DESC LIMIT 25"); $q->execute([$t]);
            $ids = array_merge($ids, $pick(array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)), 10 - count($ids), $week . $t . 'b'));
        } catch (Throwable $e) {}
        $out['ids'][$t] = $ids;
    }
    $out['comm'] = showcase_community();
    return $out;
}
/** Community-Übersicht aus Sicht eines Nutzers ohne eigene Einträge (gleiche Berechnung wie api/community.php), Namen sehr deutlich anonymisiert. */
function showcase_community(): ?array {
    static $done = false; if ($done) return null; $done = true; // community.php lässt sich pro Aufruf nur einmal einbinden (Funktionen darin)
    if (!defined('SD_SHOWCASE')) define('SD_SHOWCASE', true);
    try { $c = include __DIR__ . '/../api/community.php'; } catch (Throwable $e) { return null; }
    if (!is_array($c)) return null;
    $us = $c['users'] ?? [];
    usort($us, function ($a, $b) { return array_sum($b['all']) <=> array_sum($a['all']); });
    foreach ($us as $i => &$x) { $x['id'] = -1 - $i; $x['name'] = 'Anonym #' . ($i + 1); } unset($x);
    $c['users'] = $us; $c['me'] = 0; $c['locked'] = false; $c['anon'] = true;
    foreach (['top', 'new', 'seen'] as $k) foreach ((array) ($c[$k] ?? []) as $t => $rows) foreach ($rows as $j => $r) $c[$k][$t][$j]['mine'] = 0;
    return $c;
}

function showcase_all_ids(array $snap): array { $o = []; foreach ((array) ($snap['ids'] ?? []) as $l) foreach ($l as $i) $o[] = (int) $i; return array_values(array_unique($o)); }

/** Einträge wie in data.php?a=list (gleiche Spalten), ohne Nutzerbezug. */
function showcase_list(PDO $pdo, array $snap): array {
    $ids = showcase_all_ids($snap); if (!$ids) return [];
    $s = $pdo->query("SELECT s.id,s.type,s.name,s.summary,s.genres,s.fetched_at,1 AS bell,0 AS fav,
        (SELECT COUNT(*) FROM works w WHERE w.subject_id=s.id) AS works,
        (SELECT MIN(r.release_date) FROM releases r WHERE r.subject_id=s.id AND r.release_date>=CURDATE()) AS next_date,
        (SELECT COUNT(*) FROM releases r WHERE r.subject_id=s.id) AS upcoming,
        s.total_works, (s.external_ids LIKE '%gbs%') AS is_series, (s.external_ids LIKE '%tmdb_col%') AS is_col, (s.external_ids LIKE '%tmdb_person%') AS is_person,
        (SELECT COUNT(*) FROM works w WHERE w.subject_id=s.id AND w.kind='Single') AS singles,
        (SELECT MAX(w.year) FROM works w WHERE w.subject_id=s.id) AS last_year,
        (SELECT SUM(CAST(SUBSTRING_INDEX(w.note,' ',1) AS UNSIGNED)) FROM works w WHERE w.subject_id=s.id AND w.kind='Staffel') AS episodes
        FROM subjects s WHERE s.id IN (" . implode(',', $ids) . ") ORDER BY s.name");
    $rows = $s->fetchAll();
    foreach ($rows as &$r) $r['genres'] = json_decode($r['genres'] ?: '[]', true) ?: []; unset($r);
    return $rows;
}
/** Termine wie in data.php?a=calendar (Podcasts ab Monatsanfang). */
function showcase_calendar(PDO $pdo, array $snap): array {
    $ids = showcase_all_ids($snap); if (!$ids) return [];
    return $pdo->query("SELECT r.id,r.title,r.release_date,r.kind,s.id AS subject_id,s.name,s.type FROM subjects s JOIN releases r ON r.subject_id=s.id
        WHERE s.id IN (" . implode(',', $ids) . ") AND (s.type<>'podcast' OR r.release_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01'))
        ORDER BY (r.release_date IS NULL OR r.release_date=''), r.release_date, s.name LIMIT 500")->fetchAll();
}
/** Seite einer Serie (nur Serien aus der Auswahl), Antwort wie data.php?a=entry, ohne Gesehen-Markierungen. */
function showcase_entry(PDO $pdo, array $snap, int $id): ?array {
    if (!in_array($id, array_map('intval', $snap['ids']['series'] ?? []), true)) return null;
    $s = $pdo->prepare('SELECT id,type,name,summary,genres,total_works,fetched_at,external_ids FROM subjects WHERE id=?'); $s->execute([$id]);
    $e = $s->fetch(); if (!$e) return null;
    $e['genres'] = json_decode($e['genres'] ?: '[]', true);
    $x = json_decode($e['external_ids'] ?: '{}', true) ?: []; $e['tvmaze'] = $x['tvmaze'] ?? null; $e['tv_status'] = $x['tv_status'] ?? ''; $e['err'] = ''; $e['page'] = ''; $e['by'] = ''; unset($e['external_ids']);
    $e['kind'] = ''; $e['dirs'] = []; $e['watch'] = null;
    try { $q = $pdo->prepare("SELECT season,number,title,airdate,(summary IS NOT NULL AND summary<>'') AS has_sum FROM episodes WHERE subject_id=? ORDER BY season, number"); $q->execute([$id]); $eps = $q->fetchAll(); }
    catch (Throwable $t) { $q = $pdo->prepare('SELECT season,number,title,airdate FROM episodes WHERE subject_id=? ORDER BY season, number'); $q->execute([$id]); $eps = $q->fetchAll(); }
    foreach ($eps as &$y) $y['k'] = 'e' . (int) $y['season'] . 'x' . (int) $y['number']; unset($y);
    try { $w = $pdo->prepare('SELECT title,year,kind,note,summary AS sum FROM works WHERE subject_id=? ORDER BY year DESC, id DESC LIMIT 300'); $w->execute([$id]); }
    catch (Throwable $t) { $w = $pdo->prepare('SELECT title,year,kind,note FROM works WHERE subject_id=? ORDER BY year DESC, id DESC LIMIT 300'); $w->execute([$id]); }
    $works = $w->fetchAll(); foreach ($works as &$y) { $y['lk'] = $y['k'] = 'w' . substr(md5(mb_strtolower(trim((string) $y['title'])) . '|' . (int) $y['year']), 0, 16); } unset($y);
    $r = $pdo->prepare('SELECT title,release_date,kind,note FROM releases WHERE subject_id=? ORDER BY release_date LIMIT 100'); $r->execute([$id]);
    return ['entry' => $e, 'works' => $works, 'releases' => $r->fetchAll(), 'episodes' => $eps, 'done' => [], 'favw' => []];
}
/** Kurzfassung einer Folge: nur, was schon gespeichert ist (keine Übersetzung für Gäste, das würde das Kontingent belasten). */
function showcase_episum(PDO $pdo, array $snap, int $id, int $se, int $nu): string {
    if (!in_array($id, array_map('intval', $snap['ids']['series'] ?? []), true)) return '';
    try { $q = $pdo->prepare('SELECT summary,summary_de FROM episodes WHERE subject_id=? AND season=? AND number=?'); $q->execute([$id, $se, $nu]); $r = $q->fetch(); }
    catch (Throwable $e) { return ''; }
    if (!$r) return '';
    return (string) ($r['summary_de'] ?: $r['summary']);
}

/** Beispielnutzer für die Oberfläche (window.SD). */
function showcase_sd(string $view): array {
    return ['demo' => ['view' => $view, 'public' => showcase_public()], 'dev' => false, 'design' => '', 'theme' => '', 'pubdesigns' => [], 'designs' => [], 'devsw' => false, 'devnotes' => [],
        'approval' => false, 'alpha' => false, 'podcast' => true, 'cats' => SHOWCASE_TYPES, 'news' => true, 'hidecats' => false, 'hide' => [], 'calhide' => [], 'admin' => false, 'mod' => false, 'admsec' => [],
        'id' => 0, 'email' => '', 'username' => 'Beispielnutzer', 'totp' => false, 'hide_email' => true, 'hide_activity' => true, 'community' => true, 'community_asked' => true, 'nerd' => false,
        'uname_free' => 0, 'uname_left' => 0, 'skin' => 'auto', 'custom_colors' => null, 'custom_own' => false, 'notify' => true, 'sample_asked' => 1,
        'n' => ['new' => 1, 'day' => 1, 'week' => 0, 'mode' => 'digest', 'weekday' => 1, 'bell_default' => 1]];
}
