<?php
// Bilder von Filmen und Serien (TMDB, TVmaze). Der Server holt jedes Bild einmal und liefert es von der eigenen Domain aus:
// Besucher-IP-Adressen gehen so nicht an TMDB/TVmaze. Gespeicherte Bilder werden nach 90 Tagen gelöscht und bei Bedarf neu geholt
// (TMDB erlaubt Zwischenspeichern höchstens 6 Monate). Quellenangabe TMDB steht auf der Info-Seite.
const PIC_DIR = __DIR__ . '/../bilder';
const PIC_MAX_DAYS = 90;
const PIC_TMDB_SIZES = ['w92', 'w185', 'w300', 'w780', 'w1280'];

/** Prüft einen Bildpfad wie „t/w780/abc.jpg“ oder „m/medium_portrait/123/456.jpg“ und gibt die Quelladresse zurück (sonst null). */
function pic_source(string $rel): ?string {
    if (preg_match('~^t/(w\d{2,4})/([A-Za-z0-9]{6,64}\.(?:jpg|png))$~', $rel, $m) && in_array($m[1], PIC_TMDB_SIZES, true)) return 'https://image.tmdb.org/t/p/' . $m[1] . '/' . $m[2];
    if (preg_match('~^m/((?:medium_portrait|medium_landscape|original_untouched)/\d{1,5}/\d{1,10}\.(?:jpg|png))$~', $rel, $m)) return 'https://static.tvmaze.com/uploads/images/' . $m[1];
    return null;
}
/** Dateiname im Speicher zum Bildpfad (nur Bilder aus pic_source). */
function pic_file(string $rel): string { return PIC_DIR . '/' . $rel; }

/** Holt ein Bild und legt es ab. Gibt den Dateipfad zurück oder null. */
function pic_fetch(string $rel): ?string {
    $src = pic_source($rel); if (!$src) return null;
    $ch = curl_init($src);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXFILESIZE => 3 * 1024 * 1024,
        CURLOPT_USERAGENT => 'Schondraussen.de/1.0 (+https://schondraussen.de)']);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
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
/** Für die Ausgabe an den Browser: relative Bildpfade (Banner groß/klein, Vorschaubild) oder null. */
function pic_out(array $x): ?array {
    $i = (array) ($x['img'] ?? []); $o = [];
    if (!empty($i['b'])) { $o['b'] = 't/w1280' . $i['b']; $o['bs'] = 't/w780' . $i['b']; }
    if (!empty($i['p'])) $o['t'] = 't/w92' . $i['p'];
    elseif (!empty($i['tm'])) $o['t'] = 'm/' . $i['tm'];
    return $o ?: null;
}
