<?php
// Öffentliche Startseiten-Bilder (Screenshots), ohne Anmeldung abrufbar
require __DIR__ . '/../lib/bootstrap.php';
$s = db()->prepare('SELECT mime,data,UNIX_TIMESTAMP(updated_at) t FROM site_images WHERE id=?');
try { $s->execute([(int) ($_GET['id'] ?? 0)]); $r = $s->fetch(); } catch (Throwable $e) { $r = false; }
if (!$r) { http_response_code(404); exit; }
header('Content-Type: ' . $r['mime']); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: public, max-age=86400'); header('Content-Length: ' . strlen($r['data'])); echo $r['data'];
