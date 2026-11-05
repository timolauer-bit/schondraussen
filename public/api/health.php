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
    } catch (Throwable $e) {
        $c = (string) $e->getCode();
        $hint = ['1045' => 'Benutzer oder Passwort falsch', '1044' => 'Benutzer hat keinen Zugriff auf diese Datenbank', '1049' => 'Datenbankname existiert nicht', '2002' => 'Host nicht erreichbar oder falsch', '2006' => 'Server hat die Verbindung beendet'];
        $out['db_error'] = 'Verbindung fehlgeschlagen';
        $out['db_code'] = $c;
        $out['db_hinweis'] = $hint[$c] ?? 'unbekannt';
        $out['config_keys'] = array_keys(is_array($cfg['db'] ?? null) ? $cfg['db'] : []);
    }
}
echo json_encode($out, JSON_PRETTY_PRINT);
