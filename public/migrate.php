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
  '007_notify_options' => [
  "ALTER TABLE user_entries ADD COLUMN bell TINYINT(1) NOT NULL DEFAULT 1",
  "ALTER TABLE users ADD COLUMN n_new TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN n_day TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN n_week TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN n_mode VARCHAR(8) NOT NULL DEFAULT 'digest', ADD COLUMN n_weekday TINYINT NOT NULL DEFAULT 1, ADD COLUMN bell_default TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN last_morning DATE NULL, ADD COLUMN last_weekly DATE NULL",
  "ALTER TABLE notified MODIFY kind ENUM('new','soon','day') NOT NULL",
 ],
  '008_blocked_emails' => [
  "CREATE TABLE IF NOT EXISTS blocked_emails (
     email VARCHAR(190) NOT NULL PRIMARY KEY,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '009_sample_asked' => [
  "ALTER TABLE users ADD COLUMN sample_asked TINYINT(1) NOT NULL DEFAULT 0",
 ],
  '010_alpha' => [
  "ALTER TABLE users ADD COLUMN alpha TINYINT(1) NOT NULL DEFAULT 0",
 ],
  '011_feedback' => [
  "CREATE TABLE IF NOT EXISTS feedback (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     kind VARCHAR(8) NOT NULL,
     title VARCHAR(120) NOT NULL,
     body VARCHAR(2000) NOT NULL,
     priority VARCHAR(8) NOT NULL DEFAULT 'normal',
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     KEY idx_kind (kind, created_at),
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS feedback_votes (
     feedback_id INT UNSIGNED NOT NULL,
     user_id INT UNSIGNED NOT NULL,
     PRIMARY KEY (feedback_id, user_id),
     FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '012_feedback_done' => [
  "ALTER TABLE feedback ADD COLUMN done_at DATETIME NULL",
 ],
  '013_images' => [
  "CREATE TABLE IF NOT EXISTS images (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     scope ENUM('chat','feedback') NOT NULL,
     ref_id INT UNSIGNED NULL,
     status ENUM('pending','approved') NOT NULL DEFAULT 'pending',
     data MEDIUMBLOB NOT NULL,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     KEY idx_ref (scope, ref_id),
     KEY idx_user (user_id, created_at),
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '014_feature_seed' => [
  "INSERT INTO feedback (user_id,kind,title,body)
     SELECT id,'feature','Listen veröffentlichen und übernehmen',
     'User können ihre Listen auf einer Seite veröffentlichen. Dann könnten andere Leute diese Daten alle oder einzeln übernehmen.\n\nZusammen mit Übersichten nach „Am häufigsten abonnierte Künstler“, zeitlich insgesamt und für die letzten 14 Tage.'
     FROM users WHERE role='admin' ORDER BY id LIMIT 1",
  "INSERT INTO feedback (user_id,kind,title,body)
     SELECT id,'feature','Obere Leiste neu aufbauen',
     'Obere Leiste neu aufbauen wegen Platzproblem.'
     FROM users WHERE role='admin' ORDER BY id LIMIT 1",
 ],
  '015_username' => [
  "ALTER TABLE users ADD COLUMN username VARCHAR(24) COLLATE utf8mb4_unicode_ci NULL, ADD COLUMN username_changed_at DATETIME NULL, ADD UNIQUE KEY uq_username (username)",
 ],
  '016_site_images' => [
  "CREATE TABLE IF NOT EXISTS site_images (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     title VARCHAR(120) NOT NULL DEFAULT '',
     position INT NOT NULL DEFAULT 0,
     mime VARCHAR(30) NOT NULL DEFAULT 'image/jpeg',
     data MEDIUMBLOB NOT NULL,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
     updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '017_name_change' => [
  "ALTER TABLE users ADD COLUMN name_change_required TINYINT(1) NOT NULL DEFAULT 0",
 ],
  '018_admin_tokens' => [
  "CREATE TABLE IF NOT EXISTS admin_tokens (
     id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
     user_id INT UNSIGNED NOT NULL,
     token_hash CHAR(64) NOT NULL UNIQUE,
     expires_at DATETIME NOT NULL,
     used_at DATETIME NULL,
     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '019_move_topbar_to_bugs' => [
  "UPDATE feedback SET kind='bug', priority='normal' WHERE kind='feature' AND title='Obere Leiste neu aufbauen'",
 ],
  '020_suggest_cache' => [
  "CREATE TABLE IF NOT EXISTS suggest_cache (
     k VARCHAR(190) NOT NULL PRIMARY KEY,
     data MEDIUMTEXT NOT NULL,
     fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '021_cron_runs' => [
  "CREATE TABLE IF NOT EXISTS cron_runs (
     id INT AUTO_INCREMENT PRIMARY KEY,
     started_at DATETIME NOT NULL,
     duration_ms INT NOT NULL DEFAULT 0,
     ok_count INT NOT NULL DEFAULT 0,
     fail_count INT NOT NULL DEFAULT 0,
     checked INT NOT NULL DEFAULT 0,
     left_count INT NOT NULL DEFAULT 0,
     stopped_early TINYINT(1) NOT NULL DEFAULT 0,
     details TEXT NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '022_privacy' => [
  "ALTER TABLE users ADD COLUMN hide_email TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN hide_activity TINYINT(1) NOT NULL DEFAULT 0",
  "CREATE TABLE IF NOT EXISTS admin_log (
     id INT AUTO_INCREMENT PRIMARY KEY,
     admin_id INT NOT NULL,
     action VARCHAR(40) NOT NULL,
     target_id INT NULL,
     created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '023_cal_token' => [
  "ALTER TABLE users ADD COLUMN cal_token CHAR(32) NULL, ADD UNIQUE KEY uq_cal_token (cal_token)",
 ],
  '024_site_state' => [
  "CREATE TABLE site_state (k VARCHAR(40) PRIMARY KEY, v VARCHAR(255) NOT NULL DEFAULT '', updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '028_keep_feedback' => [
  "SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feedback' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1)",
  "SET @q := CONCAT('ALTER TABLE feedback DROP FOREIGN KEY ', @fk)",
  "PREPARE s FROM @q",
  "EXECUTE s",
  "DEALLOCATE PREPARE s",
  "ALTER TABLE feedback MODIFY user_id INT UNSIGNED NULL",
  "ALTER TABLE feedback ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL",
  "ALTER TABLE feedback ADD COLUMN author_gone VARCHAR(60) NULL",
  "SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='images' AND COLUMN_NAME='user_id' AND REFERENCED_TABLE_NAME='users' LIMIT 1)",
  "SET @q := CONCAT('ALTER TABLE images DROP FOREIGN KEY ', @fk)",
  "PREPARE s FROM @q",
  "EXECUTE s",
  "DEALLOCATE PREPARE s",
  "ALTER TABLE images MODIFY user_id INT UNSIGNED NULL",
  "ALTER TABLE images ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL",
 ],
  '027_bug_no' => [
  "ALTER TABLE feedback ADD COLUMN bug_no INT NULL",
  "SET @n := 0",
  "UPDATE feedback SET bug_no = (@n := @n + 1) WHERE kind='bug' ORDER BY id",
 ],
  '026_user_done' => [
  "CREATE TABLE user_done (user_id INT UNSIGNED NOT NULL, subject_id INT UNSIGNED NOT NULL, item_key VARCHAR(40) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (user_id, subject_id, item_key), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '025_totp' => [
  "ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL, ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN totp_codes TEXT NULL, ADD COLUMN totp_step BIGINT NOT NULL DEFAULT 0",
 ],
];
foreach ($M as $name => $stmts) {
    $q = $pdo->prepare('SELECT 1 FROM migrations WHERE name=?'); $q->execute([$name]);
    if ($q->fetch()) { echo "schon vorhanden: $name\n"; continue; }
    foreach ($stmts as $s) { $pdo->exec($s); }
    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute([$name]);
    echo "eingespielt: $name\n";
}
// Startseiten-Bilder einmalig aus den mitgelieferten Dateien übernehmen
$q = $pdo->prepare('SELECT 1 FROM migrations WHERE name=?'); $q->execute(['016_site_images_seed']);
if (!$q->fetch()) {
    if ((int) $pdo->query('SELECT COUNT(*) FROM site_images')->fetchColumn() === 0) {
        foreach ([['shot1.webp', 'Kalender mit allen angekündigten Veröffentlichungen'], ['shot2.webp', 'Serienübersicht mit Liste und kommenden Veröffentlichungen']] as $i => $sf) {
            $f = __DIR__ . '/shots/' . $sf[0]; if (!is_file($f)) continue;
            $pdo->prepare('INSERT INTO site_images (title,position,mime,data) VALUES (?,?,?,?)')->execute([$sf[1], $i + 1, 'image/webp', file_get_contents($f)]);
        }
    }
    $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute(['016_site_images_seed']);
    echo "eingespielt: 016_site_images_seed\n";
}
echo "Fertig.\n";
