<?php
// Bilder von Filmen und Serien (TMDB, TVmaze), Büchern (Open Library) und Musik (Cover Art Archive). Der Server holt jedes Bild einmal und liefert es von der eigenen Domain aus:
// Besucher-IP-Adressen gehen so nicht an TMDB/TVmaze. Gespeicherte Bilder werden nach 90 Tagen gelöscht und bei Bedarf neu geholt
// (TMDB erlaubt Zwischenspeichern höchstens 6 Monate). Quellenangabe TMDB steht auf der Info-Seite.
const PIC_DIR = __DIR__ . '/../bilder';
const PIC_MAX_DAYS = 90;
const PIC_TMDB_SIZES = ['w92', 'w185', 'w300', 'w780', 'w1280'];

/** Prüft einen Bildpfad wie „t/w780/abc.jpg“ oder „m/medium_portrait/123/456.jpg“ und gibt die Quelladresse zurück (sonst null). */
function pic_source(string $rel): ?string {
    if (preg_match('~^t/(w\d{2,4})/([A-Za-z0-9]{6,64}\.(?:jpg|png))$~', $rel, $m) && in_array($m[1], PIC_TMDB_SIZES, true)) return 'https://image.tmdb.org/t/p/' . $m[1] . '/' . $m[2];
    if (preg_match('~^m/((?:medium_portrait|medium_landscape|original_untouched)/\d{1,5}/\d{1,10}\.(?:jpg|png))$~', $rel, $m)) return 'https://static.tvmaze.com/uploads/images/' . $m[1];
    if (preg_match('~^c/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})-250\.jpg$~', $rel, $m)) return 'https://coverartarchive.org/release-group/' . $m[1] . '/front-250'; // Cover Art Archive: Albumcover über die MusicBrainz-Nummer
    if (preg_match('~^o/([ab])/([1-9]\d{0,11})-([SML])\.jpg$~', $rel, $m)) return 'https://covers.openlibrary.org/' . $m[1] . '/id/' . $m[2] . '-' . $m[3] . '.jpg?default=false'; // Open Library: Buchcover (b) und Autorenfotos (a) über die Cover-Nummer
    return null;
}
/** Dateiname im Speicher zum Bildpfad (nur Bilder aus pic_source). */
function pic_file(string $rel): string { return PIC_DIR . '/' . $rel; }

/** Holt ein Bild und legt es ab. Gibt den Dateipfad zurück oder null. */
function pic_fetch(string $rel): ?string {
    $src = pic_source($rel); if (!$src) return null;
    $raw = false; $code = 0;
    for ($hop = 0; $hop < 6; $hop++) { // Open Library und Cover Art Archive leiten an archive.org weiter: nur HTTPS-Zielen dieser Dienste folgen
        $ch = curl_init($src);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 3 * 1024 * 1024,
            CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)']);
        $raw = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $loc = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL); curl_close($ch);
        if ($code < 300 || $code >= 400 || !in_array($rel[0], ['o', 'c'], true)) break;
        if (!preg_match('~^https://([a-z0-9-]+\.)*(openlibrary\.org|archive\.org|coverartarchive\.org)/~i', $loc)) return null;
        $src = $loc;
    }
    if (!is_string($raw) || $code !== 200 || strlen($raw) < 100 || strlen($raw) > 3 * 1024 * 1024) return null;
    $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2] ?? 0, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) return null;
    $f = pic_file($rel); $d = dirname($f);
    if (!is_dir($d) && !@mkdir($d, 0755, true)) return null;
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $raw) === false) return null;
    if (!@rename($tmp, $f)) { @unlink($tmp); return null; }
    return $f;
}

/** Löscht Bilder, die älter als PIC_MAX_DAYS sind (läuft im Cron). Gibt die Zahl gelöschter Dateien zurück. */
function pic_cleanup(): int {
    if (!is_dir(PIC_DIR)) return 0;
    $n = 0; $lim = time() - PIC_MAX_DAYS * 86400;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(PIC_DIR, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        if ($f->isDir()) { @rmdir($f->getPathname()); continue; } // leere Ordner entfernen (volle bleiben)
        if (preg_match('/\.(jpg|png|tmp)$/', $f->getFilename()) && $f->getMTime() < $lim) { if (@unlink($f->getPathname())) $n++; }
    }
    return $n;
}

/** TMDB-Pfad wie „/abc.jpg“ prüfen (sonst ''). */
function pic_tmdb_path($p): string { $p = (string) $p; return preg_match('~^/[A-Za-z0-9]{6,64}\.(?:jpg|png)$~', $p) ? $p : ''; }
/** TVmaze-Bildadresse auf den Pfad nach /uploads/images/ kürzen (sonst ''). */
function pic_tvm_path($url): string { return preg_match('~^https?://static\.tvmaze\.com/uploads/images/((?:medium_portrait|original_untouched)/\d{1,5}/\d{1,10}\.(?:jpg|png))$~', (string) $url, $m) ? $m[1] : ''; }

/** Bilder einer Serie: TMDB (über die IMDb-Nummer von TVmaze), ersatzweise das TVmaze-Poster. Ergänzt $ext['img'] und $ext['tmdb_tv']. */
function pic_series(array &$ext, array $show): void {
    $img = [];
    $tm = pic_tvm_path($show['image']['medium'] ?? ''); if ($tm !== '') $img['tm'] = $tm;
    if (function_exists('tmdb_enabled') && tmdb_enabled()) {
        $tv = null;
        if (!empty($ext['tmdb_tv'])) $tv = tmdb_get('/tv/' . (int) $ext['tmdb_tv']);
        elseif (preg_match('/^tt\d{5,10}$/', (string) ($show['externals']['imdb'] ?? ''))) {
            $f = tmdb_get('/find/' . $show['externals']['imdb'], ['external_source' => 'imdb_id']);
            $tv = $f['tv_results'][0] ?? null;
        }
        if (is_array($tv) && !empty($tv['id'])) {
            $ext['tmdb_tv'] = (int) $tv['id'];
            if (($b = pic_tmdb_path($tv['backdrop_path'] ?? '')) !== '') $img['b'] = $b;
            if (($p = pic_tmdb_path($tv['poster_path'] ?? '')) !== '') $img['p'] = $p;
        } elseif (!empty($ext['img']['b'])) { $img += array_intersect_key($ext['img'], ['b' => 1, 'p' => 1]); } // TMDB gerade nicht erreichbar: alte Pfade behalten
    }
    if ($img) $ext['img'] = $img; else unset($ext['img']);
}
/** Bilder eines Films oder einer Filmreihe aus der TMDB-Antwort. */
function pic_film(array &$ext, array $m): void {
    $img = [];
    if (($b = pic_tmdb_path($m['backdrop_path'] ?? '')) !== '') $img['b'] = $b;
    if (($p = pic_tmdb_path($m['poster_path'] ?? '')) !== '') $img['p'] = $p;
    if ($img) $ext['img'] = $img; else unset($ext['img']);
}
/**
 * Bilder für Bücher (nur Open Library): Autorenfoto und bis zu 5 Cover von Autor:innen bzw. Bänden einer Reihe.
 * Gespeichert werden nur die Cover-Nummern in $ext['olimg'] (['a' => Foto, 'c' => [Cover…]]); neu abgefragt höchstens alle 30 Tage.
 * Gibt true zurück, wenn neu abgefragt wurde (dann speichern).
 */
function pic_book(array &$ext, string $name): bool {
    if ((int) ($ext['olimg_v'] ?? 1) === 5 && (int) ($ext['olimg_t'] ?? 0) > time() - 30 * 86400) return false; // olimg_v 5: nur Werke mit deutscher Ausgabe, bekannteste zuerst (ältere Stände neu holen)
    $img = []; $covers = [];
    $fields = 'title,cover_i,editions,editions.cover_i,editions.language';
    $pick = function (array $docs, ?callable $ok = null) use (&$covers) {
        foreach ($docs as $d) { // nur Cover deutscher Ausgaben, passend zu den deutschen Titeln auf der Seite
            if (count($covers) >= 5) return;
            if ($ok && !$ok($d)) continue;
            $ed = $d['editions']['docs'][0] ?? []; $de = in_array('ger', (array) ($ed['language'] ?? []), true);
            if (!$de) continue;
            $c = (int) (!empty($ed['cover_i']) ? $ed['cover_i'] : ($d['cover_i'] ?? 0)); // Ausgaben liefern oft keine Cover-Nummer: dann das Cover des Werks (Werk mit deutscher Ausgabe)
            if ($c > 0 && !in_array($c, $covers, true)) $covers[] = $c;
        }
    };
    if (!empty($ext['gbs'])) { // Reihe: Werke mit dem Reihentitel im Titel
        $t = trim((string) $ext['gbs']);
        $r = http_json('https://openlibrary.org/search.json?limit=40&lang=de&fields=' . $fields . '&title=' . rawurlencode($t), 10);
        if ($r === null) return false; // Quelle nicht erreichbar: beim nächsten Lauf erneut
        $docs = (array) ($r['docs'] ?? []);
        $num = function ($d) { return preg_match('/(\d+)\s*$/', (string) ($d['title'] ?? ''), $m) ? (int) $m[1] : 9999; };
        usort($docs, fn($a, $b) => $num($a) <=> $num($b)); // Band 1, 2, 3 … zuerst
        $pick($docs, fn($d) => mb_stripos((string) ($d['title'] ?? ''), $t) !== false);
    } elseif (!empty($ext['olid'])) { // Autor:in: Foto aus dem Open-Library-Profil, dazu Cover der bekanntesten Werke mit deutscher Ausgabe
        $ol = basename((string) $ext['olid']);
        $a = http_json('https://openlibrary.org/authors/' . rawurlencode($ol) . '.json', 10);
        if ($a === null) return false;
        foreach ((array) ($a['photos'] ?? []) as $ph) if ((int) $ph > 0) { $img['a'] = (int) $ph; break; }
        // Werke mit deutscher Ausgabe über die Suche nach language:ger (ohne Ausgaben-Unterfelder); Cover ist das Cover des Werks
        $r = http_json('https://openlibrary.org/search.json?limit=100&sort=editions&fields=title,cover_i&q=' . rawurlencode('author_key:' . $ol . ' language:ger'), 12);
        $nDe = $r !== null ? count((array) ($r['docs'] ?? [])) : -1;
        if ($r !== null) foreach ((array) ($r['docs'] ?? []) as $d) { if (count($covers) >= 5) break; $c = (int) ($d['cover_i'] ?? 0); if ($c > 0 && !in_array($c, $covers, true)) $covers[] = $c; }
        $info = 'Foto ' . (isset($img['a']) ? 'ja' : 'nein') . ', Werke mit deutscher Ausgabe ' . ($nDe < 0 ? 'nicht abrufbar' : $nDe) . ', Cover ' . count($covers);
    } else return false;
    if ($covers) $img['c'] = $covers;
    $ext['olimg_info'] = $info ?? ('Cover ' . count($covers));
    $ext['olimg_t'] = time(); $ext['olimg_v'] = 5;
    if ($img) $ext['olimg'] = $img; else unset($ext['olimg']);
    return true; // abgefragt: Stand und Diagnose speichern
}
/** Buchbilder für den Browser: ['kind' => 'book', 'a' => Foto, 'c' => [Cover…]] als relative Pfade, oder null. */
function pic_book_out(array $x): ?array {
    $i = (array) ($x['olimg'] ?? []); $o = ['kind' => 'book'];
    if (!empty($i['a'])) $o['a'] = 'o/a/' . (int) $i['a'] . '-M.jpg';
    foreach (array_slice((array) ($i['c'] ?? []), 0, 5) as $c) if ((int) $c > 0) $o['c'][] = 'o/b/' . (int) $c . '-M.jpg';
    return (isset($o['a']) || !empty($o['c'])) ? $o : null;
}
/** Albumcover für den Browser (Cover Art Archive): ['kind' => 'music', 'c' => [Cover…]] als relative Pfade, oder null. */
function pic_music_out(array $x): ?array {
    $c = []; foreach (array_slice((array) ($x['caa'] ?? []), 0, 8) as $id) if (preg_match('/^[0-9a-f-]{36}$/', (string) $id)) $c[] = 'c/' . $id . '-250.jpg';
    return $c ? ['kind' => 'music', 'c' => $c] : null;
}
/** Für die Ausgabe an den Browser: relative Bildpfade (Banner groß/klein, Vorschaubild) oder null. */
function pic_out(array $x): ?array {
    $i = (array) ($x['img'] ?? []); $o = [];
    if (!empty($i['b'])) { $o['b'] = 't/w1280' . $i['b']; $o['bs'] = 't/w780' . $i['b']; }
    if (!empty($i['p'])) $o['t'] = 't/w92' . $i['p'];
    elseif (!empty($i['tm'])) $o['t'] = 'm/' . $i['tm'];
    return $o ?: null;
}
