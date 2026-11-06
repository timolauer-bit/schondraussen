<?php
// Betriebsüberwachung: Cron-Ausfall und Datensicherung. Der Cron kann einen eigenen Ausfall nicht melden,
// deshalb wird beim Aufruf der Seite geprüft (höchstens alle 10 Minuten) und der Admin per Mail gewarnt.
require_once __DIR__ . '/mail.php';
const CRON_OVERDUE_MIN = 360;   // 6 Stunden, der Cron läuft alle 2 Stunden
const BACKUP_OVERDUE_MIN = 2160; // 36 Stunden, gesichert wird täglich

function state_get(PDO $pdo, string $k): ?array {
    try { $s = $pdo->prepare('SELECT v, TIMESTAMPDIFF(MINUTE, updated_at, NOW()) age FROM site_state WHERE k=?'); $s->execute([$k]); $r = $s->fetch(); return $r ?: null; } catch (Throwable $e) { return null; }
}
function state_set(PDO $pdo, string $k, string $v): void {
    try { $pdo->prepare('INSERT INTO site_state (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v), updated_at=NOW()')->execute([$k, $v]); } catch (Throwable $e) {}
}
/** Zustand für Info-Seite, Systemcheck und Warnmail. */
function ops_health(PDO $pdo): array {
    $o = ['cron_minutes' => null, 'cron_overdue' => false, 'backup_minutes' => null, 'backup_overdue' => false, 'backup_info' => null, 'backup_error' => null];
    try { $m = $pdo->query('SELECT TIMESTAMPDIFF(MINUTE, MAX(started_at), NOW()) FROM cron_runs')->fetchColumn(); if ($m !== null && $m !== false) { $o['cron_minutes'] = (int) $m; $o['cron_overdue'] = $m > CRON_OVERDUE_MIN; } } catch (Throwable $e) {}
    if ($b = state_get($pdo, 'backup_ok')) { $o['backup_minutes'] = (int) $b['age']; $o['backup_info'] = $b['v']; $o['backup_overdue'] = $b['age'] > BACKUP_OVERDUE_MIN; }
    if ($e = state_get($pdo, 'backup_error')) $o['backup_error'] = $e['v'];
    return $o;
}
function ops_watchdog(PDO $pdo): void {
    $c = state_get($pdo, 'watchdog_check'); if ($c && $c['age'] < 10) return;
    state_set($pdo, 'watchdog_check', '1');
    $h = ops_health($pdo); $probs = [];
    if ($h['cron_overdue']) $probs['cron'] = 'Der Cron-Job hat sich seit ' . round($h['cron_minutes'] / 60, 1) . " Stunden nicht gemeldet. Einträge werden nicht aktualisiert und es gehen keine Benachrichtigungen raus.\nPrüfen: all-inkl KAS, Cronjobs, ob der Job aktiv ist und die Adresse mit Token stimmt.";
    if ($h['backup_overdue'] || ($h['backup_error'] && !$h['backup_info'])) $probs['backup'] = 'Die automatische Datenbanksicherung ist überfällig oder fehlgeschlagen' . ($h['backup_error'] ? ' (' . $h['backup_error'] . ')' : '') . '.';
    foreach (['cron', 'backup'] as $k) {
        $w = state_get($pdo, "warned_$k");
        if (!isset($probs[$k])) { if ($w) { try { $pdo->prepare('DELETE FROM site_state WHERE k=?')->execute(["warned_$k"]); } catch (Throwable $e) {} } continue; }
        if ($w && $w['age'] < 1440) continue; // höchstens eine Warnung pro Tag
        $sent = false;
        foreach ($pdo->query("SELECT email FROM users WHERE role='admin' AND email_verified_at IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $adm)
            $sent = send_mail($adm, 'Warnung Schondraussen.de: ' . ($k === 'cron' ? 'Cron-Job meldet sich nicht' : 'Datenbanksicherung'), $probs[$k] . "\n\nDiese Warnung kommt höchstens einmal am Tag, bis das Problem behoben ist.") || $sent;
        if ($sent) state_set($pdo, "warned_$k", '1');
    }
}
