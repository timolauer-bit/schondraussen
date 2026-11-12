<?php
// Kontakt-Chat zwischen Nutzer:innen und Admin
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/images.php';
require_once __DIR__ . '/../lib/anon.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $admin = $u['role'] === 'admin'; $a = $_GET['a'] ?? '';

if ($a === 'unread') {
    if ($admin) { $n = $pdo->query('SELECT COUNT(*) FROM messages WHERE from_admin=0 AND read_at IS NULL')->fetchColumn(); try { $n += (int) $pdo->query('SELECT COUNT(*) FROM admin_notices WHERE read_at IS NULL')->fetchColumn(); } catch (Throwable $e) {} }
    else { $s = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id=? AND from_admin=1 AND read_at IS NULL'); $s->execute([$u['id']]); $n = $s->fetchColumn(); }
    if (is_alpha($u)) { try { $q = $pdo->prepare('SELECT COUNT(*) FROM team_messages WHERE user_id<>? AND created_at > COALESCE((SELECT seen_at FROM team_chat_seen WHERE user_id=?), \'1970-01-01\')'); $q->execute([$u['id'], $u['id']]); $n += (int) $q->fetchColumn(); } catch (Throwable $e) {} }
    if (is_alpha($u) && !$admin) { try { $q = $pdo->prepare('SELECT COUNT(*) FROM alpha_dm WHERE to_id=? AND read_at IS NULL'); $q->execute([$u['id']]); $n += (int) $q->fetchColumn(); } catch (Throwable $e) {} }
    json_out(['unread' => (int) $n]);
}
// ---- Team-Chat: gemeinsamer Chat von Admin und Alphatestern (Rechte: „Ideen und Bugs: alle Beiträge sehen und verwalten“) ----
if (strncmp($a, 'team_', 5) === 0 || $a === 'team') {
    if (!is_alpha($u)) json_out(['error' => 'Nur für Admin und Alphatester.'], 403);
    try {
        if ($a === 'team_thread') {
            try { $rows = $pdo->query('SELECT m.id, m.user_id, m.body, m.is_poll, m.created_at, m.closed_at, u.username FROM team_messages m LEFT JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC); }
            catch (Throwable $e) { $rows = $pdo->query('SELECT m.id, m.user_id, m.body, m.is_poll, m.created_at, u.username FROM team_messages m LEFT JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC); }
            $rows = array_reverse($rows);
            $ids = array_map(fn($r) => (int) $r['id'], array_filter($rows, fn($r) => $r['is_poll']));
            $opts = []; $mine = []; 
            if ($ids) {
                $in = implode(',', $ids);
                foreach ($pdo->query("SELECT o.id, o.msg_id, o.label, (SELECT COUNT(*) FROM team_poll_votes v WHERE v.opt_id=o.id) AS votes FROM team_poll_options o WHERE o.msg_id IN ($in) ORDER BY o.id")->fetchAll(PDO::FETCH_ASSOC) as $o) $opts[$o['msg_id']][] = ['id' => (int) $o['id'], 'label' => $o['label'], 'votes' => (int) $o['votes']];
                foreach ($pdo->query("SELECT msg_id, opt_id FROM team_poll_votes WHERE user_id=" . (int) $u['id'] . " AND msg_id IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $v) $mine[$v['msg_id']] = (int) $v['opt_id'];
            }
            foreach ($rows as &$r) { $r['name'] = trim((string) $r['username']) ?: 'Mitglied Nr. ' . (int) $r['user_id']; $r['mine'] = (int) $r['user_id'] === (int) $u['id']; $r['can_close'] = $r['mine'] || $admin; $r['closed'] = !empty($r['closed_at']); unset($r['username'], $r['closed_at']); if ($r['is_poll']) { $r['options'] = $opts[$r['id']] ?? []; $r['my_vote'] = $mine[$r['id']] ?? null; } }
            try { // Bilder und Textdateien zu den Nachrichten
                $all = implode(',', array_map(fn($r) => (int) $r['id'], $rows)); $by = [];
                if ($all) foreach ($pdo->query("SELECT id, ref_id, kind, name FROM images WHERE scope='team' AND ref_id IN ($all) AND removed_at IS NULL ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $im) $by[$im['ref_id']][] = ['id' => (int) $im['id'], 'kind' => $im['kind'] ?? 'image', 'name' => $im['name']];
                foreach ($rows as &$r) $r['images'] = $by[$r['id']] ?? [];
                unset($r);
            } catch (Throwable $e) {}
            $pdo->prepare('INSERT INTO team_chat_seen (user_id, seen_at) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE seen_at=NOW()')->execute([$u['id']]);
            json_out(['messages' => $rows, 'team' => true]);
        }
        if ($a === 'team_unread') { json_out(['ok' => true]); }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'Unbekannte Aktion'], 400);
        $b = body();
        if ($a === 'team_send') {
            $text = trim((string) ($b['text'] ?? '')); $poll = is_array($b['poll'] ?? null) ? $b['poll'] : null; $imgs = is_array($b['images'] ?? null) ? $b['images'] : [];
            if ($poll) {
                $q = trim((string) ($poll['q'] ?? '')); $os = array_values(array_unique(array_filter(array_map(fn($x) => trim(mb_substr((string) $x, 0, 120)), (array) ($poll['options'] ?? [])), fn($x) => $x !== '')));
                if ($q === '' || mb_strlen($q) > 300) json_out(['error' => 'Bitte eine Frage bis 300 Zeichen eingeben.'], 422);
                if (count($os) < 2 || count($os) > 8) json_out(['error' => 'Bitte 2 bis 8 verschiedene Antworten angeben.'], 422);
                $pdo->prepare('INSERT INTO team_messages (user_id, body, is_poll) VALUES (?,?,1)')->execute([$u['id'], $q]); $mid = (int) $pdo->lastInsertId();
                $ins = $pdo->prepare('INSERT INTO team_poll_options (msg_id, label) VALUES (?,?)'); foreach ($os as $o) $ins->execute([$mid, $o]);
                json_out(['ok' => true]);
            }
            if (($text === '' && !$imgs) || mb_strlen($text) > 1000) json_out(['error' => 'Bitte eine Nachricht bis 1000 Zeichen eingeben.'], 422);
            $c = $pdo->prepare('SELECT COUNT(*) FROM team_messages WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
            if ((int) $c->fetchColumn() >= 60) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
            $pdo->prepare('INSERT INTO team_messages (user_id, body) VALUES (?,?)')->execute([$u['id'], $text]); $mid = (int) $pdo->lastInsertId();
            if ($imgs) { try { images_attach($pdo, $imgs, (int) $u['id'], 'team', $mid, 'approved'); } catch (Throwable $e) {} }
            json_out(['ok' => true]);
        }
        if ($a === 'team_close') { // Ersteller (oder Admin) schließt die Abstimmung oder öffnet sie wieder
            $mid = (int) ($b['msg'] ?? 0); $q = $pdo->prepare('SELECT user_id, closed_at FROM team_messages WHERE id=? AND is_poll=1'); $q->execute([$mid]); $m = $q->fetch(PDO::FETCH_ASSOC);
            if (!$m) json_out(['error' => 'Abstimmung nicht gefunden.'], 404);
            if ((int) $m['user_id'] !== (int) $u['id'] && !$admin) json_out(['error' => 'Nur wer die Abstimmung erstellt hat, kann sie schließen.'], 403);
            $pdo->prepare($m['closed_at'] ? 'UPDATE team_messages SET closed_at=NULL WHERE id=?' : 'UPDATE team_messages SET closed_at=NOW() WHERE id=?')->execute([$mid]);
            json_out(['ok' => true, 'closed' => !$m['closed_at']]);
        }
        if ($a === 'team_vote') {
            $mid = (int) ($b['msg'] ?? 0); $oid = (int) ($b['opt'] ?? 0);
            try { $cl = $pdo->prepare('SELECT closed_at FROM team_messages WHERE id=?'); $cl->execute([$mid]); if ($cl->fetchColumn()) json_out(['error' => 'Die Abstimmung ist geschlossen.'], 409); } catch (PDOException $e) {}
            $ok = $pdo->prepare('SELECT 1 FROM team_poll_options o JOIN team_messages m ON m.id=o.msg_id AND m.is_poll=1 WHERE o.id=? AND o.msg_id=?'); $ok->execute([$oid, $mid]);
            if (!$ok->fetchColumn()) json_out(['error' => 'Antwort nicht gefunden.'], 404);
            $cur = $pdo->prepare('SELECT opt_id FROM team_poll_votes WHERE msg_id=? AND user_id=?'); $cur->execute([$mid, $u['id']]);
            if ((int) $cur->fetchColumn() === $oid) $pdo->prepare('DELETE FROM team_poll_votes WHERE msg_id=? AND user_id=?')->execute([$mid, $u['id']]); // gleiche Antwort nochmal = Stimme zurücknehmen
            else $pdo->prepare('INSERT INTO team_poll_votes (msg_id, user_id, opt_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE opt_id=VALUES(opt_id)')->execute([$mid, $u['id'], $oid]);
            json_out(['ok' => true]);
        }
    } catch (Throwable $e) { json_out(['error' => 'Der Team-Chat ist noch nicht eingerichtet (Migration 067 einspielen).'], 500); }
    json_out(['error' => 'Unbekannte Aktion'], 400);
}
// ---- Direktnachrichten zwischen Alphatestern (Admin liest nicht mit, mit ihm gibt es den normalen Chat) ----
function alpha_peers(PDO $pdo, int $me): array {
    try { return $pdo->query("SELECT id, username FROM users WHERE alpha=1 AND role<>'admin' AND id<>" . (int) $me . " AND approved_at IS NOT NULL AND admin_deleted=0 AND delete_requested_at IS NULL ORDER BY username IS NULL, username, id LIMIT 200")->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { return []; }
}
if (($a === 'dm_thread' || $a === 'dm_send') && is_alpha($u) && !$admin) {
    $peer = (int) ($_GET['uid'] ?? body()['uid'] ?? 0); $ok = false; foreach (alpha_peers($pdo, (int) $u['id']) as $pr) if ((int) $pr['id'] === $peer) $ok = true;
    if (!$ok) json_out(['error' => 'Diese Person ist kein Alphatester.'], 404);
    try {
        if ($a === 'dm_thread') {
            $pdo->prepare('UPDATE alpha_dm SET read_at=NOW() WHERE from_id=? AND to_id=? AND read_at IS NULL')->execute([$peer, $u['id']]);
            $q = $pdo->prepare('SELECT id, from_id, body, created_at, read_at FROM alpha_dm WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?) ORDER BY id DESC LIMIT 200');
            $q->execute([$u['id'], $peer, $peer, $u['id']]); $rows = array_reverse($q->fetchAll(PDO::FETCH_ASSOC));
            foreach ($rows as &$r) { $r['mine'] = (int) $r['from_id'] === (int) $u['id']; unset($r['from_id']); } unset($r);
            json_out(['messages' => $rows]);
        }
        $text = trim((string) (body()['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 1000) json_out(['error' => 'Bitte eine Nachricht bis 1000 Zeichen eingeben.'], 422);
        $c = $pdo->prepare('SELECT COUNT(*) FROM alpha_dm WHERE from_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
        if ((int) $c->fetchColumn() >= 60) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
        $pdo->prepare('INSERT INTO alpha_dm (from_id, to_id, body) VALUES (?,?,?)')->execute([$u['id'], $peer, $text]);
        json_out(['ok' => true]);
    } catch (PDOException $e) { json_out(['error' => 'Die Direktnachrichten sind noch nicht eingerichtet (Migration 073 einspielen).'], 500); }
}
if ($a === 'threads' && ($admin || is_alpha($u))) {
    $rows = !$admin ? [] : $pdo->query("SELECT u.id,u.username,
        (SELECT IF(body='','[Bild]',body) FROM messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT from_admin FROM messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_admin,
        (SELECT MAX(created_at) FROM messages m WHERE m.user_id=u.id) AS last_at,
        (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.from_admin=0 AND m.read_at IS NULL) AS unread
        FROM users u WHERE EXISTS (SELECT 1 FROM messages m WHERE m.user_id=u.id) ORDER BY last_at DESC")->fetchAll();
    if ($admin) try { // Unterhaltung „System“: automatische Meldungen der Seite an den Admin
        $sys = $pdo->query("SELECT (SELECT body FROM admin_notices ORDER BY id DESC LIMIT 1) AS last_body, (SELECT MAX(created_at) FROM admin_notices) AS last_at, (SELECT COUNT(*) FROM admin_notices WHERE read_at IS NULL) AS unread")->fetch();
        if ($sys && $sys['last_at']) { array_unshift($rows, ['id' => 'sys', 'username' => 'System', 'last_body' => $sys['last_body'], 'last_admin' => 0, 'last_at' => $sys['last_at'], 'unread' => (int) $sys['unread']]); }
    } catch (Throwable $e) {}
    try { $tm = $pdo->query('SELECT (SELECT CONCAT(IFNULL(NULLIF(TRIM(uu.username),\'\'),\'Mitglied\'), \': \', IF(m.is_poll, \'[Abstimmung] \', \'\'), m.body) FROM team_messages m LEFT JOIN users uu ON uu.id=m.user_id ORDER BY m.id DESC LIMIT 1) AS last_body, (SELECT MAX(created_at) FROM team_messages) AS last_at')->fetch(PDO::FETCH_ASSOC);
        $q = $pdo->prepare('SELECT COUNT(*) FROM team_messages WHERE user_id<>? AND created_at > COALESCE((SELECT seen_at FROM team_chat_seen WHERE user_id=?), \'1970-01-01\')'); $q->execute([$u['id'], $u['id']]);
        array_unshift($rows, ['id' => 'team', 'username' => 'Team (Admin und Alphatester)', 'last_body' => $tm['last_body'] ?? '', 'last_admin' => 0, 'last_at' => $tm['last_at'] ?? null, 'unread' => (int) $q->fetchColumn()]);
    } catch (Throwable $e) {}
    if (!$admin) try { // Direktchats mit den anderen Alphatestern
        foreach (alpha_peers($pdo, (int) $u['id']) as $pr) {
            $pid = (int) $pr['id'];
            $q = $pdo->prepare('SELECT body, from_id, created_at FROM alpha_dm WHERE (from_id=? AND to_id=?) OR (from_id=? AND to_id=?) ORDER BY id DESC LIMIT 1'); $q->execute([$u['id'], $pid, $pid, $u['id']]); $l = $q->fetch(PDO::FETCH_ASSOC) ?: [];
            $q = $pdo->prepare('SELECT COUNT(*) FROM alpha_dm WHERE from_id=? AND to_id=? AND read_at IS NULL'); $q->execute([$pid, $u['id']]);
            $rows[] = ['id' => 'dm' . $pid, 'username' => trim((string) $pr['username']) !== '' ? $pr['username'] : 'User-' . $pid, 'last_body' => $l['body'] ?? '', 'last_admin' => isset($l['from_id']) && (int) $l['from_id'] === (int) $u['id'] ? 1 : 0, 'last_at' => $l['created_at'] ?? null, 'unread' => (int) $q->fetchColumn(), 'dm' => 1];
        }
    } catch (Throwable $e) {}
    json_out(['threads' => $rows]);
}
if ($a === 'users' && $admin) { // Auswahlliste für neue Unterhaltungen (ohne E-Mail-Adressen)
    try { $rows = $pdo->query("SELECT id,username FROM users WHERE role<>'admin' AND approved_at IS NOT NULL AND admin_deleted=0 AND delete_requested_at IS NULL ORDER BY username IS NULL, username, id LIMIT 1000")->fetchAll(); }
    catch (Throwable $e) { $rows = $pdo->query("SELECT id,username FROM users WHERE role<>'admin' AND approved_at IS NOT NULL ORDER BY username IS NULL, username, id LIMIT 1000")->fetchAll(); }
    json_out(['users' => $rows]);
}
if ($admin && $a === 'thread' && ($_GET['uid'] ?? '') === 'sys') {
    try {
        $pdo->exec('UPDATE admin_notices SET read_at=NOW() WHERE read_at IS NULL');
        $msgs = $pdo->query('SELECT id, 0 AS from_admin, body, created_at, read_at, kind FROM admin_notices ORDER BY id DESC LIMIT 200')->fetchAll(); $msgs = array_reverse($msgs);
    } catch (Throwable $e) { $msgs = []; }
    json_out(['messages' => $msgs, 'system' => true]);
}
if ($admin && $a === 'send' && (($_GET['uid'] ?? '') === 'sys' || (body()['uid'] ?? '') === 'sys')) json_out(['error' => 'Die Systemmeldungen sind nur zum Lesen. Wähle links einen Nutzer, um zu antworten.'], 422);
$uid = $admin ? (int) ($_GET['uid'] ?? body()['uid'] ?? 0) : (int) $u['id'];
if ($a === 'thread') {
    if (!$uid) json_out(['messages' => []]);
    $other = $admin ? 0 : 1; // vom Gegenüber geschriebene Nachrichten werden beim Öffnen gelesen
    $pdo->prepare('UPDATE messages SET read_at=NOW() WHERE user_id=? AND from_admin=? AND read_at IS NULL')->execute([$uid, $other]);
    $s = $pdo->prepare('SELECT id,from_admin,body,created_at,read_at FROM messages WHERE user_id=? ORDER BY id DESC LIMIT 200');
    $s->execute([$uid]);
    $msgs = array_reverse($s->fetchAll());
    if ($msgs) { // Bilder zu den Nachrichten
        try {
            $ids = implode(',', array_map('intval', array_column($msgs, 'id')));
            $by = [];
            foreach ($pdo->query("SELECT id,ref_id FROM images WHERE scope='chat' AND ref_id IN ($ids) ORDER BY id")->fetchAll() as $im) $by[$im['ref_id']][] = (int) $im['id'];
            foreach ($msgs as &$m) $m['images'] = $by[$m['id']] ?? [];
        } catch (Throwable $e) {}
    }
    json_out(['messages' => $msgs]);
}
if ($a === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $text = trim((string) (body()['text'] ?? ''));
    $imgs = is_array(body()['images'] ?? null) ? body()['images'] : [];
    if (($text === '' && !$imgs) || mb_strlen($text) > 1000) json_out(['error' => 'Bitte eine Nachricht bis 1000 Zeichen eingeben.'], 422);
    if (!$uid) json_out(['error' => 'Keine Unterhaltung gewählt.'], 422);
    if (!$admin) { // höchstens 20 Nachrichten pro Stunde
        $c = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id=? AND from_admin=0 AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$uid]);
        if ((int) $c->fetchColumn() >= 20) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
    }
    $prior = 0;
    if (!$admin) { $p = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id=? AND from_admin=0 AND read_at IS NULL AND created_at > NOW() - INTERVAL 15 MINUTE'); $p->execute([$uid]); $prior = (int) $p->fetchColumn(); }
    $pdo->prepare('INSERT INTO messages (user_id,from_admin,body) VALUES (?,?,?)')->execute([$uid, $admin ? 1 : 0, $text]);
    $mid = (int) $pdo->lastInsertId();
    if ($imgs) { try { images_attach($pdo, $imgs, (int) $u['id'], 'chat', $mid, 'approved'); } catch (Throwable $e) {} }
    if (!$admin && !$prior) {
        try {
            $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
            foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
                send_mail($adm, 'Neue Nachricht auf Schondraussen.de', "Von " . user_label($u) . ":\n\n" . ($text === '' ? '[Bild]' : $text) . ($imgs ? "\n(mit Bild)" : '') . "\n\nAntworten unter Kontakt: $base/\n");
        } catch (Throwable $e) {}
    }
    $mailed = null;
    if ($admin && !empty(body()['mail'])) { // zusätzlich per E-Mail an den Nutzer (Adresse bleibt auf dem Server)
        $mailed = false;
        try {
            $q = $pdo->prepare("SELECT email FROM users WHERE id=? AND role<>'admin' AND email_verified_at IS NOT NULL"); $q->execute([$uid]); $to = (string) $q->fetchColumn();
            if ($to !== '') {
                $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
                $mailed = send_mail($to, '[Direkt vom Admin] Neue Nachricht auf Schondraussen.de',
                    "Der Administrator von Schondraussen.de hat dir geschrieben:\n\n" . ($text === '' ? '[Bild, bitte auf der Webseite ansehen]' : $text) . "\n\n---\nAuf diese E-Mail kannst du nicht antworten. Bitte antworte auf der Webseite unter „Kontakt“:\n$base/\n");
            }
        } catch (Throwable $e) { $mailed = false; }
    }
    json_out(['ok' => true, 'mailed' => $mailed]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
