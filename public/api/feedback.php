<?php
// Featurewünsche (für alle freigeschalteten Nutzer sichtbar, mit Stimmen) und Bugmeldungen (nur Admin und Alphatester)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $admin = $u['role'] === 'admin'; $alpha = is_alpha($u);
$kinds = ['feature' => 'Featurewunsch', 'bug' => 'Bugmeldung'];

if ($a === 'list') {
    $kind = (string) ($_GET['kind'] ?? 'feature');
    if (!isset($kinds[$kind])) json_out(['error' => 'Unbekannte Liste.'], 400);
    if ($kind === 'bug') require_alpha($u);
    try {
        $s = $pdo->prepare("SELECT f.id,f.title,f.body,f.priority,f.created_at,f.user_id,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id) AS votes,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id AND v.user_id=?) AS mine_vote
            FROM feedback f WHERE f.kind=? " . ($kind === 'bug'
                ? "ORDER BY FIELD(f.priority,'urgent','normal','low'), f.created_at DESC"
                : "ORDER BY votes DESC, f.created_at DESC") . " LIMIT 300");
        $s->execute([$u['id'], $kind]); $rows = $s->fetchAll();
    } catch (Throwable $e) { json_out(['items' => [], 'error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    foreach ($rows as &$r) { $r['mine'] = (int) $r['user_id'] === (int) $u['id']; if ($kind === 'feature') unset($r['priority']); if (!$alpha) unset($r['user_id']); }
    json_out(['items' => $rows, 'admin' => $admin]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
$b = body();

if ($a === 'add') {
    $kind = (string) ($b['kind'] ?? ''); $text = trim((string) ($b['text'] ?? ''));
    if (!isset($kinds[$kind])) json_out(['error' => 'Bitte wähle Featurewunsch oder Bugmeldung.'], 422);
    if (mb_strlen($text) < 5 || mb_strlen($text) > 2000) json_out(['error' => 'Bitte beschreibe es in 5 bis 2000 Zeichen.'], 422);
    try {
        $c = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
        if ((int) $c->fetchColumn() >= 10) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
        $first = trim(strtok($text, "\n")); $title = mb_strlen($first) > 80 ? mb_substr($first, 0, 79) . '…' : $first;
        $pdo->prepare('INSERT INTO feedback (user_id,kind,title,body) VALUES (?,?,?,?)')->execute([$u['id'], $kind, $title, $text]);
    } catch (PDOException $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    try {
        $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
        foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
            send_mail($adm, 'Neue ' . $kinds[$kind] . ' auf Schondraussen.de', "Von " . $u['email'] . ":\n\n" . $text . "\n\nZur Liste: $base/\n");
    } catch (Throwable $e) {}
    json_out(['ok' => true]);
}
$id = (int) ($b['id'] ?? 0);
$f = $pdo->prepare('SELECT kind FROM feedback WHERE id=?'); $f->execute([$id]); $kind = $f->fetchColumn();
if (!$kind) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
if ($kind === 'bug') require_alpha($u);

if ($a === 'vote') {
    if ($kind !== 'feature') json_out(['error' => 'Nur Featurewünsche haben Stimmen.'], 422);
    if (!empty($b['on'])) $pdo->prepare('INSERT IGNORE INTO feedback_votes (feedback_id,user_id) VALUES (?,?)')->execute([$id, $u['id']]);
    else $pdo->prepare('DELETE FROM feedback_votes WHERE feedback_id=? AND user_id=?')->execute([$id, $u['id']]);
    $n = $pdo->prepare('SELECT COUNT(*) FROM feedback_votes WHERE feedback_id=?'); $n->execute([$id]);
    json_out(['ok' => true, 'votes' => (int) $n->fetchColumn()]);
}
if ($a === 'priority') {
    if ($kind !== 'bug') json_out(['error' => 'Nur Bugmeldungen haben eine Dringlichkeit.'], 422);
    $p = (string) ($b['priority'] ?? 'normal'); if (!in_array($p, ['urgent', 'normal', 'low'], true)) json_out(['error' => 'Ungültig.'], 422);
    $pdo->prepare('UPDATE feedback SET priority=? WHERE id=?')->execute([$p, $id]);
    json_out(['ok' => true]);
}
if ($a === 'delete') {
    if (!$admin) json_out(['error' => 'Nicht erlaubt.'], 403);
    $pdo->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
