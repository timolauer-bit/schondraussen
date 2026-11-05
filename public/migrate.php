<?php
// Spielt Datenbankänderungen ein (idempotent). Aufruf: /migrate.php?token=<cron_token>
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/lib/bootstrap.php';
$tok = (string) cfg('cron_token', '');
if (strlen($tok) < 20 || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token fehlt oder falsch.\n"); }
$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$M = [
 '001_auth' => [
  "CREATE TABLE IF NOT EXISTS email_tokens (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     kind ENUM('verify','reset') NOT NULL,
     token_hash CHAR(64) NOT NULL UNIQUE,
     expires_at DATETIME NOT NULL,
     used_at DATETIME NULL,
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS login_attempts (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     ip VARCHAR(45) NOT NULL,
     kind VARCHAR(12) NOT NULL,
     at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     KEY idx_ip_at (ip, kind, at)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '002_approval' => [
  "ALTER TABLE users ADD COLUMN approved_at DATETIME NULL",
  "UPDATE users SET approved_at=NOW() WHERE role='admin'",
 ],
];
foreach ($M as $name => $stmts) {
    $q = $pdo->prepare('SELECT 1 FROM migrations WHERE name=?'); $q->execute([$name]);
    if ($q->fetch()) { echo "schon vorhanden: $name\n"; continue; }
    foreach ($stmts as $s) { $pdo->exec($s); }
    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
    echo "eingespielt: $name\n";
}
echo "Fertig.\n";
