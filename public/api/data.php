<?php
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
$u = current_user();
if (!$u) json_out(['error' => 'Bitte anmelden.'], 401);
if (empty($u['approved'])) json_out(['error' => 'Dein Konto wartet auf Freischaltung.', 'pending' => true], 403);
$pdo = db(); $a = $_GET['a'] ?? '';

if ($a === 'news') { // ungelesene Systemnachrichten (nur ab dem Anmeldedatum des Kontos)
    try {
        $s = $pdo->prepare('SELECT n.id,n.title,n.body,n.created_at FROM announcements n WHERE n.created_at >= (SELECT created_at FROM users WHERE id=?) AND NOT EXISTS (SELECT 1 FROM announcement_reads r WHERE r.ann_id=n.id AND r.user_id=?) ORDER BY n.id DESC LIMIT 3');
        $s->execute([$u['id'], $u['id']]); json_out(['news' => $s->fetchAll()]);
    } catch (Throwable $e) { json_out(['news' => []]); }
}
if ($a === 'news_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ((array) (body()['ids'] ?? []) as $i) { try { $pdo->prepare('INSERT IGNORE INTO announcement_reads (user_id,ann_id) VALUES (?,?)')->execute([$u['id'], (int) $i]); } catch (Throwable $e) {} }
    json_out(['ok' => true]);
}
if ($a === 'lastrun') { // grobe Angabe für alle, ohne Namen oder Zahlen einzelner Einträge
    $m = $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, MAX(fetched_at), NOW()) FROM subjects')->fetchColumn();
    json_out(['minutes' => $m === null || $m === false ? null : max(0, (int) $m)]);
}
if ($a === 'calinfo') {
    $tok = null; try { $s = $pdo->prepare('SELECT cal_token FROM users WHERE id=?'); $s->execute([$u['id']]); $tok = $s->fetchColumn() ?: null; } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
    json_out(['url' => $tok ? $base . '/kalender/' . $tok . '.ics' : null]);
}
if ($a === 'caltoken' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) (body()['mode'] ?? '');
    if (!in_array($mode, ['create', 'reset', 'off'], true)) json_out(['error' => 'Unbekannte Aktion.'], 422);
    $tok = $mode === 'off' ? null : bin2hex(random_bytes(16));
    try { $pdo->prepare('UPDATE users SET cal_token=? WHERE id=?')->execute([$tok, $u['id']]); } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    $base = rtrim((string) cfg('base_url', 'https://schondraussen.de'), '/');
    json_out(['ok' => true, 'url' => $tok ? $base . '/kalender/' . $tok . '.ics' : null]);
}
if ($a === 'export') { // alle eigenen Daten, als JSON oder (nur die Liste) als CSV
    $fmt = ($_GET['fmt'] ?? 'json') === 'csv' ? 'csv' : 'json';
    $typ = ['author' => 'Autor:in', 'artist' => 'Musiker:in', 'film' => 'Film', 'series' => 'Serie', 'podcast' => 'Podcast'];
    $bellOk = true; try { $pdo->query('SELECT bell FROM user_entries LIMIT 1'); } catch (Throwable $e) { $bellOk = false; }
    $s = $pdo->prepare('SELECT s.type,s.name,e.added_at' . ($bellOk ? ',e.bell' : ',1 AS bell') . ' FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? ORDER BY s.type,s.name');
    $s->execute([$u['id']]); $list = $s->fetchAll();
    $stamp = date('Y-m-d');
    header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store');
    if ($fmt === 'csv') {
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="schondraussen-liste-' . $stamp . '.csv"');
        $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['Typ', 'Name', 'Hinzugefügt am', 'Benachrichtigung']);
        foreach ($list as $r) fputcsv($f, [$typ[$r['type']] ?? $r['type'], $r['name'], $r['added_at'], $r['bell'] ? 'an' : 'aus']);
        fclose($f); exit;
    }
    $set = []; foreach (['skin', 'city', 'notify', 'n_new', 'n_day', 'n_week', 'n_mode', 'n_weekday', 'bell_default', 'hide_email', 'hide_activity'] as $k) if (array_key_exists($k, $u)) $set[$k] = $u[$k];
    $msgs = []; try { $m = $pdo->prepare('SELECT from_admin,body,created_at FROM messages WHERE user_id=? ORDER BY id'); $m->execute([$u['id']]); foreach ($m->fetchAll() as $r) $msgs[] = ['von' => $r['from_admin'] ? 'Administrator' : 'du', 'text' => $r['body'], 'am' => $r['created_at']]; } catch (Throwable $e) {}
    $fb = []; try { $m = $pdo->prepare('SELECT kind,title,body,created_at FROM feedback WHERE user_id=? ORDER BY id'); $m->execute([$u['id']]); foreach ($m->fetchAll() as $r) $fb[] = ['art' => $r['kind'] === 'bug' ? 'Bugmeldung' : 'Idee', 'titel' => $r['title'], 'text' => $r['body'], 'am' => $r['created_at']]; } catch (Throwable $e) {}
    $dn = []; try { $m = $pdo->prepare('SELECT s.name, d.item_key, d.created_at FROM user_done d JOIN subjects s ON s.id=d.subject_id WHERE d.user_id=? ORDER BY s.name, d.item_key'); $m->execute([$u['id']]); foreach ($m->fetchAll() as $r) $dn[] = ['eintrag' => $r['name'], 'schluessel' => $r['item_key'], 'am' => $r['created_at']]; } catch (Throwable $e) {}
    $out = ['exportiert_am' => date('c'), 'hinweis' => 'Hochgeladene Bilder sind nicht enthalten.',
        'konto' => ['email' => $u['email'], 'benutzername' => $u['username'] ?? null, 'mitgliedsnummer' => (int) $u['id'], 'rolle' => $u['role'], 'angelegt_am' => $u['created_at']],
        'einstellungen' => $set,
        'liste' => array_map(fn($r) => ['typ' => $typ[$r['type']] ?? $r['type'], 'name' => $r['name'], 'hinzugefuegt_am' => $r['added_at'], 'benachrichtigung' => (bool) $r['bell']], $list),
        'erledigt_gesehen_gelesen_gehoert' => $dn, 'nachrichten' => $msgs, 'ideen_und_bugs' => $fb];
    header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename="schondraussen-daten-' . $stamp . '.json"');
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); exit;
}
if ($a === 'list') {
    require_once __DIR__ . '/../lib/entries.php';
    $rows = list_entries($pdo, (int) $u['id']);
    $site = ['tag' => '', 'domain' => 'amazon.de'];
    try { foreach ($pdo->query('SELECT k,v FROM site_settings')->fetchAll() as $r) $site[$r['k']] = $r['v']; } catch (Throwable $e) {}
    json_out(['entries' => $rows, 'site' => $site]);
}
if ($a === 'calendar') {
    $s = $pdo->prepare("SELECT r.id,r.title,r.release_date,r.kind,s.id AS subject_id,s.name,s.type
        FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN releases r ON r.subject_id=s.id
        WHERE e.user_id=? AND (s.type<>'podcast' OR r.release_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) ORDER BY (r.release_date IS NULL OR r.release_date=''), r.release_date, s.name LIMIT 500");
    $s->execute([$u['id']]);
    json_out(['releases' => $s->fetchAll()]);
}
if ($a === 'devmode' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Admin: zwischen Live- und Entwicklungsversion wechseln (nur Cookie, keine Daten)
    if (($u['role'] ?? '') !== 'admin') json_out(['error' => 'Nur für Admins.'], 403);
    $on = !empty(body()['on']);
    setcookie('sd_dev', $on ? '1' : '', ['expires' => $on ? time() + 30 * 86400 : time() - 3600, 'path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Lax']);
    json_out(['ok' => true]);
}
if ($a === 'hidecats' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hidecats_ok($u)) json_out(['error' => 'Diese Funktion ist noch nicht freigegeben.'], 403);
    $c = array_values(array_intersect(array_unique(array_map('strval', (array) (body()['cats'] ?? []))), ['author', 'artist', 'film', 'series', 'podcast']));
    try { $pdo->prepare('UPDATE users SET hide_cats=? WHERE id=?')->execute([$c ? implode(',', $c) : null, $u['id']]); } catch (Throwable $e) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'skin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sk = body()['skin'] ?? '';
    $sk = in_array($sk, ['light', 'dark', 'paper', 'cinema', 'mint', 'eighties'], true) ? $sk : null;
    $pdo->prepare('UPDATE users SET skin=? WHERE id=?')->execute([$sk, $u['id']]);
    json_out(['ok' => true]);
}
if ($a === 'notify' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $on = !empty(body()['on']) ? 1 : 0;
    $pdo->prepare('UPDATE users SET notify=? WHERE id=?')->execute([$on, $u['id']]);
    json_out(['ok' => true, 'on' => $on]);
}
if ($a === 'notifyset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body();
    $mode = ($b['mode'] ?? '') === 'instant' ? 'instant' : 'digest';
    $wd = max(1, min(7, (int) ($b['weekday'] ?? 1)));
    try {
        $pdo->prepare('UPDATE users SET n_new=?, n_day=?, n_week=?, n_mode=?, n_weekday=?, bell_default=? WHERE id=?')
            ->execute([!empty($b['new']) ? 1 : 0, !empty($b['day']) ? 1 : 0, !empty($b['week']) ? 1 : 0, $mode, $wd, !empty($b['bell_default']) ? 1 : 0, $u['id']]);
    } catch (Throwable $e) { json_out(['ok' => false, 'msg' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    json_out(['ok' => true]);
}
if ($a === 'bellall' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('UPDATE user_entries SET bell=? WHERE user_id=?')->execute([!empty(body()['on']) ? 1 : 0, $u['id']]);
    json_out(['ok' => true]);
}
if ($a === 'testmail' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../lib/notify.php';
    start_session();
    if (time() - (int) ($_SESSION['testmail'] ?? 0) < 120) json_out(['ok' => false, 'msg' => 'Bitte kurz warten, bevor du erneut eine Testmail schickst.']);
    $_SESSION['testmail'] = time();
    [$subj, $txt] = build_preview($pdo, $u);
    $ok = send_user_mail($pdo, $u, $subj, $txt);
    json_out(['ok' => $ok, 'msg' => $ok ? 'Vorschau ist unterwegs. Schau auch im Spam-Ordner nach.' : 'Der Mailversand ist fehlgeschlagen.']);
}
$TYPES = ['author', 'artist', 'film', 'series'];
if (podcast_ok($u)) $TYPES[] = 'podcast';
if ($a === 'search') {
    $type = $_GET['type'] ?? ''; $q = trim((string) ($_GET['q'] ?? ''));
    if (!in_array($type, $TYPES, true) || mb_strlen($q) < 2) json_out(['results' => []]);
    $out = [];
    if ($type === 'podcast') $out = podcast_search($q);
    elseif ($type === 'series') {
        $r = http_json('https://api.tvmaze.com/search/shows?q=' . rawurlencode($q)) ?? [];
        foreach (array_slice($r, 0, 8) as $x) { $s = $x['show'] ?? []; if (empty($s['name'])) continue;
            $out[] = ['name' => $s['name'], 'hint' => trim(substr((string) ($s['premiered'] ?? ''), 0, 4) . ' ' . implode(', ', array_slice($s['genres'] ?? [], 0, 2))), 'ext' => ['tvmaze' => (int) $s['id']]]; }
    } elseif ($type === 'artist') {
        $r = http_json('https://musicbrainz.org/ws/2/artist/?fmt=json&limit=8&query=' . rawurlencode($q)) ?? [];
        foreach (($r['artists'] ?? []) as $x) {
            $h = array_filter([$x['disambiguation'] ?? '', $x['area']['name'] ?? '', !empty($x['life-span']['begin']) ? 'seit ' . substr($x['life-span']['begin'], 0, 4) : '']);
            $out[] = ['name' => $x['name'], 'hint' => implode(' · ', $h), 'ext' => ['mbid' => $x['id']]]; }
    }
    elseif ($type === 'author' && ($_GET['mode'] ?? '') === 'title') { // Suche über den Buchtitel: Treffer sind die Autor:innen der gefundenen Bücher
        $r = http_json('https://openlibrary.org/search.json?limit=60&sort=editions&fields=title,author_name,author_key,first_publish_year,edition_count&title=' . rawurlencode($q)) ?? [];
        $nz = function (string $x): string { return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($x))); };
        $docs = [];
        foreach (($r['docs'] ?? []) as $i => $x) {
            if (empty($x['author_name']) || empty($x['author_key'])) continue;
            $docs[] = ['x' => $x, 'exact' => $nz((string) ($x['title'] ?? '')) === $nz($q) ? 1 : 0, 'ed' => (int) ($x['edition_count'] ?? 0), 'i' => $i];
        }
        usort($docs, fn($a, $b) => $b['exact'] <=> $a['exact'] ?: $b['ed'] <=> $a['ed'] ?: $a['i'] <=> $b['i']); // Bücher mit genau diesem Titel und vielen Ausgaben zuerst, Sekundärliteratur danach
        $seenA = []; $lat = 0;
        foreach ($docs as $d) {
            $x = $d['x']; $an = (array) $x['author_name']; $ak = (array) $x['author_key'];
            $k = (string) $ak[0]; if (isset($seenA[$k])) continue; $seenA[$k] = 1;
            $nm = (string) $an[0]; $orig = '';
            if (!preg_match('/\p{Latin}/u', $nm) && $lat < 3) { // Name nur in fremder Schrift (z. B. 村上春樹): lateinische Schreibweise aus dem Autoreneintrag holen
                $lat++; $aj = http_json('https://openlibrary.org/authors/' . rawurlencode($k) . '.json', 5) ?? [];
                foreach (array_merge((array) ($aj['alternate_names'] ?? []), [(string) ($aj['fuller_name'] ?? ''), (string) ($aj['personal_name'] ?? '')]) as $alt) { if (is_string($alt) && preg_match('/\p{Latin}/u', $alt) && !preg_match('/[\d\/]/', $alt)) { $orig = $nm; $nm = trim($alt); if ($nm === mb_strtoupper($nm)) $nm = mb_convert_case(mb_strtolower($nm), MB_CASE_TITLE); break; } }
            }
            $out[] = ['name' => $nm, 'hint' => ($orig !== '' ? $orig . ' · ' : '') . '„' . mb_substr((string) ($x['title'] ?? $q), 0, 80) . '“' . (!empty($x['first_publish_year']) ? ' · ' . $x['first_publish_year'] : '') . ($d['ed'] > 1 ? ' · ' . $d['ed'] . ' Ausgaben' : ''), 'ext' => ['olid' => $k]];
            if (count($out) >= 8) break;
        }
    }
    elseif ($type === 'author') {
        $r = http_json('https://openlibrary.org/search/authors.json?limit=8&q=' . rawurlencode($q)) ?? [];
        foreach (($r['docs'] ?? []) as $x) {
            $h = array_filter([!empty($x['top_work']) ? '„' . $x['top_work'] . '“' : '', !empty($x['work_count']) ? $x['work_count'] . ' Werke' : '', $x['birth_date'] ?? '']);
            $out[] = ['name' => $x['name'], 'hint' => implode(' · ', $h), 'ext' => ['olid' => $x['key']]]; }
        $reihe = ['name' => $q . ' (Reihe)', 'hint' => 'Reihe über den Titel suchen, z. B. Manga oder Comics (Katalog der Deutschen Nationalbibliothek, deutsche Ausgaben)', 'ext' => ['gbs' => $q]];
        $tok = function (string $x): array { return array_values(array_filter(preg_split('/[\s,.\-]+/u', mb_strtolower($x)))); };
        $qt = $tok($q); $isAuthor = false; // Suchbegriff passt auf einen Personennamen: mindestens zwei Wörter, die alle im Namen vorkommen (oder ein Einwortname, der genau stimmt), höchstens vier Namensteile, keine Ziffern oder Satzzeichen
        foreach ($out as $o) { $nt = $tok((string) $o['name']); if ($qt && !array_diff($qt, $nt) && (count($qt) >= 2 || $nt === $qt) && count($nt) <= 4 && !preg_match('/[\d():;]/', (string) $o['name'])) { $isAuthor = true; break; } }
        $buch = ['name' => $q . ' (Buchtitel)', 'hint' => 'Nach einem einzelnen Buch suchen und dessen Autor:in übernehmen', 'title_search' => true];
        if ($isAuthor) { $out[] = $reihe; $out[] = $buch; } else { array_unshift($out, $buch); array_unshift($out, $reihe); } // kein Autor gefunden: Reihe und Buchtitel stehen ganz oben
    }
    json_out(['results' => $out]);
}
if ($a === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body(); $type = $b['type'] ?? ''; $name = trim(preg_replace('/\s+/', ' ', (string) ($b['name'] ?? '')));
    if (mb_strlen($name) > 3 && preg_match('/\p{Lu}{2}/u', $name) && $name === mb_strtoupper($name) && preg_match('/\s/', $name) && preg_match('/\p{L}/u', $name) && ($type === 'author')) $name = mb_convert_case(mb_strtolower($name), MB_CASE_TITLE); // Autorenname nur in Großbuchstaben: Groß-/Kleinschreibung herstellen
    if (!in_array($type, $TYPES, true) || $name === '' || mb_strlen($name) > 190) json_out(['error' => 'Bitte einen Namen eingeben.'], 422);
    $n = $pdo->prepare('SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $n->execute([$u['id'], $type]);
    if ((int) $n->fetchColumn() >= 100) json_out(['error' => 'Du hast die Grenze von 100 Einträgen für diese Liste erreicht. Bitte lösche zuerst einige Einträge.'], 422);
    $ext = [];
    foreach (['tvmaze' => 'int', 'itunes' => 'int', 'mbid' => 'str', 'olid' => 'ol'] as $k => $t) if (!empty($b['ext'][$k])) $ext[$k] = $t === 'int' ? (int) $b['ext'][$k] : ($t === 'ol' ? preg_replace('/[^A-Za-z0-9]/', '', (string) $b['ext'][$k]) : preg_replace('/[^0-9a-f-]/', '', (string) $b['ext'][$k]));
    if (!empty($b['ext']['gbs']) && is_string($b['ext']['gbs'])) $ext = ['gbs' => mb_substr(trim(preg_replace('/[\x00-\x1f]+/u', ' ', $b['ext']['gbs'])), 0, 120)];
    $pdo->prepare('INSERT INTO subjects (type,name,external_ids) VALUES (?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$type, $name, $ext ? json_encode($ext) : null]);
    $sid = (int) $pdo->lastInsertId();
    if ($ext) { // Kennung ergänzen, falls der Titel bisher keine hat
        $c = $pdo->prepare('SELECT external_ids FROM subjects WHERE id=?'); $c->execute([$sid]); $old = json_decode((string) $c->fetchColumn(), true) ?: [];
        if (!$old) $pdo->prepare('UPDATE subjects SET external_ids=? WHERE id=?')->execute([json_encode($ext), $sid]);
    }
    $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)')->execute([$u['id'], $sid]);
    if ($type === 'podcast') { // bisherige Folgen gelten als gehört, nur neue Folgen sollen auffallen
        $fa = $pdo->prepare('SELECT fetched_at FROM subjects WHERE id=?'); $fa->execute([$sid]);
        $fat = $fa->fetchColumn(); if (!$fat || time() - strtotime((string) $fat) > 3600) refresh_subject($pdo, $sid);
        try { $ws = $pdo->prepare('SELECT title,year FROM works WHERE subject_id=?'); $ws->execute([$sid]); $dn = $pdo->prepare('INSERT IGNORE INTO user_done (user_id,subject_id,item_key) VALUES (?,?,?)'); foreach ($ws->fetchAll() as $wk) $dn->execute([$u['id'], $sid, done_key_work($wk['title'], $wk['year'])]); } catch (Throwable $e) {}
    }
    if (isset($u['bell_default']) && !(int) $u['bell_default']) { try { $pdo->prepare('UPDATE user_entries SET bell=0 WHERE user_id=? AND subject_id=?')->execute([$u['id'], $sid]); } catch (Throwable $e) {} }
    json_out(['ok' => true, 'id' => $sid, 'type' => $type]);
}
if ($a === 'effort') { // gemessene Umfangszahlen für „Entstehung und Aufwand“ (wöchentlich vom Cronjob)
    require_once __DIR__ . '/../lib/watchdog.php';
    $s = state_get($pdo, 'effort'); $d = $s ? json_decode($s['v'], true) : null;
    json_out(['ok' => true, 'm' => is_array($d) ? $d : null]);
}
if ($a === 'mystats') { // persönliche Statistik für die Info-Seite (nur eigene Zahlen)
    $o = ['since' => null, 'entries' => ['author' => 0, 'artist' => 0, 'film' => 0, 'series' => 0], 'works' => 0, 'ideas' => 0, 'bugs' => 0, 'messages' => 0];
    try { $q = $pdo->prepare('SELECT created_at FROM users WHERE id=?'); $q->execute([$u['id']]); $o['since'] = $q->fetchColumn() ?: null; } catch (Throwable $e) {}
    try { $q = $pdo->prepare('SELECT s.type, COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? GROUP BY s.type'); $q->execute([$u['id']]); foreach ($q->fetchAll(PDO::FETCH_NUM) as $x) if (isset($o['entries'][$x[0]])) $o['entries'][$x[0]] = (int) $x[1]; } catch (Throwable $e) {}
    try { $q = $pdo->prepare('SELECT COUNT(*) FROM works w JOIN user_entries e ON e.subject_id=w.subject_id WHERE e.user_id=?'); $q->execute([$u['id']]); $o['works'] = (int) $q->fetchColumn(); } catch (Throwable $e) {}
    try { $q = $pdo->prepare('SELECT kind, COUNT(*) FROM feedback WHERE user_id=? GROUP BY kind'); $q->execute([$u['id']]); foreach ($q->fetchAll(PDO::FETCH_NUM) as $x) { if ($x[0] === 'feature') $o['ideas'] = (int) $x[1]; if ($x[0] === 'bug') $o['bugs'] = (int) $x[1]; } } catch (Throwable $e) {}
    try { $q = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id=? AND from_admin=0'); $q->execute([$u['id']]); $o['messages'] = (int) $q->fetchColumn(); } catch (Throwable $e) {}
    try { // Aktivitätsrang: Einträge + eingereichte Beiträge + gesendete Chatnachrichten, verglichen mit allen aktiven Nutzern (nur Prozentstufe, keine Namen)
        $sc = $pdo->query("SELECT u.id, (SELECT COUNT(*) FROM user_entries e WHERE e.user_id=u.id) + (SELECT COUNT(*) FROM feedback f WHERE f.user_id=u.id) + (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.from_admin=0) AS sc FROM users u WHERE u.role<>'admin' AND u.approved_at IS NOT NULL AND u.delete_requested_at IS NULL")->fetchAll(PDO::FETCH_KEY_PAIR);
        $n = count($sc);
        if ($n >= 10 && isset($sc[$u['id']]) && (int) $sc[$u['id']] > 0) {
            $mine = (int) $sc[$u['id']]; $higher = 0; foreach ($sc as $v) if ((int) $v > $mine) $higher++;
            $pct = ($higher + 1) / $n * 100;
            foreach ([1, 5, 10, 25, 50] as $st) if ($pct <= $st) { $o['top_percent'] = $st; break; }
        }
    } catch (Throwable $e) {}
    json_out($o);
}
if ($a === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = []; $seen = [];
    foreach ((array) (body()['names'] ?? []) as $x) {
        $t = trim(preg_replace('/[\x00-\x1f\s]+/u', ' ', (string) $x));
        if ($t === '' || mb_strlen($t) > 190 || isset($seen[mb_strtolower($t)])) continue;
        $seen[mb_strtolower($t)] = 1; $names[] = $t;
        if (count($names) >= 300) break;
    }
    if (!$names) json_out(['ok' => false, 'msg' => 'Keine Namen gefunden.']);
    $it = (string) (body()['type'] ?? 'artist'); if (!in_array($it, $TYPES, true)) $it = 'artist';
    $c = $pdo->prepare("SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?"); $c->execute([$u['id'], $it]);
    $room = max(0, 100 - (int) $c->fetchColumn()); $added = 0; $had = 0; $over = 0;
    $sub = $pdo->prepare("INSERT INTO subjects (type,name) VALUES (?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $ue = $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)');
    $off = isset($u['bell_default']) && !(int) $u['bell_default'];
    foreach ($names as $nm) {
        $sub->execute([$it, $nm]); $sid = (int) $pdo->lastInsertId();
        $chk = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $chk->execute([$u['id'], $sid]);
        if ($chk->fetch()) { $had++; continue; }
        if ($room <= 0) { $over++; continue; }
        $ue->execute([$u['id'], $sid]); $room--; $added++;
        if ($off) { try { $pdo->prepare('UPDATE user_entries SET bell=0 WHERE user_id=? AND subject_id=?')->execute([$u['id'], $sid]); } catch (Throwable $e) {} }
    }
    json_out(['ok' => true, 'added' => $added, 'had' => $had, 'over' => $over]);
}
if ($a === 'trakt' && $_SERVER['REQUEST_METHOD'] === 'POST') { // Import aus einem Trakt-Export (vom Browser vorbereitet: Serien/Filme + gesehene Folgen)
    $items = (array) (body()['items'] ?? []); $res = ['series' => ['added' => 0, 'had' => 0, 'over' => 0], 'film' => ['added' => 0, 'had' => 0, 'over' => 0], 'eps' => 0];
    $room = []; foreach (['series', 'film'] as $t) { $c = $pdo->prepare('SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $c->execute([$u['id'], $t]); $room[$t] = max(0, 100 - (int) $c->fetchColumn()); }
    $off = isset($u['bell_default']) && !(int) $u['bell_default']; $seen = []; $epBudget = 20000; $n = 0;
    $chk = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $ue = $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)');
    $dn = $pdo->prepare('INSERT IGNORE INTO user_done (user_id,subject_id,item_key) VALUES (?,?,?)');
    foreach ($items as $it) {
        if (++$n > 600 || !is_array($it)) break;
        $t = ($it['type'] ?? '') === 'film' ? 'film' : 'series'; $nm = trim(preg_replace('/[\x00-\x1f\s]+/u', ' ', (string) ($it['name'] ?? '')));
        if ($nm === '' || mb_strlen($nm) > 190 || isset($seen[$t . mb_strtolower($nm)])) continue; $seen[$t . mb_strtolower($nm)] = 1;
        $pdo->prepare('INSERT INTO subjects (type,name) VALUES (?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)')->execute([$t, $nm]); $sid = (int) $pdo->lastInsertId();
        $chk->execute([$u['id'], $sid]);
        if ($chk->fetch()) $res[$t]['had']++;
        elseif ($room[$t] <= 0) { $res[$t]['over']++; continue; }
        else {
            $ue->execute([$u['id'], $sid]); $room[$t]--; $res[$t]['added']++;
            if ($off) { try { $pdo->prepare('UPDATE user_entries SET bell=0 WHERE user_id=? AND subject_id=?')->execute([$u['id'], $sid]); } catch (Throwable $e) {} }
        }
        if ($t === 'series' && !empty($it['eps']) && is_array($it['eps'])) {
            try { foreach ($it['eps'] as $e) { if ($epBudget <= 0) break; $s1 = (int) ($e[0] ?? 0); $n1 = (int) ($e[1] ?? 0); if ($s1 < 0 || $s1 > 100 || $n1 < 1 || $n1 > 2000) continue; $dn->execute([$u['id'], $sid, 'e' . $s1 . 'x' . $n1]); $res['eps'] += $dn->rowCount(); $epBudget--; } } catch (Throwable $e) {}
        }
    }
    json_out(['ok' => true] + $res);
}
if ($a === 'samples' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) (body()['mode'] ?? '');
    try { if ($mode !== 'remove') $pdo->prepare('UPDATE users SET sample_asked=1 WHERE id=?')->execute([$u['id']]); } catch (Throwable $e) { json_out(['ok' => false, 'msg' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).']); }
    if ($mode === 'remove') {
        $q = $pdo->prepare('DELETE FROM user_entries WHERE user_id=? AND is_sample=1'); $q->execute([$u['id']]);
        json_out(['ok' => true, 'removed' => $q->rowCount()]);
    }
    if ($mode !== 'add') json_out(['ok' => true, 'added' => 0]);
    $adm = (int) $pdo->query("SELECT MIN(id) FROM users WHERE role='admin'")->fetchColumn(); $added = 0; $today = date('Y-m-d');
    if (!$adm || $adm === (int) $u['id']) json_out(['ok' => true, 'added' => 0]);
    foreach ($TYPES as $t) {
        $c = $pdo->prepare('SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $c->execute([$u['id'], $t]);
        $room = 100 - (int) $c->fetchColumn(); if ($room <= 0) continue;
        $q = $pdo->prepare("SELECT s.id FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND e.is_sample=0 AND s.type=? AND s.fetched_at IS NOT NULL
            ORDER BY (SELECT COUNT(*) FROM releases r WHERE r.subject_id=s.id AND r.release_date>=?) DESC, s.name LIMIT 10");
        $q->execute([$adm, $t, $today]);
        foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $sid) {
            if ($room <= 0) break;
            try { $ins = $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id,is_sample,bell) VALUES (?,?,1,0)'); $ins->execute([$u['id'], $sid]); }
            catch (Throwable $e) { $ins = $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id,is_sample) VALUES (?,?,1)'); $ins->execute([$u['id'], $sid]); }
            if ($ins->rowCount()) { $added++; $room--; }
        }
    }
    json_out(['ok' => true, 'added' => $added]);
}
// Ab hier: ein Titel, nur wenn er in der eigenen Liste steht
$id = (int) ($_GET['id'] ?? (body()['id'] ?? 0));
$own = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $own->execute([$u['id'], $id]);
if (!$own->fetch()) json_out(['error' => 'Eintrag nicht gefunden.'], 404);

if ($a === 'refresh' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $f = $pdo->prepare('SELECT fetched_at FROM subjects WHERE id=?'); $f->execute([$id]);
    $t = $f->fetchColumn();
    $hasData = (int) $pdo->query('SELECT (SELECT COUNT(*) FROM works WHERE subject_id=' . (int) $id . ') + (SELECT COUNT(*) FROM releases WHERE subject_id=' . (int) $id . ')')->fetchColumn() > 0;
    if ($t && $hasData && time() - strtotime($t) < 600) json_out(['ok' => true, 'wait' => true, 'msg' => 'Dieser Eintrag wurde gerade erst aktualisiert.']);
    $res = refresh_subject($pdo, $id);
    $res['wait'] = (int) $pdo->query('SELECT (SELECT COUNT(*) FROM works WHERE subject_id=' . (int) $id . ') + (SELECT COUNT(*) FROM releases WHERE subject_id=' . (int) $id . ')')->fetchColumn() > 0;
    json_out($res);
}
if ($a === 'relink' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $b = body(); $ext = [];
    foreach (['tvmaze' => 'int', 'itunes' => 'int', 'mbid' => 'str', 'olid' => 'ol'] as $k => $t) if (!empty($b['ext'][$k])) $ext[$k] = $t === 'int' ? (int) $b['ext'][$k] : ($t === 'ol' ? preg_replace('/[^A-Za-z0-9]/', '', (string) $b['ext'][$k]) : preg_replace('/[^0-9a-f-]/', '', (string) $b['ext'][$k]));
    if (!empty($b['ext']['gbs']) && is_string($b['ext']['gbs'])) $ext = ['gbs' => mb_substr(trim(preg_replace('/[\x00-\x1f]+/u', ' ', $b['ext']['gbs'])), 0, 120)];
    if (!$ext) json_out(['ok' => false, 'msg' => 'Kein Treffer gewählt.']);
    $pdo->prepare('UPDATE subjects SET external_ids=?, fetched_at=NULL WHERE id=?')->execute([json_encode($ext), $id]);
    json_out(refresh_subject($pdo, $id));
}
if ($a === 'bell' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $on = !empty(body()['on']) ? 1 : 0;
    $pdo->prepare('UPDATE user_entries SET bell=? WHERE user_id=? AND subject_id=?')->execute([$on, $u['id'], $id]);
    json_out(['ok' => true, 'on' => $on]);
}
if ($a === 'remove' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('DELETE FROM user_entries WHERE user_id=? AND subject_id=?')->execute([$u['id'], $id]);
    json_out(['ok' => true]);
}
function done_key_work($title, $year): string { return 'w' . substr(md5(mb_strtolower(trim((string) $title)) . '|' . (int) $year), 0, 16); }
function done_key_ep($season, $number): string { return 'e' . (int) $season . 'x' . (int) $number; }
if ($a === 'done' && $_SERVER['REQUEST_METHOD'] === 'POST') { // gesehen / gelesen / gehört
    $b = body(); $sid = (int) ($b['id'] ?? 0); $keys = array_values(array_unique(array_filter((array) ($b['keys'] ?? []), fn($k) => is_string($k) && preg_match('/^(w[0-9a-f]{16}|e\d{1,3}x\d{1,4})$/', $k))));
    if (!$sid || !$keys || count($keys) > 400) json_out(['error' => 'Ungültige Angabe.'], 422);
    $own = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $own->execute([$u['id'], $sid]); if (!$own->fetch()) json_out(['error' => 'Der Eintrag steht nicht auf deiner Liste.'], 404);
    try {
        if (!empty($b['on'])) { $q = $pdo->prepare('INSERT IGNORE INTO user_done (user_id,subject_id,item_key) VALUES (?,?,?)'); foreach ($keys as $k) $q->execute([$u['id'], $sid, $k]); }
        else { $q = $pdo->prepare('DELETE FROM user_done WHERE user_id=? AND subject_id=? AND item_key=?'); foreach ($keys as $k) $q->execute([$u['id'], $sid, $k]); }
    } catch (Throwable $t) { json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500); }
    json_out(['ok' => true]);
}
if ($a === 'episum') { // Kurzfassung einer Serienfolge (bei Bedarf übersetzt und gemerkt)
    $sid = (int) ($_GET['id'] ?? 0); $se = (int) ($_GET['s'] ?? 0); $nu = (int) ($_GET['n'] ?? 0);
    try {
        $c = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $c->execute([$u['id'], $sid]);
        if (!$c->fetchColumn()) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
        $q = $pdo->prepare('SELECT e.summary,e.summary_de,s.external_ids FROM episodes e JOIN subjects s ON s.id=e.subject_id WHERE e.subject_id=? AND e.season=? AND e.number=?'); $q->execute([$sid, $se, $nu]);
        $r = $q->fetch();
        if (!$r || (string) $r['summary'] === '') json_out(['text' => '']);
        $txt = (string) $r['summary_de'];
        if ($txt === '') {
            $lang = (string) ((json_decode($r['external_ids'] ?: '{}', true) ?: [])['lang'] ?? '');
            $txt = (string) $r['summary'];
            if (!in_array($lang, ['German', ''], true) && ($tr = translate_de($txt, 'EN')) !== null) {
                $txt = $tr;
                $pdo->prepare('UPDATE episodes SET summary_de=? WHERE subject_id=? AND season=? AND number=?')->execute([mb_substr($tr, 0, 900), $sid, $se, $nu]);
            }
        }
        json_out(['text' => $txt]);
    } catch (Throwable $t) { json_out(['text' => '']); }
}
if ($a === 'entry') {
    $s = $pdo->prepare('SELECT id,type,name,summary,genres,total_works,fetched_at,external_ids FROM subjects WHERE id=?'); $s->execute([$id]);
    $e = $s->fetch(); if ($e && $e['type'] === 'podcast' && !podcast_ok($u)) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
    $e['genres'] = json_decode($e['genres'] ?: '[]', true);
    $x = json_decode($e['external_ids'] ?: '{}', true) ?: []; $e['tvmaze'] = $x['tvmaze'] ?? null; $e['tv_status'] = $x['tv_status'] ?? ''; $e['err'] = (string) ($x['err'] ?? ''); $e['page'] = $e['type'] === 'podcast' && preg_match('~^https://(podcasts|itunes)\.apple\.com/~', (string) ($x['page'] ?? '')) ? $x['page'] : ''; $e['by'] = (string) ($x['by'] ?? ''); unset($e['external_ids']);
    $eps = [];
    if ($e['type'] === 'series') { try { try { $q = $pdo->prepare("SELECT season,number,title,airdate,(summary IS NOT NULL AND summary<>'') AS has_sum FROM episodes WHERE subject_id=? ORDER BY season, number"); $q->execute([$id]); } catch (Throwable $t2) { $q = $pdo->prepare('SELECT season,number,title,airdate FROM episodes WHERE subject_id=? ORDER BY season, number'); $q->execute([$id]); } $eps = $q->fetchAll(); } catch (Throwable $t) {} }
    $w = $pdo->prepare('SELECT title,year,kind,note FROM works WHERE subject_id=? ORDER BY year DESC, id DESC LIMIT 300'); $w->execute([$id]);
    $r = $pdo->prepare('SELECT title,release_date,kind,note FROM releases WHERE subject_id=? ORDER BY release_date LIMIT 100'); $r->execute([$id]);
    $works = $w->fetchAll(); foreach ($works as &$x) $x['k'] = done_key_work($x['title'], $x['year']); unset($x);
    foreach ($eps as &$x) $x['k'] = done_key_ep($x['season'], $x['number']); unset($x);
    $done = []; try { $q = $pdo->prepare('SELECT item_key FROM user_done WHERE user_id=? AND subject_id=?'); $q->execute([$u['id'], $id]); $done = $q->fetchAll(PDO::FETCH_COLUMN); } catch (Throwable $t) {}
    json_out(['entry' => $e, 'works' => $works, 'releases' => $r->fetchAll(), 'episodes' => $eps, 'done' => $done]);
}
json_out(['error' => 'Unbekannte Aktion'], 400);
