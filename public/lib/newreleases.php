<?php
// Öffentliche Seite „Neuerscheinungen“ (/neuerscheinungen, /neuerscheinungen/2026-kw48): alle Termine einer Kalenderwoche
// von Einträgen, die mindestens ein Nutzer auf seiner Liste hat. Keine Angaben zu Nutzern.
// Vergangene Termine bleiben in der Tabelle releases nur bis Monatsende erhalten, deshalb wird jede Woche als Schnappschuss
// in news_cache gespeichert (Cron bei jedem Lauf, solange die Woche läuft); das Archiv zeigt dann den Schnappschuss.
require_once __DIR__ . '/showcase.php';

const NR_FUTURE_WEEKS = 4; // so viele Wochen im Voraus gibt es Seiten
const NR_TYPES = ['author' => 'Bücher', 'artist' => 'Musik', 'film' => 'Filme', 'series' => 'Serien', 'podcast' => 'Podcasts'];

function nr_tz(): DateTimeZone { return new DateTimeZone('Europe/Berlin'); }
/** [Jahr, Woche] der aktuellen ISO-Kalenderwoche. */
function nr_current(): array { $d = new DateTime('now', nr_tz()); return [(int) $d->format('o'), (int) $d->format('W')]; }
/** Montag und Sonntag (Y-m-d) einer ISO-Woche. */
function nr_bounds(int $y, int $w): array { $d = new DateTime('now', nr_tz()); $d->setISODate($y, $w, 1); $a = $d->format('Y-m-d'); $d->modify('+6 days'); return [$a, $d->format('Y-m-d')]; }
/** Nächste/vorige Woche. */
function nr_shift(int $y, int $w, int $n): array { $d = new DateTime('now', nr_tz()); $d->setISODate($y, $w, 1); $d->modify(($n >= 0 ? '+' : '') . (7 * $n) . ' days'); return [(int) $d->format('o'), (int) $d->format('W')]; }
function nr_key(int $y, int $w): string { return sprintf('%04d-kw%02d', $y, $w); }
function nr_path(int $y, int $w): string { return '/neuerscheinungen/' . nr_key($y, $w); }
/** Abstand in Wochen zur aktuellen Woche (negativ = vergangen). */
function nr_offset(int $y, int $w): int { [$cy, $cw] = nr_current(); [$a] = nr_bounds($y, $w); [$b] = nr_bounds($cy, $cw); return (int) round((strtotime($a) - strtotime($b)) / 604800); }

/** Kategorien, die normale Nutzer sehen dürfen (Rechte der Nutzergruppen); nur diese erscheinen öffentlich. */
function nr_types(): array {
    $v = perm_values(); $o = [];
    foreach (NR_TYPES as $t => $n) if (!empty($v[$t === 'podcast' ? 'podcast' : 'cat_' . $t]['user'])) $o[] = $t;
    return $o;
}

/** Termine der Woche frisch aus der Datenbank. */
function nr_query(PDO $pdo, int $y, int $w): array {
    [$a, $b] = nr_bounds($y, $w); $types = nr_types(); if (!$types) return [];
    $q = $pdo->prepare("SELECT r.release_date AS d, r.title, r.kind, s.id AS sid, s.name, s.type FROM releases r JOIN subjects s ON s.id=r.subject_id
        WHERE LENGTH(r.release_date)=10 AND r.release_date BETWEEN ? AND ? AND s.type IN ('" . implode("','", $types) . "')
        AND EXISTS (SELECT 1 FROM user_entries ue WHERE ue.subject_id=s.id)
        ORDER BY r.release_date, FIELD(s.type,'author','artist','film','series','podcast'), s.name, r.title LIMIT 800");
    $q->execute([$a, $b]);
    $rows = [];
    foreach ($q->fetchAll() as $r) $rows[] = ['d' => (string) $r['d'], 't' => (string) $r['title'], 'k' => (string) ($r['kind'] ?? ''), 'sid' => (int) $r['sid'], 'n' => (string) $r['name'], 'ty' => (string) $r['type']];
    return $rows;
}
function nr_snap_get(PDO $pdo, int $y, int $w): ?array {
    try { $s = $pdo->prepare('SELECT data FROM news_cache WHERE k=?'); $s->execute(['nr_' . nr_key($y, $w)]); $j = json_decode((string) $s->fetchColumn(), true); return is_array($j) ? $j : null; } catch (Throwable $e) { return null; }
}
function nr_snap_save(PDO $pdo, int $y, int $w, array $rows): void {
    try { $pdo->prepare('REPLACE INTO news_cache (k,data) VALUES (?,?)')->execute(['nr_' . nr_key($y, $w), json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)]); } catch (Throwable $e) {}
}
/** Gespeicherte Wochen (für das Archiv), neueste zuerst: Liste von [Jahr, Woche]. */
function nr_archive(PDO $pdo): array {
    $o = [];
    try { foreach ($pdo->query("SELECT k FROM news_cache WHERE k LIKE 'nr\\_%-kw%' ORDER BY k DESC")->fetchAll(PDO::FETCH_COLUMN) as $k) if (preg_match('/^nr_(\d{4})-kw(\d{2})$/', $k, $m)) $o[] = [(int) $m[1], (int) $m[2]]; } catch (Throwable $e) {}
    return $o;
}
/** Termine einer Woche: vergangene Wochen aus dem Schnappschuss, laufende und kommende frisch (laufende wird dabei gespeichert). null = Seite gibt es nicht. */
function nr_rows(PDO $pdo, int $y, int $w): ?array {
    $off = nr_offset($y, $w);
    if ($off > NR_FUTURE_WEEKS) return null;
    if ($off < 0) return nr_snap_get($pdo, $y, $w); // Archiv: nur, was gespeichert wurde
    $rows = nr_query($pdo, $y, $w);
    if ($off === 0) nr_snap_save($pdo, $y, $w, $rows);
    return $rows;
}
/** Für den Cron: laufende Woche speichern (auch ohne Besucher), damit das Archiv vollständig ist. */
function nr_cron(PDO $pdo): string { [$y, $w] = nr_current(); $r = nr_query($pdo, $y, $w); nr_snap_save($pdo, $y, $w, $r); return 'KW ' . $w . ' gespeichert (' . count($r) . ' Termine)'; }
/** Alle Adressen für die Sitemap. */
function nr_sitemap_paths(PDO $pdo): array {
    [$cy, $cw] = nr_current(); $o = ['/neuerscheinungen'];
    for ($i = 1; $i <= NR_FUTURE_WEEKS; $i++) { [$y, $w] = nr_shift($cy, $cw, $i); $o[] = nr_path($y, $w); }
    foreach (nr_archive($pdo) as [$y, $w]) if (nr_offset($y, $w) < 0) $o[] = nr_path($y, $w);
    return $o;
}
