<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
$u = current_user();
if (!$u) json_out(['error' => 'Bitte anmelden.'], 401);
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
if ($a === 'entry') {
    $s = $pdo->prepare('SELECT id,type,name,summary,genres,total_works,fetched_at FROM subjects WHERE id=?'); $s->execute([$id]);
    $e = $s->fetch(); $e['genres'] = json_decode($e['genres'] ?: '[]', true);
    $w = $pdo->prepare('SELECT title,year,kind,note FROM works WHERE subject_id=? ORDER BY year DESC, id DESC LIMIT 300'); $w->execute([$id]);
    $r = $pdo->prepare('SELECT title,release_date,kind,note FROM releases WHERE subject_id=? ORDER BY release_date LIMIT 100'); $r->execute([$id]);
    json_out(['entry' => $e, 'works' => $w->fetchAll(), 'releases' => $r->fetchAll()]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
