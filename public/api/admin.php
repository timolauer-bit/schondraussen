<?php
// Nutzerverwaltung (nur Admin)
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'admin') json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $b = body();
if ($a === 'users') {
    $r = $pdo->query("SELECT u.id,u.email,u.role,u.created_at,u.last_seen_at,u.email_verified_at,u.approved_at,
        (SELECT COUNT(*) FROM user_entries e WHERE e.user_id=u.id) AS entries FROM users u ORDER BY (u.approved_at IS NULL AND u.role<>'admin') DESC, u.created_at DESC")->fetchAll();
    json_out(['users' => $r, 'me' => (int) $u['id']]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
$id = (int) ($b['id'] ?? 0);
if ($id === (int) $u['id']) json_out(['error' => 'Das eigene Konto kann hier nicht geändert werden.'], 422);
if ($a === 'approve') { $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$id]); json_out(['ok' => true]); }
if ($a === 'revoke')  { $pdo->prepare("UPDATE users SET approved_at=NULL WHERE id=? AND role<>'admin'")->execute([$id]); json_out(['ok' => true]); }
if ($a === 'delete')  { $pdo->prepare("DELETE FROM users WHERE id=? AND role<>'admin'")->execute([$id]); json_out(['ok' => true]); }
json_out(['error' => 'Unbekannte Aktion'], 400);
