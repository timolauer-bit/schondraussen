<?php
// Mail-Benachrichtigungen: neu angekündigte Termine und Termine der nächsten 7 Tage.
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
           "Keine Mails mehr: $unsub\n" . base_url() . "/impressum.php\n";
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
function run_digests(PDO $pdo, int $maxMails = 40): array {
    $sent = 0; $fail = 0;
    try { $users = $pdo->query("SELECT id,email,unsub_token FROM users WHERE notify=1 AND email_verified_at IS NOT NULL AND (role='admin' OR approved_at IS NOT NULL) AND delete_requested_at IS NULL")->fetchAll(); }
    catch (Throwable $e) { $users = $pdo->query("SELECT id,email,unsub_token FROM users WHERE notify=1 AND email_verified_at IS NOT NULL AND (role='admin' OR approved_at IS NOT NULL)")->fetchAll(); }
    $today = date('Y-m-d'); $soonTo = date('Y-m-d', strtotime('+7 days'));
    $subs = $pdo->prepare('SELECT s.id,s.name FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.fetched_at IS NOT NULL');
    $rels = $pdo->prepare("SELECT title,release_date FROM releases WHERE subject_id=? AND release_date<>'' AND release_date IS NOT NULL");
    $known = $pdo->prepare('SELECT key_hash FROM notified WHERE user_id=?');
    $seenSub = $pdo->prepare('SELECT DISTINCT subject_id FROM notified WHERE user_id=?');
    $ins = $pdo->prepare('INSERT IGNORE INTO notified (user_id,subject_id,key_hash,kind) VALUES (?,?,?,?)');
    $h = fn($uid, $sid, $t, $d, $k) => sha1("$uid|$sid|$t|$d|$k");
    foreach ($users as $u) {
        $uid = (int) $u['id'];
        $known->execute([$uid]); $K = array_flip($known->fetchAll(PDO::FETCH_COLUMN));
        $seenSub->execute([$uid]); $SS = array_flip($seenSub->fetchAll(PDO::FETCH_COLUMN));
        $subs->execute([$uid]);
        $new = []; $soon = []; $mark = [];
        foreach ($subs->fetchAll() as $s) {
            $sid = (int) $s['id']; $rels->execute([$sid]); $R = $rels->fetchAll();
            if (!isset($SS[$sid])) { // erstes Mal: bisherigen Stand merken, nichts melden
                $ins->execute([$uid, $sid, $h($uid, $sid, '', '', 'new'), 'new']);
                foreach ($R as $r) $ins->execute([$uid, $sid, $h($uid, $sid, $r['title'], $r['release_date'], 'new'), 'new']);
                continue;
            }
            foreach ($R as $r) {
                $kn = $h($uid, $sid, $r['title'], $r['release_date'], 'new'); $ks = $h($uid, $sid, $r['title'], $r['release_date'], 'soon');
                $d = $r['release_date']; $line = $s['name'] . ' – ' . $r['title'] . ' (' . fmt_date($d) . ')';
                $isNew = !isset($K[$kn]); $isSoon = strlen($d) === 10 && $d >= $today && $d <= $soonTo && !isset($K[$ks]);
                if ($isNew) { $new[] = [$d, $line]; $mark[] = [$sid, $kn, 'new']; if (strlen($d) === 10 && $d <= $soonTo) $mark[] = [$sid, $ks, 'soon']; }
                elseif ($isSoon) { $soon[] = [$d, $line]; $mark[] = [$sid, $ks, 'soon']; }
            }
        }
        if (!$new && !$soon) continue;
        if ($sent >= $maxMails) break;
        $sort = fn($a, $b) => strcmp($a[0], $b[0]);
        usort($new, $sort); usort($soon, $sort);
        $fmt = function (array $l) { $o = array_map(fn($x) => '- ' . $x[1], array_slice($l, 0, 40)); if (count($l) > 40) $o[] = '… und ' . (count($l) - 40) . ' weitere'; return implode("\n", $o); };
        $text = "Hallo,\n\nes gibt Neuigkeiten in deinen Listen.\n";
        if ($new) $text .= "\nNEU ANGEKÜNDIGT\n" . $fmt($new) . "\n";
        if ($soon) $text .= "\nIN DEN NÄCHSTEN 7 TAGEN\n" . $fmt($soon) . "\n";
        $text .= "\nAlles im Überblick: " . base_url() . "/\n";
        $n = count($new) + count($soon);
        if (send_user_mail($pdo, $u, 'Schondraussen.de: ' . ($n === 1 ? '1 Neuigkeit' : "$n Neuigkeiten"), $text)) {
            foreach ($mark as $m) $ins->execute([$uid, $m[0], $m[1], $m[2]]);
            $sent++;
        } else $fail++;
    }
    return [$sent, $fail];
}
