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
        } elseif ($im['ref_id'] !== null && $im['scope'] === 'feedback') {
            $f = $pdo->prepare('SELECT kind FROM feedback WHERE id=?'); $f->execute([$im['ref_id']]); $kind = $f->fetchColumn();
            if ($kind === 'bug') $ok = $alpha;                       // Bugs sieht nur Admin/Alpha (Admin oben schon erledigt)
            elseif ($kind === 'feature') $ok = $alpha; // Ideen sind noch Alpha
        }
    }
    if (!$ok) { http_response_code(404); exit; }
    header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox"); header('Cache-Control: private, max-age=3600');
    header('Content-Length: ' . strlen($im['data'])); echo $im['data']; exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);

if ($a === 'upload') {
    $scope = ($_GET['scope'] ?? '') === 'feedback' ? 'feedback' : 'chat';
    $c = $pdo->prepare('SELECT COUNT(*) FROM images WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
    if ((int) $c->fetchColumn() >= 15) json_out(['error' => 'Bitte etwas später weitere Bilder hochladen.'], 429);
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
if ($a === 'remove') { // Admin immer; Absender nur, solange das Bild noch an keinem Beitrag hängt
    if (!$admin && !((int) $im['user_id'] === (int) $u['id'] && $im['ref_id'] === null)) json_out(['error' => 'Nicht erlaubt.'], 403);
    $pdo->prepare('DELETE FROM images WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
