<?php
// Kontakt-Chat zwischen Nutzer:innen und Admin
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/images.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $admin = $u['role'] === 'admin'; $a = $_GET['a'] ?? '';

if ($a === 'unread') {
    if ($admin) $n = $pdo->query('SELECT COUNT(*) FROM messages WHERE from_admin=0 AND read_at IS NULL')->fetchColumn();
    else { $s = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id=? AND from_admin=1 AND read_at IS NULL'); $s->execute([$u['id']]); $n = $s->fetchColumn(); }
    json_out(['unread' => (int) $n]);
}
if ($a === 'threads' && $admin) {
    $rows = $pdo->query("SELECT u.id,u.username,
        (SELECT IF(body='','[Bild]',body) FROM messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_body,
        (SELECT from_admin FROM messages m WHERE m.user_id=u.id ORDER BY m.id DESC LIMIT 1) AS last_admin,
        (SELECT MAX(created_at) FROM messages m WHERE m.user_id=u.id) AS last_at,
        (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.from_admin=0 AND m.read_at IS NULL) AS unread
        FROM users u WHERE EXISTS (SELECT 1 FROM messages m WHERE m.user_id=u.id) ORDER BY last_at DESC")->fetchAll();
    json_out(['threads' => $rows]);
}
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
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
