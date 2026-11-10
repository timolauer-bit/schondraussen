<?php
// Tägliche Sicherung der Datenbank als gzip-SQL in einem Ordner oberhalb des Webverzeichnisses (Ausweichordner /_backup, per .htaccess gesperrt), die letzten 7 bleiben.
require_once __DIR__ . '/watchdog.php';
function run_backup(PDO $pdo, bool $force = false): string {
    $l = state_get($pdo, 'backup_ok'); if (!$force && $l && $l['age'] < 1200) return 'übersprungen (heute schon gesichert)';
    try {
        $dir = __DIR__ . '/../_backup'; // Ausweichordner im Webverzeichnis (durch .htaccess gesperrt)
        $dr = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'); // bevorzugt: Ordner oberhalb des Webverzeichnisses, damit er nie ausgeliefert werden kann
        if ($dr !== '' && is_dir(dirname($dr))) { $out = dirname($dr) . '/schondraussen_backup'; if (is_dir($out) || @mkdir($out, 0750)) { if (is_writable($out)) $dir = $out; } }
        if (!is_dir($dir) && !@mkdir($dir, 0750)) throw new RuntimeException('Sicherungsordner nicht anlegbar');
        if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\n");
        if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
        $salt = state_get($pdo, 'backup_salt')['v'] ?? null; if (!$salt) { $salt = bin2hex(random_bytes(8)); state_set($pdo, 'backup_salt', $salt); }
        $file = "$dir/db-" . date('Ymd-His') . "-$salt.sql.gz";
        $gz = gzopen($file, 'wb6'); if (!$gz) throw new RuntimeException('Datei nicht schreibbar');
        gzwrite($gz, "-- Schondraussen.de Sicherung " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
        $n = 0;
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            $ct = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM)[1];
            gzwrite($gz, "\nDROP TABLE IF EXISTS `$t`;\n$ct;\n");
            $st = $pdo->query("SELECT * FROM `$t`", PDO::FETCH_NUM);
            $buf = [];
            while ($r = $st->fetch()) {
                $buf[] = '(' . implode(',', array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $r)) . ')'; $n++;
                if (count($buf) >= 100) { gzwrite($gz, "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n"); $buf = []; }
            }
            if ($buf) gzwrite($gz, "INSERT INTO `$t` VALUES " . implode(",\n", $buf) . ";\n");
        }
        gzwrite($gz, "\nSET FOREIGN_KEY_CHECKS=1;\n"); gzclose($gz);
        $files = glob("$dir/db-*.sql.gz"); rsort($files); foreach (array_slice($files, 7) as $old) @unlink($old);
        $kb = (int) round(filesize($file) / 1024);
        state_set($pdo, 'backup_ok', date('d.m.Y H:i') . ", $kb KB, $n Zeilen");
        try { $pdo->prepare('DELETE FROM site_state WHERE k=?')->execute(['backup_error']); } catch (Throwable $e) {}
        return "ok ($kb KB, $n Zeilen)";
    } catch (Throwable $e) {
        state_set($pdo, 'backup_error', mb_substr($e->getMessage(), 0, 200));
        return 'Fehler: ' . $e->getMessage();
    }
}
