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
  '003_notify' => [
  "ALTER TABLE users ADD COLUMN notify TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN unsub_token CHAR(40) NULL, ADD UNIQUE KEY uq_unsub (unsub_token)",
  "CREATE TABLE IF NOT EXISTS notified (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     subject_id INT UNSIGNED NOT NULL,
     key_hash CHAR(40) NOT NULL UNIQUE,
     kind ENUM('new','soon') NOT NULL,
     sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     KEY idx_user_subject (user_id, subject_id),
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '004_chat_site' => [
  "CREATE TABLE IF NOT EXISTS messages (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     from_admin TINYINT(1) NOT NULL DEFAULT 0,
     body VARCHAR(1000) NOT NULL,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     read_at DATETIME NULL,
     KEY idx_thread (user_id, created_at),
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS site_settings (k VARCHAR(40) PRIMARY KEY, v VARCHAR(200) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '005_episodes' => [
  "CREATE TABLE IF NOT EXISTS episodes (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     subject_id INT UNSIGNED NOT NULL,
     season SMALLINT UNSIGNED NOT NULL,
     number SMALLINT UNSIGNED NOT NULL,
     title VARCHAR(255) NULL,
     airdate VARCHAR(10) NULL,
     KEY idx_ep (subject_id, season, number),
     FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '006_delete_request' => [
  "ALTER TABLE users ADD COLUMN delete_requested_at DATETIME NULL",
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
