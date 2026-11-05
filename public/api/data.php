<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
$u = current_user();
if (!$u) json_out(['error' => 'Bitte anmelden.'], 401);
if (empty($u['approved'])) json_out(['error' => 'Dein Konto wartet auf Freischaltung.', 'pending' => true], 403);
$pdo = db(); $a = $_GET['a'] ?? '';

if ($a === 'list') {
    $s = $pdo->prepare("SELECT s.id,s.type,s.name,s.summary,s.fetched_at,
        (SELECT COUNT(*) FROM works w WHERE w.subject_id=s.id) AS works,
        (SELECT MIN(r.release_date) FROM releases r WHERE r.subject_id=s.id AND r.release_date>=CURDATE()) AS next_date,
        (SELECT COUNT(*) FROM releases r WHERE r.subject_id=s.id) AS upcoming
        FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? ORDER BY s.name");
    $s->execute([$u['id']]);
    json_out(['entries' => $s->fetchAll()]);
}
if ($a === 'calendar') {
    $s = $pdo->prepare("SELECT r.id,r.title,r.release_date,r.kind,s.id AS subject_id,s.name,s.type
        FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN releases r ON r.subject_id=s.id
        WHERE e.user_id=? ORDER BY (r.release_date IS NULL OR r.release_date=''), r.release_date, s.name LIMIT 500");
    $s->execute([$u['id']]);
    json_out(['releases' => $s->fetchAll()]);
}
if ($a === 'skin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sk = body()['skin'] ?? '';
    $sk = in_array($sk, ['light', 'dark', 'paper', 'cinema', 'mint', 'eighties'], true) ? $sk : null;
    $pdo->prepare('UPDATE users SET skin=? WHERE id=?')->execute([$sk, $u['id']]);
    json_out(['ok' => true]);
}
$TYPES = ['author', 'artist', 'film', 'series'];
if ($a === 'search') {
    $type = $_GET['type'] ?? ''; $q = trim((string) ($_GET['q'] ?? ''));
    if (!in_array($type, $TYPES, true) || mb_strlen($q) < 2) json_out(['results' => []]);
    $out = [];
    if ($type === 'series') {
        $r = http_json('https://api.tvmaze.com/search/shows?q=' . rawurlencode($q)) ?? [];
        foreach (array_slice($r, 0, 8) as $x) { $s = $x['show'] ?? []; if (empty($s['name'])) continue;
            $out[] = ['name' => $s['name'], 'hint' => trim(substr((string) ($s['premiered'] ?? ''), 0, 4) . ' ' . implode(', ', array_slice($s['genres'] ?? [], 0, 2))), 'ext' => ['tvmaze' => (int) $s['id']]]; }
    } elseif ($type === 'artist') {
        $r = http_json('https://musicbrainz.org/ws/2/artist/?fmt=json&limit=8&query=' . rawurlencode($q)) ?? [];
        foreach (($r['artists'] ?? []) as $x) {
            $h = array_filter([$x['disambiguation'] ?? '', $x['area']['name'] ?? '', !empty($x['life-span']['begin']) ? 'seit ' . substr($x['life-span']['begin'], 0, 4) : '']);
            $out[] = ['name' => $x['name'], 'hint' => implode(' · ', $h), 'ext' => ['mbid' => $x['id']]]; }
    }
    elseif ($type === 'author') {
        $r = http_json('https://openlibrary.org/search/authors.json?limit=8&q=' . rawurlencode($q)) ?? [];
        foreach (($r['docs'] ?? []) as $x) {
            $h = array_filter([!empty($x['top_work']) ? '„' . $x['top_work'] . '“' : '', !empty($x['work_count']) ? $x['work_count'] . ' Werke' : '', $x['birth_date'] ?? '']);
            $out[] = ['name' => $x['name'], 'hint' => implode(' · ', $h), 'ext' => ['olid' => $x['key']]]; }
    }
    json_out(['results' => $out]);
}
if ($a === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body(); $type = $b['type'] ?? ''; $name = trim(preg_replace('/\s+/', ' ', (string) ($b['name'] ?? '')));
    if (!in_array($type, $TYPES, true) || $name === '' || mb_strlen($name) > 190) json_out(['error' => 'Bitte einen Namen eingeben.'], 422);
    $n = $pdo->prepare('SELECT COUNT(*) FROM user_entries WHERE user_id=?'); $n->execute([$u['id']]);
    if ((int) $n->fetchColumn() >= 500) json_out(['error' => 'Die Liste ist voll (500 Einträge).'], 422);
    $ext = [];
    foreach (['tvmaze' => 'int', 'mbid' => 'str', 'olid' => 'ol'] as $k => $t) if (!empty($b['ext'][$k])) $ext[$k] = $t === 'int' ? (int) $b['ext'][$k] : ($t === 'ol' ? preg_replace('/[^A-Za-z0-9]/', '', (string) $b['ext'][$k]) : preg_replace('/[^0-9a-f-]/', '', (string) $b['ext'][$k]));
    $pdo->prepare('INSERT INTO subjects (type,name,external_ids) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$type, $name, $ext ? json_encode($ext) : null]);
    $sid = (int) $pdo->lastInsertId();
    if ($ext) { // Kennung ergänzen, falls der Titel bisher keine hat
        $c = $pdo->prepare('SELECT external_ids FROM subjects WHERE id=?'); $c->execute([$sid]); $old = json_decode((string) $c->fetchColumn(), true) ?: [];
        if (!$old) $pdo->prepare('UPDATE subjects SET external_ids=? WHERE id=?')->execute([json_encode($ext), $sid]);
    }
    $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)')->execute([$u['id'], $sid]);
    json_out(['ok' => true, 'id' => $sid, 'type' => $type]);
}
// Ab hier: ein Titel, nur wenn er in der eigenen Liste steht
$id = (int) ($_GET['id'] ?? (body()['id'] ?? 0));
$own = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $own->execute([$u['id'], $id]);
if (!$own->fetch()) json_out(['error' => 'Eintrag nicht gefunden.'], 404);

if ($a === 'refresh' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $pdo->prepare('SELECT fetched_at FROM subjects WHERE id=?'); $f->execute([$id]);
    $t = $f->fetchColumn();
    if ($t && time() - strtotime($t) < 600) json_out(['ok' => true, 'msg' => 'Wurde gerade erst aktualisiert.']);
    json_out(refresh_subject($pdo, $id));
}
if ($a === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('DELETE FROM user_entries WHERE user_id=? AND subject_id=?')->execute([$u['id'], $id]);
    json_out(['ok' => true]);
}
if ($a === 'entry') {
    $s = $pdo->prepare('SELECT id,type,name,summary,genres,total_works,fetched_at FROM subjects WHERE id=?'); $s->execute([$id]);
    $e = $s->fetch(); $e['genres'] = json_decode($e['genres'] ?: '[]', true);
    $w = $pdo->prepare('SELECT title,year,kind,note FROM works WHERE subject_id=? ORDER BY year DESC, id DESC LIMIT 300'); $w->execute([$id]);
    $r = $pdo->prepare('SELECT title,release_date,kind,note FROM releases WHERE subject_id=? ORDER BY release_date LIMIT 100'); $r->execute([$id]);
    json_out(['entry' => $e, 'works' => $w->fetchAll(), 'releases' => $r->fetchAll()]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
