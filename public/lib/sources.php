<?php
// Datenquellen. Bisher angebunden: TVmaze (Serien). Weitere folgen (MusicBrainz, Open Library, TMDB).
function http_json(string $url, int $timeout = 12): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code >= 400) return null;
    $j = json_decode($r, true);
    return is_array($j) ? $j : null;
}

// Übersetzung ins Deutsche über DeepL (Schlüssel 'deepl_key' in config.php). Gibt null zurück, wenn kein Schlüssel da ist oder der Dienst nicht antwortet.
function translate_de(string $text, string $from = 'EN'): ?string {
    $key = trim((string) cfg('deepl_key', '')); $text = trim($text);
    if ($key === '' || $text === '') return null;
    $host = substr($key, -3) === ':fx' ? 'api-free.deepl.com' : 'api.deepl.com';
    $ch = curl_init('https://' . $host . '/v2/translate');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key, 'Content-Type: application/json', 'User-Agent: Schondraussen.de/1.0'],
        CURLOPT_POSTFIELDS => json_encode(['text' => [mb_substr($text, 0, 1500)], 'source_lang' => $from, 'target_lang' => 'DE'], JSON_UNESCAPED_UNICODE)]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($r === false || $code >= 400) return null;
    $j = json_decode($r, true); $t = trim((string) ($j['translations'][0]['text'] ?? ''));
    return $t !== '' ? $t : null;
}

function clean_text(?string $h, int $max = 380): string {
    $t = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $h), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    return mb_strlen($t) > $max ? mb_substr($t, 0, $max - 1) . '…' : $t;
}

// Holt eine Serie samt Folgen von TVmaze.
function tvmaze_fetch(string $name, ?int $id = null): ?array {
    $url = $id ? "https://api.tvmaze.com/shows/$id?embed=episodes"
               : 'https://api.tvmaze.com/singlesearch/shows?embed=episodes&q=' . rawurlencode($name);
    return http_json($url);
}

// Aktualisiert einen Titel in der Datenbank. Gibt ['ok'=>bool,'msg'=>string] zurück.
function refresh_subject(PDO $pdo, int $sid): array {
    $r = refresh_subject_run($pdo, $sid);
    // Auch bei Misserfolg Zeitpunkt merken, damit nicht bei jedem Aufruf und jedem Cron-Lauf neu versucht wird.
    if (empty($r['ok'])) $pdo->prepare('UPDATE subjects SET fetched_at=NOW() WHERE id=?')->execute([$sid]);
    return $r;
}
function refresh_subject_run(PDO $pdo, int $sid): array {
    $s = $pdo->prepare('SELECT * FROM subjects WHERE id=?'); $s->execute([$sid]); $sub = $s->fetch();
    if (!$sub) return ['ok' => false, 'msg' => 'Titel nicht gefunden'];
    if ($sub['type'] === 'artist') return musicbrainz_refresh($pdo, $sub);
    if ($sub['type'] === 'author') return openlibrary_refresh($pdo, $sub);
    if ($sub['type'] !== 'series') {
        $pdo->prepare('UPDATE subjects SET fetched_at=NOW() WHERE id=?')->execute([$sid]);
        return ['ok' => false, 'msg' => 'Für diese Kategorie ist die Datenquelle noch nicht angebunden.'];
    }
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    $show = tvmaze_fetch($sub['name'], isset($ext['tvmaze']) ? (int) $ext['tvmaze'] : null);
    if (!$show || empty($show['id'])) return ['ok' => false, 'msg' => 'Bei TVmaze nicht gefunden oder nicht erreichbar.'];
    $eps = $show['_embedded']['episodes'] ?? [];
    $seasons = []; $today = date('Y-m-d'); $rel = []; $epRows = [];
    foreach ($eps as $e) {
        $n = (int) ($e['season'] ?? 0); if ($n < 1) continue;
        $epRows[] = [$n, (int) ($e['number'] ?? 0), mb_substr((string) ($e['name'] ?? ''), 0, 255), (string) ($e['airdate'] ?? '')];
        $seasons[$n] = $seasons[$n] ?? ['n' => 0, 'first' => null];
        $seasons[$n]['n']++;
        $d = $e['airdate'] ?? '';
        if ($d && (!$seasons[$n]['first'] || $d < $seasons[$n]['first'])) $seasons[$n]['first'] = $d;
        if ($d && $d >= $today) $rel[] = [sprintf('S%02dE%02d %s', $n, (int) ($e['number'] ?? 0), $e['name'] ?? ''), $d, 'Folge'];
    }
    $pdo->beginTransaction();
    try {
        $ext['tvmaze'] = (int) $show['id']; $ext['tv_status'] = (string) ($show['status'] ?? '');
        $genres = $show['genres'] ?? [];
        $sumTxt = clean_text($show['summary'] ?? '');
        if ($sumTxt !== '' && !in_array((string) ($show['language'] ?? ''), ['German', ''], true)) { // englische Beschreibung einmal übersetzen und merken
            $hh = sha1($sumTxt);
            if (($ext['sum_h'] ?? '') === $hh && !empty($ext['sum_de'])) $sumTxt = $ext['sum_de'];
            elseif (($tr = translate_de($sumTxt, 'EN')) !== null) { $ext['sum_h'] = $hh; $ext['sum_de'] = $tr; $sumTxt = $tr; }
        }
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext), $sumTxt, json_encode($genres, JSON_UNESCAPED_UNICODE), count($seasons), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $w = $pdo->prepare('INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        ksort($seasons);
        foreach ($seasons as $n => $x) $w->execute([$sid, "Staffel $n", $x['first'] ? (int) substr($x['first'], 0, 4) : null, 'Staffel', $x['n'] . ((int) $x['n'] === 1 ? ' Folge' : ' Folgen')]);
        $r = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($rel, 0, 60) as $x) $r->execute([$sid, $x[0], $x[1], $x[2], null]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
    try { // Folgenliste (Tabelle kann in älteren Ständen fehlen)
        $pdo->prepare('DELETE FROM episodes WHERE subject_id=?')->execute([$sid]);
        $q = $pdo->prepare('INSERT INTO episodes (subject_id,season,number,title,airdate) VALUES (?,?,?,?,?)');
        foreach (array_slice($epRows, 0, 1500) as $x) $q->execute([$sid, $x[0], $x[1], $x[2] ?: null, $x[3] ?: null]);
    } catch (Throwable $e) {}
    return ['ok' => true, 'msg' => count($seasons) . ' Staffeln, ' . count($rel) . ' kommende Folgen'];
}

// Teilweise Datumsangaben (YYYY, YYYY-MM) vorsichtig als „in der Zukunft“ einstufen.
function date_is_future(string $d): bool {
    $n = strlen($d);
    if ($n === 10) return $d >= date('Y-m-d');
    if ($n === 7) return $d >= date('Y-m');
    if ($n === 4) return (int) $d > (int) date('Y');
    return false;
}

// MusicBrainz (Künstler): höchstens eine Anfrage pro Sekunde erlaubt.
function musicbrainz_refresh(PDO $pdo, array $sub): array {
    $sid = (int) $sub['id'];
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    $summary = $sub['summary']; $genres = json_decode($sub['genres'] ?: '[]', true) ?: [];
    if (empty($ext['mbid'])) {
        $r = http_json('https://musicbrainz.org/ws/2/artist/?fmt=json&limit=5&query=' . rawurlencode('artist:"' . str_replace('"', '', $sub['name']) . '"'));
        usleep(1100000);
        $pick = null;
        foreach (($r['artists'] ?? []) as $a) {
            if (($a['score'] ?? 0) >= 80 && mb_strtolower($a['name']) === mb_strtolower($sub['name'])) { $pick = $a; break; }
        }
        if (!$pick && !empty($r['artists'][0]) && ($r['artists'][0]['score'] ?? 0) >= 95) $pick = $r['artists'][0];
        if (!$pick) return ['ok' => false, 'msg' => 'Bei MusicBrainz nicht eindeutig gefunden.'];
        $ext['mbid'] = $pick['id'];
        $tags = $pick['tags'] ?? [];
        usort($tags, fn($x, $y) => ($y['count'] ?? 0) <=> ($x['count'] ?? 0));
        $genres = array_slice(array_map(fn($t) => $t['name'], $tags), 0, 4);
        $kind = ($pick['type'] ?? '') === 'Person' ? 'Solo' : (($pick['type'] ?? '') === 'Group' ? 'Band' : 'Künstler');
        $bits = [$kind];
        if (!empty($pick['area']['name'])) $bits[] = $pick['area']['name'];
        if (!empty($pick['life-span']['begin'])) $bits[] = 'seit ' . substr($pick['life-span']['begin'], 0, 4);
        $summary = implode(' · ', $bits);
    }
    // Kurzvorstellung aus Wikipedia (über den Verweis bei MusicBrainz); wird einmal geholt, bei Misserfolg frühestens nach 30 Tagen erneut
    if (empty($ext['bits'])) $ext['bits'] = (string) $summary;
    if (!isset($ext['intro_t']) || (empty($ext['intro']) && (int) $ext['intro_t'] < time() - 30 * 86400)) {
        usleep(1100000); $ext['intro_t'] = time(); $ext['intro'] = wiki_intro_for_mbid($ext['mbid']) ?: '';
    }
    if (!empty($ext['intro'])) $summary = $ext['bits'] . ' – ' . $ext['intro'];
    $groups = []; $total = 0;
    for ($page = 0; $page < 2; $page++) {
        $r = http_json('https://musicbrainz.org/ws/2/release-group?fmt=json&limit=100&offset=' . ($page * 100) . '&type=album|ep&artist=' . $ext['mbid']);
        usleep(1100000);
        if (!$r) { if ($page === 0) return ['ok' => false, 'msg' => 'MusicBrainz nicht erreichbar.']; break; }
        $total = (int) ($r['release-group-count'] ?? 0);
        foreach (($r['release-groups'] ?? []) as $g) $groups[] = $g;
        if (count($groups) >= $total) break;
    }
    $works = []; $rel = [];
    foreach ($groups as $g) {
        $d = (string) ($g['first-release-date'] ?? '');
        $sec = array_map(fn($x) => $x, $g['secondary-types'] ?? []);
        if (array_intersect($sec, ['Compilation', 'Live', 'Remix', 'Demo', 'DJ-mix', 'Mixtape/Street', 'Soundtrack', 'Spokenword', 'Interview', 'Audiobook'])) { if (!date_is_future($d)) continue; }
        $kind = $g['primary-type'] ?? 'Album';
        if (date_is_future($d)) $rel[] = [$g['title'], $d, $kind];
        elseif ($d !== '') $works[] = [$g['title'], (int) substr($d, 0, 4), $kind];
    }
    usort($works, fn($x, $y) => $y[1] <=> $x[1]);
    usort($rel, fn($x, $y) => strcmp($x[1], $y[1]));
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext), $summary, json_encode($genres, JSON_UNESCAPED_UNICODE), count($works), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $w = $pdo->prepare('INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($works, 0, 200) as $x) $w->execute([$sid, mb_substr($x[0], 0, 255), $x[1] ?: null, $x[2], null]);
        $r = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($rel, 0, 40) as $x) $r->execute([$sid, mb_substr($x[0], 0, 255), $x[1], $x[2], null]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
    return ['ok' => true, 'msg' => count($works) . ' Alben, ' . count($rel) . ' kommende Veröffentlichungen'];
}

// Kurzvorstellung (höchstens ca. 420 Zeichen) aus der Wikipedia, gefunden über die Verweise bei MusicBrainz bzw. Wikidata. Deutsch bevorzugt, sonst Englisch.
function wiki_intro_for_mbid(string $mbid): ?string {
    $r = http_json('https://musicbrainz.org/ws/2/artist/' . rawurlencode($mbid) . '?fmt=json&inc=url-rels', 10);
    if (!$r) return null;
    $pages = []; $qid = null;
    foreach (($r['relations'] ?? []) as $rel) {
        $u = (string) ($rel['url']['resource'] ?? '');
        if (($rel['type'] ?? '') === 'wikipedia' && preg_match('#^https://(de|en)\.wikipedia\.org/wiki/([^?\#]+)#', $u, $m)) $pages[$m[1]] = urldecode($m[2]);
        if (($rel['type'] ?? '') === 'wikidata' && preg_match('#/(Q\d+)$#', $u, $m)) $qid = $m[1];
    }
    if (empty($pages['de']) && $qid) {
        $w = http_json('https://www.wikidata.org/wiki/Special:EntityData/' . $qid . '.json', 10);
        $sl = $w['entities'][$qid]['sitelinks'] ?? [];
        foreach (['de' => 'dewiki', 'en' => 'enwiki'] as $l => $k) if (empty($pages[$l]) && !empty($sl[$k]['title'])) $pages[$l] = str_replace(' ', '_', $sl[$k]['title']);
    }
    foreach (['de', 'en'] as $l) {
        if (empty($pages[$l])) continue;
        $p = http_json('https://' . $l . '.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode($pages[$l]), 10);
        $t = trim((string) ($p['extract'] ?? ''));
        if (!$p || ($p['type'] ?? '') === 'disambiguation' || mb_strlen($t) < 60) continue;
        if (mb_strlen($t) > 420) { $cut = mb_substr($t, 0, 420); $i = max(mb_strrpos($cut, '. '), mb_strrpos($cut, '! ')); $t = $i > 150 ? mb_substr($cut, 0, $i + 1) : rtrim($cut, " ,;") . '…'; }
        if ($l === 'en' && ($tr = translate_de($t, 'EN')) !== null) $t = $tr; // englischen Text ins Deutsche übersetzen, falls möglich
        return $t . ' (Quelle: Wikipedia)';
    }
    return null;
}

// Google Books: angekündigte Bücher einer Person (Datum als JJJJ, JJJJ-MM oder JJJJ-MM-TT). $seen = bereits bekannte Titel (klein geschrieben).
function googlebooks_upcoming(string $name, array $seen): array {
    $r = http_json('https://www.googleapis.com/books/v1/volumes?maxResults=40&orderBy=newest&printType=books&q=' . rawurlencode('inauthor:"' . str_replace('"', '', $name) . '"'), 8);
    $out = []; $today = date('Y-m-d'); $ym = date('Y-m'); $y = date('Y');
    $norm = fn($t) => trim(mb_strtolower(preg_split('/\s*[:–-]\s/u', $t)[0]));
    $known = []; foreach ($seen as $k => $_) $known[$norm((string) $k)] = 1;
    foreach (($r['items'] ?? []) as $it) {
        $v = $it['volumeInfo'] ?? []; $t = trim((string) ($v['title'] ?? '')); $d = (string) ($v['publishedDate'] ?? '');
        if (!in_array($v['language'] ?? 'de', ['de'], true)) continue;
        if ($t === '' || !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $d)) continue;
        $has = false; foreach (($v['authors'] ?? []) as $a) if (mb_strtolower(trim($a)) === mb_strtolower($name)) $has = true;
        if (!$has) continue;
        $future = strlen($d) === 10 ? $d > $today : (strlen($d) === 7 ? $d > $ym : $d > $y);
        if (!$future || isset($known[$norm($t)])) continue;
        $known[$norm($t)] = 1; $out[] = [$t, $d, 'Buch'];
    }
    return $out;
}

// Open Library (Autor:innen). Kommende Bücher liefert diese Quelle kaum; dafür kommt später eine zweite.
function openlibrary_refresh(PDO $pdo, array $sub): array {
    $sid = (int) $sub['id'];
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    $summary = $sub['summary'];
    if (empty($ext['olid'])) {
        $r = http_json('https://openlibrary.org/search/authors.json?limit=10&q=' . rawurlencode($sub['name']));
        $best = null;
        foreach (($r['docs'] ?? []) as $d) {
            if (mb_strtolower($d['name'] ?? '') !== mb_strtolower($sub['name'])) continue;
            if (!$best || ($d['work_count'] ?? 0) > ($best['work_count'] ?? 0)) $best = $d;
        }
        if (!$best) return ['ok' => false, 'msg' => 'Bei Open Library nicht eindeutig gefunden.'];
        $ext['olid'] = $best['key'];
        $bits = [];
        if (!empty($best['birth_date'])) $bits[] = 'geboren ' . $best['birth_date'];
        if (!empty($best['top_work'])) $bits[] = 'bekannt für „' . $best['top_work'] . '“';
        $summary = implode(' · ', $bits);
    }
    $r = http_json('https://openlibrary.org/search.json?limit=200&fields=title,first_publish_year,language&sort=new&author=' . rawurlencode($ext['olid']));
    if (!$r) {
        $r = http_json('https://openlibrary.org/search.json?limit=200&fields=title,first_publish_year,language&sort=new&q=author_key:' . rawurlencode($ext['olid']));
    }
    if (!$r) return ['ok' => false, 'msg' => 'Open Library nicht erreichbar.'];
    $seen = []; $works = []; $rel = []; $y = (int) date('Y');
    foreach (($r['docs'] ?? []) as $d) {
        $t = trim((string) ($d['title'] ?? '')); if ($t === '') continue;
        $lg = (array) ($d['language'] ?? []); if ($lg && !array_intersect($lg, ['ger', 'mul'])) continue; // nur Werke mit deutscher Ausgabe (ohne Sprachangabe bleibt es drin)
        $k = mb_strtolower($t); if (isset($seen[$k])) continue; $seen[$k] = 1;
        $yr = (int) ($d['first_publish_year'] ?? 0);
        if ($yr > $y) $rel[] = [$t, (string) $yr, 'Buch'];
        else $works[] = [$t, $yr ?: null, 'Buch'];
    }
    foreach (googlebooks_upcoming($sub['name'], $seen) as $x) $rel[] = $x; // angekündigte Bücher mit Datum
    usort($works, fn($a, $b) => ($b[1] ?? 0) <=> ($a[1] ?? 0));
    // Neu bei der Quelle aufgetauchte Bücher (letzte 4 Wochen) wie Ankündigungen behandeln; beim ersten Abruf wird nur der Bestand gemerkt.
    try {
        $have = $pdo->prepare('SELECT k,first_seen FROM work_seen WHERE subject_id=?'); $have->execute([$sid]); $H = $have->fetchAll(PDO::FETCH_KEY_PAIR); $first = !$H;
        $insS = $pdo->prepare('INSERT IGNORE INTO work_seen (subject_id,k,first_seen) VALUES (?,?,?)');
        $today = date('Y-m-d'); $limit = date('Y-m-d', strtotime('-28 days')); $nNew = 0; $relT = []; foreach ($rel as $x) $relT[mb_strtolower($x[0])] = 1;
        foreach ($works as $x) {
            $k = sha1(mb_strtolower($x[0]));
            if (!isset($H[$k])) { $H[$k] = $first ? '2000-01-01' : $today; $insS->execute([$sid, $k, $H[$k]]); }
            if ($H[$k] >= $limit && $nNew < 10 && !isset($relT[mb_strtolower($x[0])])) { $nNew++; $rel[] = [$x[0], (string) ($x[1] ?: $y), 'Buch', 'neu hinzugefügt']; }
        }
    } catch (Throwable $e) {}
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext), $summary, $sub['genres'] ?: '[]', (int) ($r['numFound'] ?? count($works)), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $w = $pdo->prepare('INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($works, 0, 200) as $x) $w->execute([$sid, mb_substr($x[0], 0, 255), $x[1], $x[2], null]);
        $q = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($rel, 0, 30) as $x) $q->execute([$sid, mb_substr($x[0], 0, 255), $x[1], $x[2], $x[3] ?? null]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
    return ['ok' => true, 'msg' => count($works) . ' Bücher, ' . count($rel) . ' angekündigt'];
}
