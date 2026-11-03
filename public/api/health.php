<?php
// Systemcheck: zeigt, ob PHP, Konfiguration und Datenbank erreichbar sind. Keine Zugangsdaten in der Ausgabe.
header('Content-Type: application/json; charset=utf-8');
$out = ['php' => PHP_VERSION, 'config' => false, 'db' => false, 'pdo_mysql' => extension_loaded('pdo_mysql'), 'curl' => extension_loaded('curl')];
$file = __DIR__ . '/../config.php';
if (is_file($file)) {
    $out['config'] = true;
    $cfg = require $file;
    try {
        $pdo = new PDO('mysql:host=' . $cfg['db']['host'] . ';dbname=' . $cfg['db']['name'] . ';charset=utf8mb4', $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $out['db'] = true;
    } catch (Throwable $e) { $out['db_error'] = 'Verbindung fehlgeschlagen'; }
}
echo json_encode($out, JSON_PRETTY_PRINT);
