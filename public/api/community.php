<?php
// Community: Ranglisten (anonym) und Nutzerübersichten (nur mit ausdrücklicher Zustimmung). Für alle freigeschalteten Nutzer.
require_once __DIR__ . '/../lib/bootstrap.php';
$SC = defined('SD_SHOWCASE'); // Einblick für Gäste (lib/showcase.php): Übersicht als Beispielnutzer ohne eigene Einträge berechnen und zurückgeben statt ausgeben
$u = $SC ? ['id' => 0, 'role' => 'admin', 'community' => 1, 'approved' => 1] : current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$pdo = db(); $a = $SC ? 'overview' : ($_GET['a'] ?? '');
$TYPES = allowed_cats($u); // nur Kategorien, die für die Gruppe des Nutzers freigeschaltet sind
// Zustimmung: ausdrücklich 1, oder noch nicht entschieden und Admin (Startmodus)
$CONSENT = "(u.community=1 OR (u.community IS NULL AND u.role='admin'))";
$UOK = "(u.role='admin' OR u.approved_at IS NOT NULL) AND u.username IS NOT NULL AND u.username<>''";

// Wer der Nutzung der eigenen Daten nicht zugestimmt hat, bekommt für Nutzerübersicht, Profilkarte und Listen nur Platzhalter-Texte vom Server (keine echten Daten, auch nicht im Quelltext).
$LOCKED = !((isset($u['community']) && (int) $u['community'] === 1) || (!isset($u['community']) && ($u['role'] ?? '') === 'admin'));
function fake_text(string $pattern, string $seed): string { // gleiche Form (Wortlängen) wie Muster, aber zufällige Buchstaben
    mt_srand(crc32($seed . '|' . $pattern)); $o = '';
    foreach (preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (preg_match('/\p{Lu}/u', $ch)) $o .= chr(mt_rand(65, 90)); elseif (preg_match('/\p{Ll}/u', $ch)) $o .= chr(mt_rand(97, 122)); elseif (preg_match('/\d/', $ch)) $o .= (string) mt_rand(0, 9); else $o .= $ch;
    }
    return $o;
}
function fake_words(string $seed, int $minLen, int $maxLen): string {
    mt_srand(crc32($seed)); $n = mt_rand(1, 3); $w = [];
    for ($i = 0; $i < $n; $i++) { $l = mt_rand(max(3, (int) ($minLen / $n)), max(4, (int) ($maxLen / $n))); $w[] = ucfirst(strtolower(fake_text(str_repeat('a', $l), $seed . $i))); }
    return implode(' ', $w);
}
function q_all(PDO $pdo, string $sql, array $p = []): array {
    try { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) {
        if (strpos($sql, 'is_sample') !== false) { // Spalte fehlt: ohne diese Bedingung erneut
            $sql = str_replace(['AND e.is_sample=0', 'WHERE e.is_sample=0 AND'], ['', 'WHERE'], $sql);
            try { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $e2) {}
        }
        if (defined('SD_SHOWCASE')) throw new RuntimeException('Community-Abfrage fehlgeschlagen');
        json_out(['error' => 'Die Datenbank ist noch nicht aktualisiert (migrate.php).'], 500);
    }
}
function consenting_user(PDO $pdo, int $id, string $CONSENT, string $UOK): ?array {
    $r = q_all($pdo, "SELECT u.id,u.username FROM users u WHERE u.id=? AND $UOK AND $CONSENT", [$id]);
    return $r[0] ?? null;
}

if ($a === 'overview') {
    $out = ['top' => [], 'new' => [], 'users' => [], 'me' => (int) $u['id']];
    foreach ($TYPES as $t) {
        // Ranglisten zählen nur Nutzer mit Zustimmung, als Summe pro Titel ohne Namen
        $out['top'][$t] = q_all($pdo, "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.is_sample=0 AND s.type=? AND (u.role='admin' OR u.approved_at IS NOT NULL) AND $CONSENT GROUP BY s.id, s.name ORDER BY c DESC, s.name LIMIT 50", [$u['id'], $t]);
        $out['new'][$t] = q_all($pdo, "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.is_sample=0 AND s.type=? AND (u.role='admin' OR u.approved_at IS NOT NULL) AND $CONSENT AND e.added_at >= (NOW() - INTERVAL 14 DAY) GROUP BY s.id, s.name ORDER BY c DESC, s.name LIMIT 50", [$u['id'], $t]);
    }
    if ($LOCKED) { // Newcomer ohne Zustimmung nur als Platzhalter (Namen und Zahlen erfunden, negative Kennungen)
        $out['locked'] = true;
        foreach ($TYPES as $t) {
            $fk = []; $k = 0;
            foreach ($out['new'][$t] as $r) {
                $k++; $c = (int) $r['c']; mt_srand(crc32('nc' . $u['id'] . '|' . $t . '|' . $k));
                $fk[] = ['id' => -1000 - $k, 'name' => fake_words('nc' . $u['id'] . $t . $k, 8, 22), 'c' => mt_rand(1, max(2, min(30, $c + 3))), 'mine' => 0];
            }
            usort($fk, fn($x, $y) => $y['c'] <=> $x['c']);
            $out['new'][$t] = $fk;
        }
    }
    // Meistgesehen/gelesen/gehört: pro Nutzer und Titel höchstens ein Punkt (nur Nutzer mit Zustimmung, keine Beispieleinträge)
    $out['seen'] = [];
    $cons = "(u.role='admin' OR u.approved_at IS NOT NULL) AND $CONSENT";
    $wk = "d.item_key = CONCAT('w', SUBSTRING(MD5(CONCAT(LOWER(TRIM(w.title)), '|', IFNULL(w.year, 0))), 1, 16))";
    $outer = "r JOIN subjects s ON s.id=r.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? GROUP BY s.id, s.name ORDER BY c DESC, s.name LIMIT 50";
    $sel = "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM (";
    $join = "JOIN users u ON u.id=d.user_id JOIN user_entries ue ON ue.user_id=d.user_id AND ue.subject_id=d.subject_id AND ue.is_sample=0";
    // Serien: mindestens eine halbe Staffel
    $out['seen']['series'] = q_all($pdo, "$sel SELECT DISTINCT d.user_id, d.subject_id FROM user_done d
            JOIN episodes ep ON ep.subject_id=d.subject_id AND d.item_key = CONCAT('e', ep.season, 'x', ep.number) $join
            WHERE $cons GROUP BY d.user_id, d.subject_id, ep.season
            HAVING COUNT(*) * 2 >= (SELECT COUNT(*) FROM episodes e2 WHERE e2.subject_id=d.subject_id AND e2.season=ep.season)
        ) $outer", [$u['id']]);
    // Musik: mindestens 5 Alben oder 20 % des Katalogs (das Kleinere, mindestens 1), damit auch kleine Diskografien zählen
    // Bücher: pro Nutzer und Autor:in bzw. Reihe ein Punkt (mindestens ein gelesenes Buch); bei Gleichstand entscheidet die Zahl der gelesenen Bücher
    $out['seen']['author'] = q_all($pdo, "SELECT s.id, s.name, COUNT(*) AS c, MAX(m.id IS NOT NULL) AS mine FROM (SELECT d.user_id, d.subject_id, COUNT(DISTINCT d.item_key) AS bk FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type='author'
            JOIN works w ON w.subject_id=d.subject_id AND $wk $join
            WHERE $cons GROUP BY d.user_id, d.subject_id
        ) r JOIN subjects s ON s.id=r.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? GROUP BY s.id, s.name ORDER BY c DESC, SUM(r.bk) DESC, s.name LIMIT 50", [$u['id']]);
    // Filme: jeder Film steht einzeln (Kennung = TMDB-Nummer), ein Punkt je Nutzer, der ihn irgendwo als gesehen markiert hat, ob im Einzeleintrag, in einer Filmreihe oder bei der Regie.
    // Ältere Markierungen (Schlüssel aus Titel und Jahr) zählen weiter, bis der Eintrag das nächste Mal geöffnet wurde.
    $out['seen']['film'] = q_all($pdo, "SELECT 0 AS id, r.fid AS fid, MIN(r.title) AS name, COUNT(*) AS c, 0 AS mine FROM (
            SELECT d.user_id, CONVERT(CONCAT('t', w.ext_id) USING utf8mb4) COLLATE utf8mb4_general_ci AS fid, CONVERT(w.title USING utf8mb4) COLLATE utf8mb4_general_ci AS title FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type='film'
            JOIN works w ON w.ext_id IS NOT NULL AND CONVERT(w.ext_id USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(SUBSTRING(d.item_key, 2) USING utf8mb4) COLLATE utf8mb4_general_ci $join
            WHERE $cons AND d.item_key LIKE 't%'
          UNION
            SELECT d.user_id, CONVERT(CONCAT('t', w.ext_id) USING utf8mb4) COLLATE utf8mb4_general_ci, CONVERT(w.title USING utf8mb4) COLLATE utf8mb4_general_ci FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type='film'
            JOIN works w ON w.subject_id=d.subject_id AND w.ext_id IS NOT NULL AND $wk $join
            WHERE $cons
          UNION
            SELECT d.user_id, CONVERT(IFNULL(CONCAT('t', (SELECT MIN(wb.ext_id) FROM works wb WHERE wb.subject_id=sb.id AND wb.ext_id IS NOT NULL)), CONCAT('s', sb.id)) USING utf8mb4) COLLATE utf8mb4_general_ci, CONVERT(sb.name USING utf8mb4) COLLATE utf8mb4_general_ci FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type='film' AND d.item_key = CONCAT('w', SUBSTRING(MD5(CONCAT(LOWER(TRIM(sb.name)), '|', 0)), 1, 16)) $join
            WHERE $cons
        ) r GROUP BY r.fid ORDER BY c DESC, name LIMIT 50");
    // Zeile mit Eintrag verknüpfen: Übernehmen (+) legt die Filmreihe an (sonst den Einzelfilm, sonst die Regie); „schon in deiner Liste“ gilt, wenn du den Film in irgendeinem dieser Einträge hast
    try {
        $tm = []; $sj = [];
        foreach ($out['seen']['film'] as $r) { $f = (string) ($r['fid'] ?? ''); if ($f !== '' && $f[0] === 't') $tm[] = (int) substr($f, 1); elseif ($f !== '' && $f[0] === 's') $sj[(int) substr($f, 1)] = [(int) substr($f, 1) => 'single']; }
        $cand = []; // fid => [subject_id => Rang (1 Reihe, 2 Einzelfilm, 3 Regie)]
        if ($tm) {
            $in = implode(',', array_fill(0, count($tm), '?'));
            foreach (q_all($pdo, "SELECT DISTINCT w.ext_id, s.id, s.external_ids FROM works w JOIN subjects s ON s.id=w.subject_id AND s.type='film' WHERE w.ext_id IN ($in)", $tm) as $x) { $j = json_decode((string) $x['external_ids'], true) ?: []; $cand['t' . $x['ext_id']][(int) $x['id']] = !empty($j['tmdb_col']) ? 1 : (!empty($j['tmdb_person']) ? 3 : 2); }
            foreach ($tm as $tid) foreach (q_all($pdo, "SELECT id FROM subjects WHERE type='film' AND (external_ids LIKE ? OR external_ids LIKE ?)", ['%"tmdb":' . $tid . '}%', '%"tmdb":' . $tid . ',%']) as $x) $cand['t' . $tid][(int) $x['id']] = 2;
        }
        foreach ($sj as $sid => $_) $cand['s' . $sid][$sid] = 2;
        $all = []; foreach ($cand as $m) foreach ($m as $sid => $_) $all[$sid] = 1;
        $have = [];
        if ($all) { $in = implode(',', array_fill(0, count($all), '?')); foreach (q_all($pdo, "SELECT subject_id FROM user_entries WHERE user_id=? AND subject_id IN ($in)", array_merge([$u['id']], array_keys($all))) as $x) $have[(int) $x['subject_id']] = 1; }
        foreach ($out['seen']['film'] as &$r) {
            $m = $cand[(string) ($r['fid'] ?? '')] ?? []; unset($r['fid']);
            if (!$m) { $r['id'] = -1; continue; } // kein Eintrag mehr vorhanden: nur anzeigen
            asort($m); $r['id'] = (int) array_key_first($m); $r['mine'] = 0;
            foreach ($m as $sid => $_) if (isset($have[$sid])) { $r['mine'] = 1; $r['id'] = $sid; break; }
        } unset($r);
    } catch (Throwable $t) { foreach ($out['seen']['film'] as &$r) { unset($r['fid']); $r['id'] = -1; } unset($r); }
    foreach (['artist' => 5] as $t => $min) {
        $out['seen'][$t] = q_all($pdo, "$sel SELECT d.user_id, d.subject_id FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type=?
            JOIN works w ON w.subject_id=d.subject_id AND $wk $join
            WHERE $cons GROUP BY d.user_id, d.subject_id HAVING COUNT(DISTINCT d.item_key) >= LEAST($min, GREATEST(1, CEIL(0.2 * (SELECT COUNT(*) FROM works w2 WHERE w2.subject_id=d.subject_id))))
        ) $outer", [$t, $u['id']]);
    }
    // Podcasts: mindestens 5 Folgen oder 20 % bewusst als gehört markiert (das automatische Abhaken beim Hinzufügen zählt nicht)
    if (in_array('podcast', $TYPES, true)) $out['seen']['podcast'] = q_all($pdo, "$sel SELECT d.user_id, d.subject_id FROM user_done d
            JOIN subjects sb ON sb.id=d.subject_id AND sb.type='podcast'
            JOIN works w ON w.subject_id=d.subject_id AND $wk $join
            WHERE $cons AND d.created_at > ue.added_at + INTERVAL 10 MINUTE GROUP BY d.user_id, d.subject_id
            HAVING COUNT(DISTINCT d.item_key) >= LEAST(5, CEIL(0.2 * (SELECT COUNT(*) FROM works w2 WHERE w2.subject_id=d.subject_id)))
        ) $outer", [$u['id']]);
    // Aktivste Nutzer: nur mit Zustimmung
    $rows = q_all($pdo, "SELECT u.id, u.username, s.type, COUNT(*) AS c, SUM(e.added_at >= (NOW() - INTERVAL 14 DAY)) AS r FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id WHERE e.is_sample=0 AND $UOK AND $CONSENT GROUP BY u.id, u.username, s.type");
    $by = [];
    foreach ($rows as $r) {
        $id = (int) $r['id'];
        if (!isset($by[$id])) $by[$id] = ['id' => $id, 'name' => $r['username'], 'all' => array_fill_keys($TYPES, 0), 'rec' => array_fill_keys($TYPES, 0)];
        $by[$id]['all'][$r['type']] = (int) $r['c']; $by[$id]['rec'][$r['type']] = (int) $r['r'];
    }
    $out['users'] = array_values($by);
    if ($LOCKED) { // Platzhalter statt echter Namen und Zahlen; Kennungen negativ, damit nichts zuordenbar ist
        $out['locked'] = true; $i = 0;
        foreach ($out['users'] as &$fu) {
            $i++; mt_srand(crc32('u' . $u['id'] . '|' . $i)); $fu['id'] = -$i; $fu['name'] = fake_words('n' . $u['id'] . '_' . $i, 5, 11);
            foreach (['all', 'rec'] as $k) foreach ($fu[$k] as $t => $c) $fu[$k][$t] = $c > 0 ? mt_rand(1, max(2, min(30, $c + 3))) : 0;
        } unset($fu);
    }
    if ($SC) return $out;
    json_out($out);
}

if ($a === 'user') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($LOCKED || $id < 0) { // Platzhalter-Profil, keine echten Daten
        $sd = 'p' . $u['id'] . '_' . $id; $lists = array_fill_keys($TYPES, []); $last = []; $n = 0;
        foreach ($TYPES as $t) { mt_srand(crc32($sd . $t)); $c = mt_rand(3, 9); for ($k = 0; $k < $c; $k++) { $n++; $lists[$t][] = ['id' => -$n, 'name' => fake_words($sd . $t . $k, 6, 20), 'type' => $t, 'mine' => 0]; } }
        for ($k = 0; $k < 8; $k++) { $t = $TYPES[$k % max(1, count($TYPES))]; $last[] = ['id' => -100 - $k, 'name' => fake_words($sd . 'l' . $k, 6, 20), 'type' => $t, 'added_at' => '', 'mine' => 0]; }
        $favs = []; foreach (['artist', 'film', 'series', 'author'] as $t) { mt_srand(crc32($sd . 'f' . $t)); $favs[$t] = [fake_words($sd . $t . '1', 5, 9), fake_words($sd . $t . '2', 5, 9)]; }
        $picks = array_fill_keys($TYPES, []); foreach ($TYPES as $t) { mt_srand(crc32($sd . 'pk' . $t)); for ($k = 0, $c = mt_rand(1, 3); $k < $c; $k++) $picks[$t][] = ['id' => -200 - $k, 'name' => fake_words($sd . 'pk' . $t . $k, 6, 20), 'type' => $t, 'mine' => 0]; }
        json_out(['locked' => true, 'user' => ['id' => $id, 'name' => fake_words($sd . 'name', 5, 11)], 'card' => ['name' => fake_words($sd . 'dn', 5, 9), 'age' => fake_text('00 Jahre', $sd), 'avatar' => '', 'favs' => $favs, 'no' => 0], 'lists' => $lists, 'last' => $last, 'picks' => $picks]);
    }
    $cu = consenting_user($pdo, $id, $CONSENT, $UOK);
    if (!$cu) json_out(['error' => 'Diese Person teilt ihre Liste nicht.'], 404);
    $lists = array_fill_keys($TYPES, []);
    foreach (q_all($pdo, "SELECT s.id, s.name, s.type, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.user_id=? AND e.is_sample=0 GROUP BY s.id, s.name, s.type ORDER BY s.name", [$u['id'], $id]) as $r) if (isset($lists[$r['type']])) $lists[$r['type']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'mine' => (int) $r['mine']];
    $last = q_all($pdo, "SELECT s.id, s.name, s.type, e.added_at, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.user_id=? AND e.is_sample=0 GROUP BY e.id, s.id, s.name, s.type, e.added_at ORDER BY e.added_at DESC, e.id DESC LIMIT 20", [$u['id'], $id]);
    $last = array_values(array_filter($last, fn($l) => in_array($l['type'], $TYPES, true))); // Podcasts nur mit Podcast-Recht
    foreach ($last as &$l) { $l['id'] = (int) $l['id']; $l['mine'] = (int) $l['mine']; } unset($l);
    $card = null;
    try { // freiwillige Profilkarte (nur Nutzer mit Community-Zustimmung kommen hier an)
        $cq = $pdo->prepare('SELECT display_name,birth_year,birth_date,age_fixed,age_show,avatar,fav_genres,id FROM users WHERE id=?'); $cq->execute([$id]); $c = $cq->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($c) {
            $age = null; if ($c['birth_year']) { if ($c['age_show'] === 'age') { $n = $c['birth_date'] ? (int) (new DateTime($c['birth_date']))->diff(new DateTime('today'))->y : ((int) date('Y') - (int) $c['birth_year']); $age = $n . ' Jahre'; } elseif ($c['age_show'] === 'year') $age = 'Jahrgang ' . (int) $c['birth_year']; }
            $card = ['name' => (string) ($c['display_name'] ?? ''), 'age' => $age, 'avatar' => (string) ($c['avatar'] ?? ''), 'favs' => json_decode((string) ($c['fav_genres'] ?? ''), true) ?: new stdClass(), 'no' => (int) $c['id']];
        }
    } catch (Throwable $t) {}
    $picks = array_fill_keys($TYPES, []);
    try { // persönliche Empfehlungen (Sterne), höchstens 5 je Kategorie
        $pq = $pdo->prepare("SELECT s.id, s.name, s.type, MAX(m.id IS NOT NULL) AS mine FROM user_entries e JOIN subjects s ON s.id=e.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE e.user_id=? AND e.fav=1 AND e.is_sample=0 GROUP BY s.id, s.name, s.type ORDER BY s.name"); $pq->execute([$u['id'], $id]);
        foreach ($pq->fetchAll(PDO::FETCH_ASSOC) as $r) if (isset($picks[$r['type']]) && count($picks[$r['type']]) < 5) $picks[$r['type']][] = ['id' => (int) $r['id'], 'name' => $r['name'], 'type' => $r['type'], 'mine' => (int) $r['mine']];
    } catch (Throwable $t) {}
    try { // Sterne an Einzeltiteln (Film, Album, Buch): als „Titel (Eintrag)“; zusammen höchstens 5 je Kategorie
        $fq = $pdo->prepare("SELECT s.id, s.name, s.type, f.title, MAX(m.id IS NOT NULL) AS mine FROM user_fav_work f JOIN user_entries e ON e.user_id=f.user_id AND e.subject_id=f.subject_id JOIN subjects s ON s.id=f.subject_id LEFT JOIN user_entries m ON m.subject_id=s.id AND m.user_id=? WHERE f.user_id=? AND e.is_sample=0 GROUP BY s.id, s.name, s.type, f.title, f.created_at ORDER BY f.created_at"); $fq->execute([$u['id'], $id]);
        foreach ($fq->fetchAll(PDO::FETCH_ASSOC) as $r) if (isset($picks[$r['type']]) && count($picks[$r['type']]) < 5) $picks[$r['type']][] = ['id' => (int) $r['id'], 'name' => $r['title'] . ' (' . $r['name'] . ')', 'type' => $r['type'], 'mine' => (int) $r['mine']];
    } catch (Throwable $t) {}
    json_out(['user' => ['id' => (int) $cu['id'], 'name' => $cu['username']], 'card' => $card, 'lists' => $lists, 'last' => $last, 'picks' => $picks]);
}

if ($a === 'adopt' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sid = (int) (body()['subject_id'] ?? 0);
    $s = $pdo->prepare('SELECT id,type FROM subjects WHERE id=?'); $s->execute([$sid]); $sub = $s->fetch(PDO::FETCH_ASSOC);
    if (!$sub || !cat_ok($u, $sub['type'])) json_out(['error' => 'Eintrag nicht gefunden.'], 404);
    $c = $pdo->prepare('SELECT COUNT(*) FROM user_entries e JOIN subjects s ON s.id=e.subject_id WHERE e.user_id=? AND s.type=?'); $c->execute([$u['id'], $sub['type']]);
    $h = $pdo->prepare('SELECT 1 FROM user_entries WHERE user_id=? AND subject_id=?'); $h->execute([$u['id'], $sid]);
    if ($h->fetchColumn()) json_out(['ok' => true, 'had' => true]);
    if ((int) $c->fetchColumn() >= ENTRY_LIMIT) json_out(['error' => 'Du hast die Grenze von ' . number_format(ENTRY_LIMIT, 0, ',', '.') . ' Einträgen für diese Liste erreicht. Bitte lösche zuerst einige Einträge.'], 422);
    $pdo->prepare('INSERT IGNORE INTO user_entries (user_id,subject_id) VALUES (?,?)')->execute([$u['id'], $sid]);
    if (isset($u['bell_default']) && !(int) $u['bell_default']) { try { $pdo->prepare('UPDATE user_entries SET bell=0 WHERE user_id=? AND subject_id=?')->execute([$u['id'], $sid]); } catch (Throwable $e) {} }
    json_out(['ok' => true]);
}
json_out(['error' => 'Unbekannte Aktion.'], 400);
