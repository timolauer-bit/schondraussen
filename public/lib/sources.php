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
    $s = $pdo->prepare('SELECT * FROM subjects WHERE id=?'); $s->execute([$sid]); $sub = $s->fetch();
    if (!$sub) return ['ok' => false, 'msg' => 'Titel nicht gefunden'];
    if ($sub['type'] === 'artist') return musicbrainz_refresh($pdo, $sub);
    if ($sub['type'] !== 'series') {
        $pdo->prepare('UPDATE subjects SET fetched_at=NOW() WHERE id=?')->execute([$sid]);
        return ['ok' => false, 'msg' => 'Für diese Kategorie ist die Datenquelle noch nicht angebunden.'];
    }
    $ext = json_decode($sub['external_ids'] ?: '{}', true) ?: [];
    $show = tvmaze_fetch($sub['name'], isset($ext['tvmaze']) ? (int) $ext['tvmaze'] : null);
    if (!$show || empty($show['id'])) return ['ok' => false, 'msg' => 'Bei TVmaze nicht gefunden oder nicht erreichbar.'];
    $eps = $show['_embedded']['episodes'] ?? [];
    $seasons = []; $today = date('Y-m-d'); $rel = [];
    foreach ($eps as $e) {
        $n = (int) ($e['season'] ?? 0); if ($n < 1) continue;
        $seasons[$n] = $seasons[$n] ?? ['n' => 0, 'first' => null];
        $seasons[$n]['n']++;
        $d = $e['airdate'] ?? '';
        if ($d && (!$seasons[$n]['first'] || $d < $seasons[$n]['first'])) $seasons[$n]['first'] = $d;
        if ($d && $d >= $today) $rel[] = [sprintf('S%02dE%02d %s', $n, (int) ($e['number'] ?? 0), $e['name'] ?? ''), $d, 'Folge'];
    }
    $pdo->beginTransaction();
    try {
        $ext['tvmaze'] = (int) $show['id'];
        $genres = $show['genres'] ?? [];
        $pdo->prepare('UPDATE subjects SET external_ids=?, summary=?, genres=?, total_works=?, fetched_at=NOW() WHERE id=?')
            ->execute([json_encode($ext), clean_text($show['summary'] ?? ''), json_encode($genres, JSON_UNESCAPED_UNICODE), count($seasons), $sid]);
        $pdo->prepare('DELETE FROM works WHERE subject_id=?')->execute([$sid]);
        $pdo->prepare('DELETE FROM releases WHERE subject_id=?')->execute([$sid]);
        $w = $pdo->prepare('INSERT INTO works (subject_id,title,year,kind,note) VALUES (?,?,?,?,?)');
        ksort($seasons);
        foreach ($seasons as $n => $x) $w->execute([$sid, "Staffel $n", $x['first'] ? (int) substr($x['first'], 0, 4) : null, 'Staffel', $x['n'] . ' Folgen']);
        $r = $pdo->prepare('INSERT INTO releases (subject_id,title,release_date,kind,note) VALUES (?,?,?,?,?)');
        foreach (array_slice($rel, 0, 60) as $x) $r->execute([$sid, $x[0], $x[1], $x[2], null]);
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Speichern fehlgeschlagen']; }
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
