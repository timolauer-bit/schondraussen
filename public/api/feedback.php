<?php
// Ideen (für alle freigeschalteten Nutzer sichtbar, mit Stimmen) und Bugmeldungen (nur Admin und Alphatester)
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/images.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $admin = $u['role'] === 'admin'; $alpha = is_alpha($u);
// Ideen sind noch Alpha (nur Admin und Alphatester); auf false setzen, um sie für alle zu öffnen
const FEATURE_ALPHA = true;
$kinds = ['feature' => 'Idee', 'bug' => 'Bugmeldung'];
/** Kurze Anmerkung zu Zurückgestellt/Abgelehnt: eine Zeile, höchstens 300 Zeichen. */
function note_clean($t): string { $t = trim(preg_replace('/\s+/u', ' ', (string) $t)); return mb_strlen($t) > 300 ? mb_substr($t, 0, 299) . '…' : $t; }
function set_status_note(PDO $pdo, int $id, string $note, array $u, bool $admin): void {
    if ($note === '') { $pdo->prepare('UPDATE feedback SET status_note=NULL, status_note_by=NULL, status_note_tier=NULL, status_note_at=NULL WHERE id=?')->execute([$id]); return; }
    $by = trim((string) ($u['username'] ?? '')) ?: 'Mitglied Nr. ' . (int) $u['id'];
    $pdo->prepare('UPDATE feedback SET status_note=?, status_note_by=?, status_note_tier=?, status_note_at=NOW() WHERE id=?')->execute([$note, $by, $admin ? 'admin' : 'alpha', $id]);
}

if ($a === 'list') {
    $kind = (string) ($_GET['kind'] ?? 'feature');
    if (!isset($kinds[$kind])) json_out(['error' => 'Unbekannte Liste.'], 400);
    $doneSel = ''; $doneOrd = '';
    $relSel = ''; $relOrd = ''; $vis = ''; $hasRel = false;
    try { $pdo->query('SELECT released_at FROM feedback LIMIT 1'); $hasRel = true; $relSel = ',f.released_at'; $relOrd = 'f.released_at IS NULL, '; } catch (Throwable $e) {}
    // Ohne Freigabe-Spalte (Migration fehlt) bleibt alles wie früher nur für Admin und Alphatester sichtbar
    if (!$alpha) { if (!$hasRel) json_out(['items' => [], 'error' => 'Die Liste ist noch nicht freigeschaltet.']); $vis = ' AND (f.released_at IS NOT NULL OR f.user_id=' . (int) $u['id'] . ')'; }
    $noSel = ''; try { $pdo->query('SELECT bug_no FROM feedback LIMIT 1'); $noSel = ',f.bug_no'; } catch (Throwable $e) {}
    try { $pdo->query('SELECT author_gone FROM feedback LIMIT 1'); $noSel .= ',f.author_gone,uu.delete_requested_at AS author_del'; } catch (Throwable $e) {}
    try { $pdo->query('SELECT done_at FROM feedback LIMIT 1'); $doneSel = ',f.done_at'; $doneOrd = 'f.done_at IS NOT NULL, '; } catch (Throwable $e) {}
    try { $pdo->query('SELECT rejected FROM feedback LIMIT 1'); $noSel .= ',f.rejected'; } catch (Throwable $e) {}
    try { $pdo->query('SELECT phase FROM feedback LIMIT 1'); $noSel .= ',f.phase'; $doneOrd .= "f.phase='postponed', "; } catch (Throwable $e) {}
    try { $pdo->query('SELECT status_note FROM feedback LIMIT 1'); $noSel .= ',f.status_note,f.status_note_by,f.status_note_tier,f.status_note_at'; } catch (Throwable $e) {}
    try { $pdo->query('SELECT withdrawn_at FROM feedback LIMIT 1'); $noSel .= ',f.withdrawn_at,(f.withdrawn_at > NOW() - INTERVAL 24 HOUR) AS new_wd'; } catch (Throwable $e) {}
    try {
        $s = $pdo->prepare("SELECT f.id,f.title,f.body,f.priority,f.created_at,f.user_id,uu.email AS author_email,uu.username AS author_name," . (users_have_privacy($pdo) ? 'uu.hide_email AS author_hide,' : '0 AS author_hide,') . "uu.role AS author_role,uu.alpha AS author_alpha$doneSel$noSel$relSel,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id) AS votes,
            (SELECT COUNT(*) FROM feedback_votes v WHERE v.feedback_id=f.id AND v.user_id=?) AS mine_vote
            FROM feedback f LEFT JOIN users uu ON uu.id=f.user_id WHERE f.kind=?$vis " . ($kind === 'bug'
                ? "ORDER BY {$relOrd}{$doneOrd}FIELD(f.priority,'urgent','normal','low'), f.created_at DESC"
                : "ORDER BY {$relOrd}{$doneOrd}votes DESC, f.created_at DESC") . " LIMIT 300");
        $s->execute([$u['id'], $kind]); $rows = $s->fetchAll();
    } catch (Throwable $e) { json_out(['items' => [], 'error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    foreach ($rows as &$r) {
        $r['mine'] = (int) $r['user_id'] === (int) $u['id'];
        if ($r['user_id'] === null) { $r['gone'] = 1; $r['author'] = ($r['author_gone'] ?? '') ?: 'gelöschtes Konto'; unset($r['author_gone']); continue; }
        if ($kind === 'feature') unset($r['priority']);
        // Absender: Admin sieht Nummer und E-Mail, Alphatester die Mitgliedsnummer
        if ($alpha) {
            $nm = $r['author_name'] ?: 'Mitglied Nr. ' . (int) $r['user_id'];
            $r['author'] = $nm . ($admin ? ($r['author_name'] ? ' (Nr. ' . (int) $r['user_id'] . ')' : '') : '');
        }
        if (!empty($r['author_del'])) $r['gone'] = 1;
        $r['author_tier'] = $r['author_role'] === 'admin' ? 'admin' : (!empty($r['author_alpha']) ? 'alpha' : '');
        unset($r['author_del'], $r['user_id'], $r['author_hide'], $r['author_email'], $r['author_name'], $r['author_role'], $r['author_alpha']);
    }
    if ($rows) { // Bilder: Freigegebene für alle, Wartende für Absender und Admin/Alpha (dort mit status)
        try {
            $ids = implode(',', array_map('intval', array_column($rows, 'id'))); $by = []; $pend = [];
            $kc = ''; try { $pdo->query('SELECT kind FROM images LIMIT 1'); $kc = ',kind,name'; } catch (Throwable $e) {}
            try { $pdo->query('SELECT removed_by FROM images LIMIT 1'); $kc .= ',removed_by'; } catch (Throwable $e) {}
            foreach ($pdo->query("SELECT id,ref_id,status,user_id$kc FROM images WHERE scope='feedback' AND ref_id IN ($ids) ORDER BY id")->fetchAll() as $im) {
                if ($im['status'] === 'approved' || $alpha || (int) $im['user_id'] === (int) $u['id']) $by[$im['ref_id']][] = ['id' => (int) $im['id'], 'status' => $im['status'], 'kind' => $im['kind'] ?? 'image', 'name' => $im['name'] ?? null, 'removed_by' => $im['removed_by'] ?? null];
                else $pend[$im['ref_id']] = ($pend[$im['ref_id']] ?? 0) + 1;
            }
            foreach ($rows as &$r) { $r['images'] = $by[$r['id']] ?? []; $r['pending_hidden'] = $pend[$r['id']] ?? 0; }
        } catch (Throwable $e) {}
    }
    if ($rows) { // Bearbeitungsverlauf (bis zu 3 frühere Fassungen je Feld)
        try {
            $ids2 = implode(',', array_map('intval', array_column($rows, 'id'))); $ed = [];
            foreach ($pdo->query("SELECT feedback_id,field,old_text,edited_at FROM feedback_edits WHERE feedback_id IN ($ids2) ORDER BY id DESC")->fetchAll() as $e) {
                $n = 0; foreach ($ed[$e['feedback_id']] ?? [] as $q) if ($q['field'] === $e['field']) $n++;
                if ($n < 3) $ed[$e['feedback_id']][] = ['field' => $e['field'], 'old' => $e['old_text'], 'at' => $e['edited_at']];
            }
            foreach ($rows as &$r) $r['edits'] = $ed[$r['id']] ?? [];
            unset($r);
        } catch (Throwable $e) {}
    }
    $cview = $alpha && perm($u, 'fb_comment_view'); $cwrite = $alpha && perm($u, 'fb_comment_write');
    if ($rows && $cview) { // interne Kommentare (nur Admin und Alphatester)
        try {
            $ids3 = implode(',', array_map('intval', array_column($rows, 'id'))); $cm = [];
            foreach ($pdo->query("SELECT c.feedback_id, c.id, c.user_id, c.body, c.created_at, u.username FROM feedback_comments c LEFT JOIN users u ON u.id=c.user_id WHERE c.feedback_id IN ($ids3) ORDER BY c.id")->fetchAll() as $c) $cm[$c['feedback_id']][] = ['id' => (int) $c['id'], 'name' => trim((string) $c['username']) ?: ($c['user_id'] ? 'Mitglied Nr. ' . (int) $c['user_id'] : 'gelöschtes Konto'), 'body' => $c['body'], 'at' => $c['created_at'], 'mine' => (int) $c['user_id'] === (int) $u['id']];
            foreach ($rows as &$r) $r['comments'] = $cm[$r['id']] ?? [];
            unset($r);
        } catch (Throwable $e) { $cview = false; }
    }
    $out = ['items' => $rows, 'admin' => $admin, 'alpha' => $alpha, 'cview' => $cview, 'cwrite' => $cwrite && $cview];
    if ($alpha && ($_GET['open'] ?? '') === '1') { // Beim Öffnen der Seite: letzten Besuch zurückgeben (für „neu“-Markierungen) und neu vermerken
        $col = $kind === 'bug' ? 'fb_seen_bug' : 'fb_seen_feature';
        try { $sq = $pdo->prepare("SELECT $col FROM users WHERE id=?"); $sq->execute([$u['id']]); $out['seen'] = $sq->fetchColumn() ?: null; $pdo->prepare("UPDATE users SET $col=NOW() WHERE id=?")->execute([$u['id']]); } catch (Throwable $e) { $out['seen'] = null; }
    }
    json_out($out);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
$b = body();

if ($a === 'comment') { // interner Kommentar unter einer Idee oder einem Bug: nur Admin und Alphatester
    if (!$alpha || !perm($u, 'fb_comment_write')) json_out(['error' => 'Nicht erlaubt.'], 403);
    $id = (int) ($b['id'] ?? 0); $text = trim((string) ($b['text'] ?? ''));
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', str_replace("\r\n", "\n", $text));
    if ($text === '' || mb_strlen($text) > 1000) json_out(['error' => 'Bitte einen Kommentar bis 1000 Zeichen schreiben.'], 422);
    try {
        $e = $pdo->prepare('SELECT 1 FROM feedback WHERE id=?'); $e->execute([$id]); if (!$e->fetchColumn()) json_out(['error' => 'Beitrag nicht gefunden.'], 404);
        $c = $pdo->prepare('SELECT COUNT(*) FROM feedback_comments WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
        if ((int) $c->fetchColumn() >= 60) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
        $pdo->prepare('INSERT INTO feedback_comments (feedback_id, user_id, body) VALUES (?,?,?)')->execute([$id, $u['id'], $text]);
    } catch (PDOException $e) { json_out(['error' => 'Kommentare sind noch nicht eingerichtet (Migration 072 einspielen).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'add') {
    $kind = (string) ($b['kind'] ?? ''); $text = trim((string) ($b['text'] ?? '')); $given = trim((string) ($b['title'] ?? ''));
    if (!isset($kinds[$kind])) json_out(['error' => 'Bitte wähle Idee oder Bugmeldung.'], 422);
    if ($given !== '') { // Kurzbeschreibung und Details getrennt
        if (mb_strlen($given) < 3 || mb_strlen($given) > 120) json_out(['error' => 'Die Kurzbeschreibung braucht 3 bis 120 Zeichen.'], 422);
        if (mb_strlen($text) > 2000) json_out(['error' => 'Die Details dürfen höchstens 2000 Zeichen haben.'], 422);
    } elseif (mb_strlen($text) < 5 || mb_strlen($text) > 2000) json_out(['error' => 'Bitte beschreibe es in 5 bis 2000 Zeichen.'], 422);
    try {
        $c = $pdo->prepare('SELECT COUNT(*) FROM feedback WHERE user_id=? AND created_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$u['id']]);
        if ((int) $c->fetchColumn() >= 10) json_out(['error' => 'Bitte etwas später weiterschreiben.'], 429);
        if ($given !== '') $title = $given; else { $first = trim(strtok($text, "\n")); $title = mb_strlen($first) > 80 ? mb_substr($first, 0, 79) . '…' : $first; }
        $bn = null; if ($kind === 'bug') { try { $bn = 1 + (int) $pdo->query("SELECT COALESCE(MAX(bug_no),0) FROM feedback WHERE kind='bug'")->fetchColumn(); } catch (Throwable $e) { $bn = null; } }
        if ($bn !== null) $pdo->prepare('INSERT INTO feedback (user_id,kind,title,body,bug_no) VALUES (?,?,?,?,?)')->execute([$u['id'], $kind, $title, $text, $bn]);
        else $pdo->prepare('INSERT INTO feedback (user_id,kind,title,body) VALUES (?,?,?,?)')->execute([$u['id'], $kind, $title, $text]);
        $fid = (int) $pdo->lastInsertId();
        // Bilder von Admin/Alpha gelten sofort als freigegeben, alle anderen warten auf die Prüfung
        if (!empty($b['images']) && is_array($b['images'])) { try { images_attach($pdo, $b['images'], (int) $u['id'], 'feedback', $fid, $alpha ? 'approved' : 'pending'); } catch (Throwable $e) {} }
    } catch (PDOException $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    try {
        $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
        foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL AND id<>" . (int) $u['id'])->fetchAll(PDO::FETCH_COLUMN) as $adm)
            send_mail($adm, 'Neue ' . $kinds[$kind] . ' auf Schondraussen.de', "Von " . user_label($u) . ":\n\n" . ($given !== '' ? $given . "\n\n" : '') . $text . "\n\nZur Liste: $base/\n");
    } catch (Throwable $e) {}
    json_out(['ok' => true]);
}
$id = (int) ($b['id'] ?? 0);
$f = $pdo->prepare('SELECT kind FROM feedback WHERE id=?'); $f->execute([$id]); $kind = $f->fetchColumn();
if (!$kind) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
if ($a === 'edit') { // Die einreichende Person darf Kurzbeschreibung und Details nachträglich ändern; frühere Fassungen bleiben für alle sichtbar
    $q = $pdo->prepare('SELECT user_id,title,body,done_at,rejected FROM feedback WHERE id=?'); $q->execute([$id]); $cur = $q->fetch();
    if (!$cur || (int) $cur['user_id'] !== (int) $u['id']) json_out(['error' => 'Nur die einreichende Person kann den Beitrag ändern.'], 403);
    if (!empty($cur['done_at']) || !empty($cur['rejected'])) json_out(['error' => 'Erledigte oder abgelehnte Beiträge lassen sich nicht mehr ändern.'], 422);
    $nt = trim((string) ($b['title'] ?? '')); $nb = trim((string) ($b['text'] ?? ''));
    if (mb_strlen($nt) < 3 || mb_strlen($nt) > 120) json_out(['error' => 'Die Kurzbeschreibung braucht 3 bis 120 Zeichen.'], 422);
    if (mb_strlen($nb) > 2000) json_out(['error' => 'Die Details dürfen höchstens 2000 Zeichen haben.'], 422);
    $ch = [];
    if ($nt !== (string) $cur['title']) $ch['title'] = [$cur['title'], $nt];
    if ($nb !== (string) $cur['body']) $ch['body'] = [$cur['body'], $nb];
    if (!$ch) json_out(['ok' => true, 'unchanged' => true]);
    try {
        $c = $pdo->prepare('SELECT COUNT(*) FROM feedback_edits WHERE feedback_id=? AND edited_at > NOW() - INTERVAL 1 HOUR'); $c->execute([$id]);
        if ((int) $c->fetchColumn() >= 10) json_out(['error' => 'Bitte etwas später weiter bearbeiten.'], 429);
        foreach ($ch as $fld => $v) $pdo->prepare('INSERT INTO feedback_edits (feedback_id,field,old_text) VALUES (?,?,?)')->execute([$id, $fld, (string) $v[0]]);
        $pdo->prepare('UPDATE feedback SET title=?, body=?, edited_at=NOW() WHERE id=?')->execute([$nt, $nb, $id]);
    } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'newcount') { // rote Zahl an „Ideen“ und „Bugs“: Beiträge, die auf Freigabe warten (nur Admin und Alphatester, auch eigene)
    $o = ['feature' => 0, 'bug' => 0];
    if (!$alpha) json_out(['ok' => true, 'n' => $o]);
    try {
        foreach ($pdo->query('SELECT kind, COUNT(*) c FROM feedback WHERE released_at IS NULL GROUP BY kind')->fetchAll(PDO::FETCH_ASSOC) as $r) if (isset($o[$r['kind']])) $o[$r['kind']] = (int) $r['c'];
        // plus: seit dem letzten Besuch neu freigegeben („Aktuell“) oder erledigt („Erledigt“) – Summe aus allen drei Reiter-Zahlen
        $sr = $pdo->prepare('SELECT fb_seen_feature, fb_seen_bug FROM users WHERE id=?'); $sr->execute([$u['id']]); $sn = $sr->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach (['feature' => 'fb_seen_feature', 'bug' => 'fb_seen_bug'] as $k => $col) {
            if (empty($sn[$col])) continue;
            $q = $pdo->prepare('SELECT SUM(done_at IS NULL AND released_at > ?) + SUM(done_at IS NOT NULL AND done_at > ?) FROM feedback WHERE kind=? AND released_at IS NOT NULL');
            $q->execute([$sn[$col], $sn[$col], $k]); $o[$k] += (int) $q->fetchColumn();
        }
    } catch (Throwable $e) {}
    json_out(['ok' => true, 'n' => $o]);
}
if ($a === 'vote') { // Abstimmen dürfen alle, aber nur bei freigegebenen Ideen
    $rl = null; try { $q = $pdo->prepare('SELECT released_at FROM feedback WHERE id=?'); $q->execute([$id]); $rl = $q->fetchColumn(); } catch (Throwable $e) {}
    if (!$alpha && !$rl) json_out(['error' => 'Nicht erlaubt.'], 403);
} elseif ($a === 'withdraw') { // Zurückziehen darf die einreichende Person, auch ohne Alpha-Rechte (Prüfung im Block unten)
} else require_alpha($u);
if ($a === 'release') {
    $on = !empty($b['on']);
    $it = null;
    try {
        $q = $pdo->prepare('SELECT user_id,title,bug_no,released_at FROM feedback WHERE id=?'); $q->execute([$id]); $it = $q->fetch();
        $pdo->prepare('UPDATE feedback SET released_at=' . ($on ? 'COALESCE(released_at,NOW())' : 'NULL') . ' WHERE id=?')->execute([$id]);
        if ($on) $pdo->prepare("UPDATE images SET status='approved' WHERE scope='feedback' AND ref_id=?")->execute([$id]);
    } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    // Systemnachricht an die Person, die den Beitrag geschrieben hat: bei der Freigabe, nicht bei eigenen Beiträgen
    if ($on && $it && empty($it['released_at']) && $it['user_id'] && (int) $it['user_id'] !== (int) $u['id']) {
        $t = (string) $it['title']; $t = mb_strlen($t) > 70 ? mb_substr($t, 0, 69) . '…' : $t;
        $txt = $kind === 'bug'
            ? 'Systemnachricht: Deine Bugmeldung' . ($it['bug_no'] ? ' #' . (int) $it['bug_no'] : '') . ' „' . $t . '“ wurde freigegeben und ist jetzt für alle unter „Bugs“ sichtbar. Danke für den Hinweis!'
            : 'Systemnachricht: Deine Idee „' . $t . '“ wurde freigegeben und ist jetzt für alle unter „Ideen“ sichtbar. Andere können dafür abstimmen. Danke für deinen Vorschlag!';
        try { $pdo->prepare('INSERT INTO messages (user_id,from_admin,body) VALUES (?,1,?)')->execute([(int) $it['user_id'], $txt]); } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}

if ($a === 'vote') {
    if ($kind !== 'feature') json_out(['error' => 'Nur Ideen haben Stimmen.'], 422);
    if (!empty($b['on'])) $pdo->prepare('INSERT IGNORE INTO feedback_votes (feedback_id,user_id) VALUES (?,?)')->execute([$id, $u['id']]);
    else $pdo->prepare('DELETE FROM feedback_votes WHERE feedback_id=? AND user_id=?')->execute([$id, $u['id']]);
    $n = $pdo->prepare('SELECT COUNT(*) FROM feedback_votes WHERE feedback_id=?'); $n->execute([$id]);
    json_out(['ok' => true, 'votes' => (int) $n->fetchColumn()]);
}
function fb_unreleased(PDO $pdo, int $id): bool { // noch nicht für alle freigegeben? (ohne Spalte: nie gesperrt)
    try { $q = $pdo->prepare('SELECT released_at FROM feedback WHERE id=?'); $q->execute([$id]); $r = $q->fetch(); return $r && empty($r['released_at']); } catch (Throwable $e) { return false; }
}
if ($a === 'done') {
    if (!empty($b['on']) && fb_unreleased($pdo, $id)) json_out(['error' => 'Bitte gib den Beitrag zuerst frei.'], 422);
    $on = !empty($b['on']);
    try {
        $q = $pdo->prepare('SELECT user_id,title,bug_no,done_at FROM feedback WHERE id=?'); $q->execute([$id]); $it = $q->fetch();
        $pdo->prepare('UPDATE feedback SET done_at=' . ($on ? 'NOW()' : 'NULL') . ' WHERE id=?')->execute([$id]);
        try { $pdo->prepare('UPDATE feedback SET rejected=0 WHERE id=?')->execute([$id]); } catch (Throwable $e) {}
        if (!$on) { try { $pdo->prepare('UPDATE feedback SET withdrawn_at=NULL WHERE id=?')->execute([$id]); } catch (Throwable $e) {} }
    } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    // Systemnachricht an die Person, die den Eintrag geschrieben hat (nur wenn der Admin abhakt, nur beim ersten Abhaken, nicht bei eigenen Einträgen)
    if ($on && $admin && $it && empty($it['done_at']) && $it['user_id'] && (int) $it['user_id'] !== (int) $u['id']) {
        $t = (string) $it['title']; $t = mb_strlen($t) > 70 ? mb_substr($t, 0, 69) . '…' : $t;
        $txt = $kind === 'bug' ? 'Systemnachricht: Deine Bugmeldung' . ($it['bug_no'] ? ' #' . (int) $it['bug_no'] : '') . ' „' . $t . '“ ist als erledigt markiert. Danke für den Hinweis!'
            : 'Systemnachricht: Deine Idee „' . $t . '“ ist als erledigt markiert. Danke für deinen Vorschlag!';
        try { $pdo->prepare('INSERT INTO messages (user_id,from_admin,body) VALUES (?,1,?)')->execute([(int) $it['user_id'], $txt]); } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}
if ($a === 'reject') {
    if (!$admin) json_out(['error' => 'Nicht erlaubt.'], 403);
    if ($kind !== 'feature') json_out(['error' => 'Nur Ideen lassen sich ablehnen.'], 422);
    $on = !empty($b['on']);
    if ($on && fb_unreleased($pdo, $id)) json_out(['error' => 'Bitte gib die Idee zuerst frei.'], 422);
    try {
        $q = $pdo->prepare('SELECT user_id,title,done_at,rejected FROM feedback WHERE id=?'); $q->execute([$id]); $it = $q->fetch();
        $pdo->prepare('UPDATE feedback SET rejected=?, done_at=' . ($on ? 'COALESCE(done_at,NOW())' : 'NULL') . ' WHERE id=?')->execute([$on ? 1 : 0, $id]);
        $note = $on ? note_clean($b['note'] ?? '') : '';
        try { set_status_note($pdo, $id, $note, $u, $admin); } catch (Throwable $e) {}
    } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    if ($on && $it && empty($it['rejected']) && $it['user_id'] && (int) $it['user_id'] !== (int) $u['id']) {
        $t = (string) $it['title']; $t = mb_strlen($t) > 70 ? mb_substr($t, 0, 69) . '…' : $t;
        try { $pdo->prepare('INSERT INTO messages (user_id,from_admin,body) VALUES (?,1,?)')->execute([(int) $it['user_id'], 'Systemnachricht: Deine Idee „' . $t . '“ wird vorerst nicht umgesetzt.' . ($note !== '' ? ' Anmerkung: „' . $note . '“.' : '') . ' Danke trotzdem für deinen Vorschlag!']); } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}
if ($a === 'priority') {
    if ($kind !== 'bug') json_out(['error' => 'Nur Bugmeldungen haben eine Dringlichkeit.'], 422);
    $p = (string) ($b['priority'] ?? 'normal'); if (!in_array($p, ['urgent', 'normal', 'low'], true)) json_out(['error' => 'Ungültig.'], 422);
    $pdo->prepare('UPDATE feedback SET priority=? WHERE id=?')->execute([$p, $id]);
    json_out(['ok' => true]);
}
if ($a === 'withdraw') { // Die einreichende Person zieht ihren Beitrag zurück: gilt als erledigt, Admin und Alphatester sehen 24 Stunden lang eine Markierung
    $q = $pdo->prepare('SELECT user_id,done_at,rejected FROM feedback WHERE id=?'); $q->execute([$id]); $cur = $q->fetch();
    if (!$cur || (int) $cur['user_id'] !== (int) $u['id']) json_out(['error' => 'Nur die einreichende Person kann den Beitrag zurückziehen.'], 403);
    if (!empty($cur['done_at']) || !empty($cur['rejected'])) json_out(['error' => 'Der Beitrag ist schon erledigt oder abgelehnt.'], 422);
    try { $pdo->prepare('UPDATE feedback SET done_at=NOW(), withdrawn_at=NOW(), phase=NULL WHERE id=?')->execute([$id]); } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'phase') { // Status für alle sichtbar: im Brainstorming, in Bearbeitung (leer = offen)
    if (!$alpha) json_out(['error' => 'Nicht erlaubt.'], 403);
    $ph = (string) ($b['phase'] ?? ''); if ($ph !== '' && !in_array($ph, ['brainstorm', 'progress', 'postponed'], true)) json_out(['error' => 'Ungültig.'], 422);
    if ($ph !== '' && fb_unreleased($pdo, $id)) json_out(['error' => 'Bitte gib den Beitrag zuerst frei.'], 422);
    try {
        $q = $pdo->prepare('SELECT user_id,title,bug_no,phase FROM feedback WHERE id=?'); $q->execute([$id]); $it = $q->fetch();
        $pdo->prepare('UPDATE feedback SET phase=? WHERE id=?')->execute([$ph === '' ? null : $ph, $id]);
        $note = $ph === 'postponed' ? note_clean($b['note'] ?? '') : '';
        try { set_status_note($pdo, $id, $note, $u, $admin); } catch (Throwable $e) {}
    } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    // Systemnachricht an die Person, die den Beitrag geschrieben hat: bei jedem Wechsel auf einen neuen Status, nicht bei eigenen Beiträgen
    if ($ph !== '' && $it && ($it['phase'] ?? '') !== $ph && $it['user_id'] && (int) $it['user_id'] !== (int) $u['id']) {
        $t = (string) $it['title']; $t = mb_strlen($t) > 70 ? mb_substr($t, 0, 69) . '…' : $t;
        $what = $kind === 'bug' ? 'Deine Bugmeldung' . ($it['bug_no'] ? ' #' . (int) $it['bug_no'] : '') . ' „' . $t . '“' : 'Deine Idee „' . $t . '“';
        $thanks = ' Danke für ' . ($kind === 'bug' ? 'den Hinweis!' : 'deinen Vorschlag!');
        if ($ph === 'brainstorm') $txt = 'Systemnachricht: ' . $what . ' ist im Brainstorming. Es wird gerade überlegt, ' . ($kind === 'bug' ? 'woran es liegt und wie sich das lösen lässt.' : 'ob und wie sich das umsetzen lässt.') . $thanks;
        elseif ($ph === 'postponed') $txt = 'Systemnachricht: ' . $what . ' ist zurückgestellt. Sie wird eventuell später wieder aufgegriffen, aktuell aber nicht umgesetzt.' . ($note !== '' ? ' Anmerkung: „' . $note . '“.' : '') . $thanks;
        else $txt = 'Systemnachricht: ' . $what . ' ist in Bearbeitung. Sobald sie fertig ist, bekommst du Bescheid.' . $thanks;
        try { $pdo->prepare('INSERT INTO messages (user_id,from_admin,body) VALUES (?,1,?)')->execute([(int) $it['user_id'], $txt]); } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}
if ($a === 'delete') {
    if (!$admin) json_out(['error' => 'Nicht erlaubt.'], 403);
    try { $pdo->prepare("DELETE FROM images WHERE scope='feedback' AND ref_id=?")->execute([$id]); } catch (Throwable $e) {}
    $pdo->prepare('DELETE FROM feedback WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
