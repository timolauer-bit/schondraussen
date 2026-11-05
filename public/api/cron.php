<?php
// Aufruf per Cron (alle 12 Stunden): /api/cron.php?token=<cron_token>
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
header('Content-Type: text/plain; charset=utf-8');
$tok = (string) cfg('cron_token', '');
if (strlen($tok) < 20 || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token fehlt oder falsch.\n"); }
set_time_limit(110);
$pdo = db();
$ids = $pdo->query("SELECT id FROM subjects WHERE type='series' AND (fetched_at IS NULL OR fetched_at < NOW() - INTERVAL 11 HOUR) ORDER BY fetched_at IS NOT NULL, fetched_at LIMIT 25")->fetchAll(PDO::FETCH_COLUMN);
$ok = 0; $fail = 0;
foreach ($ids as $id) { $r = refresh_subject($pdo, (int) $id); $r['ok'] ? $ok++ : $fail++; usleep(400000); }
echo "Aktualisiert: $ok, fehlgeschlagen: $fail, geprüft: " . count($ids) . "\n";
