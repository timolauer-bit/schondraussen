<?php
// Mail-Benachrichtigungen: neu angekündigt, am Erscheinungstag, Wochenübersicht (je Eintrag mit Glocke).
require_once __DIR__ . '/mail.php';

function base_url(): string { return rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/'); }

function user_unsub_token(PDO $pdo, array $u): string { // Token nur einmal anlegen, auch wenn in einem Lauf mehrere Mails an dieselbe Person gehen
    static $cache = [];
    if (!empty($u['unsub_token'])) return $u['unsub_token'];
    $id = (int) $u['id']; if (isset($cache[$id])) return $cache[$id];
    $q = $pdo->prepare('SELECT unsub_token FROM users WHERE id=?'); $q->execute([$id]); $t = (string) $q->fetchColumn();
    if ($t === '') { $pdo->prepare('UPDATE users SET unsub_token=? WHERE id=? AND (unsub_token IS NULL OR unsub_token=\'\')')->execute([bin2hex(random_bytes(20)), $id]); $q->execute([$id]); $t = (string) $q->fetchColumn(); }
    return $cache[$id] = $t;
}

function mail_footer(string $unsub): string {
    return "\n--\nDu erhältst diese Mail, weil du bei Schondraussen.de Benachrichtigungen eingeschaltet hast.\n" .
           "Einstellungen ändern: " . base_url() . "/\nKeine Mails mehr: $unsub\n" . base_url() . "/impressum.php\n";
}

function send_user_mail(PDO $pdo, array $u, string $subject, string $text): bool {
    if (empty($u['email'])) return false; // Konten ohne E-Mail-Adresse bekommen keine Mails
    $unsub = base_url() . '/unsubscribe.php?t=' . user_unsub_token($pdo, $u);
    return send_mail($u['email'], $subject, $text . mail_footer($unsub), ["List-Unsubscribe: <$unsub>", 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
}

/** Zählt versendete Benachrichtigungsmails (für die Seitenstatistik auf der Info-Seite). Fehler dabei dürfen den Versand nie stören. */
function mail_count(PDO $pdo, array $kinds): void {
    try { foreach (array_merge(['all'], $kinds) as $k) $pdo->prepare('INSERT INTO mail_stats (kind,n) VALUES (?,1) ON DUPLICATE KEY UPDATE n=n+1')->execute([$k]); } catch (Throwable $e) {}
}

function fmt_date(string $d): string {
    $p = explode('-', $d);
    if (count($p) === 3) return "$p[2].$p[1].$p[0]";
    if (count($p) === 2) return "$p[1].$p[0]";
    return $d;
}

// Kurzinfo und aktuelle Schlagzeilen (Google-News-Suche, kostenlos) zu einem Titel für die Benachrichtigungsmail. Höchstens 8 Zeilen.
function news_for(PDO $pdo, string $name, string $title): array {
    static $fetches = 0;
    $q = '"' . str_replace('"', '', $name) . '"' . (preg_match('/^S\d+E\d+/i', $title) ? '' : ' ' . str_replace('"', '', $title)) . ' when:7d';
    $key = sha1($q); $json = null;
    try { $s = $pdo->prepare('SELECT data FROM news_cache WHERE k=? AND fetched_at > (NOW() - INTERVAL 12 HOUR)'); $s->execute([$key]); $json = $s->fetchColumn() ?: null; } catch (Throwable $e) {}
    if ($json !== null) return json_decode($json, true) ?: [];
    if ($fetches >= 25) return []; // pro Lauf begrenzen
    $fetches++;
    $ch = curl_init('https://news.google.com/rss/search?q=' . rawurlencode($q) . '&hl=de&gl=DE&ceid=DE:de');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)']);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($r === false || $code >= 400) api_note('https://news.google.com/rss', $code, $r, $cerr); elseif (function_exists('api_ok')) api_ok('https://news.google.com/rss');
    $out = [];
    if ($r !== false && $code < 400 && ($x = @simplexml_load_string($r)) && isset($x->channel->item)) {
        foreach ($x->channel->item as $it) {
            $head = trim((string) $it->title); $src = trim((string) ($it->source ?? '')); $link = trim((string) $it->link);
            if ($src !== '' && substr($head, -strlen($src) - 3) === ' - ' . $src) $head = substr($head, 0, -strlen($src) - 3);
            if ($head === '' || $link === '' || mb_stripos($head, $name) === false) continue; // nur Treffer, die den Namen nennen
            $ts = strtotime((string) $it->pubDate); if ($ts && $ts < time() - 7 * 86400) continue;
            $out[] = [mb_substr($head, 0, 110), $src, $link];
            if (count($out) >= 2) break;
        }
        try { $pdo->prepare('REPLACE INTO news_cache (k,data) VALUES (?,?)')->execute([$key, json_encode($out, JSON_UNESCAPED_UNICODE)]); } catch (Throwable $e) {}
    }
    return $out;
}
function item_info(PDO $pdo, string $name, string $title, ?string $summary): string {
    $lines = [];
    $sum = trim((string) $summary);
    if ($sum !== '') {
        $w = explode("\n", wordwrap('Über ' . $name . ': ' . mb_substr($sum, 0, 240) . (mb_strlen($sum) > 240 ? '…' : ''), 74, "\n", true));
        foreach (array_slice($w, 0, 3) as $l) $lines[] = '  ' . $l;
    }
    foreach (news_for($pdo, $name, $title) as $nw) { $lines[] = '  Presse: ' . $nw[0] . ($nw[1] !== '' ? ' (' . $nw[1] . ')' : ''); $lines[] = '  ' . $nw[2]; }
    return $lines ? "\n" . implode("\n", array_slice($lines, 0, 8)) : '';
}

// Gibt [versendete Mails, Fehler] zurück.
// Einstellungen je Nutzer: n_new, n_day, n_week, n_mode ('digest' = eine Sammelmail täglich ab 7 Uhr, 'instant' = bei jedem Lauf),
// n_weekday (1=Mo..7=So), Glocke je Eintrag (user_entries.bell).
function run_digests(PDO $pdo, int $maxMails = 40): array {
    date_default_timezone_set('Europe/Berlin');
    $sent = 0; $fail = 0;
    $hour = (int) date('G'); $today = date('Y-m-d'); $dow = (int) date('N'); $weekTo = date('Y-m-d', strtotime('+14 days'));
    $users = $pdo->query("SELECT id,email,role,alpha,moderator,hide_cats,unsub_token,n_new,n_day,n_week,n_mode,n_weekday,last_morning,last_weekly FROM users
        WHERE notify=1 AND email IS NOT NULL AND email_verified_at IS NOT NULL AND (role='admin' OR approved_at IS NOT NULL) AND delete_requested_at IS NULL")->fetchAll();
    $subs = $pdo->prepare('SELECT s.id,s.name,s.summary,s.type FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND e.bell=1 AND s.fetched_at IS NOT NULL');
    $rels = $pdo->prepare("SELECT title,release_date FROM releases WHERE subject_id=? AND release_date<>'' AND release_date IS NOT NULL");
    $known = $pdo->prepare('SELECT key_hash FROM notified WHERE user_id=?');
    $seenSub = $pdo->prepare('SELECT DISTINCT subject_id FROM notified WHERE user_id=?');
    $ins = $pdo->prepare('INSERT IGNORE INTO notified (user_id,subject_id,key_hash,kind) VALUES (?,?,?,?)');
    $h = fn($uid, $sid, $t, $d, $k) => sha1("$uid|$sid|$t|$d|$k");
    $hk = fn($t) => preg_match('/^(S\d+E\d+)\b/i', (string) $t, $mm) ? strtoupper($mm[1]) : (string) $t; // Serienfolgen nur über die Folgennummer erkennen, damit eine umbenannte Folge (z. B. TBA → Titel) keine zweite Mail auslöst
    $sort = fn($a, $b) => strcmp($a[0], $b[0]);
    $bud = 0; // Zahl der Titel mit Kurzinfo je Mail (nur Neu angekündigt und Heute erscheint)
    $fmt = function (array $l) use ($pdo, &$bud) { usort($l, fn($a, $b) => strcmp($a[0], $b[0])); $o = array_map(function ($x) use ($pdo, &$bud) { $t = '- ' . $x[1]; if (isset($x[3]) && $bud > 0) { $bud--; $t .= item_info($pdo, $x[3], $x[4], $x[5] ?? null); } return $t; }, array_slice($l, 0, 40)); if (count($l) > 40) $o[] = '… und ' . (count($l) - 40) . ' weitere'; return implode("\n", $o); };
    foreach ($users as $u) {
        $uid = (int) $u['id']; $digest = $u['n_mode'] !== 'instant';
        $known->execute([$uid]); $K = array_flip($known->fetchAll(PDO::FETCH_COLUMN));
        $seenSub->execute([$uid]); $SS = array_flip($seenSub->fetchAll(PDO::FETCH_COLUMN));
        $subs->execute([$uid]);
        $new = []; $day = []; $past = []; $week = []; $mark = []; $cm = $hour >= 7;
        $hid = hidden_cats($u); // in den Einstellungen ausgeblendete Kategorien kommen auch nicht in die Mails
        $keep = [];
        foreach ($subs->fetchAll() as $s) {
            if (in_array($s['type'], $hid, true)) continue;
            $keep[] = (int) $s['id'];
            $sid = (int) $s['id']; $rels->execute([$sid]); $R = $rels->fetchAll();
            if (!isset($SS[$sid])) { // erstes Mal (oder Glocke neu an): bisherigen Stand merken, nichts melden
                $ins->execute([$uid, $sid, $h($uid, $sid, '', '', 'new'), 'new']);
                foreach ($R as $r) $ins->execute([$uid, $sid, $h($uid, $sid, $hk($r['title']), $r['release_date'], 'new'), 'new']);
                continue;
            }
            foreach ($R as $r) {
                $d = $r['release_date']; $line = $s['name'] . ' – ' . $r['title'] . ' (' . fmt_date($d) . ')';
                $kn = $h($uid, $sid, $hk($r['title']), $d, 'new'); $kd = $h($uid, $sid, $hk($r['title']), $d, 'day');
                if (isset($K[$h($uid, $sid, $r['title'], $d, 'new')])) $K[$kn] = 1; if (isset($K[$h($uid, $sid, $r['title'], $d, 'day')])) $K[$kd] = 1; // ältere Merkzeichen mit vollem Titel gelten weiter
                if (!isset($K[$kn])) {
                    $full = strlen($d) === 10; $nrow = [$d, $line, $sid, $s['name'], $r['title'], $s['summary'], $s['type']];
                    if ($cm && $s['type'] !== 'podcast' && $full && $d === $today && ($u['n_new'] || $u['n_day'])) { // erst jetzt bekannt, erscheint aber heute: gehört zu den heutigen Veröffentlichungen
                        $day[$sid . '|' . $r['title']] = [$d, $s['name'] . ' – ' . $r['title'], $sid, $s['name'], $r['title'], $s['summary']]; $mark[] = [$sid, $kn, 'new']; $mark[] = [$sid, $kd, 'day'];
                    } elseif ($u['n_new']) {
                        if ($s['type'] !== 'podcast' && $full && $d < $today) $past[] = $nrow; // schon erschienen
                        else $new[] = $nrow;
                        $mark[] = [$sid, $kn, 'new'];
                    }
                    else $ins->execute([$uid, $sid, $kn, 'new']); // still merken, damit später nichts auf einmal kommt
                }
                if (strlen($d) === 10) {
                    if ($d === $today && $u['n_day'] && !isset($K[$kd]) && $s['type'] !== 'podcast') { $day[$sid . '|' . $r['title']] = [$d, $s['name'] . ' – ' . $r['title'], $sid, $s['name'], $r['title'], $s['summary']]; $mark[] = [$sid, $kd, 'day']; }
                    if ($d >= $today && $d <= $weekTo) $week[] = [$d, $line];
                }
            }
        }
        // Merkstand für Einträge löschen, die nicht mehr beobachtet werden (entfernt, Glocke aus, Kategorie ausgeblendet): beim Wiedereinschalten gilt dann der neue Stand, statt alle Zwischentermine als neu zu melden
        try { if ($keep) { $pdo->prepare('DELETE FROM notified WHERE user_id=? AND subject_id NOT IN (' . implode(',', array_fill(0, count($keep), '?')) . ')')->execute(array_merge([$uid], $keep)); } else $pdo->prepare('DELETE FROM notified WHERE user_id=?')->execute([$uid]); } catch (Throwable $e) {}
        $pod = array_values(array_filter($new, fn($x) => ($x[6] ?? '') === 'podcast')); // Podcast-Folgen stehen unter eigener Überschrift
        $new = array_values(array_filter($new, fn($x) => ($x[6] ?? '') !== 'podcast'));
        $canMorning = $hour >= 7;
        $window = $digest ? ($canMorning && $u['last_morning'] !== $today) : ($hour >= 7 && $hour < 22);
        if (!$canMorning) $day = []; // Erscheinungstag-Mails erst ab 7 Uhr
        if ($window && ($new || $day || $pod || $past)) {
            if ($sent >= $maxMails) break;
            $bud = 8;
            $text = "Hallo,\n\nes gibt Neuigkeiten in deinen Listen.\n";
            if ($day) $text .= "\nHEUTIGE VERÖFFENTLICHUNGEN\n" . $fmt($day) . "\n";
            if ($past) $text .= "\nNEU ERSCHIENEN\n" . $fmt($past) . "\n";
            if ($new) $text .= "\nNEU ANGEKÜNDIGT\n" . $fmt($new) . "\n";
            if ($pod) $text .= "\nNEUE PODCAST-FOLGEN\n" . $fmt($pod) . "\n";
            $text .= "\nAlles im Überblick: " . base_url() . "/\n" . ($new || $day || $past ? "\nDie Schlagzeilen stammen aus der Google-News-Suche, die Kurzinfos aus den Datenquellen der Seite.\n" : '');
            $subj = (count($day) && !count($new) && !count($pod) && !count($past)) ? (count($day) === 1 ? 'Heute erscheint 1 Titel' : 'Heute erscheinen ' . count($day) . ' Titel') : ((count($new) + count($day) + count($pod) + count($past)) === 1 ? '1 Neuigkeit' : (count($new) + count($day) + count($pod) + count($past)) . ' Neuigkeiten');
            if (send_user_mail($pdo, $u, 'Schondraussen.de: ' . $subj, $text)) {
                foreach ($mark as $m) $ins->execute([$uid, $m[0], $m[1], $m[2]]);
                mail_count($pdo, array_values(array_filter([($new || $past || $pod) ? 'new' : null, $day ? 'day' : null])));
                if ($digest) $pdo->prepare('UPDATE users SET last_morning=? WHERE id=?')->execute([$today, $uid]);
                $sent++;
            } else $fail++;
        }
        if ($u['n_week'] && $canMorning && $dow === (int) $u['n_weekday'] && $u['last_weekly'] !== $today) {
            if ($sent >= $maxMails) break;
            $pdo->prepare('UPDATE users SET last_weekly=? WHERE id=?')->execute([$today, $uid]);
            $bud = 0;
            if ($week) {
                $text = "Hallo,\n\nhier sind die Termine der nächsten 14 Tage aus deinen Listen:\n\n" . $fmt($week) . "\n\nAlles im Überblick: " . base_url() . "/\n";
                if (send_user_mail($pdo, $u, 'Schondraussen.de: Deine Wochenübersicht (' . count($week) . ($week && count($week) === 1 ? ' Termin)' : ' Termine)'), $text)) { $sent++; mail_count($pdo, ['week']); } else $fail++;
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
            WHERE e.user_id=? AND e.bell=1 AND LENGTH(r.release_date)=10 AND r.release_date>=?" . (hidden_cats($u) ? ' AND s.type NOT IN (' . implode(',', array_fill(0, count(hidden_cats($u)), '?')) . ')' : '') . " ORDER BY r.release_date, s.name, r.title LIMIT 12");
        $s->execute(array_merge([$u['id'], $today], hidden_cats($u))); $R = $s->fetchAll();
    } catch (Throwable $e) { $R = []; }
    $demo = !$R;
    if ($demo) $R = [['name' => 'Beispiel-Band', 'title' => 'Neues Album', 'release_date' => date('Y-m-d', strtotime('+3 days'))], ['name' => 'Beispiel-Serie', 'title' => 'S02E05 Folgentitel', 'release_date' => date('Y-m-d', strtotime('+9 days'))]];
    $line = fn($r) => '- ' . $r['name'] . ' – ' . $r['title'] . ' (' . fmt_date($r['release_date']) . ')';
    $nNew = (int) ($u['n_new'] ?? 1); $nDay = (int) ($u['n_day'] ?? 1); $nWeek = (int) ($u['n_week'] ?? 0);
    $text = "VORSCHAU: So sieht deine Benachrichtigungsmail nach deinen aktuellen Einstellungen aus.\n" .
            "Die Termine sind nur als Beispiel eingesetzt" . ($demo ? " (Beispieldaten, du hast noch keine kommenden Termine mit Glocke)" : " (deine nächsten echten Termine mit Glocke)") . ". In der echten Mail steht nur, was wirklich zutrifft.\n\n" .
            "--- Morgenmail ---\n\nHallo,\n\nes gibt Neuigkeiten in deinen Listen.\n";
    if ($nDay) $text .= "\nHEUTIGE VERÖFFENTLICHUNGEN\n[Beispiel: Hier stehen nur Titel, die am selben Tag erscheinen.]\n" . '- Beispiel-Serie – S01E01 Folgentitel' . "\n";
    if ($nNew) $text .= "\nNEU ANGEKÜNDIGT\n[Beispiel: Hier steht jeder neue Termin nur einmal.]\n" . implode("\n", array_map($line, array_slice($R, 0, 6))) . "\n";
    if ($nNew || $nDay) $text .= "\n[Beispiel: In der echten Mail folgt unter jedem neuen Titel eine Kurzinfo (höchstens 8 Zeilen) und, wenn vorhanden, bis zu zwei aktuelle Schlagzeilen mit Link.]\n";
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
