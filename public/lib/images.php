<?php
// Bilder: prüfen, neu berechnen (entfernt EXIF/GPS und eingebetteten Code), verkleinern, als JPEG liefern.
const IMG_MAX_BYTES = 6 * 1024 * 1024;   // Rohdaten
const IMG_MAX_SIDE = 1600;
const IMG_MAX_PER_POST = 3;

/** Gibt JPEG-Bytes zurück oder wirft RuntimeException mit einer Nutzermeldung. */
function image_clean(string $raw): string {
    if ($raw === '' || strlen($raw) > IMG_MAX_BYTES) throw new RuntimeException('Das Bild ist zu groß (höchstens 6 MB).');
    if (!function_exists('imagecreatefromstring')) throw new RuntimeException('Bilder sind auf dem Server gerade nicht möglich.');
    $info = @getimagesizefromstring($raw);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new RuntimeException('Erlaubt sind JPG, PNG und WebP.');
    [$w, $h] = $info;
    if ($w < 1 || $h < 1 || $w * $h > 36000000) throw new RuntimeException('Das Bild hat zu viele Pixel.');
    $im = @imagecreatefromstring($raw);
    if (!$im) throw new RuntimeException('Das Bild konnte nicht gelesen werden.');
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $ex = @exif_read_data('data://image/jpeg;base64,' . base64_encode($raw));
        $rot = [3 => 180, 6 => -90, 8 => 90][$ex['Orientation'] ?? 0] ?? 0;
        if ($rot) { $r = imagerotate($im, $rot, 0); if ($r) { $im = $r; $w = imagesx($im); $h = imagesy($im); } }
    }
    $k = min(1, IMG_MAX_SIDE / max($w, $h)); $nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // Transparenz auf Weiß
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, imagesx($im), imagesy($im));
    ob_start(); imagejpeg($out, null, 82); $jpg = (string) ob_get_clean();
    if ($jpg === '') throw new RuntimeException('Das Bild konnte nicht verarbeitet werden.');
    return $jpg;
}

/** Hängt hochgeladene Bilder (IDs) des Nutzers an einen Beitrag. Gibt die Anzahl zurück. */
function images_attach(PDO $pdo, array $ids, int $uid, string $scope, int $ref, string $status): int {
    $ids = array_slice(array_values(array_unique(array_map('intval', $ids))), 0, IMG_MAX_PER_POST); $n = 0;
    foreach ($ids as $id) {
        $s = $pdo->prepare('UPDATE images SET ref_id=?, status=? WHERE id=? AND user_id=? AND scope=? AND ref_id IS NULL');
        $s->execute([$ref, $status, $id, $uid, $scope]); $n += $s->rowCount();
    }
    return $n;
}
