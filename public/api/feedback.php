<?php
// Featurewünsche (für alle freigeschalteten Nutzer sichtbar, mit Stimmen) und Bugmeldungen (nur Admin und Alphatester)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/images.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $admin = $u['role'] === 'admin'; $alpha = is_alpha($u);
// Featurewünsche sind noch Alpha (nur Admin und Alphatester); auf false setzen, um sie für alle zu öffnen
const FEATURE_ALPHA = true;
$kinds = ['feature' => 'Featurewunsch', 'bug' => 'Bugmeldung'];

if ($a === 'list') {
    $kind = (string) ($_GET['kind'] ?? 'feature');
    if (!isset($kinds[$kind])) json_out(['error' => 'Unbekannte Liste.'], 400);
    if ($kind === 'bug' || FEATURE_ALPHA) require_alpha($u);
    $doneSel = ''; $doneOrd = '';
    try { $pdo->query('SELECT done_at FROM feedback LIMIT 1'); $doneSel = ',f.done_at'; $doneOrd = 'f.done_at IS NOT NULL, '; } catch (Throwable $e) {}
    try {
        $s = $pdo->prepare("SELECT f.id,f.title,f.body,f.priority,f.created_at,f.user_id,uu.email AS author_email,uu.username AS author_name,uu.role AS author_role,uu.alpha AS author_alpha$doneSel,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id) AS votes,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id AND v.user_id=?) AS mine_vote
            FROM feedback f LEFT JOIN users uu ON uu.id=f.user_id WHERE f.kind=? " . ($kind === 'bug'
                ? "ORDER BY {$doneOrd}FIELD(f.priority,'urgent','normal','low'), f.created_at DESC"
                : "ORDER BY votes DESC, f.created_at DESC") . " LIMIT 300");
        $s->execute([$u['id'], $kind]); $rows = $s->fetchAll();
    } catch (Throwable $e) { json_out(['items' => [], 'error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    foreach ($rows as &$r) {
        $r['mine'] = (int) $r['user_id'] === (int) $u['id'];
        if ($kind === 'feature') unset($r['priority']);
        // Absender: Admin sieht Nummer und E-Mail, Alphatester die Mitgliedsnummer
        if ($alpha) {
            $nm = $r['author_name'] ?: 'Mitglied Nr. ' . (int) $r['user_id'];
            $r['author'] = $nm . ($admin ? ($r['author_name'] ? ' (Nr. ' . (int) $r['user_id'] . ')' : '') . ($r['author_email'] ? ' · ' . $r['author_email'] : '') : '');
        }
        $r['author_tier'] = $r['author_role'] === 'admin' ? 'admin' : (!empty($r['author_alpha']) ? 'alpha' : '');
        unset($r['user_id'], $r['author_email'], $r['author_name'], $r['author_role'], $r['author_alpha']);
    }
    if ($rows) { // Bilder: Freigegebene für alle, Wartende für Absender und Admin/Alpha (dort mit status)
        try {
            $ids = implode(',', array_map('intval', array_column($rows, 'id'))); $by = []; $pend = [];
            foreach ($pdo->query("SELECT id,ref_id,status,user_id FROM images WHERE scope='feedback' AND ref_id IN ($ids) ORDER BY id")->fetchAll() as $im) {
                if ($im['status'] === 'approved' || $alpha || (int) $im['user_id'] === (int) $u['id']) $by[$im['ref_id']][] = ['id' => (int) $im['id'], 'status' => $im['status']];
                else $pend[$im['ref_id']] = ($pend[$im['ref_id']] ?? 0) + 1;
            }
            foreach ($rows as &$r) { $r['images'] = $by[$r['id']] ?? []; $r['pending_hidden'] = $pend[$r['id']] ?? 0; }
        } catch (Throwable $e) {}
    }
    json_out(['items' => $rows, 'admin' => $admin, 'alpha' => $alpha]);
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
        $fid = (int) $pdo->lastInsertId();
        // Bilder von Admin/Alpha gelten sofort als freigegeben, alle anderen warten auf die Prüfung
        if (!empty($b['images']) && is_array($b['images'])) { try { images_attach($pdo, $b['images'], (int) $u['id'], 'feedback', $fid, $alpha ? 'approved' : 'pending'); } catch (Throwable $e) {} }
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
if ($kind === 'bug' || FEATURE_ALPHA) require_alpha($u);

if ($a === 'vote') {
    if ($kind !== 'feature') json_out(['error' => 'Nur Featurewünsche haben Stimmen.'], 422);
    if (!empty($b['on'])) $pdo->prepare('INSERT IGNORE INTO feedback_votes (feedback_id,user_id) VALUES (?,?)')->execute([$id, $u['id']]);
    else $pdo->prepare('DELETE FROM feedback_votes WHERE feedback_id=? AND user_id=?')->execute([$id, $u['id']]);
    $n = $pdo->prepare('SELECT COUNT(*) FROM feedback_votes WHERE feedback_id=?'); $n->execute([$id]);
    json_out(['ok' => true, 'votes' => (int) $n->fetchColumn()]);
}
if ($a === 'done') {
    if ($kind !== 'bug') json_out(['error' => 'Nur Bugmeldungen lassen sich abhaken.'], 422);
    try { $pdo->prepare('UPDATE feedback SET done_at=' . (!empty($b['on']) ? 'NOW()' : 'NULL') . ' WHERE id=?')->execute([$id]); }
    catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'priority') {
    if ($kind !== 'bug') json_out(['error' => 'Nur Bugmeldungen haben eine Dringlichkeit.'], 422);
    $p = (string) ($b['priority'] ?? 'normal'); if (!in_array($p, ['urgent', 'normal', 'low'], true)) json_out(['error' => 'Ungültig.'], 422);
    $pdo->prepare('UPDATE feedback SET priority=? WHERE id=?')->execute([$p, $id]);
    json_out(['ok' => true]);
}
if ($a === 'delete') {
    if (!$admin) json_out(['error' => 'Nicht erlaubt.'], 403);
    try { $pdo->prepare("DELETE FROM images WHERE scope='feedback' AND ref_id=?")->execute([$id]); } catch (Throwable $e) {}
    $pdo->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
