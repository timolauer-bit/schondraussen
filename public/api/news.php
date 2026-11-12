<?php
// News: eine Meldung je Tag, zwei Bereiche (Seitennews, News zu Inhalten). Lesen: alle mit Recht „news“; Schreiben: nur Admin.
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$admin = $u['role'] === 'admin'; $a = $_GET['a'] ?? 'list'; $pdo = db();
if (!perm($u, 'news')) json_out(['error' => 'Diese Seite ist für dich nicht freigeschaltet.'], 403);
function news_clean($t, int $max): string { $t = str_replace(["\r\n", "\r"], "\n", (string) $t); $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t); $t = trim($t); return mb_substr($t, 0, $max); }
function news_day($d): ?string { $d = (string) $d; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false ? $d : null; }
try {
    if ($a === 'list') {
        $w = $admin ? '' : ' WHERE day <= CURDATE()';
        $rows = $pdo->query("SELECT day, site_title, site_text, content_title, content_text, day > CURDATE() AS future FROM news_days$w ORDER BY day DESC LIMIT 90")->fetchAll(PDO::FETCH_ASSOC);
        json_out(['days' => $rows, 'admin' => $admin]);
    }
    if (!$admin) json_out(['error' => 'Nur für Admins.'], 403);
    $b = body();
    if ($a === 'save') {
        $day = news_day($b['day'] ?? ''); if (!$day) json_out(['error' => 'Bitte ein gültiges Datum wählen.'], 400);
        $st = news_clean($b['site_title'] ?? '', 160); $sx = news_clean($b['site_text'] ?? '', 6000);
        $ct = news_clean($b['content_title'] ?? '', 160); $cx = news_clean($b['content_text'] ?? '', 6000);
        if ($st . $sx . $ct . $cx === '') json_out(['error' => 'Mindestens ein Feld muss ausgefüllt sein. Zum Entfernen den Tag löschen.'], 400);
        $n = fn($s) => $s === '' ? null : $s;
        $pdo->prepare('INSERT INTO news_days (day, site_title, site_text, content_title, content_text) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE site_title=VALUES(site_title), site_text=VALUES(site_text), content_title=VALUES(content_title), content_text=VALUES(content_text)')->execute([$day, $n($st), $n($sx), $n($ct), $n($cx)]);
        json_out(['ok' => true]);
    }
    if ($a === 'delete') {
        $day = news_day($b['day'] ?? ''); if (!$day) json_out(['error' => 'Ungültiges Datum.'], 400);
        $pdo->prepare('DELETE FROM news_days WHERE day=?')->execute([$day]);
        json_out(['ok' => true]);
    }
    json_out(['error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $e) { json_out(['error' => 'Die News-Tabelle fehlt noch (Migration 066 einspielen).'], 500); }
