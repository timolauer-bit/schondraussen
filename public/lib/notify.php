<?php
// Mail-Benachrichtigungen: neu angekündigt, am Erscheinungstag, Wochenübersicht (je Eintrag mit Glocke).
require_once __DIR__ . '/mail.php';

function base_url(): string { return rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/'); }

function user_unsub_token(PDO $pdo, array $u): string {
    if (!empty($u['unsub_token'])) return $u['unsub_token'];
    $t = bin2hex(random_bytes(20));
    $pdo->prepare('UPDATE users SET unsub_token=? WHERE id=?')->execute([$t, $u['id']]);
    return $t;
}

function mail_footer(string $unsub): string {
    return "\n--\nDu erhältst diese Mail, weil du bei Schondraussen.de Benachrichtigungen eingeschaltet hast.\n" .
           "Einstellungen ändern: " . base_url() . "/\nKeine Mails mehr: $unsub\n" . base_url() . "/impressum.php\n";
}

function send_user_mail(PDO $pdo, array $u, string $subject, string $text): bool {
    $unsub = base_url() . '/unsubscribe.php?t=' . user_unsub_token($pdo, $u);
    return send_mail($u['email'], $subject, $text . mail_footer($unsub), ["List-Unsubscribe: <$unsub>", 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
}

function fmt_date(string $d): string {
    $p = explode('-', $d);
    if (count($p) === 3) return "$p[2].$p[1].$p[0]";
    if (count($p) === 2) return "$p[1].$p[0]";
    return $d;
}

// Gibt [versendete Mails, Fehler] zurück.
// Einstellungen je Nutzer: n_new, n_day, n_week, n_mode ('digest' = eine Sammelmail täglich ab 7 Uhr, 'instant' = bei jedem Lauf),
// n_weekday (1=Mo..7=So), Glocke je Eintrag (user_entries.bell).
function run_digests(PDO $pdo, int $maxMails = 40): array {
    date_default_timezone_set('Europe/Berlin');
    $sent = 0; $fail = 0;
    $hour = (int) date('G'); $today = date('Y-m-d'); $dow = (int) date('N'); $weekTo = date('Y-m-d', strtotime('+14 days'));
    $users = $pdo->query("SELECT id,email,unsub_token,n_new,n_day,n_week,n_mode,n_weekday,last_morning,last_weekly FROM users
        WHERE notify=1 AND email_verified_at IS NOT NULL AND (role='admin' OR approved_at IS NOT NULL) AND delete_requested_at IS NULL")->fetchAll();
    $subs = $pdo->prepare('SELECT s.id,s.name FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND e.bell=1 AND s.fetched_at IS NOT NULL');
    $rels = $pdo->prepare("SELECT title,release_date FROM releases WHERE subject_id=? AND release_date<>'' AND release_date IS NOT NULL");
    $known = $pdo->prepare('SELECT key_hash FROM notified WHERE user_id=?');
    $seenSub = $pdo->prepare('SELECT DISTINCT subject_id FROM notified WHERE user_id=?');
    $ins = $pdo->prepare('INSERT IGNORE INTO notified (user_id,subject_id,key_hash,kind) VALUES (?,?,?,?)');
    $h = fn($uid, $sid, $t, $d, $k) => sha1("$uid|$sid|$t|$d|$k");
    $sort = fn($a, $b) => strcmp($a[0], $b[0]);
    $fmt = function (array $l) { usort($l, fn($a, $b) => strcmp($a[0], $b[0])); $o = array_map(fn($x) => '- ' . $x[1], array_slice($l, 0, 40)); if (count($l) > 40) $o[] = '… und ' . (count($l) - 40) . ' weitere'; return implode("\n", $o); };
    foreach ($users as $u) {
        $uid = (int) $u['id']; $digest = $u['n_mode'] !== 'instant';
        $known->execute([$uid]); $K = array_flip($known->fetchAll(PDO::FETCH_COLUMN));
        $seenSub->execute([$uid]); $SS = array_flip($seenSub->fetchAll(PDO::FETCH_COLUMN));
        $subs->execute([$uid]);
        $new = []; $day = []; $week = []; $mark = [];
        foreach ($subs->fetchAll() as $s) {
            $sid = (int) $s['id']; $rels->execute([$sid]); $R = $rels->fetchAll();
            if (!isset($SS[$sid])) { // erstes Mal (oder Glocke neu an): bisherigen Stand merken, nichts melden
                $ins->execute([$uid, $sid, $h($uid, $sid, '', '', 'new'), 'new']);
                foreach ($R as $r) $ins->execute([$uid, $sid, $h($uid, $sid, $r['title'], $r['release_date'], 'new'), 'new']);
                continue;
            }
            foreach ($R as $r) {
                $d = $r['release_date']; $line = $s['name'] . ' – ' . $r['title'] . ' (' . fmt_date($d) . ')';
                $kn = $h($uid, $sid, $r['title'], $d, 'new'); $kd = $h($uid, $sid, $r['title'], $d, 'day');
                if (!isset($K[$kn])) {
                    if ($u['n_new']) { $new[] = [$d, $line]; $mark[] = [$sid, $kn, 'new']; }
                    else $ins->execute([$uid, $sid, $kn, 'new']); // still merken, damit später nichts auf einmal kommt
                }
                if (strlen($d) === 10) {
                    if ($d === $today && $u['n_day'] && !isset($K[$kd])) { $day[] = [$d, $s['name'] . ' – ' . $r['title']]; $mark[] = [$sid, $kd, 'day']; }
                    if ($d >= $today && $d <= $weekTo) $week[] = [$d, $line];
                }
            }
        }
        $canMorning = $hour >= 7;
        $window = $digest ? ($canMorning && $u['last_morning'] !== $today) : ($hour >= 7 && $hour < 22);
        if (!$canMorning) $day = []; // Erscheinungstag-Mails erst ab 7 Uhr
        if ($window && ($new || $day)) {
            if ($sent >= $maxMails) break;
            $text = "Hallo,\n\nes gibt Neuigkeiten in deinen Listen.\n";
            if ($day) $text .= "\nHEUTE ERSCHEINT\n" . $fmt($day) . "\n";
            if ($new) $text .= "\nNEU ANGEKÜNDIGT\n" . $fmt($new) . "\n";
            $text .= "\nAlles im Überblick: " . base_url() . "/\n";
            $subj = (count($day) && !count($new)) ? (count($day) === 1 ? 'Heute erscheint 1 Titel' : 'Heute erscheinen ' . count($day) . ' Titel') : ((count($new) + count($day)) === 1 ? '1 Neuigkeit' : (count($new) + count($day)) . ' Neuigkeiten');
            if (send_user_mail($pdo, $u, 'Schondraussen.de: ' . $subj, $text)) {
                foreach ($mark as $m) $ins->execute([$uid, $m[0], $m[1], $m[2]]);
                if ($digest) $pdo->prepare('UPDATE users SET last_morning=? WHERE id=?')->execute([$today, $uid]);
                $sent++;
            } else $fail++;
        }
        if ($u['n_week'] && $canMorning && $dow === (int) $u['n_weekday'] && $u['last_weekly'] !== $today) {
            if ($sent >= $maxMails) break;
            $pdo->prepare('UPDATE users SET last_weekly=? WHERE id=?')->execute([$today, $uid]);
            if ($week) {
                $text = "Hallo,\n\nhier sind die Termine der nächsten 14 Tage aus deinen Listen:\n\n" . $fmt($week) . "\n\nAlles im Überblick: " . base_url() . "/\n";
                if (send_user_mail($pdo, $u, 'Schondraussen.de: Deine Wochenübersicht (' . count($week) . ($week && count($week) === 1 ? ' Termin)' : ' Termine)'), $text)) $sent++; else $fail++;
            }
        }
    }
    return [$sent, $fail];
}

// Vorschau-Mail für den Testmail-Button: so sieht die Morgenmail nach den aktuellen Einstellungen aus.
// Die Beispiele stammen aus den nächsten echten Terminen der Einträge mit Glocke (sonst feste Beispiele).
function build_preview(PDO $pdo, array $u): array {
    date_default_timezone_set('Europe/Berlin');
    $today = date('Y-m-d');
    try {
        $s = $pdo->prepare("SELECT s.name, r.title, r.release_date FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN releases r ON r.subject_id=s.id
            WHERE e.user_id=? AND e.bell=1 AND LENGTH(r.release_date)=10 AND r.release_date>=? ORDER BY r.release_date, s.name LIMIT 12");
        $s->execute([$u['id'], $today]); $R = $s->fetchAll();
    } catch (Throwable $e) { $R = []; }
    $demo = !$R;
    if ($demo) $R = [['name' => 'Beispiel-Band', 'title' => 'Neues Album', 'release_date' => date('Y-m-d', strtotime('+3 days'))], ['name' => 'Beispiel-Serie', 'title' => 'S02E05 Folgentitel', 'release_date' => date('Y-m-d', strtotime('+9 days'))]];
    $line = fn($r) => '- ' . $r['name'] . ' – ' . $r['title'] . ' (' . fmt_date($r['release_date']) . ')';
    $nNew = (int) ($u['n_new'] ?? 1); $nDay = (int) ($u['n_day'] ?? 1); $nWeek = (int) ($u['n_week'] ?? 0);
    $text = "VORSCHAU: So sieht deine Benachrichtigungsmail nach deinen aktuellen Einstellungen aus.\n" .
            "Die Termine sind nur als Beispiel eingesetzt" . ($demo ? " (Beispieldaten, du hast noch keine kommenden Termine mit Glocke)" : " (deine nächsten echten Termine mit Glocke)") . ". In der echten Mail steht nur, was wirklich zutrifft.\n\n" .
            "--- Morgenmail ---\n\nHallo,\n\nes gibt Neuigkeiten in deinen Listen.\n";
    if ($nDay) $text .= "\nHEUTE ERSCHEINT\n[Beispiel: Hier stehen nur Titel, die am selben Tag erscheinen.]\n" . implode("\n", array_map(fn($r) => '- ' . $r['name'] . ' – ' . $r['title'], array_slice($R, 0, 1))) . "\n";
    if ($nNew) $text .= "\nNEU ANGEKÜNDIGT\n[Beispiel: Hier steht jeder neue Termin nur einmal.]\n" . implode("\n", array_map($line, array_slice($R, 0, 3))) . "\n";
    if (!$nDay && !$nNew) $text .= "\n(Du hast gerade weder „Neu angekündigt“ noch „Am Erscheinungstag“ eingeschaltet, deshalb gäbe es keine solche Mail.)\n";
    $text .= "\nAlles im Überblick: " . base_url() . "/\n";
    $wk = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'][max(1, min(7, (int) ($u['n_weekday'] ?? 1))) - 1];
    if ($nWeek) {
        $in14 = array_values(array_filter($R, fn($r) => $r['release_date'] <= date('Y-m-d', strtotime('+14 days'))));
        $text .= "\n--- Wochenübersicht (kommt jeden $wk) ---\n\nHallo,\n\nhier sind die Termine der nächsten 14 Tage aus deinen Listen:\n\n" .
            ($in14 ? implode("\n", array_map($line, $in14)) : "[Aktuell hast du in den nächsten 14 Tagen keinen Termin mit Glocke. Dann wird keine Wochenübersicht verschickt.]") . "\n\nAlles im Überblick: " . base_url() . "/\n";
    } else $text .= "\n(Die Wochenübersicht ist ausgeschaltet.)\n";
    return ['Schondraussen.de: Vorschau deiner Benachrichtigungen', $text];
}
