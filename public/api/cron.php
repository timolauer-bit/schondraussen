<?php
// Aufruf per Cron (alle 12 Stunden): /api/cron.php?token=<cron_token>
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/sources.php';
require __DIR__ . '/../lib/notify.php';
header('Content-Type: text/plain; charset=utf-8');
$tok = (string) cfg('cron_token', '');
if (strlen($tok) < 20 || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token fehlt oder falsch.\n"); }
set_time_limit(110);
$pdo = db();
$ids = [];
foreach ([['series', 15], ['artist', 12], ['author', 10]] as [$t, $lim])
    foreach ($pdo->query("SELECT id FROM subjects WHERE type='$t' AND (fetched_at IS NULL OR fetched_at < NOW() - INTERVAL 11 HOUR) ORDER BY fetched_at IS NOT NULL, fetched_at LIMIT $lim")->fetchAll(PDO::FETCH_COLUMN) as $i) $ids[] = $i;
$ok = 0; $fail = 0;
foreach ($ids as $id) { $r = refresh_subject($pdo, (int) $id); $r['ok'] ? $ok++ : $fail++; usleep(400000); }
echo "Aktualisiert: $ok, fehlgeschlagen: $fail, geprüft: " . count($ids) . "\n";
try { [$m, $f] = run_digests($pdo); echo "Mails: $m versendet, $f fehlgeschlagen\n"; } catch (Throwable $e) { echo "Mails: übersprungen (" . $e->getMessage() . ")\n"; }
try {
    $dead = $pdo->query("SELECT id FROM users WHERE role<>'admin' AND delete_requested_at IS NOT NULL AND delete_requested_at < NOW() - INTERVAL 7 DAY")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dead as $uid) {
        foreach (['email_tokens', 'notified'] as $t) { try { $pdo->prepare("DELETE FROM $t WHERE user_id=?")->execute([$uid]); } catch (Throwable $e) {} }
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    }
    echo 'Konten endgültig gelöscht: ' . count($dead) . "\n";
} catch (Throwable $e) { echo "Kontolöschung: übersprungen (" . $e->getMessage() . ")\n"; }
