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
