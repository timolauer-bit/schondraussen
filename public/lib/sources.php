<?php
// Datenquellen. Bisher angebunden: TVmaze (Serien). Weitere folgen (MusicBrainz, Open Library, TMDB).
// Fehlerprotokoll der Datenquellen: nur Komplettfehler (nicht erreichbar, Limit/Kontingent, Zugang abgelehnt, Dienst-Fehler), keine Einzelfälle wie „nicht gefunden“.
function api_note(string $url, $code, $raw, string $cerr = ''): void {
    $code = (int) $code; $kind = null;
    if ($raw === false || $code === 0) $kind = 'nicht erreichbar';
    elseif (in_array($code, [402, 429, 456], true)) $kind = 'Limit oder Kontingent erreicht';
    elseif (in_array($code, [401, 403], true)) $kind = 'Zugang abgelehnt oder gesperrt';
    elseif ($code >= 500) $kind = 'Fehler beim Dienst';
    if ($kind === null || !function_exists('db')) return;
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $map = ['itunes.apple.com' => 'Apple Podcasts', 'musicbrainz.org' => 'MusicBrainz', 'listenbrainz.org' => 'ListenBrainz', 'tvmaze.com' => 'TVmaze', 'openlibrary.org' => 'Open Library', 'googleapis.com' => 'Google Books', 'deepl.com' => 'DeepL', 'dnb.de' => 'Deutsche Nationalbibliothek', 'lobid.org' => 'lobid.org', 'wikipedia.org' => 'Wikipedia', 'wikidata.org' => 'Wikidata', 'themoviedb.org' => 'TMDB', 'tmdb.org' => 'TMDB', 'news.google.com' => 'Google News'];
    $src = $host; foreach ($map as $d => $n) if ($host === $d || substr($host, -strlen($d) - 1) === '.' . $d) { $src = $n; break; }
    $msg = $raw === false ? mb_substr($cerr, 0, 160) : '';
    if ($raw !== false && $raw !== '') {
        $j = json_decode((string) $raw, true);
        $msg = is_array($j) ? (string) ($j['error']['message'] ?? $j['message'] ?? $j['detail'] ?? $j['error'] ?? '') : '';
        if (!is_string($msg) || $msg === '') $msg = trim(preg_replace('/\s+/', ' ', strip_tags((string) $raw)));
        $msg = mb_substr($msg, 0, 160);
    }
    $msg = ($code ? 'Status ' . $code : '') . ($msg !== '' ? ($code ? ': ' : '') . $msg : '');
    try {
        db()->prepare('INSERT INTO api_errors (source,kind,http_code,message,cnt,first_at,last_at) VALUES (?,?,?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE first_at=IF(last_at < NOW() - INTERVAL 1 DAY, NOW(), first_at), cnt=IF(last_at < NOW() - INTERVAL 1 DAY, 1, cnt + 1), http_code=VALUES(http_code), message=VALUES(message), last_at=NOW()')
            ->execute([$src, $kind, $code, $msg]);
    } catch (Throwable $e) {}
}

function http_json(string $url, int $timeout = 12): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($r === false || $code >= 400) { api_note($url, $code, $r, $cerr); return null; }
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
    $r = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($r === false || $code >= 400) { api_note('https://' . $host . '/v2/translate', $code, $r, $cerr); return null; }
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
    try { // Grund des letzten Fehlschlags merken (wird bei leeren Einträgen angezeigt), bei Erfolg entfernen
        $q = $pdo->prepare('SELECT external_ids FROM subjects WHERE id=?'); $q->execute([$sid]); $x = json_decode((string) $q->fetchColumn(), true);
        if (is_array($x) || empty($r['ok'])) {
            $x = is_array($x) ? $x : [];
            if (empty($r['ok'])) $x['err'] = mb_substr((string) ($r['msg'] ?? ''), 0, 300); else unset($x['err']);
            $pdo->prepare('UPDATE subjects SET external_ids=? WHERE id=?')->execute([json_encode($x, JSON_UNESCAPED_UNICODE), $sid]);
        }
    } catch (Throwable $e) {}
    return $r;
}
function refresh_subject_run(PDO $pdo, int $sid): array {
    $s = $pdo->prepare('SELECT * FROM subjects WHERE id=?'); $s->execute([$sid]); $sub = $s->fetch();
    if (!$sub) return ['ok' => false, 'msg' => 'Titel nicht gefunden'];
    if ($sub['type'] === 'artist') return musicbrainz_refresh($pdo, $sub);
    if ($sub['type'] === 'author') return openlibrary_refresh($pdo, $sub);
    if ($sub['type'] === 'podcast') return podcast_refresh($pdo, $sub);
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
        $epRows[] = [$n, (int) ($e['number'] ?? 0), mb_substr((string) ($e['name'] ?? ''), 0, 255), (string) ($e['airdate'] ?? ''), clean_text((string) ($e['summary'] ?? ''), 700)];
        $seasons[$n] = $seasons[$n] ?? ['n' => 0, 'first' => null];
        $seasons[$n]['n']++;
        $d = $e['airdate'] ?? '';
        if ($d && (!$seasons[$n]['first'] || $d < $seasons[$n]['first'])) $seasons[$n]['first'] = $d;
        if ($d && $d >= $today) $rel[] = [sprintf('S%02dE%02d %s', $n, (int) ($e['number'] ?? 0), $e['name'] ?? ''), $d, 'Folge'];
    }
    $pdo->beginTransaction();
    try {
        $ext['tvmaze'] = (int) $show['id']; $ext['tv_status'] = (string) ($show['status'] ?? ''); $ext['lang'] = (string) ($show['language'] ?? '');
        $genres = $show['genres'] ?? [];
        $sumTxt = clean_text($show['summary'] ?? '', 600);
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
        $old = [];
        try { $oq = $pdo->prepare('SELECT season,number,summary,summary_de FROM episodes WHERE subject_id=?'); $oq->execute([$sid]); foreach ($oq->fetchAll() as $o) $old[$o['season'] . '-' . $o['number']] = $o; } catch (Throwable $t) { $old = null; }
        $pdo->prepare('DELETE FROM episodes WHERE subject_id=?')->execute([$sid]);
        if ($old !== null) {
            $q = $pdo->prepare('INSERT INTO episodes (subject_id,season,number,title,airdate,summary,summary_de) VALUES (?,?,?,?,?,?,?)');
            foreach (array_slice($epRows, 0, 1500) as $x) {
                $o = $old[$x[0] . '-' . $x[1]] ?? null;
                $de = ($o && $x[4] !== '' && (string) $o['summary'] === $x[4]) ? $o['summary_de'] : null; // Übersetzung behalten, wenn Text gleich
                $q->execute([$sid, $x[0], $x[1], $x[2] ?: null, $x[3] ?: null, $x[4] ?: null, $de]);
            }
        } else {
            $q = $pdo->prepare('INSERT INTO episodes (subject_id,season,number,title,airdate) VALUES (?,?,?,?,?)');
            foreach (array_slice($epRows, 0, 1500) as $x) $q->execute([$sid, $x[0], $x[1], $x[2] ?: null, $x[3] ?: null]);
        }
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
    return wiki_intro_from($pages, $qid);
}
// Gemeinsamer Teil: Seiten (de/en) und/oder Wikidata-Nummer -> Kurztext.
function wiki_intro_from(array $pages, ?string $qid): ?string {
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
    $key = trim((string) cfg('google_books_key', ''));
    $r = http_json('https://www.googleapis.com/books/v1/volumes?maxResults=40&orderBy=newest&printType=books' . ($key !== '' ? '&key=' . rawurlencode($key) : '') . '&q=' . rawurlencode('inauthor:"' . str_replace('"', '', $name) . '"'), 8);
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

// Deutsche Nationalbibliothek über lobid.org (freie Schnittstelle, deutsche Titel auch mit angekündigtem Erscheinungsjahr).
function lobid_strings($v, array $skip, string $key = ''): array { // alle Textwerte eines Katalogeintrags, ohne Titel- und Inhaltsfelder
    if (in_array($key, $skip, true)) return [];
    if (is_string($v)) return [$v];
    $o = []; if (is_array($v)) foreach ($v as $k => $x) $o = array_merge($o, lobid_strings($x, $skip, is_string($k) ? $k : $key));
    return $o;
}
// Deutsche Nationalbibliothek (SRU, MARC21): nummerierte Bände einer Reihe, auch angekündigte (Erscheinungsjahr in der Zukunft).
function dnb_sru(string $query, int $start): array {
    $url = 'https://services.dnb.de/sru/dnb?version=1.1&operation=searchRetrieve&recordSchema=MARC21-xml&maximumRecords=100&startRecord=' . $start . '&query=' . rawurlencode($query);
    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)']);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($raw === false || $code >= 400) { api_note($url, $code, $raw, $cerr); return ['err' => $raw === false ? 'Verbindungsfehler: ' . $cerr : 'Status ' . $code]; }
    $dom = new DOMDocument(); $prev = libxml_use_internal_errors(true); $ok = $dom->loadXML($raw); libxml_clear_errors(); libxml_use_internal_errors($prev);
    if (!$ok) return ['err' => 'Antwort nicht lesbar'];
    $xp = new DOMXPath($dom);
    $total = (int) $xp->evaluate("string(//*[local-name()='numberOfRecords'])");
    $diag = trim((string) $xp->evaluate("string((//*[local-name()='diagnostic']//*[local-name()='message'])[1])")) ?: trim((string) $xp->evaluate("string((//*[local-name()='diagnostic']//*[local-name()='details'])[1])"));
    $recs = [];
    foreach ($xp->query("//*[local-name()='record'][*[local-name()='datafield']]") as $r) {
        $sf = function (string $tag, string $c) use ($xp, $r): array {
            $o = []; foreach ($xp->query("./*[local-name()='datafield'][@tag='" . $tag . "']/*[local-name()='subfield'][@code='" . $c . "']", $r) as $n) $o[] = trim($n->textContent); return $o;
        };
        $recs[] = [
            'a' => $sf('245', 'a')[0] ?? '', 'b' => $sf('245', 'b')[0] ?? '', 'n' => $sf('245', 'n')[0] ?? '', 'p' => $sf('245', 'p')[0] ?? '',
            'year' => ($sf('264', 'c') ?: $sf('260', 'c'))[0] ?? '',
            'ser' => array_merge($sf('490', 'a'), $sf('830', 'a')), 'serv' => array_merge($sf('490', 'v'), $sf('830', 'v')),
            'lang' => $sf('041', 'a'), 'aut' => $sf('100', 'a'), 'med' => $sf('245', 'h'),
        ];
    }
    return ['total' => $total, 'diag' => $diag, 'recs' => $recs];
}

// Bücher einer Person aus dem DNB-Katalog: deutsche Ausgaben mit deutschem Titel (Hauptverfasser:in, ohne Tonträger), je Titel das älteste Jahr.
function dnb_name_key(string $s): string {
    $s = mb_strtolower(preg_replace('/\s*[\(\[].*?[\)\]]/u', '', $s)); $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ó' => 'o', 'ñ' => 'n', 'ç' => 'c']);
    $t = preg_split('/[^a-z]+/', $s, -1, PREG_SPLIT_NO_EMPTY); sort($t); return implode(' ', $t);
}
function dnb_author_works(string $name): array {
    $clean = fn(string $s): string => trim(preg_replace('/\s+/u', ' ', trim($s, " \t\n\r\0\x0B/:;,=-–")));
    $parts = preg_split('/\s+/u', trim(str_replace('"', '', $name))); if (count($parts) < 2) return [[], [], 'Name besteht nur aus einem Wort'];
    $last = array_pop($parts); $first = implode(' ', $parts); $want = dnb_name_key($name);
    $recs = []; $total = 0; $info = ''; $t0 = time();
    foreach (['per="' . $last . ', ' . $first . '" and spr=ger', 'per="' . $first . ' ' . $last . '" and spr=ger'] as $query) {
        $recs = []; $total = 0;
        for ($st = 1; $st <= 501; $st += 100) { // höchstens 600 Katalogeinträge je Abruf
            $r = dnb_sru($query, $st);
            if (isset($r['err'])) { $info = $r['err']; break; }
            if ($r['diag'] !== '' && !$r['recs']) { $info = 'Fehlermeldung: ' . mb_substr($r['diag'], 0, 120); break; }
            $total = $r['total']; foreach ($r['recs'] as $x) $recs[] = $x;
            if (count($r['recs']) < 100 || count($recs) >= $total || time() - $t0 > 45) break;
        }
        if ($recs) break;
    }
    if (!$recs) return [[], [], 'DNB: ' . ($info !== '' ? $info : 'keine Treffer')];
    $by = []; $nOther = 0; $nMed = 0; $nNoYear = 0; $y = (int) date('Y');
    foreach ($recs as $x) {
        $ok = false; foreach ($x['aut'] as $a) if (dnb_name_key($a) === $want) { $ok = true; break; }
        if (!$ok) { $nOther++; continue; } // andere Hauptverfasser:in (z. B. Bücher über die Person)
        if ($x['med'] && preg_match('/h[öo]rbuch|tontr|cd|dvd|film|video|elektron/iu', implode(' ', $x['med']))) { $nMed++; continue; }
        if ($x['lang'] && !array_intersect(array_map('strtolower', $x['lang']), ['ger', 'mul', 'und'])) continue;
        $t = $clean($x['a']); if ($t === '' || mb_strlen($t) > 200 || preg_match('/^(hörbuch|kalender)/iu', $t)) continue;
        if (!preg_match('/\d{4}/', $x['year'], $m)) { $nNoYear++; continue; }
        $yr = (int) $m[0]; $k = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($t));
        if (!isset($by[$k]) || $yr < $by[$k][1]) $by[$k] = [$t, $yr];
    }
    $works = []; $rel = [];
    foreach ($by as $v) { if ($v[1] > $y) $rel[] = [$v[0], (string) $v[1], 'Buch']; else $works[] = [$v[0], $v[1], 'Buch']; }
    usort($works, fn($a, $b) => ($b[1] ?? 0) <=> ($a[1] ?? 0));
    return [$works, $rel, 'DNB: ' . count($recs) . ' von ' . $total . ' Einträgen gelesen, ' . $nOther . ' mit anderer Hauptverfasserschaft, ' . $nMed . ' Tonträger/Medien, ' . $nNoYear . ' ohne Jahr, ' . count($by) . ' Titel']; 
}

function dnb_series(string $title): array {
    $clean = fn(string $s): string => trim(preg_replace('/\s+/u', ' ', trim($s, " \t\n\r\0\x0B/:;,=-–")));
    $q = str_replace('"', '', $title); $ser = preg_quote($title, '/');
    $startRe = '/^\W*' . $ser . '(?![\p{L}\p{N}])/iu';
    $volRe = '/^\W*' . $ser . '\s*,?\s*(?:Band|Bd\.?|Vol\.?|Nr\.?)?\s*(\d{1,4})(?!\d)\s*[:\-–.]?\s*(.*)$/isu';
    $recs = []; $total = 0; $info = '';
    foreach (['tit="' . $q . '" and spr=ger', 'tit="' . $q . '"'] as $qi => $query) { // zweiter Versuch ohne Sprachindex, falls der erste nichts oder einen Fehler liefert
        $recs = []; $total = 0; $info = '';
        for ($st = 1; $st <= 901; $st += 100) {
            $r = dnb_sru($query, $st);
            if (isset($r['err'])) { $info = $r['err']; break; }
            if ($r['diag'] !== '' && !$r['recs']) { $info = 'Fehlermeldung: ' . mb_substr($r['diag'], 0, 120); break; }
            $total = $r['total']; foreach ($r['recs'] as $x) $recs[] = $x;
            if (count($r['recs']) < 100 || count($recs) >= $total) break;
        }
        if ($recs) break;
    }
    if (!$recs) return [[], [], 'DNB-SRU: ' . ($info !== '' ? $info : 'keine Treffer')];
    $byNum = []; $nOther = 0; $nDate = 0; $nLang = 0; $y = (int) date('Y');
    foreach ($recs as $x) {
        if ($x['lang'] && !array_intersect(array_map('strtolower', $x['lang']), ['ger', 'jpn', 'mul', 'und'])) { $nLang++; continue; }
        $a = $clean($x['a']); $n = $clean($x['n']); $p = $clean($x['p']); $b = $clean($x['b']);
        $num = 0; $rest = '';
        if (preg_match($volRe, trim($a . ' ' . $n), $mm)) { $num = (int) $mm[1]; $rest = $clean($mm[2]); if ($p !== '') $rest = $rest !== '' ? $rest . ': ' . $p : $p; elseif ($b !== '' && $rest === '') $rest = $b; }
        else { // Band mit eigenem Titel, Reihenname und Zählung stehen in der Reihenangabe
            foreach ($x['ser'] as $i => $s) {
                if (preg_match($startRe, trim($s))) {
                    $v = (string) ($x['serv'][$i] ?? ''); if (!preg_match('/(\d{1,4})/', $v . ' ' . $s, $m2)) continue;
                    $num = (int) $m2[1]; $rest = $clean($a . ($n !== '' ? ' ' . $n : '') . ($p !== '' ? ': ' . $p : '')); break;
                }
            }
        }
        if ($num <= 0) { $nOther++; continue; }
        $rest = $clean(preg_replace(['/[\s,;:.\-–]*\b' . $num . '\s*$/u', '/^' . $num . '\s*[:\-–.,]?\s*/u'], '', $rest)); // angehängte oder vorangestellte Bandnummer im Titelrest entfernen
        if ($rest === (string) $num) $rest = '';
        if (!preg_match('/\d{4}/', $x['year'], $my)) { $nDate++; continue; }
        $yr = (int) $my[0];
        if (!isset($byNum[$num])) $byNum[$num] = ['y' => $yr, 'rest' => $rest];
        else { $byNum[$num]['y'] = min($byNum[$num]['y'], $yr); if ($byNum[$num]['rest'] === '') $byNum[$num]['rest'] = $rest; } // Druck- und E-Book-Ausgaben, Neuauflagen: ältestes Jahr zählt
    }
    $cnt = []; foreach ($byNum as $v) if ($v['rest'] !== '') { $kk = mb_strtolower($v['rest']); $cnt[$kk] = ($cnt[$kk] ?? 0) + 1; }
    foreach ($byNum as $num => $v) if ($v['rest'] !== '' && ($cnt[mb_strtolower($v['rest'])] ?? 0) >= 3) $byNum[$num]['rest'] = ''; // gleicher Untertitel bei vielen Bänden ist ein Werbespruch, kein Bandtitel
    $works = []; $rel = [];
    foreach ($byNum as $num => $v) {
        $name = mb_substr($title . ', Band ' . $num . ($v['rest'] !== '' ? ': ' . $v['rest'] : ''), 0, 255);
        if ($v['y'] > $y) $rel[] = [$name, (string) $v['y'], 'Buch']; else $works[] = [$name, $v['y'], 'Band'];
    }
    return [$works, $rel, 'DNB-SRU: ' . $total . ' Treffer, ' . count($recs) . ' geprüft, ' . count($byNum) . ' nummerierte Bände, ' . $nOther . ' ohne Bandnummer oder Reihenbezug, ' . $nLang . ' andere Sprache, ' . $nDate . ' ohne Jahr'];
}

function lobid_fetch(string $q, int $from, string $sort = ''): ?array {
    $url = 'https://lobid.org/resources/search?format=json&size=100&from=' . $from . ($sort !== '' ? '&sort=' . $sort : '') . '&q=' . rawurlencode($q);
    $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($raw === false || $code >= 400) api_note($url, $code, $raw, $cerr);
    $j = ($raw !== false && $code < 400) ? json_decode($raw, true) : null;
    return is_array($j) ? $j : (['_err' => $code ?: 0]);
}

function lobid_series(string $title): array {
    $works = []; $rel = []; $seen = []; $seenId = []; $n = 0; $nLang = 0; $nTitle = 0; $nDate = 0; $nPart = 0; $nType = 0; $total = 0; $y = (int) date('Y');
    $startRe = '/^\W*' . preg_quote($title, '/') . '(?![\p{L}\p{N}])/iu'; // Text muss mit dem Reihennamen beginnen (nicht „…Psychologie von One Piece…“)
    $skip = ['title', 'otherTitleInformation', 'subject', 'abstract', 'description', 'note', 'tableOfContents', 'hasItem', 'contribution', 'publication', 'language', 'medium', 'id', 'type', 'isbn', 'wikidataLink'];
    $members = []; $parents = [];
    $q0 = '"' . str_replace('"', '', $title) . '"';
    for ($from = 0; $from < 300; $from += 100) {
        $j = lobid_fetch($q0, $from, 'newest');
        if (isset($j['_err'])) { if ($from === 0) return [[], [], 'DNB/lobid nicht erreichbar' . ($j['_err'] ? ' (Status ' . $j['_err'] . ')' : '')]; break; }
        $total = (int) ($j['totalItems'] ?? $total); $mem = (array) ($j['member'] ?? []); if (!$mem) break;
        foreach ($mem as $m) {
            $members[] = $m;
            $tp = (array) ($m['type'] ?? []);
            if (in_array('MultiVolumeBook', $tp, true) && preg_match($startRe, trim((string) ($m['title'] ?? ''))) && !empty($m['id'])) $parents[preg_replace('/#!$/', '', (string) $m['id'])] = 1; // Gesamtaufnahme der mehrbändigen Reihe
        }
        if (count($mem) < 100) break;
    }
    $nParent = count($parents);
    foreach (array_slice(array_keys($parents), 0, 3) as $pid) { // Bände über den Verweis auf die Gesamtaufnahme einsammeln
        for ($from = 0; $from < 300; $from += 100) {
            $j = lobid_fetch('isPartOf.hasSuperordinate.id:"' . $pid . '"', $from);
            if (isset($j['_err'])) break; $mem = (array) ($j['member'] ?? []); if (!$mem) break;
            foreach ($mem as $m) $members[] = $m;
            if (count($mem) < 100) break;
        }
    }
    foreach ($members as $m) {
        $n++;
        $mid = (string) ($m['id'] ?? ''); if ($mid !== '') { if (isset($seenId[$mid])) { $n--; continue; } $seenId[$mid] = 1; }
        $t = trim((string) ($m['title'] ?? '')); if ($t === '') continue;
        $tp = (array) ($m['type'] ?? []);
        $med = array_map(fn($x) => (string) ($x['label'] ?? ''), (array) ($m['medium'] ?? []));
        if (array_intersect($tp, ['Series', 'MultiVolumeBook', 'Periodical', 'Journal', 'Newspaper']) || array_intersect($med, ['Video', 'Audio-Visuell', 'Audio'])) { $nType++; continue; } // Gesamtaufnahmen, Zeitschriften, DVDs
        $langs = array_map(fn($l) => substr((string) ($l['id'] ?? ''), -3), (array) ($m['language'] ?? []));
        if ($langs && !array_intersect($langs, ['ger', 'jpn', 'mul', 'und', 'zxx'])) { $nLang++; continue; } // Übersetzungen führen als Sprache oft das Original (jpn)
        $oti = (array) ($m['otherTitleInformation'] ?? []); $sub = trim((string) ($oti[0] ?? ''));
        $full = $t . ($sub !== '' && mb_stripos($sub, $t) === false ? ': ' . $sub : '');
        $num = '';
        $hit = null; $pn = '';
        foreach ((array) ($m['isPartOf'] ?? []) as $ip) { // Reihenzugehörigkeit mit Bandzählung
            foreach ((array) ($ip['hasSuperordinate'] ?? []) as $hs) {
                if (preg_match($startRe, trim((string) ($hs['label'] ?? '')))) { $hit = (string) $hs['label']; $pn = trim((string) ($ip['numbering'] ?? '')); break 2; }
            }
        }
        if ($hit !== null) {
            $nPart++;
            if (preg_match('/(\d{1,4})/', $pn, $mm)) $num = $mm[1];
            if (!preg_match($startRe, $full)) $full = $title . ($num !== '' ? ', Band ' . $num : '') . ': ' . $full;
            elseif ($num !== '' && !preg_match('/\d\s*$/', $full)) $full .= ', Band ' . $num;
        } elseif (!preg_match($startRe, $full)) { // Reihenname steht in irgendeiner anderen Angabe, der Band hat einen eigenen Titel
            foreach (lobid_strings($m, $skip) as $s) { if (preg_match($startRe, trim($s))) { $hit = $s; break; } }
            if ($hit === null) { $nTitle++; continue; }
            $nPart++;
            if (preg_match('/[;,]\s*(?:Band\s*)?(\d{1,4})\s*$/iu', trim($hit), $mm)) $num = $mm[1];
            $full = $title . ($num !== '' ? ', Band ' . $num : '') . ': ' . $full;
        } else { $nTitle++; continue; } // Titel beginnt nur zufällig mit dem Reihennamen (Sachbuch, Rezeptbuch, Roman): keine Reihenzugehörigkeit im Katalog, also kein Band
        $yr = 0; foreach ((array) ($m['publication'] ?? []) as $pb) { if (preg_match('/\d{4}/', (string) ($pb['startDate'] ?? ''), $mm)) { $yr = (int) $mm[0]; break; } }
        if (!$yr) { $nDate++; continue; }
        $k = mb_strtolower($full); if (isset($seen[$k])) continue; $seen[$k] = 1;
        if ($yr > $y) $rel[] = [mb_substr($full, 0, 255), (string) $yr, 'Buch']; else $works[] = [mb_substr($full, 0, 255), $yr, 'Band'];
    }
    return [$works, $rel, 'DNB: ' . $total . ' Treffer, ' . $n . ' geprüft, ' . $nParent . ' Gesamtaufnahme(n), ' . $nType . ' Reihe/DVD/Zeitschrift, ' . $nLang . ' andere Sprache, ' . $nTitle . ' ohne Reihenbezug, ' . $nDate . ' ohne Jahr, ' . $nPart . ' über Reihenzugehörigkeit'];
}

// Buchreihe über den Titel (z. B. Manga): Google Books, nur deutsche Ausgaben, deren Titel den Reihennamen enthält.
function bookseries_refresh(PDO $pdo, array $sub, array $ext): array {
    $sid = (int) $sub['id']; $title = trim((string) $ext['gbs']);
    $works = []; $rel = []; $seen = []; $got = false; $today = date('Y-m-d');
    $key = trim((string) cfg('google_books_key', '')); $lastErr = ''; $nItems = 0; $nLang = 0; $nTitle = 0; $langs = []; $sample = [];
    [$works, $rel, $ldInfo] = dnb_series($title); // zuerst der Katalog der Deutschen Nationalbibliothek (SRU)
    if (!$works && !$rel) { [$works, $rel, $ld2] = lobid_series($title); $ldInfo .= ' | ' . $ld2; } // dann lobid.org, zuletzt Google Books
    if (!$works && !$rel) {
    for ($p = 0; $p < 2; $p++) {
        $items = null;
        foreach ([['intitle:"' . str_replace('"', '', $title) . '"', '&orderBy=newest&langRestrict=de'], ['"' . str_replace('"', '', $title) . '"', '&langRestrict=de'], ['intitle:"' . str_replace('"', '', $title) . '"', '']] as [$qq, $extra]) { // mehrere Versuche, der erste mit Treffern zählt (Filter erfolgt ohnehin im Code)
            $url = 'https://www.googleapis.com/books/v1/volumes?maxResults=40&startIndex=' . ($p * 40) . '&printType=books' . $extra . ($key !== '' ? '&key=' . rawurlencode($key) : '') . '&q=' . rawurlencode($qq);
            $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)']);
            $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
            if ($raw !== false && $code < 400 && is_array($j = json_decode($raw, true))) { $got = true; $items = $j['items'] ?? []; if ($items) break; continue; }
            api_note($url, $code, $raw, $cerr);
            $lastErr = $raw === false ? ('Verbindungsfehler: ' . $cerr) : ('Status ' . $code . (($m = json_decode((string) $raw, true)['error']['message'] ?? '') ? ', ' . mb_substr($m, 0, 120) : ''));
        }
        if ($items === null) { if ($p === 0) return ['ok' => false, 'msg' => 'Google Books nicht erreichbar (' . $lastErr . ').']; break; }
        if (!$items) break;
        foreach ($items as $it) {
            $nItems++;
            $v = $it['volumeInfo'] ?? []; $t = trim((string) ($v['title'] ?? '')); $d = (string) ($v['publishedDate'] ?? '');
            if ($t === '') continue;
            if (!in_array($v['language'] ?? 'de', ['de'], true)) { $nLang++; $lg = (string) ($v['language'] ?? '?'); $langs[$lg] = ($langs[$lg] ?? 0) + 1; if (count($sample) < 3) $sample[] = '„' . mb_substr($t, 0, 40) . '“ (' . $lg . ')'; continue; }
            if (mb_stripos($t, $title) === false) { $nTitle++; continue; }
            $full = $t . (!empty($v['subtitle']) ? ': ' . $v['subtitle'] : '');
            $k = mb_strtolower($full); if (isset($seen[$k])) continue; $seen[$k] = 1;
            if (!preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $d)) { $works[] = [$full, null, 'Band']; continue; }
            if (date_is_future($d)) $rel[] = [$full, $d, 'Buch']; else $works[] = [$full, (int) substr($d, 0, 4), 'Band'];
        }
        if (count($items) < 40) break;
    }
    }
    if (!$works && !$rel) return ['ok' => false, 'msg' => 'Keine deutschen Bände gefunden (' . $ldInfo . '; Google Books lieferte ' . $nItems . ' Treffer, davon ' . $nLang . ' in anderer Sprache und ' . $nTitle . ' ohne den Reihentitel im Namen; Sprachen: ' . ($langs ? implode(', ', array_map(fn($k, $n) => $k . ' ' . $n, array_keys($langs), $langs)) : 'keine') . '; Beispiele: ' . ($sample ? implode(', ', $sample) : 'keine') . '). Versuche einen anderen oder genaueren Titel.'];
    if (!$got && !$works && !$rel) return ['ok' => false, 'msg' => 'Weder DNB noch Google Books erreichbar (' . $ldInfo . '; ' . $lastErr . ').'];
    $num = function ($t) { return preg_match('/Band\s+(\d+)/', $t, $m) || preg_match('/(\d+)\s*$/', $t, $m) ? (int) $m[1] : 0; };
    usort($works, fn($a, $b) => $num($b[0]) <=> $num($a[0]) ?: strcmp($a[0], $b[0]));
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext, JSON_UNESCAPED_UNICODE), 'Reihe „' . $title . '“ · Bände nach Titel aus dem Katalog der Deutschen Nationalbibliothek (nummerierte deutsche Ausgaben, auch angekündigte). Abfrage: ' . ($ldInfo ?? ''), '[]', count($works), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $w = $pdo->prepare('INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($works, 0, 200) as $x) $w->execute([$sid, mb_substr($x[0], 0, 255), $x[1], $x[2], null]);
        $q = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($rel, 0, 30) as $x) $q->execute([$sid, mb_substr($x[0], 0, 255), $x[1], $x[2], null]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
    return ['ok' => true, 'msg' => count($works) . ' Bände, ' . count($rel) . ' angekündigt'];
}

// Genres für Autor:innen: zuerst das Genre (P136) aus Wikidata auf Deutsch, ersatzweise häufige Themen der Bücher bei Open Library.
function author_genres(string $qid, string $olid): array {
    $g = [];
    if (preg_match('/^Q\d+$/', $qid)) {
        $j = http_json('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=claims&ids=' . $qid, 8);
        $ids = [];
        foreach (($j['entities'][$qid]['claims']['P136'] ?? []) as $c) { $v = $c['mainsnak']['datavalue']['value']['id'] ?? null; if (is_string($v) && preg_match('/^Q\d+$/', $v)) $ids[] = $v; }
        $ids = array_slice(array_values(array_unique($ids)), 0, 6);
        if ($ids) {
            $l = http_json('https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages=de|en&ids=' . implode('|', $ids), 8);
            foreach ($ids as $id) { $lab = trim((string) ($l['entities'][$id]['labels']['de']['value'] ?? ($l['entities'][$id]['labels']['en']['value'] ?? ''))); if ($lab !== '') $g[] = mb_strtoupper(mb_substr($lab, 0, 1)) . mb_substr($lab, 1); }
        }
    }
    if (!$g && $olid !== '') {
        $r = http_json('https://openlibrary.org/search.json?limit=60&fields=subject&author=' . rawurlencode($olid), 10);
        $cnt = []; $bad = '/(large type|large print|accessible|protected daisy|lending|in library|new york times|nyt |reading level|open library|overdrive|bestsell|translations?|^[a-z]{1,2}$|\d{3,})/i';
        $de = ['fiction' => 'Belletristik', 'science fiction' => 'Science-Fiction', 'fantasy' => 'Fantasy', 'horror' => 'Horror', 'mystery' => 'Krimi', 'thriller' => 'Thriller', 'suspense' => 'Thriller', 'romance' => 'Liebesroman', 'historical fiction' => 'Historischer Roman', 'biography' => 'Biografie', 'juvenile fiction' => 'Kinder- und Jugendbuch', "children's fiction" => 'Kinder- und Jugendbuch', 'young adult fiction' => 'Jugendbuch', 'humor' => 'Humor', 'poetry' => 'Lyrik', 'comics & graphic novels' => 'Comics', 'manga' => 'Manga', 'crime' => 'Krimi', 'detective and mystery stories' => 'Krimi', 'short stories' => 'Kurzgeschichten', 'american fiction' => 'Belletristik', 'english fiction' => 'Belletristik'];
        foreach (($r['docs'] ?? []) as $d) foreach (array_slice((array) ($d['subject'] ?? []), 0, 12) as $sj) {
            $sj = trim((string) $sj); if ($sj === '' || mb_strlen($sj) > 40 || preg_match($bad, $sj)) continue;
            $k = mb_strtolower($sj); $lab = $de[$k] ?? mb_strtoupper(mb_substr($sj, 0, 1)) . mb_substr($sj, 1); $cnt[$lab] = ($cnt[$lab] ?? 0) + 1;
        }
        arsort($cnt); foreach ($cnt as $lab => $n) { if ($n < 3) break; $g[] = $lab; if (count($g) >= 4) break; }
    }
    return array_slice(array_values(array_unique($g)), 0, 4);
}

// Open Library (Autor:innen). Kommende Bücher liefert diese Quelle kaum; dafür kommt später eine zweite.
function openlibrary_refresh(PDO $pdo, array $sub): array {
    $sid = (int) $sub['id'];
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    if (!empty($ext['gbs'])) return bookseries_refresh($pdo, $sub, $ext);
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
    // Kurzvorstellung aus der Wikipedia (über die Wikidata-Nummer bei Open Library); bei Misserfolg frühestens nach 30 Tagen erneut
    if (empty($ext['bits'])) $ext['bits'] = (string) $summary;
    if (!isset($ext['intro_t']) || (empty($ext['intro']) && (int) $ext['intro_t'] < time() - 30 * 86400)) {
        $ext['intro_t'] = time(); $ext['intro'] = '';
        $a = http_json('https://openlibrary.org/authors/' . rawurlencode(basename((string) $ext['olid'])) . '.json', 10);
        $qid = $a['remote_ids']['wikidata'] ?? null;
        if ($qid && preg_match('/^Q\d+$/', (string) $qid)) { $ext['qid'] = (string) $qid; $ext['intro'] = wiki_intro_from([], $qid) ?: ''; }
    }
    // Genres (Wikidata, ersatzweise Open Library); bei leerem Ergebnis frühestens nach 30 Tagen, sonst nach 90 Tagen neu
    if (!isset($ext['gen_t']) || (empty($ext['gen']) && (int) $ext['gen_t'] < time() - 30 * 86400) || (int) $ext['gen_t'] < time() - 90 * 86400) {
        $ext['gen_t'] = time();
        if (!isset($ext['qid']) && !isset($ext['qid_t'])) { $ext['qid_t'] = time(); $a2 = http_json('https://openlibrary.org/authors/' . rawurlencode(basename((string) $ext['olid'])) . '.json', 8); $q2 = $a2['remote_ids']['wikidata'] ?? null; if ($q2 && preg_match('/^Q\d+$/', (string) $q2)) $ext['qid'] = (string) $q2; }
        $ext['gen'] = author_genres((string) ($ext['qid'] ?? ''), basename((string) $ext['olid']));
    }
    if (!empty($ext['intro'])) $summary = $ext['bits'] . ' – ' . $ext['intro'];
    $r = http_json('https://openlibrary.org/search.json?limit=200&fields=title,first_publish_year,language&sort=new&author=' . rawurlencode($ext['olid']));
    if (!$r) {
        $r = http_json('https://openlibrary.org/search.json?limit=200&fields=title,first_publish_year,language&sort=new&q=author_key:' . rawurlencode($ext['olid']));
    }
    if (!$r) return ['ok' => false, 'msg' => 'Open Library nicht erreichbar.'];
    $seen = []; $works = []; $rel = []; $y = (int) date('Y');
    foreach (($r['docs'] ?? []) as $d) {
        $t = trim((string) ($d['title'] ?? '')); if ($t === '' || preg_match('/^_*(none|null|n\/a|unknown|untitled)_*$/i', $t)) continue; // Platzhaltertitel der Quelle
        $lg = (array) ($d['language'] ?? []); if ($lg && !array_intersect($lg, ['ger', 'mul'])) continue; // nur Werke mit deutscher Ausgabe
        if (looks_english_title($t)) continue; // Original-Titel in Englisch (auch ohne Sprachangabe oder mit nur einer Übersetzung): nicht anzeigen
        $k = mb_strtolower($t); if (isset($seen[$k])) continue; $seen[$k] = 1;
        $yr = (int) ($d['first_publish_year'] ?? 0);
        if ($yr > $y) $rel[] = [$t, (string) $yr, 'Buch'];
        else $works[] = [$t, $yr ?: null, 'Buch'];
    }
    // Bevorzugt der DNB-Katalog (deutsche Titel); liefert er zu wenig oder bricht ab, bleibt es bei Open Library mit Sprachfilter.
    $dn = []; try { $dn = dnb_author_works((string) $sub['name']); } catch (Throwable $e) { $dn = [[], [], 'DNB: Fehler']; }
    $ext['works_src'] = 'Open Library'; $ext['works_info'] = (string) ($dn[2] ?? '');
    if (count($dn[0] ?? []) + count($dn[1] ?? []) >= 3) { $works = $dn[0]; $rel = $dn[1]; $seen = []; foreach (array_merge($works, $rel) as $x) $seen[mb_strtolower($x[0])] = 1; $ext['works_src'] = 'DNB'; }
    foreach (googlebooks_upcoming($sub['name'], $seen) as $x) $rel[] = $x; // angekündigte Bücher mit Datum
    usort($works, fn($a, $b) => ($b[1] ?? 0) <=> ($a[1] ?? 0));
    // Neu bei der Quelle aufgetauchte Bücher (letzte 4 Wochen) wie Ankündigungen behandeln; beim ersten Abruf wird nur der Bestand gemerkt.
    try {
        $have = $pdo->prepare('SELECT k,first_seen FROM work_seen WHERE subject_id=?'); $have->execute([$sid]); $H = $have->fetchAll(PDO::FETCH_KEY_PAIR); $first = !$H;
        $insS = $pdo->prepare('INSERT IGNORE INTO work_seen (subject_id,k,first_seen) VALUES (?,?,?)');
        $today = date('Y-m-d'); $limit = date('Y-m-d', strtotime('-28 days')); $nNew = 0; $relT = []; foreach ($rel as $x) $relT[mb_strtolower($x[0])] = 1;
        foreach ($works as $x) { // nur aktuelle Jahrgänge (ab Vorjahr) gelten als neu; alte Bücher, die die Quelle nachträgt, sind keine Neuerscheinung
            $k = sha1(mb_strtolower($x[0]));
            if (!isset($H[$k])) { $H[$k] = $first ? '2000-01-01' : $today; $insS->execute([$sid, $k, $H[$k]]); }
            if ($H[$k] >= $limit && $nNew < 10 && (int) $x[1] >= $y - 1 && !isset($relT[mb_strtolower($x[0])])) { $nNew++; $rel[] = [$x[0], (string) ($x[1] ?: $y), 'Buch', 'neu hinzugefügt']; }
        }
    } catch (Throwable $e) {}
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext), $summary, !empty($ext['gen']) ? json_encode($ext['gen'], JSON_UNESCAPED_UNICODE) : ($sub['genres'] ?: '[]'), ($ext['works_src'] === 'DNB' ? count($works) : (int) ($r['numFound'] ?? count($works))), $sid]);
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


// ---------- Podcasts: Suche und Kennung über die Apple-Podcasts-Suche (iTunes Search API, ohne Schlüssel), Folgen aus dem öffentlichen RSS-Feed des Podcasts ----------
function podcast_search(string $q): array {
    $r = http_json('https://itunes.apple.com/search?media=podcast&entity=podcast&country=DE&limit=8&term=' . rawurlencode($q)) ?? [];
    $out = [];
    foreach (($r['results'] ?? []) as $x) {
        if (empty($x['collectionId']) || empty($x['collectionName'])) continue;
        $h = array_filter([(string) ($x['artistName'] ?? ''), (string) ($x['primaryGenreName'] ?? ''), !empty($x['trackCount']) ? $x['trackCount'] . ' Folgen' : '']);
        $out[] = ['name' => mb_substr((string) $x['collectionName'], 0, 190), 'hint' => implode(' · ', $h), 'ext' => ['itunes' => (int) $x['collectionId']]];
    }
    return $out;
}
// Lädt einen RSS-Feed (nur http/https, höchstens 4 MB) und gibt das XML zurück oder null.
function podcast_feed_fetch(string $url): ?SimpleXMLElement {
    if (!preg_match('~^https?://~i', $url)) return null;
    $buf = ''; $cut = false; $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'],
        CURLOPT_WRITEFUNCTION => function ($c, $d) use (&$buf, &$cut) { $buf .= $d; if (strlen($buf) > 6000000) { $cut = true; return 0; } return strlen($d); }]);
    $ok = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($cut && $buf !== '' && ($p = strrpos($buf, '</item>')) !== false) { $buf = substr($buf, 0, $p + 7) . '</channel></rss>'; $code = 200; } // sehr großer Feed: bis zur letzten vollständigen Folge verwenden (neueste stehen vorn)
    if ($code >= 400 || $code === 0 || $buf === '') { api_note($url, $code, $ok === false && $buf === '' ? false : $buf, $cerr); return null; }
    $prev = libxml_use_internal_errors(true);
    $x = @simplexml_load_string($buf, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    return $x ?: null;
}
function podcast_refresh(PDO $pdo, array $sub): array {
    $sid = (int) $sub['id']; $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    if (empty($ext['itunes'])) { // ohne Kennung zuerst über den Namen suchen
        $f = podcast_search((string) $sub['name']); if ($f) $ext['itunes'] = (int) $f[0]['ext']['itunes'];
    }
    if (empty($ext['itunes'])) return ['ok' => false, 'msg' => 'Bei Apple Podcasts nicht gefunden oder nicht erreichbar.'];
    $lk = http_json('https://itunes.apple.com/lookup?entity=podcast&country=DE&id=' . (int) $ext['itunes']);
    $info = $lk['results'][0] ?? null;
    $feed = is_array($info) && !empty($info['feedUrl']) ? (string) $info['feedUrl'] : (string) ($ext['feed'] ?? '');
    if ($feed === '') return ['ok' => false, 'msg' => 'Für diesen Podcast ist keine Feed-Adresse bekannt.'];
    $x = podcast_feed_fetch($feed);
    if (!$x || !isset($x->channel)) return ['ok' => false, 'msg' => 'Der Feed des Podcasts ist nicht erreichbar oder nicht lesbar.'];
    $ch = $x->channel; $eps = [];
    foreach ($ch->item as $it) {
        $title = trim(preg_replace('/\s+/u', ' ', (string) $it->title)); if ($title === '') continue;
        $ts = strtotime((string) $it->pubDate); if (!$ts) continue;
        $desc = trim((string) $it->description);
        if ($desc === '') { $ns = $it->children('http://www.itunes.com/dtds/podcast-1.0.dtd'); $desc = trim((string) ($ns->summary ?? '')) ?: trim((string) ($ns->subtitle ?? '')); }
        if ($desc === '') { $cn = $it->children('http://purl.org/rss/1.0/modules/content/'); $desc = trim((string) ($cn->encoded ?? '')); }
        $eps[] = [$ts, mb_substr($title, 0, 255), clean_text($desc, 600)];
    }
    usort($eps, fn($a, $b) => $b[0] <=> $a[0]);
    $eps = array_slice($eps, 0, 30);
    if (!$eps) return ['ok' => false, 'msg' => 'Im Feed wurden keine Folgen gefunden.'];
    $sum = clean_text((string) $ch->description, 400);
    $genres = []; if (is_array($info) && !empty($info['genres'])) foreach ((array) $info['genres'] as $g) if (is_string($g) && $g !== 'Podcasts' && count($genres) < 4) $genres[] = $g;
    $ext['feed'] = $feed; if (is_array($info) && !empty($info['collectionViewUrl'])) $ext['page'] = (string) $info['collectionViewUrl'];
    if (is_array($info) && !empty($info['artistName'])) $ext['by'] = mb_substr((string) $info['artistName'], 0, 120);
    $recent = time() - 14 * 86400; $tz = new DateTimeZone('Europe/Berlin');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext, JSON_UNESCAPED_UNICODE), $sum !== '' ? $sum : null, json_encode($genres, JSON_UNESCAPED_UNICODE), count($eps), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $hasSum = true; try { $pdo->query('SELECT summary FROM works LIMIT 1'); } catch (Throwable $t0) { $hasSum = false; } // Spalte kommt mit Migration 063
        $w = $pdo->prepare($hasSum ? 'INSERT INTO works (subject_id,title,year,kind,note,summary) VALUES (?,?,?,?,?,?)' : 'INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        $r = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_reverse($eps) as $e) { // älteste zuerst, damit die neueste Folge die höchste Kennung hat
            $d = (new DateTime('@' . $e[0]))->setTimezone($tz);
            $w->execute(array_merge([$sid, $e[1], (int) $d->format('Y'), 'Folge', $d->format('d.m.Y')], $hasSum ? [$e[2] !== '' ? mb_substr($e[2], 0, 700) : null] : []));
            if ($e[0] >= $recent && $e[0] <= time() + 86400) $r->execute([$sid, $e[1], $d->format('Y-m-d'), 'Folge', null]);
        }
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
    return ['ok' => true, 'msg' => count($eps) . ' Folgen'];
}

/** Grobe Erkennung englischer Titel (Open Library führt oft Originaltitel statt der deutschen Ausgabe). Lieber einen Titel zu viel zeigen als einen deutschen zu verlieren: nur bei eindeutigen Mustern. */
function looks_english_title(string $t): bool {
    $l = ' ' . mb_strtolower(preg_replace('/[^\p{L}\' ]+/u', ' ', $t)) . ' ';
    $en = preg_match_all('/ (?=(the|of|and|with|for|from|into|your|his|her|their|my|our|is|are|was|were|to|in|on|at|by|a|an) )/', $l);
    $de = preg_match_all('/ (?=(der|die|das|den|dem|des|und|mit|für|von|ein|eine|einer|eines|ist|sind|im|zum|zur|nicht|ich|wir|sie|er|es|auf|aus|nach|über|unter|ohne|wie) )/u', $l);
    if ($de > 0 || preg_match('/[äöüß]/u', $l)) return false;
    if ($en >= 2 || preg_match('/^ (the|a|an) /', $l) === 1 || preg_match("/[a-z]'(s|t|re|ve|ll) /", $l) === 1) return true;
    // Spanisch, Französisch, Italienisch, Portugiesisch, Niederländisch: Akzentbuchstaben, die im Deutschen nicht vorkommen, oder eindeutige Funktionswörter
    if (preg_match('/[éèêëàâçñíìîóòôúùûãõœ¿¡ıİşŞğĞłąęćńśźżčřšžěůýÿ]/u', $l)) return true;
    return preg_match('/ (el|los|las|del|una|por|que|les|des|du|une|pour|avec|dans|sur|est|il|gli|della|degli|che|per|os|das|dos|uma|para|com|het|een|van|voor|naar) /', $l) === 1;
}
