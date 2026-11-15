<?php
// FAQ: Themenbereiche mit Fragen und Antworten. Lesen: alle angemeldeten Nutzer; Bearbeiten: Recht „adm_faq“ (Admin, Moderatoren nach Rechtetabelle).
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$admin = perm($u, 'adm_faq'); $a = $_GET['a'] ?? 'list'; $pdo = db();
function faq_clean($t, int $max): string { $t = str_replace(["\r\n", "\r"], "\n", (string) $t); $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t); return mb_substr(trim($t), 0, $max); }
function faq_renum(PDO $pdo, string $table, string $where, array $args): array { // Reihenfolge 1..n lückenlos setzen, liefert die Kennungen in Reihenfolge
    $s = $pdo->prepare("SELECT id FROM $table WHERE $where ORDER BY pos, id"); $s->execute($args); $ids = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));
    $up = $pdo->prepare("UPDATE $table SET pos=? WHERE id=?"); foreach ($ids as $i => $id) $up->execute([$i + 1, $id]);
    return $ids;
}
try {
    if ($a === 'list') {
        $T = $pdo->query('SELECT id,title FROM faq_topics ORDER BY pos,id')->fetchAll(PDO::FETCH_ASSOC);
        $I = $pdo->query('SELECT id,topic_id,question,answer FROM faq_items ORDER BY pos,id')->fetchAll(PDO::FETCH_ASSOC);
        $by = []; foreach ($I as $i) $by[(int) $i['topic_id']][] = ['id' => (int) $i['id'], 'question' => $i['question'], 'answer' => $i['answer']];
        $out = []; foreach ($T as $t) $out[] = ['id' => (int) $t['id'], 'title' => $t['title'], 'items' => $by[(int) $t['id']] ?? []];
        json_out(['topics' => $out, 'admin' => $admin]);
    }
    if (!$admin) json_out(['error' => 'Dafür fehlt dir das Recht.'], 403);
    $b = body();
    if ($a === 'topic_save') {
        $title = faq_clean($b['title'] ?? '', 120); if ($title === '') json_out(['error' => 'Bitte einen Namen für den Themenbereich eingeben.'], 400);
        $id = (int) ($b['id'] ?? 0);
        if ($id) $pdo->prepare('UPDATE faq_topics SET title=? WHERE id=?')->execute([$title, $id]);
        else { $pos = (int) $pdo->query('SELECT COALESCE(MAX(pos),0)+1 FROM faq_topics')->fetchColumn(); $pdo->prepare('INSERT INTO faq_topics (title,pos) VALUES (?,?)')->execute([$title, $pos]); $id = (int) $pdo->lastInsertId(); }
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($a === 'topic_delete') {
        $id = (int) ($b['id'] ?? 0);
        $pdo->prepare('DELETE FROM faq_items WHERE topic_id=?')->execute([$id]); $pdo->prepare('DELETE FROM faq_topics WHERE id=?')->execute([$id]);
        json_out(['ok' => true]);
    }
    if ($a === 'item_save') {
        $q = faq_clean($b['question'] ?? '', 240); $ans = faq_clean($b['answer'] ?? '', 8000); $tid = (int) ($b['topic_id'] ?? 0); $id = (int) ($b['id'] ?? 0);
        if ($q === '' || $ans === '') json_out(['error' => 'Bitte Frage und Antwort ausfüllen.'], 400);
        $c = $pdo->prepare('SELECT 1 FROM faq_topics WHERE id=?'); $c->execute([$tid]); if (!$c->fetchColumn()) json_out(['error' => 'Bitte einen Themenbereich wählen.'], 400);
        if ($id) {
            $o = $pdo->prepare('SELECT topic_id FROM faq_items WHERE id=?'); $o->execute([$id]); $old = $o->fetchColumn(); if ($old === false) json_out(['error' => 'Beitrag nicht gefunden.'], 404);
            if ((int) $old !== $tid) { $pos = (int) $pdo->query('SELECT COALESCE(MAX(pos),0)+1 FROM faq_items WHERE topic_id=' . $tid)->fetchColumn(); $pdo->prepare('UPDATE faq_items SET topic_id=?,pos=? WHERE id=?')->execute([$tid, $pos, $id]); }
            $pdo->prepare('UPDATE faq_items SET question=?,answer=? WHERE id=?')->execute([$q, $ans, $id]);
        } else {
            $pos = (int) $pdo->query('SELECT COALESCE(MAX(pos),0)+1 FROM faq_items WHERE topic_id=' . $tid)->fetchColumn();
            $pdo->prepare('INSERT INTO faq_items (topic_id,question,answer,pos) VALUES (?,?,?,?)')->execute([$tid, $q, $ans, $pos]); $id = (int) $pdo->lastInsertId();
        }
        json_out(['ok' => true, 'id' => $id]);
    }
    if ($a === 'item_delete') { $pdo->prepare('DELETE FROM faq_items WHERE id=?')->execute([(int) ($b['id'] ?? 0)]); json_out(['ok' => true]); }
    if ($a === 'move') { // Themenbereich oder Beitrag eine Stelle nach oben (-1) oder unten (1)
        $kind = (string) ($b['kind'] ?? ''); $id = (int) ($b['id'] ?? 0); $dir = ((int) ($b['dir'] ?? 0)) < 0 ? -1 : 1;
        if ($kind === 'topic') { $ids = faq_renum($pdo, 'faq_topics', '1=?', [1]); $tb = 'faq_topics'; }
        elseif ($kind === 'item') { $s = $pdo->prepare('SELECT topic_id FROM faq_items WHERE id=?'); $s->execute([$id]); $t = $s->fetchColumn(); if ($t === false) json_out(['error' => 'Beitrag nicht gefunden.'], 404); $ids = faq_renum($pdo, 'faq_items', 'topic_id=?', [(int) $t]); $tb = 'faq_items'; }
        else json_out(['error' => 'Unbekannte Aktion.'], 400);
        $i = array_search($id, $ids, true);
        if ($i !== false && isset($ids[$i + $dir])) { $j = $i + $dir; $pdo->prepare("UPDATE $tb SET pos=? WHERE id=?")->execute([$j + 1, $id]); $pdo->prepare("UPDATE $tb SET pos=? WHERE id=?")->execute([$i + 1, $ids[$j]]); }
        json_out(['ok' => true]);
    }
    json_out(['error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $e) { json_out(['error' => 'Die FAQ ist noch nicht eingerichtet (Migration 079).'], 500); }
