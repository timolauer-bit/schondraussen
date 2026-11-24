<?php
// Liefert ein Bild aus /bilder/ aus; fehlt es noch, wird es einmal bei TMDB, TVmaze, Open Library oder dem Cover Art Archive geholt (Aufruf per .htaccess, wenn die Datei fehlt).
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/pics.php';
$rel = (string) ($_GET['p'] ?? '');
if (!pic_source($rel)) { http_response_code(404); exit; }
$u = current_user();
if (!$u || empty($u['approved'])) { // Gäste: nur Bilder, die zu einem Eintrag der Seite gehören
    $name = basename($rel);
    if ($rel[0] === 'o' || $rel[0] === 'c') $name = preg_replace('/-([SML]|250)\.jpg$/', '', $name); // Open Library: die Cover-Nummer steht in den Daten des Eintrags
    try { $q = db()->prepare('SELECT 1 FROM subjects WHERE external_ids LIKE ? LIMIT 1'); $q->execute(['%' . addcslashes($name, '\\%_') . '%']); $ok = (bool) $q->fetchColumn(); } catch (Throwable $e) { $ok = false; }
    if (!$ok) { http_response_code(404); exit; }
}
$f = pic_file($rel);
if (!is_file($f)) $f = pic_fetch($rel);
if (!$f || !is_file($f)) { http_response_code(404); header('Cache-Control: no-store'); exit; }
header('Content-Type: ' . (substr($f, -4) === '.png' ? 'image/png' : 'image/jpeg'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=2592000');
header('Content-Length: ' . filesize($f));
readfile($f);
