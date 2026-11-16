<?php
// Vorschläge aus freien Quellen: ListenBrainz/MusicBrainz (Künstler:innen), TVmaze (Serien), Open Library (Autor:innen).
require_once __DIR__ . '/sources.php';

function sg_cache_get(PDO $pdo, string $k, int $hours): ?array {
    try { $s = $pdo->prepare('SELECT data FROM suggest_cache WHERE k=? AND fetched_at > NOW() - INTERVAL ? HOUR'); $s->execute([$k, $hours]); $d = $s->fetchColumn(); }
    catch (Throwable $e) { return null; }
    return $d === false ? null : (json_decode((string) $d, true) ?: []);
}
function sg_cache_put(PDO $pdo, string $k, array $data): void {
    try { $pdo->prepare('REPLACE INTO suggest_cache (k,data,fetched_at) VALUES (?,?,NOW())')->execute([$k, json_encode($data, JSON_UNESCAPED_UNICODE)]); } catch (Throwable $e) {}
}

/** Pool bekannter Serien (TVmaze): beliebte ältere und aktuell laufende, mit Genres und Gewicht. */
function sg_series_pool(PDO $pdo): array {
    $c = sg_cache_get($pdo, 'pool:series', 72); if ($c !== null && $c) return $c;
    @set_time_limit(60); $pool = [];
    $add = function ($s) use (&$pool) {
        if (!is_array($s) || empty($s['id']) || empty($s['name'])) return;
        $id = (int) $s['id'];
        $pool[$id] = ['id' => $id, 'name' => (string) $s['name'], 'genres' => array_values($s['genres'] ?? []), 'weight' => (int) ($s['weight'] ?? 0),
            'year' => substr((string) ($s['premiered'] ?? ''), 0, 4), 'lang' => (string) ($s['language'] ?? '')];
    };
    $urls = [];
    for ($p = 0; $p < 4; $p++) $urls[] = "https://api.tvmaze.com/shows?page=$p";
    foreach (['US', 'GB', 'DE'] as $cc) $urls[] = "https://api.tvmaze.com/schedule?country=$cc&date=" . date('Y-m-d');
    foreach (sg_fetch_multi($urls, 12) as $u => $j) {
        if (strpos($u, '/schedule') !== false) { foreach ($j as $ep) $add($ep['show'] ?? ($ep['_embedded']['show'] ?? null)); }
        else foreach ($j as $s) $add($s);
    }
    uasort($pool, fn($a, $b) => $b['weight'] <=> $a['weight']);
    $pool = array_slice(array_values($pool), 0, 900);
    if ($pool) { sg_cache_put($pdo, 'pool:series', $pool); return $pool; }
    $old = sg_cache_get($pdo, 'pool:series', 24 * 365); // Quelle gerade nicht erreichbar: alten Bestand weiterverwenden
    return $old ?: [];
}

/** Mehrere JSON-Adressen gleichzeitig laden (schneller als nacheinander). Gibt [url => Liste] für erfolgreiche Antworten zurück. */
function sg_fetch_multi(array $urls, int $timeout = 12): array {
    $mh = curl_multi_init(); $hs = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        curl_multi_add_handle($mh, $ch); $hs[$u] = $ch;
    }
    do { $st = curl_multi_exec($mh, $act); if ($act) curl_multi_select($mh, 1.0); } while ($act && $st === CURLM_OK);
    $out = [];
    foreach ($hs as $u => $ch) {
        $r = curl_multi_getcontent($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($r !== false && $r !== '' && $code < 400) { $j = json_decode($r, true); if (is_array($j)) $out[$u] = $j; }
        curl_multi_remove_handle($mh, $ch); curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
}

/** Autor:innen eines Open-Library-Themas, gewichtet nach Ausgaben. */
function sg_ol_subject_authors(string $subject, string $exceptKey = ''): array {
    $slug = trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($subject)), '_'); if ($slug === '') return [];
    $j = http_json('https://openlibrary.org/subjects/' . rawurlencode($slug) . '.json?limit=60', 20) ?? [];
    $by = [];
    foreach (($j['works'] ?? []) as $w) foreach (($w['authors'] ?? []) as $a) {
        $key = (string) ($a['key'] ?? ''); $name = trim((string) ($a['name'] ?? '')); if ($key === '' || $name === '' || ($exceptKey && strpos($key, $exceptKey) !== false)) continue;
        $by[$key] ??= ['name' => $name, 'olid' => $key, 'score' => 0, 'top' => '', 'topn' => 0];
        $n = (int) ($w['edition_count'] ?? 1); $by[$key]['score'] += $n;
        if ($n > $by[$key]['topn']) { $by[$key]['topn'] = $n; $by[$key]['top'] = (string) ($w['title'] ?? ''); }
    }
    uasort($by, fn($a, $b) => $b['score'] <=> $a['score']);
    return array_values($by);
}

/** Zeile für die Ausgabe. */
function sg_item(string $name, string $note, array $ext): array { return ['name' => $name, 'note' => $note, 'ext' => $ext]; }

function sg_similar(PDO $pdo, array $sub): array {
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: []; $out = [];
    if ($sub['type'] === 'artist') {
        $mb = (string) ($ext['mbid'] ?? ''); if (!preg_match('/^[0-9a-f-]{36}$/', $mb)) return ['items' => [], 'msg' => 'Dieser Eintrag ist noch keinem Künstler in der Datenbank zugeordnet.'];
        $j = http_json('https://labs.api.listenbrainz.org/similar-artists/json?artist_mbids=' . $mb . '&algorithm=session_based_days_9000_session_300_contribution_5_threshold_15_limit_50_skip_30', 25) ?? [];
        $rows = isset($j['data']) && is_array($j['data']) ? $j['data'] : $j;
        foreach ($rows as $r) { if (!is_array($r) || empty($r['name']) || ($r['artist_mbid'] ?? '') === $mb) continue; $out[] = sg_item((string) $r['name'], (string) ($r['comment'] ?? ''), ['mbid' => (string) ($r['artist_mbid'] ?? '')]); if (count($out) >= 12) break; }
    } elseif ($sub['type'] === 'series') {
        $g = json_decode($sub['genres'] ?: '[]', true) ?: []; if (!$g) return ['items' => [], 'msg' => 'Zu dieser Serie sind noch keine Genres bekannt. Aktualisiere sie zuerst.'];
        $sc = [];
        foreach (sg_series_pool($pdo) as $s) { $sh = array_intersect($g, $s['genres']); if (!$sh || mb_strtolower($s['name']) === mb_strtolower($sub['name'])) continue; $sc[] = [count($sh) * 100 + $s['weight'], $s, $sh]; }
        usort($sc, fn($a, $b) => $b[0] <=> $a[0]);
        foreach (array_slice($sc, 0, 12) as [$x, $s, $sh]) $out[] = sg_item($s['name'], trim($s['year'] . ' · ' . implode(', ', array_slice($s['genres'], 0, 3)), ' ·'), ['tvmaze' => $s['id']]);
    } elseif ($sub['type'] === 'author') {
        if (!preg_match('/OL\d+A/', (string) ($ext['olid'] ?? ''), $m)) return ['items' => [], 'msg' => 'Dieser Eintrag ist noch keinem Autor in der Datenbank zugeordnet.'];
        $own = $m[0];
        $j = http_json("https://openlibrary.org/authors/$own/works.json?limit=40", 20) ?? [];
        $cnt = []; $skip = ['fiction', 'accessible book', 'protected daisy', 'in library', 'large type books', 'lending library', 'open library staff picks', 'reading level-grade 11', 'reading level-grade 9', 'nyt:combined-print-and-e-book-fiction', 'new york times bestseller', 'english language', 'novela', 'romans, nouvelles'];
        foreach (($j['entries'] ?? []) as $w) foreach (($w['subjects'] ?? []) as $s) { $s = trim((string) $s); $l = mb_strtolower($s); if ($s === '' || mb_strlen($s) > 32 || in_array($l, $skip, true) || strpos($l, 'nyt:') === 0 || strpos($l, 'reading level') === 0) continue; $cnt[$s] = ($cnt[$s] ?? 0) + 1; }
        arsort($cnt); $by = [];
        foreach (array_slice(array_keys($cnt), 0, 3) as $subj) foreach (sg_ol_subject_authors($subj, $own) as $a) {
            $k = $a['olid']; if (!isset($by[$k])) $by[$k] = ['a' => $a, 'score' => 0, 'subj' => $subj]; $by[$k]['score'] += $a['score'];
        }
        uasort($by, fn($a, $b) => $b['score'] <=> $a['score']);
        foreach (array_slice($by, 0, 12) as $x) $out[] = sg_item($x['a']['name'], 'Thema: ' . $x['subj'], ['olid' => $x['a']['olid']]);
    } elseif ($sub['type'] === 'film') {
        if (!tmdb_enabled()) return ['items' => [], 'msg' => 'Die Filmdatenbank ist gerade nicht erreichbar.'];
        if (!empty($ext['tmdb_person'])) return ['items' => [], 'msg' => 'Für Regie-Einträge gibt es keine Vorschläge.'];
        $skip = []; $mid = (int) ($ext['tmdb'] ?? 0);
        if (!empty($ext['tmdb_col'])) { // Reihe: Empfehlungen zum ersten Teil, ohne die Teile der Reihe selbst
            $c = tmdb_get('/collection/' . (int) $ext['tmdb_col']); $parts = (array) ($c['parts'] ?? []);
            usort($parts, fn($x, $y) => strcmp((string) ($x['release_date'] ?? '9999'), (string) ($y['release_date'] ?? '9999')));
            foreach ($parts as $x) $skip[(int) ($x['id'] ?? 0)] = 1; $mid = (int) ($parts[0]['id'] ?? 0);
        }
        if (!$mid) return ['items' => [], 'msg' => 'Dieser Eintrag ist noch keinem Film in der Datenbank zugeordnet. Aktualisiere ihn zuerst.'];
        $skip[$mid] = 1; $j = tmdb_get("/movie/$mid/recommendations") ?? [];
        foreach ((array) ($j['results'] ?? []) as $r) {
            if (empty($r['id']) || empty($r['title']) || isset($skip[(int) $r['id']])) continue;
            $out[] = sg_item((string) $r['title'], trim(substr((string) ($r['release_date'] ?? ''), 0, 4) . (!empty($r['vote_average']) && (int) ($r['vote_count'] ?? 0) >= 100 ? ' · ' . number_format((float) $r['vote_average'], 1, ',', '') . ' von 10' : ''), ' ·'), ['tmdb' => (int) $r['id']]);
            if (count($out) >= 12) break;
        }
    } else return ['items' => [], 'msg' => 'Für diese Kategorie gibt es keine Vorschläge.'];
    return ['items' => $out, 'msg' => $out ? '' : 'Keine Vorschläge gefunden.'];
}

function sg_popular(PDO $pdo, string $type, string $genre): array {
    $out = [];
    if ($type === 'artist') {
        $tag = mb_strtolower(trim($genre));
        $j = http_json('https://musicbrainz.org/ws/2/artist/?fmt=json&limit=100&query=' . rawurlencode('tag:"' . str_replace('"', '', $tag) . '"'), 25) ?? [];
        $rows = [];
        foreach (($j['artists'] ?? []) as $a) { $c = 0; foreach (($a['tags'] ?? []) as $t) if (mb_strtolower((string) ($t['name'] ?? '')) === $tag) $c = max($c, (int) ($t['count'] ?? 0)); if ($c > 0) $rows[] = [$c, (int) ($a['score'] ?? 0), $a]; }
        usort($rows, fn($x, $y) => [$y[0], $y[1]] <=> [$x[0], $x[1]]);
        foreach (array_slice($rows, 0, 10) as [$c, $s, $a]) { $h = array_filter([$a['disambiguation'] ?? '', $a['area']['name'] ?? '', !empty($a['life-span']['begin']) ? 'seit ' . substr($a['life-span']['begin'], 0, 4) : '']); $out[] = sg_item((string) $a['name'], implode(' · ', $h), ['mbid' => (string) $a['id']]); }
    } elseif ($type === 'series') {
        $n = 0;
        foreach (sg_series_pool($pdo) as $s) if (in_array($genre, $s['genres'], true)) { $out[] = sg_item($s['name'], trim($s['year'] . ' · ' . implode(', ', array_slice($s['genres'], 0, 3)), ' ·'), ['tvmaze' => $s['id']]); if (++$n >= 10) break; }
    } elseif ($type === 'author') {
        foreach (array_slice(sg_ol_subject_authors($genre), 0, 10) as $a) $out[] = sg_item($a['name'], $a['top'] !== '' ? '„' . $a['top'] . '“' : '', ['olid' => $a['olid']]);
    } elseif ($type === 'film') {
        if (!tmdb_enabled() || !ctype_digit($genre)) return ['items' => [], 'msg' => 'Die Filmdatenbank ist gerade nicht erreichbar.'];
        $j = tmdb_get('/discover/movie', ['with_genres' => $genre, 'sort_by' => 'vote_average.desc', 'vote_count.gte' => 3000, 'include_adult' => 'false']) ?? [];
        foreach (array_slice((array) ($j['results'] ?? []), 0, 10) as $r) if (!empty($r['id']) && !empty($r['title'])) $out[] = sg_item((string) $r['title'], trim(substr((string) ($r['release_date'] ?? ''), 0, 4) . ' · ' . number_format((float) ($r['vote_average'] ?? 0), 1, ',', '') . ' von 10', ' ·'), ['tmdb' => (int) $r['id']]);
    } else return ['items' => [], 'msg' => 'Für diese Kategorie gibt es keine Vorschläge.'];
    return ['items' => $out, 'msg' => $out ? '' : 'Dazu wurden keine Vorschläge gefunden.'];
}
