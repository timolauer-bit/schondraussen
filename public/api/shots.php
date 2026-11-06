<?php
// Admin: Bilder der Startseite verwalten (Liste, Hochladen, Beschreibung, Reihenfolge, Löschen)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/images.php';
$u = current_user();
if (!$u || $u['role'] !== 'admin') json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? 'list';
try {
    if ($a === 'list') json_out(['items' => $pdo->query('SELECT id,title,position,UNIX_TIMESTAMP(updated_at) v FROM site_images ORDER BY position,id')->fetchAll()]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
    if ($a === 'upload') {
        $n = (int) $pdo->query('SELECT COUNT(*) FROM site_images')->fetchColumn();
        if ($n >= 12) json_out(['error' => 'Es sind höchstens 12 Bilder möglich.'], 422);
        $raw = file_get_contents('php://input', false, null, 0, IMG_MAX_BYTES + 1);
        try { $jpg = image_clean((string) $raw, 2000, 88); } catch (RuntimeException $e) { json_out(['error' => $e->getMessage()], 422); }
        $title = mb_substr(trim((string) rawurldecode($_GET['title'] ?? '')), 0, 120);
        $pos = (int) $pdo->query('SELECT COALESCE(MAX(position),0)+1 FROM site_images')->fetchColumn();
        $pdo->prepare('INSERT INTO site_images (title,position,mime,data) VALUES (?,?,?,?)')->execute([$title, $pos, 'image/jpeg', $jpg]);
        json_out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
    }
    $b = body();
    if ($a === 'title') { $pdo->prepare('UPDATE site_images SET title=? WHERE id=?')->execute([mb_substr(trim((string) ($b['title'] ?? '')), 0, 120), (int) ($b['id'] ?? 0)]); json_out(['ok' => true]); }
    if ($a === 'order') {
        $ids = array_values(array_map('intval', is_array($b['ids'] ?? null) ? $b['ids'] : []));
        foreach ($ids as $i => $id) $pdo->prepare('UPDATE site_images SET position=? WHERE id=?')->execute([$i + 1, $id]);
        json_out(['ok' => true]);
    }
    if ($a === 'delete') { $pdo->prepare('DELETE FROM site_images WHERE id=?')->execute([(int) ($b['id'] ?? 0)]); json_out(['ok' => true]); }
} catch (PDOException $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
json_out(['error' => 'Unbekannte Aktion'], 400);
