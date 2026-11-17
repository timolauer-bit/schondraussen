<?php
// Privates Kalender-Abo: /kalender/<token>.ics (per .htaccess auf diese Datei umgeleitet)
require_once __DIR__ . '/lib/bootstrap.php';
function ics_esc(string $s): string { return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\;", "\\,", "\\n", "\\n", "\\n"], $s); }
function ics_fold(string $line): string { // 75 Oktette, an Zeichengrenzen
    $out = ''; $cur = '';
    foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (strlen($cur) + strlen($ch) > ($out === '' ? 75 : 74)) { $out .= ($out === '' ? '' : ' ') . $cur . "\r\n"; $cur = ''; }
        $cur .= $ch;
    }
    return $out . ($out === '' ? '' : ' ') . $cur . "\r\n";
}
header('X-Robots-Tag: noindex, nofollow');
$t = (string) ($_GET['t'] ?? '');
$row = null;
if (preg_match('/^[A-Za-z0-9]{32}$/', $t)) {
    try { $s = db()->prepare('SELECT id,role,approved_at,delete_requested_at FROM users WHERE cal_token=?'); $s->execute([$t]); $row = $s->fetch(); } catch (Throwable $e) {}
}
if (!$row || ($row['role'] !== 'admin' && !$row['approved_at']) || !empty($row['delete_requested_at'])) { http_response_code(404); header('Content-Type: text/plain; charset=utf-8'); exit('Nicht gefunden.'); }
$pdo = db();
$types = null; try { $c = $pdo->prepare('SELECT ical_cats FROM users WHERE id=?'); $c->execute([$row['id']]); $v = (string) $c->fetchColumn(); if ($v !== '') $types = array_values(array_intersect(explode(',', $v), ['author', 'artist', 'film', 'series', 'podcast'])); } catch (Throwable $e) {}
$tf = $types ? ' AND s.type IN (' . implode(',', array_fill(0, count($types), '?')) . ')' : '';
$q = $pdo->prepare("SELECT r.title,r.release_date,r.kind,s.id sid,s.name,s.type FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN releases r ON r.subject_id=s.id
    WHERE e.user_id=? AND r.release_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND r.release_date >= CURDATE() - INTERVAL 14 DAY AND (s.type<>'podcast' OR r.release_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01'))" . $tf . " ORDER BY r.release_date, s.name LIMIT 2000");
$q->execute(array_merge([$row['id']], $types ?: []));
$cat = ['author' => 'Autor:in', 'artist' => 'Musiker:in', 'film' => 'Film', 'series' => 'Serie', 'podcast' => 'Podcast'];
$base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
$now = gmdate('Ymd\THis\Z');
$o = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schondraussen.de//Kalender//DE\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
   . ics_fold('X-WR-CALNAME:Schondraussen.de') . "REFRESH-INTERVAL;VALUE=DURATION:PT12H\r\nX-PUBLISHED-TTL:PT12H\r\n";
$uids = []; $n = 0;
foreach ($q->fetchAll() as $r) {
    $d = str_replace('-', '', $r['release_date']);
    try { $end = (new DateTimeImmutable($r['release_date'], new DateTimeZone('UTC')))->modify('+1 day')->format('Ymd'); } catch (Throwable $e) { continue; } // ganztägig: Folgetag, unabhängig von der Zeitzone des Servers
    $uid = sha1($r['sid'] . '|' . $r['title'] . '|' . $r['release_date']); if (isset($uids[$uid])) continue; $uids[$uid] = 1; $n++; // jeder Termin nur einmal
    $o .= "BEGIN:VEVENT\r\n" . ics_fold('UID:' . $uid . '@schondraussen.de')
        . "DTSTAMP:$now\r\nDTSTART;VALUE=DATE:$d\r\nDTEND;VALUE=DATE:$end\r\n"
        . ics_fold('SUMMARY:' . ics_esc($r['name'] . ' – ' . $r['title']))
        . ics_fold('CATEGORIES:' . ics_esc($cat[$r['type']] ?? $r['type']))
        . ics_fold('DESCRIPTION:' . ics_esc(trim(($r['kind'] ? $r['kind'] . '. ' : '') . 'Termine vor dem Kauf prüfen. ' . $base . '/')))
        . "TRANSP:TRANSPARENT\r\nEND:VEVENT\r\n";
}
if ($n === 0) $o .= "BEGIN:VTIMEZONE\r\nTZID:Europe/Berlin\r\nBEGIN:STANDARD\r\nDTSTART:19701025T030000\r\nRRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU\r\nTZOFFSETFROM:+0200\r\nTZOFFSETTO:+0100\r\nTZNAME:CET\r\nEND:STANDARD\r\nBEGIN:DAYLIGHT\r\nDTSTART:19700329T020000\r\nRRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU\r\nTZOFFSETFROM:+0100\r\nTZOFFSETTO:+0200\r\nTZNAME:CEST\r\nEND:DAYLIGHT\r\nEND:VTIMEZONE\r\n"; // ein Kalender braucht mindestens einen Bestandteil (RFC 5545), auch wenn gerade keine Termine anstehen
$o .= "END:VCALENDAR\r\n";
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="schondraussen.ics"');
header('Cache-Control: private, max-age=1800');
echo $o;
