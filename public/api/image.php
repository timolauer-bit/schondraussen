<?php
// Bilder ausliefern und hochladen (nur für angemeldete, freigeschaltete Nutzer)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/images.php';
$u = current_user();
$a = $_GET['a'] ?? 'get';
if (!$u || empty($u['approved'])) { if ($a === 'get') { http_response_code(403); exit; } json_out(['error' => 'Nicht erlaubt.'], 403); }
$pdo = db(); $admin = $u['role'] === 'admin'; $alpha = is_alpha($u);

if ($a === 'get') {
    $s = $pdo->prepare('SELECT * FROM images WHERE id=?'); $s->execute([(int) ($_GET['id'] ?? 0)]); $im = $s->fetch();
    $ok = false;
    if ($im) {
        if ($admin || (int) $im['user_id'] === (int) $u['id']) $ok = true;
        elseif ($im['ref_id'] !== null && $im['scope'] === 'chat') {
            $m = $pdo->prepare('SELECT user_id FROM messages WHERE id=?'); $m->execute([$im['ref_id']]); $ok = (int) $m->fetchColumn() === (int) $u['id'];
        } elseif ($im['ref_id'] !== null && $im['scope'] === 'team') {
            $ok = $alpha; // Team-Chat: nur Admin und Alphatester
        } elseif ($im['ref_id'] !== null && $im['scope'] === 'feedback') {
            // Anhänge sehen Admin/Alpha immer, alle anderen nur bei freigegebenen Beiträgen und freigegebenen Anhängen
            $ok = $alpha;
            if (!$ok && $im['status'] === 'approved') { try { $f = $pdo->prepare('SELECT released_at FROM feedback WHERE id=?'); $f->execute([$im['ref_id']]); $ok = (bool) $f->fetchColumn(); } catch (Throwable $e) { $ok = false; } }
        }
    }
    if (!$ok) { http_response_code(404); exit; }
    if (!empty($im['removed_at'])) { http_response_code(410); exit; }
    $isText = ($im['kind'] ?? 'image') === 'text';
    header('Content-Type: ' . ($isText ? 'text/plain; charset=utf-8' : 'image/jpeg')); header('X-Content-Type-Options: nosniff');
    if ($isText) header('Content-Disposition: inline; filename="anhang.txt"');
    header("Content-Security-Policy: default-src 'none'; sandbox"); header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . strlen($im['data'])); echo $im['data']; exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);

if ($a === 'upload') {
    $scope = ['feedback' => 'feedback', 'team' => 'team'][$_GET['scope'] ?? ''] ?? 'chat';
    if ($scope === 'team' && !$alpha) json_out(['error' => 'Nicht erlaubt.'], 403);
    $c = $pdo->prepare('SELECT COUNT(*) FROM images WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
    if ((int) $c->fetchColumn() >= 15) json_out(['error' => 'Bitte etwas später weitere Bilder hochladen.'], 429);
    if (($_GET['kind'] ?? '') === 'text') { // Textdatei als Anhang (nur bei Ideen und Bugs)
        if ($scope === 'chat') json_out(['error' => 'Textdateien sind nur bei Ideen, Bugs und im Team-Chat möglich.'], 422);
        $raw = file_get_contents('php://input', false, null, 0, 200 * 1024 + 1);
        if ($raw === '' || strlen($raw) > 200 * 1024) json_out(['error' => 'Die Textdatei ist leer oder zu groß (höchstens 200 KB).'], 422);
        if (strpos($raw, "\0") !== false || !mb_check_encoding($raw, 'UTF-8')) json_out(['error' => 'Das ist keine Textdatei (UTF-8).'], 422);
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw);
        $nm = preg_replace('/[^\p{L}\p{N}._ -]/u', '', basename((string) ($_GET['name'] ?? 'anhang.txt'))); $nm = mb_substr($nm !== '' ? $nm : 'anhang.txt', 0, 100);
        try { $pdo->prepare("INSERT INTO images (user_id,scope,data,kind,name) VALUES (?,?,?,'text',?)")->execute([$u['id'], $scope, $raw, $nm]); }
        catch (Throwable $e) { json_out(['error' => 'Textdateien sind noch nicht freigeschaltet (migrate.php einspielen).'], 500); }
        json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId(), 'name' => $nm]);
    }
    $raw = file_get_contents('php://input', false, null, 0, IMG_MAX_BYTES + 1);
    try { $jpg = image_clean((string) $raw); } catch (RuntimeException $e) { json_out(['error' => $e->getMessage()], 422); }
    $pdo->prepare('INSERT INTO images (user_id,scope,data) VALUES (?,?,?)')->execute([$u['id'], $scope, $jpg]);
    json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
}
$b = body(); $id = (int) ($b['id'] ?? 0);
$s = $pdo->prepare('SELECT * FROM images WHERE id=?'); $s->execute([$id]); $im = $s->fetch();
if (!$im) json_out(['error' => 'Bild nicht gefunden.'], 404);

if ($a === 'approve' || $a === 'reject') {
    if (!$alpha || $im['scope'] !== 'feedback') json_out(['error' => 'Nicht erlaubt.'], 403);
    if ($a === 'approve') $pdo->prepare("UPDATE images SET status='approved' WHERE id=?")->execute([$id]);
    else $pdo->prepare('DELETE FROM images WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
if ($a === 'strike') { // Admin/Alpha entfernt ein Medium aus einem Beitrag; der Hinweis „entfernt von …“ bleibt stehen
    if (!$alpha || $im['scope'] !== 'feedback' || $im['ref_id'] === null) json_out(['error' => 'Nicht erlaubt.'], 403);
    $who = $admin ? 'Admin' : (!empty($u['username']) ? (string) $u['username'] : 'Mitglied Nr. ' . (int) $u['id']);
    try { $pdo->prepare("UPDATE images SET data='', name=NULL, removed_by=?, removed_at=NOW() WHERE id=?")->execute([mb_substr($who, 0, 60), $id]); }
    catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'remove') { // Admin immer; Absender nur, solange das Bild noch an keinem Beitrag hängt
    if (!$admin && !((int) $im['user_id'] === (int) $u['id'] && $im['ref_id'] === null)) json_out(['error' => 'Nicht erlaubt.'], 403);
    $pdo->prepare('DELETE FROM images WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
