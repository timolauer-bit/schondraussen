<?php
// Datenpflege: erkennt verdächtige Einträge (Import-Reste, Müll-Namen, dauerhaft fehlschlagende Aktualisierung). Es wird nichts automatisch gelöscht.
function junk_name(string $t): bool { // Zeilen aus CSV-Exporten (z. B. IMDb: „1,tt0110912,2013-08-29,…“), Links und reine Nummern sind keine Namen
    return (bool) (preg_match('~https?://|www\.|imdb\.com|\btt\d{5,}\b~i', $t) || preg_match('/^\d+\s*,/', $t) || substr_count($t, ',') >= 4 || preg_match('/^[\d\s\p{P}]+$/u', $t) && !preg_match('/^\d{1,4}$/', $t));
}

function suspicious_subjects(PDO $pdo, int $limit = 300): array {
    $rows = $pdo->query("SELECT s.id, s.name, s.type, s.external_ids, s.fetched_at,
        (SELECT COUNT(*) FROM user_entries e WHERE e.subject_id=s.id) AS lists,
        (SELECT COUNT(*) FROM works w WHERE w.subject_id=s.id) AS works,
        (SELECT COUNT(*) FROM releases r WHERE r.subject_id=s.id) AS rels
        FROM subjects s ORDER BY s.id")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $why = [];
        if (junk_name((string) $r['name'])) $why[] = 'Name sieht nicht wie ein Name aus';
        if ((int) $r['lists'] === 0) $why[] = 'steht auf keiner Liste';
        $x = json_decode((string) $r['external_ids'], true);
        if (is_array($x) && !empty($x['err']) && (int) $r['works'] === 0 && (int) $r['rels'] === 0 && $r['fetched_at']) $why[] = 'Aktualisierung schlägt fehl: ' . mb_substr((string) $x['err'], 0, 120);
        if (!$why) continue;
        $out[] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'type' => (string) $r['type'], 'lists' => (int) $r['lists'], 'why' => $why];
        if (count($out) >= $limit) break;
    }
    return $out;
}
function delete_subjects(PDO $pdo, array $ids): int {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return 0;
    $in = implode(',', array_fill(0, count($ids), '?'));
    foreach (['episodes', 'work_seen'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE subject_id IN ($in)")->execute($ids); } catch (Throwable $e) {} }
    $d = $pdo->prepare("DELETE FROM subjects WHERE id IN ($in)"); $d->execute($ids); // Werke, Termine, Listeneinträge und Markierungen hängen per Fremdschlüssel daran
    return $d->rowCount();
}
