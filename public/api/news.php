<?php
// News: eine Meldung je Tag, zwei Bereiche (Seitennews, News zu Inhalten). Lesen: alle mit Recht „news“; Schreiben: nur Admin.
require __DIR__ . '/../lib/bootstrap.php';
$u = current_user();
if (!$u || empty($u['approved'])) json_out(['error' => 'Nicht erlaubt.'], 403);
$admin = $u['role'] === 'admin'; $a = $_GET['a'] ?? 'list'; $pdo = db();
if (!perm($u, 'news')) json_out(['error' => 'Diese Seite ist für dich nicht freigeschaltet.'], 403);
function news_clean($t, int $max): string { $t = str_replace(["\r\n", "\r"], "\n", (string) $t); $t = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $t); $t = trim($t); return mb_substr($t, 0, $max); }
/** Quellen: eine pro Zeile, „Name | https://…“ oder nur der Link; nur http(s), höchstens 8. */
function news_sources($t): string {
    $out = [];
    foreach (explode("\n", str_replace("\r", '', (string) $t)) as $ln) {
        $ln = trim($ln); if ($ln === '') continue;
        $name = ''; $url = $ln; if (strpos($ln, '|') !== false) { [$name, $url] = array_map('trim', explode('|', $ln, 2)); }
        if (!preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || mb_strlen($url) > 400) continue;
        if ($name === '') $name = preg_replace('~^www\.~', '', (string) parse_url($url, PHP_URL_HOST));
        $out[] = mb_substr(str_replace('|', '/', $name), 0, 80) . ' | ' . $url; if (count($out) >= 8) break;
    }
    return implode("\n", $out);
}
function news_day($d): ?string { $d = (string) $d; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false ? $d : null; }
try {
    if ($a === 'list') {
        $w = $admin ? '' : ' WHERE day <= CURDATE()';
        try { $rows = $pdo->query("SELECT day, site_title, site_text, site_sources, content_title, content_text, content_sources, day > CURDATE() AS future FROM news_days$w ORDER BY day DESC LIMIT 90")->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { $rows = $pdo->query("SELECT day, site_title, site_text, content_title, content_text, day > CURDATE() AS future FROM news_days$w ORDER BY day DESC LIMIT 90")->fetchAll(PDO::FETCH_ASSOC); }
        json_out(['days' => $rows, 'admin' => $admin]);
    }
    if (!$admin) json_out(['error' => 'Nur für Admins.'], 403);
    $b = body();
    if ($a === 'save') {
        $day = news_day($b['day'] ?? ''); if (!$day) json_out(['error' => 'Bitte ein gültiges Datum wählen.'], 400);
        $st = news_clean($b['site_title'] ?? '', 160); $sx = news_clean($b['site_text'] ?? '', 6000);
        $ct = news_clean($b['content_title'] ?? '', 160); $cx = news_clean($b['content_text'] ?? '', 6000);
        if ($st . $sx . $ct . $cx . trim((string) ($b['site_sources'] ?? '')) . trim((string) ($b['content_sources'] ?? '')) === '') json_out(['error' => 'Mindestens ein Feld muss ausgefüllt sein. Zum Entfernen den Tag löschen.'], 400);
        $n = fn($s) => $s === '' ? null : $s;
        $ss = news_sources($b['site_sources'] ?? ''); $cs = news_sources($b['content_sources'] ?? '');
        try { $pdo->prepare('INSERT INTO news_days (day, site_title, site_text, site_sources, content_title, content_text, content_sources) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE site_title=VALUES(site_title), site_text=VALUES(site_text), site_sources=VALUES(site_sources), content_title=VALUES(content_title), content_text=VALUES(content_text), content_sources=VALUES(content_sources)')->execute([$day, $n($st), $n($sx), $n($ss), $n($ct), $n($cx), $n($cs)]); }
        catch (Throwable $e) { if ($ss . $cs !== '') json_out(['error' => 'Für Quellen fehlt noch Migration 068.'], 500); $pdo->prepare('INSERT INTO news_days (day, site_title, site_text, content_title, content_text) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE site_title=VALUES(site_title), site_text=VALUES(site_text), content_title=VALUES(content_title), content_text=VALUES(content_text)')->execute([$day, $n($st), $n($sx), $n($ct), $n($cx)]); }
        json_out(['ok' => true]);
    }
    if ($a === 'delete') {
        $day = news_day($b['day'] ?? ''); if (!$day) json_out(['error' => 'Ungültiges Datum.'], 400);
        $pdo->prepare('DELETE FROM news_days WHERE day=?')->execute([$day]);
        json_out(['ok' => true]);
    }
    json_out(['error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $e) { json_out(['error' => 'Die News-Tabelle fehlt noch (Migration 066 einspielen).'], 500); }
