<?php
// Vorschläge: ähnliche Einträge und beliebte Einträge je Genre (freie Quellen, zwischengespeichert)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/suggest.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? '';
register_shutdown_function(function () { // Fataler Fehler oder Zeitüberschreitung: trotzdem eine lesbare Antwort liefern
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
        http_response_code(200); header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['items' => [], 'msg' => 'Die Vorschläge konnten gerade nicht geladen werden. Versuche es in ein paar Minuten noch einmal.']);
    }
});
try { $pdo->query('SELECT 1 FROM suggest_cache LIMIT 1'); } // Tabelle vorhanden?
 catch (Throwable $e) { json_out(['items' => [], 'msg' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }

function have_names(PDO $pdo, int $uid, string $type): array {
    $q = $pdo->prepare('SELECT LOWER(s.name) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $q->execute([$uid, $type]);
    return array_flip($q->fetchAll(PDO::FETCH_COLUMN));
}
function finish(array $r, array $have): void {
    $items = [];
    foreach ($r['items'] as $it) { $it['have'] = isset($have[mb_strtolower($it['name'])]); $items[] = $it; }
    json_out(['items' => $items, 'msg' => $r['msg'] ?? '']);
}

if ($a === 'similar') {
    $id = (int) ($_GET['id'] ?? 0);
    $q = $pdo->prepare('SELECT s.id,s.type,s.name,s.genres,s.external_ids FROM subjects s JOIN user_entries e ON e.subject_id=s.id WHERE s.id=? AND e.user_id=?'); $q->execute([$id, $u['id']]); $sub = $q->fetch();
    if (!$sub) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
    $key = 'similar:' . $id; $r = sg_cache_get($pdo, $key, 24 * 14);
    if ($r === null || !empty($_GET['fresh'])) { $r = sg_similar($pdo, $sub); if ($r['items']) sg_cache_put($pdo, $key, $r); }
    finish($r, have_names($pdo, (int) $u['id'], $sub['type']));
}
if ($a === 'popular') {
    $type = (string) ($_GET['type'] ?? ''); $genre = trim((string) ($_GET['genre'] ?? ''));
    if (!in_array($type, ['author', 'artist', 'series', 'film'], true) || $genre === '' || mb_strlen($genre) > 60) json_out(['error' => 'Ungültige Auswahl.'], 422);
    $key = 'popular:' . $type . ':' . mb_strtolower($genre); $r = sg_cache_get($pdo, $key, 24 * 7);
    if ($r === null) { $r = sg_popular($pdo, $type, $genre); if ($r['items']) sg_cache_put($pdo, $key, $r); }
    finish($r, have_names($pdo, (int) $u['id'], $type));
}
json_out(['error' => 'Unbekannte Aktion'], 400);
