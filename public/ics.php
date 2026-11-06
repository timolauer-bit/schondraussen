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
$q = $pdo->prepare("SELECT r.title,r.release_date,r.kind,s.id sid,s.name,s.type FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN releases r ON r.subject_id=s.id
    WHERE e.user_id=? AND r.release_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' AND r.release_date >= CURDATE() - INTERVAL 14 DAY ORDER BY r.release_date, s.name LIMIT 2000");
$q->execute([$row['id']]);
$cat = ['author' => 'Autor:in', 'artist' => 'Musiker:in', 'film' => 'Film', 'series' => 'Serie'];
$base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
$now = gmdate('Ymd\THis\Z');
$o = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Schondraussen.de//Kalender//DE\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
   . ics_fold('X-WR-CALNAME:Schondraussen.de') . "REFRESH-INTERVAL;VALUE=DURATION:PT12H\r\nX-PUBLISHED-TTL:PT12H\r\n";
foreach ($q->fetchAll() as $r) {
    $d = str_replace('-', '', $r['release_date']);
    $end = gmdate('Ymd', strtotime($r['release_date'] . ' +1 day'));
    $o .= "BEGIN:VEVENT\r\n" . ics_fold('UID:' . sha1($r['sid'] . '|' . $r['title'] . '|' . $r['release_date']) . '@schondraussen.de')
        . "DTSTAMP:$now\r\nDTSTART;VALUE=DATE:$d\r\nDTEND;VALUE=DATE:$end\r\n"
        . ics_fold('SUMMARY:' . ics_esc($r['name'] . ' – ' . $r['title']))
        . ics_fold('CATEGORIES:' . ics_esc($cat[$r['type']] ?? $r['type']))
        . ics_fold('DESCRIPTION:' . ics_esc(trim(($r['kind'] ? $r['kind'] . '. ' : '') . 'Termine vor dem Kauf prüfen. ' . $base . '/')))
        . "TRANSP:TRANSPARENT\r\nEND:VEVENT\r\n";
}
$o .= "END:VCALENDAR\r\n";
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="schondraussen.ics"');
header('Cache-Control: private, max-age=1800');
echo $o;
