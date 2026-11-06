<?php
// Für Überwachungsdienste (z. B. UptimeRobot): 200 und "OK", wenn die Seite und die Datenbank antworten, sonst 503.
// Mit ?strict=1 meldet die Adresse auch einen überfälligen Cron-Job oder eine überfällige Sicherung als Fehler. Keine Interna in der Ausgabe.
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/watchdog.php';
header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
try {
    $pdo = db(); $pdo->query('SELECT 1');
    $h = ops_health($pdo);
    if (!empty($_GET['strict']) && ($h['cron_overdue'] || $h['backup_overdue'])) { http_response_code(503); exit($h['cron_overdue'] ? "FEHLER cron\n" : "FEHLER backup\n"); }
    ops_watchdog($pdo);
    echo "OK\n";
} catch (Throwable $e) { http_response_code(503); echo "FEHLER db\n"; }
