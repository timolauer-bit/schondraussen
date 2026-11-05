<?php
// Einmalige Einrichtung der Datenbanktabellen. Aufruf: /install.php?token=<cron_token aus config.php>
// Die Datei löscht sich nach erfolgreichem Lauf selbst; falls das nicht klappt, bitte manuell vom Server löschen.
header('Content-Type: text/plain; charset=utf-8');
$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : null;
if (!$cfg) { http_response_code(500); exit("config.php fehlt.\n"); }
$tok = (string) ($cfg['cron_token'] ?? '');
if ($tok === '' || $tok === 'langer-zufaelliger-wert' || strlen($tok) < 20) {
    http_response_code(403); exit("Setze in config.php zuerst 'cron_token' auf einen langen zufälligen Wert (mindestens 20 Zeichen).\n");
}
if (!hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token falsch.\n"); }
try {
    $pdo = new PDO('mysql:host=' . $cfg['db']['host'] . ';dbname=' . $cfg['db']['name'] . ';charset=utf8mb4', $cfg['db']['user'], $cfg['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) { http_response_code(500); exit("Datenbankverbindung fehlgeschlagen.\n"); }
$stmts = [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  city VARCHAR(60) NULL,
  skin VARCHAR(20) NULL,
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('author','artist','film','series') NOT NULL,
  name VARCHAR(190) NOT NULL,
  external_ids JSON NULL,
  summary VARCHAR(400) NULL,
  genres JSON NULL,
  total_works INT NULL,
  fetched_at DATETIME NULL,
  UNIQUE KEY uq_subject (type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS works (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  year SMALLINT NULL,
  kind VARCHAR(40) NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS releases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  release_date VARCHAR(10) NULL,
  kind VARCHAR(40) NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS user_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_sample TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_subject (user_id, subject_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL,
    <<<'SQL'
CREATE TABLE IF NOT EXISTS alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  release_id INT UNSIGNED NULL,
  kind ENUM('follow','release') NOT NULL,
  unsubscribe_token CHAR(40) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
SQL
];
foreach ($stmts as $s) {
    try { $pdo->exec($s); preg_match('/TABLE IF NOT EXISTS (\w+)/', $s, $m); echo "OK: " . ($m[1] ?? '?') . "\n"; }
    catch (Throwable $e) { http_response_code(500); exit("Fehler (Code " . $e->getCode() . ") bei: " . substr($s, 0, 60) . "\n"); }
}
echo "\nFertig. Tabellen vorhanden.\n";
echo @unlink(__FILE__) ? "install.php wurde gelöscht.\n" : "BITTE install.php jetzt manuell vom Server löschen.\n";
