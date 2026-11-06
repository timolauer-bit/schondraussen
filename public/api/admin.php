<?php
// Nutzerverwaltung (nur Admin)
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || $u['role'] !== 'admin') json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $b = body();
if ($a === 'users') {
    $cols = 'u.id,u.email,u.username,u.name_change_required,u.role,u.created_at,u.last_seen_at,u.email_verified_at,u.approved_at';
    $sql = " FROM users u ORDER BY (u.approved_at IS NULL AND u.role<>'admin') DESC, u.created_at DESC";
    $ent = ',(SELECT COUNT(*) FROM user_entries e WHERE e.user_id=u.id) AS entries';
    try { $r = $pdo->query('SELECT ' . $cols . ',u.alpha' . $ent . $sql)->fetchAll(); }
    catch (Throwable $e) { $r = $pdo->query('SELECT ' . str_replace('u.username,u.name_change_required,', '', $cols) . $ent . $sql)->fetchAll(); }
    json_out(['users' => $r, 'me' => (int) $u['id']]);
}
if ($a === 'site') {
    $o = ['tag' => '', 'domain' => 'amazon.de'];
    try { foreach ($pdo->query('SELECT k,v FROM site_settings')->fetchAll() as $r) $o[$r['k']] = $r['v']; } catch (Throwable $e) {}
    json_out($o);
}
if ($a === 'savesite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag = trim((string) ($b['tag'] ?? '')); $dom = (string) ($b['domain'] ?? 'amazon.de');
    if (!preg_match('/^[A-Za-z0-9._-]{0,64}$/', $tag)) json_out(['error' => 'Nur Buchstaben, Ziffern, Punkt, Bindestrich.'], 422);
    if (!in_array($dom, ['amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.co.uk', 'amazon.com'], true)) json_out(['error' => 'Unbekannter Shop.'], 422);
    $q = $pdo->prepare('INSERT INTO site_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
    $q->execute(['tag', $tag]); $q->execute(['domain', $dom]);
    json_out(['ok' => true]);
}
if ($a === 'blocked') {
    try { json_out(['blocked' => $pdo->query('SELECT email,created_at FROM blocked_emails ORDER BY created_at DESC')->fetchAll()]); }
    catch (Throwable $e) { json_out(['blocked' => [], 'error' => 'Datenbank noch nicht aktualisiert (migrate.php).']); }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
if ($a === 'block' || $a === 'unblock') {
    $mail = strtolower(trim((string) ($b['email'] ?? '')));
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL) || strlen($mail) > 190) json_out(['error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 422);
    try {
        if ($a === 'unblock') $pdo->prepare('DELETE FROM blocked_emails WHERE email=?')->execute([$mail]);
        else {
            if (strcasecmp($mail, $u['email']) === 0) json_out(['error' => 'Die eigene Adresse kann nicht gesperrt werden.'], 422);
            $pdo->prepare('INSERT IGNORE INTO blocked_emails (email) VALUES (?)')->execute([$mail]);
            $pdo->prepare("DELETE FROM users WHERE email=? AND role<>'admin'")->execute([$mail]);
        }
    } catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
$id = (int) ($b['id'] ?? 0);
if ($id === (int) $u['id']) json_out(['error' => 'Das eigene Konto kann hier nicht geändert werden.'], 422);
if ($a === 'alpha') {
    try { $pdo->prepare("UPDATE users SET alpha=? WHERE id=? AND role<>'admin'")->execute([!empty($b['on']) ? 1 : 0, $id]); }
    catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'renamereq') {
    $on = !empty($b['on']);
    try { $pdo->prepare("UPDATE users SET name_change_required=? WHERE id=? AND role<>'admin'")->execute([$on ? 1 : 0, $id]); }
    catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    if ($on) {
        try {
            require_once __DIR__ . '/../lib/mail.php';
            $s = $pdo->prepare('SELECT email FROM users WHERE id=?'); $s->execute([$id]); $em = $s->fetchColumn();
            if ($em) send_mail($em, 'Bitte wähle einen neuen Benutzernamen', "Dein Benutzername auf Schondraussen.de entspricht nicht unseren Regeln.\n\nBitte melde dich an und wähle einen neuen Namen. Bis dahin kannst du die Seite nicht nutzen. Erlaubt sind Namen ohne anstößige, beleidigende oder verbotene Inhalte und ohne Verwechslung mit dem Betreiber.\n\n" . rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/') . "/\n");
        } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}
if ($a === 'approve') { $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$id]); json_out(['ok' => true]); }
if ($a === 'revoke')  { $pdo->prepare("UPDATE users SET approved_at=NULL WHERE id=? AND role<>'admin'")->execute([$id]); json_out(['ok' => true]); }
if ($a === 'delete')  {
    if (!empty($b['block'])) { try { $pdo->prepare("INSERT IGNORE INTO blocked_emails (email) SELECT email FROM users WHERE id=? AND role<>'admin'")->execute([$id]); } catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); } }
    $pdo->prepare("DELETE FROM users WHERE id=? AND role<>'admin'")->execute([$id]); json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
