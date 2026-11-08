<?php
// Nutzerverwaltung (nur Admin)
require __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/mail.php';
require_once __DIR__ . '/../lib/invite.php';
if (!function_exists('valid_email')) { function valid_email(string $e): bool { return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL); } }
$u = current_user();
if (!$u || $u['role'] !== 'admin') json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $_GET['a'] ?? ''; $b = body();
if ($a === 'users') {
    $cols = 'u.id,u.email,u.username,u.name_change_required,u.role,u.created_at,u.last_seen_at,u.email_verified_at,u.approved_at,u.delete_requested_at';
    $sql = " FROM users u ORDER BY (u.approved_at IS NULL AND u.role<>'admin') DESC, u.created_at DESC";
    $ent = ',(SELECT COUNT(*) FROM user_entries e WHERE e.user_id=u.id) AS entries';
    try { $r = $pdo->query('SELECT ' . $cols . ',u.alpha,u.hide_email,u.hide_activity,u.admin_deleted' . $ent . $sql)->fetchAll(); }
    catch (Throwable $e) {
        try { $r = $pdo->query('SELECT ' . $cols . ',u.alpha' . $ent . $sql)->fetchAll(); }
        catch (Throwable $e2) { $r = $pdo->query('SELECT ' . str_replace('u.username,u.name_change_required,', '', $cols) . $ent . $sql)->fetchAll(); }
    }
    foreach ($r as &$x) {
        if ($x['role'] !== 'admin') { $x['email'] = null; $x['email_hidden'] = 1; } // Adressen sind für den Admin grundsätzlich ausgeblendet (Aufdecken nur einzeln und protokolliert)
        if (!empty($x['hide_activity']) && $x['role'] !== 'admin') { $x['seen_label'] = seen_bucket($x['last_seen_at']); $x['last_seen_at'] = null; }
    }
    unset($x);
    try { // letzte Namensänderungen (bis zu 3 je Nutzer)
        $hist = [];
        foreach ($pdo->query('SELECT user_id,old_name,new_name,changed_at FROM username_log ORDER BY id DESC LIMIT 2000')->fetchAll() as $h) { if (count($hist[$h['user_id']] ?? []) < 3) $hist[$h['user_id']][] = ['from' => $h['old_name'], 'to' => $h['new_name'], 'at' => $h['changed_at']]; }
        foreach ($r as &$x) $x['name_hist'] = $hist[$x['id']] ?? [];
        unset($x);
    } catch (Throwable $e) {}
    json_out(['users' => $r, 'me' => (int) $u['id']]);
}
if ($a === 'testmail' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Testmail im Format der echten Benachrichtigungen (für mail-tester.com u. ä.)
    require_once __DIR__ . '/../lib/notify.php';
    $to = trim((string) ($b['email'] ?? ''));
    if (!valid_email($to)) json_out(['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
    $unsub = base_url() . '/unsubscribe.php?t=' . bin2hex(random_bytes(20)); // Platzhalter, nicht mit einem Konto verbunden
    $text = "Hallo,\n\nbei deinen Einträgen gibt es Neues:\n\n- Beispiel-Künstler: neues Album „Beispieltitel“ erscheint am 20.11.2026\n- Beispiel-Autorin: neues Buch „Beispielroman“ erscheint am 03.12.2026\n\nAlle Termine siehst du im Kalender: " . base_url() . "/\n\nDas ist eine Testmail im Format der echten Benachrichtigungen.\n";
    $ok = send_mail($to, 'Neuerscheinungen bei deinen Einträgen (Testmail)', $text . mail_footer($unsub), ["List-Unsubscribe: <$unsub>", 'List-Unsubscribe-Post: List-Unsubscribe=One-Click']);
    json_out(['ok' => (bool) $ok, 'error' => $ok ? null : 'Die Mail konnte nicht gesendet werden.']);
}
if ($a === 'entry_check') { // Diagnose: Einträge zu einem Namen samt Besitzern (ohne E-Mail-Adressen)
    $q = trim((string) ($_GET['q'] ?? '')); if (mb_strlen($q) < 2) json_out(['error' => 'Bitte mindestens 2 Zeichen eingeben.'], 400);
    $s = $pdo->prepare('SELECT id,type,name,fetched_at,external_ids,CHAR_LENGTH(summary) AS sum_len FROM subjects WHERE name LIKE ? ORDER BY id LIMIT 20'); $s->execute(['%' . $q . '%']); $out = [];
    foreach ($s->fetchAll() as $r) {
        $o = $pdo->prepare('SELECT user_id,is_sample,bell,added_at FROM user_entries WHERE subject_id=? ORDER BY user_id LIMIT 50'); $o->execute([$r['id']]); $r['owners'] = $o->fetchAll();
        $r['list_check'] = []; // Wie sieht die Liste für jeden Besitzer aus? (gleiche Abfrage wie die Seite)
        foreach (array_slice($r['owners'], 0, 3) as $ow) {
            try {
                require_once __DIR__ . '/../lib/entries.php';
                $rows = list_entries($pdo, (int) $ow['user_id']); $has = false; $mine = null; foreach ($rows as $x) if ((int) $x['id'] === (int) $r['id']) { $has = $x['type']; $mine = $x; }
                $enc = json_encode($rows, JSON_UNESCAPED_UNICODE); $r['list_check'][] = 'Nr. ' . $ow['user_id'] . ': ' . count($rows) . ' Einträge (' . implode(', ', array_map(fn($x) => $x['type'] . ':' . $x['id'], $rows)) . '), dieser ' . ($has ? 'enthalten (Typ ' . $has . ')' : 'NICHT enthalten') . ', JSON ' . ($enc === false ? 'FEHLER: ' . json_last_error_msg() : 'ok') . ($mine ? ' | Zeile: ' . mb_substr(json_encode(array_diff_key($mine, ['summary' => 1]), JSON_UNESCAPED_UNICODE), 0, 500) : '');
            } catch (Throwable $e) { $r['list_check'][] = 'Nr. ' . $ow['user_id'] . ': Abfrage fehlgeschlagen: ' . $e->getMessage(); }
        }
        foreach (['releases' => 'releases', 'works' => 'works'] as $k => $t) { $c = $pdo->prepare("SELECT COUNT(*) FROM $t WHERE subject_id=?"); $c->execute([$r['id']]); $r[$k] = (int) $c->fetchColumn(); }
        $out[] = $r;
    }
    json_out(['subjects' => $out, 'hint' => 'user_id ist die Nutzernummer (Nr. in der Nutzerverwaltung).']);
}
if ($a === 'news_list') {
    try { $r = $pdo->query('SELECT n.id,n.title,n.body,n.created_at,(SELECT COUNT(*) FROM announcement_reads r WHERE r.ann_id=n.id) AS reads_n FROM announcements n ORDER BY n.id DESC LIMIT 20')->fetchAll(); }
    catch (Throwable $e) { json_out(['news' => [], 'needs_migration' => true]); }
    json_out(['news' => $r]);
}
if ($a === 'news_create' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Systemnachricht: erscheint allen Nutzern beim nächsten Besuch als Hinweisfenster
    $t = trim((string) ($b['title'] ?? '')); $x = trim((string) ($b['body'] ?? ''));
    if (mb_strlen($t) < 3 || mb_strlen($t) > 120) json_out(['error' => 'Die Überschrift braucht 3 bis 120 Zeichen.'], 400);
    if (mb_strlen($x) < 3 || mb_strlen($x) > 2000) json_out(['error' => 'Der Text braucht 3 bis 2000 Zeichen.'], 400);
    try { $pdo->prepare('INSERT INTO announcements (title,body) VALUES (?,?)')->execute([$t, $x]); }
    catch (Throwable $e) { json_out(['error' => 'Bitte zuerst Migration 040 einspielen.'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'news_delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $i = (int) ($b['id'] ?? 0);
    try { $pdo->prepare('DELETE FROM announcements WHERE id=?')->execute([$i]); $pdo->prepare('DELETE FROM announcement_reads WHERE ann_id=?')->execute([$i]); } catch (Throwable $e) {}
    json_out(['ok' => true]);
}
if ($a === 'invite_text_get') {
    $d = invite_text_default(); $t = invite_text($pdo);
    json_out(['text' => $t, 'is_default' => $t === $d, 'footer' => invite_text_footer('(dein Einladungslink)')]);
}
if ($a === 'invite_text_set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = trim(str_replace(["\r\n", "\r"], "\n", (string) ($b['text'] ?? '')));
    if (mb_strlen($t) > 4000) json_out(['ok' => false, 'error' => 'Der Text ist zu lang (höchstens 4000 Zeichen).']);
    try {
        if ($t === '' || $t === invite_text_default()) $pdo->prepare("DELETE FROM app_texts WHERE k='invite_text'")->execute();
        else $pdo->prepare("INSERT INTO app_texts (k,v) VALUES ('invite_text',?) ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([$t]);
    } catch (Throwable $e) { json_out(['ok' => false, 'error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    json_out(['ok' => true, 'text' => invite_text($pdo)]);
}
if ($a === 'invite_list') {
    try { $r = $pdo->query("SELECT id,email,created_at,expires_at FROM invites WHERE used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 50")->fetchAll(); }
    catch (Throwable $e) { json_out(['invites' => [], 'needs_migration' => true]); }
    json_out(['invites' => $r]);
}
if ($a === 'invite_revoke' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $pdo->prepare('DELETE FROM invites WHERE id=? AND used_at IS NULL')->execute([(int) ($b['id'] ?? 0)]); } catch (Throwable $e) {}
    json_out(['ok' => true]);
}
if ($a === 'invite_create' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Einladung: Einmal-Link, optional per Mail
    $email = strtolower(trim((string) ($b['email'] ?? '')));
    if ($email !== '' && !valid_email($email)) json_out(['ok' => false, 'error' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
    try {
        if ((int) $pdo->query('SELECT COUNT(*) FROM invites WHERE used_at IS NULL AND expires_at > NOW()')->fetchColumn() >= 30) json_out(['ok' => false, 'error' => 'Es sind schon 30 offene Einladungen vorhanden. Widerrufe zuerst einige.']);
        [$tk, $th] = token_new();
        $pdo->prepare('INSERT INTO invites (token_hash,email,created_by,expires_at) VALUES (?,?,?,NOW() + INTERVAL 14 DAY)')->execute([$th, $email !== '' ? $email : null, $u['id']]);
    } catch (Throwable $e) { json_out(['ok' => false, 'error' => 'Die Einladungen sind noch nicht eingerichtet (migrate.php ausführen).']); }
    $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/'); $link = $base . '/?invite=' . $tk; $sent = false;
    if ($email !== '') {
        try { $sent = (bool) send_mail($email, 'Einladung zu Schondraussen.de', str_replace(["\r\n", "\r"], "\n", trim(invite_text($pdo))) . invite_text_footer($link)); } catch (Throwable $e) {}
    }
    json_out(['ok' => true, 'link' => $link, 'sent' => $sent, 'email' => $email !== '']);
}
if ($a === 'reveal' && $_SERVER['REQUEST_METHOD'] === 'POST') { // E-Mail eines Nutzers aufdecken, wird protokolliert
    $id = (int) ($b['id'] ?? 0);
    $s = $pdo->prepare('SELECT email FROM users WHERE id=?'); $s->execute([$id]); $em = $s->fetchColumn();
    if (!$em) json_out(['error' => 'Nutzer nicht gefunden.'], 404);
    try { $pdo->prepare('INSERT INTO admin_log (admin_id,action,target_id) VALUES (?,?,?)')->execute([$u['id'], 'reveal_email', $id]); }
    catch (Throwable $e) { json_out(['error' => 'Protokoll nicht möglich (migrate.php einspielen).'], 500); }
    json_out(['ok' => true, 'email' => $em]);
}
if ($a === 'adminlog') {
    try {
        $r = $pdo->query("SELECT l.created_at,l.action,l.target_id,a.username AS admin_name,a.email AS admin_email,t.username AS target_name FROM admin_log l LEFT JOIN users a ON a.id=l.admin_id LEFT JOIN users t ON t.id=l.target_id ORDER BY l.id DESC LIMIT 100")->fetchAll();
    } catch (Throwable $e) { json_out(['error' => 'Protokoll nicht lesbar (migrate.php einspielen).'], 500); }
    json_out(['log' => $r]);
}
if ($a === 'cron') {
    $o = ['runs' => null, 'stats' => [], 'stale' => [], 'empty' => []];
    try { $o['runs'] = $pdo->query('SELECT started_at,duration_ms,ok_count,fail_count,left_count,stopped_early,details FROM cron_runs ORDER BY id DESC LIMIT 8')->fetchAll(); } catch (Throwable $e) { $o['needs_migration'] = true; }
    $o['stats'] = $pdo->query("SELECT type, COUNT(*) total, SUM(fetched_at IS NULL) never, SUM(fetched_at IS NOT NULL AND fetched_at < NOW() - INTERVAL 12 HOUR) old12, SUM(fetched_at IS NOT NULL AND (total_works IS NULL OR total_works=0)) empty, MIN(fetched_at) oldest, MAX(fetched_at) newest FROM subjects GROUP BY type")->fetchAll();
    $o['stale'] = $pdo->query("SELECT type,name,fetched_at FROM subjects WHERE fetched_at IS NULL OR fetched_at < NOW() - INTERVAL 12 HOUR ORDER BY fetched_at IS NOT NULL, fetched_at LIMIT 40")->fetchAll();
    $o['empty'] = $pdo->query("SELECT type,name,fetched_at FROM subjects WHERE fetched_at IS NOT NULL AND (total_works IS NULL OR total_works=0) ORDER BY fetched_at LIMIT 40")->fetchAll();
    $o['now'] = date('Y-m-d H:i:s');
    require_once __DIR__ . '/../lib/watchdog.php'; $o['ops'] = ops_health($pdo);
    try { $o['api_errors'] = $pdo->query("SELECT source,kind,http_code,message,cnt,first_at,last_at FROM api_errors WHERE last_at > NOW() - INTERVAL 7 DAY ORDER BY last_at DESC LIMIT 30")->fetchAll(); } catch (Throwable $e) { $o['api_errors'] = null; }
    json_out($o);
}
if ($a === 'apierrors_clear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $pdo->exec('DELETE FROM api_errors'); } catch (Throwable $e) {}
    json_out(['ok' => true]);
}
if ($a === 'site') {
    $o = ['tag' => '', 'domain' => 'amazon.de'];
    try { foreach ($pdo->query('SELECT k,v FROM site_settings')->fetchAll() as $r) $o[$r['k']] = $r['v']; } catch (Throwable $e) {}
    json_out($o);
}
if ($a === 'approval') {
    $pend = 0; try { $pend = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role<>'admin' AND approved_at IS NULL AND delete_requested_at IS NULL AND email_verified_at IS NOT NULL")->fetchColumn(); } catch (Throwable $e) {}
    json_out(['required' => approval_required(), 'pending' => $pend]);
}
if ($a === 'setapproval' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO site_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')->execute(['approval_required', !empty($b['required']) ? '1' : '0']);
    json_out(['ok' => true, 'required' => approval_required()]);
}
if ($a === 'savesite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tag = trim((string) ($b['tag'] ?? '')); $dom = (string) ($b['domain'] ?? 'amazon.de');
    if (!preg_match('/^[A-Za-z0-9._-]{0,64}$/', $tag)) json_out(['error' => 'Nur Buchstaben, Ziffern, Punkt, Bindestrich.'], 422);
    if (!in_array($dom, ['amazon.de', 'amazon.fr', 'amazon.it', 'amazon.es', 'amazon.co.uk', 'amazon.com'], true)) json_out(['error' => 'Unbekannter Shop.'], 422);
    $q = $pdo->prepare('INSERT INTO site_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
    $q->execute(['tag', $tag]); $q->execute(['domain', $dom]);
    json_out(['ok' => true]);
}
if ($a === 'blocked') {
    try {
        $rows = $pdo->query('SELECT email,created_at FROM blocked_emails ORDER BY created_at DESC')->fetchAll();
        foreach ($rows as &$r) { // nur maskiert anzeigen, gesperrt wird über einen Schlüssel aufgehoben
            [$lo, $dom] = array_pad(explode('@', (string) $r['email'], 2), 2, ''); $dp = explode('.', $dom); $tld = array_pop($dp);
            $r['masked'] = mb_substr($lo, 0, 2) . '***@' . mb_substr($dom, 0, 1) . '***.' . $tld; $r['key'] = sha1((string) $r['email']); unset($r['email']);
        }
        json_out(['blocked' => $rows]);
    }
    catch (Throwable $e) { json_out(['blocked' => [], 'error' => 'Datenbank noch nicht aktualisiert (migrate.php).']); }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST nötig'], 405);
if ($a === 'block' || $a === 'unblock') {
    $mail = strtolower(trim((string) ($b['email'] ?? '')));
    if ($a === 'unblock' && !empty($b['key'])) { try { $kq = $pdo->prepare('SELECT email FROM blocked_emails WHERE SHA1(email)=?'); $kq->execute([(string) $b['key']]); $mail = (string) $kq->fetchColumn(); } catch (Throwable $e) { $mail = ''; } }
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL) || strlen($mail) > 190) json_out(['error' => 'Bitte eine gültige E-Mail-Adresse eingeben.'], 422);
    try {
        if ($a === 'unblock') $pdo->prepare('DELETE FROM blocked_emails WHERE email=?')->execute([$mail]);
        else {
            if (strcasecmp($mail, $u['email']) === 0) json_out(['error' => 'Die eigene Adresse kann nicht gesperrt werden.'], 422);
            $pdo->prepare('INSERT IGNORE INTO blocked_emails (email) VALUES (?)')->execute([$mail]);
            $x = $pdo->prepare("SELECT id FROM users WHERE email=? AND role<>'admin'"); $x->execute([$mail]); foreach ($x->fetchAll(PDO::FETCH_COLUMN) as $xid) soft_delete_user($pdo, (int) $xid);
        }
    } catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
$id = (int) ($b['id'] ?? 0);
if ($id === (int) $u['id']) json_out(['error' => 'Das eigene Konto kann hier nicht geändert werden.'], 422);
if ($a === 'alpha') {
    try { $pdo->prepare("UPDATE users SET alpha=? WHERE id=? AND role<>'admin'")->execute([!empty($b['on']) ? 1 : 0, $id]); }
    catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'renamereq') {
    $on = !empty($b['on']);
    try { $pdo->prepare("UPDATE users SET name_change_required=? WHERE id=? AND role<>'admin'")->execute([$on ? 1 : 0, $id]); }
    catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); }
    if ($on) {
        try {
            require_once __DIR__ . '/../lib/mail.php';
            $s = $pdo->prepare('SELECT email FROM users WHERE id=?'); $s->execute([$id]); $em = $s->fetchColumn();
            if ($em) send_mail($em, 'Bitte wähle einen neuen Benutzernamen', "Dein Benutzername auf Schondraussen.de entspricht nicht unseren Regeln.\n\nBitte melde dich an und wähle einen neuen Namen. Bis dahin kannst du die Seite nicht nutzen. Erlaubt sind Namen ohne anstößige, beleidigende oder verbotene Inhalte und ohne Verwechslung mit dem Betreiber.\n\n" . rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/') . "/\n");
        } catch (Throwable $e) {}
    }
    json_out(['ok' => true]);
}
if ($a === 'approve') { $pdo->prepare('UPDATE users SET approved_at=NOW() WHERE id=?')->execute([$id]); json_out(['ok' => true]); }
if ($a === 'revoke')  { $pdo->prepare("UPDATE users SET approved_at=NULL WHERE id=? AND role<>'admin'")->execute([$id]); json_out(['ok' => true]); }
if ($a === 'delete')  {
    if (!empty($b['block'])) { try { $pdo->prepare("INSERT IGNORE INTO blocked_emails (email) SELECT email FROM users WHERE id=? AND role<>'admin'")->execute([$id]); } catch (Throwable $e) { json_out(['error' => 'Datenbank noch nicht aktualisiert (migrate.php).'], 500); } }
    $ad = $pdo->prepare("SELECT id FROM users WHERE id=? AND role<>'admin'"); $ad->execute([$id]); if ($ad->fetch()) soft_delete_user($pdo, (int) $id); json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
