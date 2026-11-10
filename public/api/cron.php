<?php
// Aufruf per Cron (alle 2 Stunden): /api/cron.php?token=<cron_token>
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
require __DIR__ . '/../lib/notify.php';
require __DIR__ . '/../lib/backup.php';
header('Content-Type: text/plain; charset=utf-8');
$tok = (string) cfg('cron_token', '');
if (strlen($tok) < 20 || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token fehlt oder falsch.\n"); }
set_time_limit(120);
$t0 = microtime(true); $started = date('Y-m-d H:i:s'); $BUDGET = 80; // Sekunden für die Aktualisierung, danach laufen noch Mails und Aufräumen
$pdo = db();
// Warteschlange je Kategorie (älteste zuerst, nie geladene vorn), reihum abgearbeitet, damit keine Kategorie verhungert
$queues = [];
foreach (['series', 'artist', 'author', 'podcast'] as $t)
    $queues[$t] = $pdo->query("SELECT id,name FROM subjects WHERE type='$t' AND (fetched_at IS NULL OR fetched_at < NOW() - INTERVAL 11 HOUR) ORDER BY fetched_at IS NOT NULL, fetched_at LIMIT 150")->fetchAll();
$ok = 0; $fail = 0; $failed = []; $byType = ['series' => 0, 'artist' => 0, 'author' => 0, 'podcast' => 0]; $stopped = false;
while (array_filter($queues)) {
    foreach ($queues as $t => &$q) {
        if (!$q) continue;
        if (microtime(true) - $t0 > $BUDGET) { $stopped = true; break 2; }
        $row = array_shift($q);
        $r = refresh_subject($pdo, (int) $row['id']);
        if ($r['ok']) { $ok++; $byType[$t]++; } else { $fail++; if (count($failed) < 15) $failed[] = $row['name'] . ': ' . mb_substr((string) ($r['msg'] ?? ''), 0, 80); }
        usleep(400000);
    }
    unset($q);
}
unset($q);
$left = 0; foreach ($queues as $q) $left += count($q);
$ms = (int) round((microtime(true) - $t0) * 1000);
echo "Aktualisiert: $ok, fehlgeschlagen: $fail, noch offen: $left" . ($stopped ? ' (Zeitbudget erreicht)' : '') . ', Dauer: ' . round($ms / 1000, 1) . " s\n";
try {
    $pdo->prepare('INSERT INTO cron_runs (started_at,duration_ms,ok_count,fail_count,checked,left_count,stopped_early,details) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$started, $ms, $ok, $fail, $ok + $fail, $left, $stopped ? 1 : 0, json_encode(['by' => $byType, 'failed' => $failed], JSON_UNESCAPED_UNICODE)]);
    $pdo->exec('DELETE FROM cron_runs WHERE id < (SELECT m FROM (SELECT MAX(id)-60 AS m FROM cron_runs) x)');
} catch (Throwable $e) {}
try { [$m, $f] = run_digests($pdo); echo "Mails: $m versendet, $f fehlgeschlagen\n"; } catch (Throwable $e) { echo "Mails: übersprungen (" . $e->getMessage() . ")\n"; }
try { $pdo->exec('DELETE FROM login_attempts WHERE at < NOW() - INTERVAL 2 DAY'); } catch (Throwable $e) {}
try { $pdo->exec('DELETE FROM invites WHERE expires_at < NOW() - INTERVAL 1 DAY OR used_at < NOW() - INTERVAL 30 DAY'); } catch (Throwable $e) {}
try { $pdo->exec("DELETE FROM images WHERE ref_id IS NULL AND created_at < NOW() - INTERVAL 1 DAY"); } catch (Throwable $e) {}
try {
    $dead = $pdo->query("SELECT id FROM users WHERE role<>'admin' AND delete_requested_at IS NOT NULL AND delete_requested_at < NOW() - INTERVAL 7 DAY")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dead as $uid) {
        foreach (['email_tokens', 'notified'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE user_id=?")->execute([$uid]); } catch (Throwable $e) {} }
        delete_user_keep_feedback($pdo, (int) $uid);
    }
    echo 'Konten endgültig gelöscht: ' . count($dead) . "\n";
} catch (Throwable $e) { echo "Kontolöschung: übersprungen (" . $e->getMessage() . ")\n"; }
try { echo 'Sicherung: ' . run_backup($pdo) . "\n"; ops_watchdog($pdo); } catch (Throwable $e) { echo 'Sicherung: übersprungen (' . $e->getMessage() . ")\n"; }
try { require_once __DIR__ . '/../lib/effort.php'; echo 'Aufwandszahlen: ' . effort_weekly($pdo) . "\n"; } catch (Throwable $e) { echo 'Aufwandszahlen: übersprungen (' . $e->getMessage() . ")\n"; }
