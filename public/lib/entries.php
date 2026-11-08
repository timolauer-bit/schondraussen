<?php
// Liste der Einträge eines Nutzers (wird von der Seite und von der Admin-Prüfung genutzt, damit beide dasselbe sehen).
function list_entries(PDO $pdo, int $uid): array {
    $bellSql = 'e.bell';
    try { $pdo->query('SELECT bell FROM user_entries LIMIT 1'); } catch (Throwable $e) { $bellSql = '1'; }
    $s = $pdo->prepare("SELECT s.id,s.type,s.name,s.summary,s.genres,s.fetched_at,$bellSql AS bell,
        (SELECT COUNT(*) FROM works w WHERE w.subject_id=s.id) AS works,
        (SELECT MIN(r.release_date) FROM releases r WHERE r.subject_id=s.id AND r.release_date>=CURDATE()) AS next_date,
        (SELECT COUNT(*) FROM releases r WHERE r.subject_id=s.id) AS upcoming,
        s.total_works, (s.external_ids LIKE '%gbs%') AS is_series,
        (SELECT MAX(w.year) FROM works w WHERE w.subject_id=s.id) AS last_year,
        (SELECT SUM(CAST(SUBSTRING_INDEX(w.note,' ',1) AS UNSIGNED)) FROM works w WHERE w.subject_id=s.id AND w.kind='Staffel') AS episodes
        FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? ORDER BY s.name");
    $s->execute([$uid]);
    $rows = $s->fetchAll();
    foreach ($rows as &$r) { $r['genres'] = json_decode($r['genres'] ?: '[]', true) ?: []; }
    return $rows;
}
