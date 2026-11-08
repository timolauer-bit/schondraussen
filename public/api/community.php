<?php
// Community: Ranglisten (anonym) und Nutzerübersichten (nur mit ausdrücklicher Zustimmung). Für alle freigeschalteten Nutzer.
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? '';
$TYPES = ['author', 'artist', 'film', 'series'];
// Zustimmung: ausdrücklich 1, oder noch nicht entschieden und Admin (Startmodus)
$CONSENT = "(u.community=1 OR (u.community IS NULL AND u.role='admin'))";
$UOK = "(u.role='admin' OR u.approved_at IS NOT NULL) AND u.username IS NOT NULL AND u.username<>''";

function q_all(PDO $pdo, string $sql, array $p = []): array {
    try { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) {
        if (strpos($sql, 'is_sample') !== false) { // Spalte fehlt: ohne diese Bedingung erneut
            $sql = str_replace(['AND e.is_sample=0', 'WHERE e.is_sample=0 AND'], ['', 'WHERE'], $sql);
            try { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e2) {}
        }
        json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500);
    }
}
function consenting_user(PDO $pdo, int $id, string $CONSENT, string $UOK): ?array {
    $r = q_all($pdo, "SELECT u.id,u.username FROM users u WHERE u.id=? AND $UOK AND $CONSENT", [$id]);
    return $r[0] ?? null;
}

if ($a === 'overview') {
    $out = ['top' => [], 'new' => [], 'users' => [], 'me' => (int) $u['id']];
    foreach ($TYPES as $t) {
        // Ranglisten zählen nur Nutzer mit Zustimmung, als Summe pro Titel ohne Namen
        $out['top'][$t] = q_all($pdo, "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.is_sample=0 AND s.type=? AND (u.role='admin' OR u.approved_at IS NOT NULL) AND $CONSENT GROUP BY s.id, s.name ORDER BY c DESC, s.name LIMIT 50", [$u['id'], $t]);
        $out['new'][$t] = q_all($pdo, "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.is_sample=0 AND s.type=? AND (u.role='admin' OR u.approved_at IS NOT NULL) AND $CONSENT AND e.added_at >= (NOW() - INTERVAL 14 DAY) GROUP BY s.id, s.name ORDER BY c DESC, s.name LIMIT 50", [$u['id'], $t]);
    }
    // Aktivste Nutzer: nur mit Zustimmung
    $rows = q_all($pdo, "SELECT u.id, u.username, s.type, COUNT(*) AS c, SUM(e.added_at >= (NOW() - INTERVAL 14 DAY)) AS r FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id WHERE e.is_sample=0 AND $UOK AND $CONSENT GROUP BY u.id, u.username, s.type");
    $by = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (!isset($by[$id])) $by[$id] = ['id' => $id, 'name' => $r['username'], 'all' => array_fill_keys($TYPES, 0), 'rec' => array_fill_keys($TYPES, 0)];
        $by[$id]['all'][$r['type']] = (int) $r['c']; $by[$id]['rec'][$r['type']] = (int) $r['r'];
    }
    $out['users'] = array_values($by);
    json_out($out);
}

if ($a === 'user') {
    $id = (int) ($_GET['id'] ?? 0);
    $cu = consenting_user($pdo, $id, $CONSENT, $UOK);
    if (!$cu) json_out(['error' => 'Diese Person teilt ihre Liste nicht.'], 404);
    $lists = array_fill_keys($TYPES, []);
    foreach (q_all($pdo, "SELECT s.id, s.name, s.type, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.user_id=? AND e.is_sample=0 GROUP BY s.id, s.name, s.type ORDER BY s.name", [$u['id'], $id]) as $r) $lists[$r['type']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'mine' => (int) $r['mine']];
    $last = q_all($pdo, "SELECT s.id, s.name, s.type, e.added_at, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.user_id=? AND e.is_sample=0 GROUP BY e.id, s.id, s.name, s.type, e.added_at ORDER BY e.added_at DESC, e.id DESC LIMIT 20", [$u['id'], $id]);
    foreach ($last as &$l) { $l['id'] = (int) $l['id']; $l['mine'] = (int) $l['mine']; } unset($l);
    json_out(['user' => ['id' => (int) $cu['id'], 'name' => $cu['username']], 'lists' => $lists, 'last' => $last]);
}

if ($a === 'adopt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int) (body()['subject_id'] ?? 0);
    $s = $pdo->prepare('SELECT id,type FROM subjects WHERE id=?'); $s->execute([$sid]); $sub = $s->fetch(PDO::FETCH_ASSOC);
    if (!$sub) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
    $c = $pdo->prepare('SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $c->execute([$u['id'], $sub['type']]);
    $h = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $h->execute([$u['id'], $sid]);
    if ($h->fetchColumn()) json_out(['ok' => true, 'had' => true]);
    if ((int) $c->fetchColumn() >= 100) json_out(['error' => 'Du hast die Grenze von 100 Einträgen für diese Liste erreicht. Bitte lösche zuerst einige Einträge.'], 422);
    $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)')->execute([$u['id'], $sid]);
    if (isset($u['bell_default']) && !(int) $u['bell_default']) { try { $pdo->prepare('UPDATE user_entries SET bell=0 WHERE user_id=? AND subject_id=?')->execute([$u['id'], $sid]); } catch (Throwable $e) {} }
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion.'], 400);
