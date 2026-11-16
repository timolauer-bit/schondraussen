<?php
// Spielt Datenbankänderungen ein (idempotent). Aufruf: /migrate.php?token=<cron_token>
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/lib/bootstrap.php';
$tok = (string) cfg('cron_token', '');
if (strlen($tok) < 20 || !hash_equals($tok, (string) ($_GET['token'] ?? ''))) { http_response_code(403); exit("Token fehlt oder falsch.\n"); }
$pdo = db();
$pdo->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$M = [
  '080_works_extid' => [
  "ALTER TABLE works ADD COLUMN ext_id VARCHAR(20) NULL, ADD INDEX idx_works_extid (ext_id)"
 ],
 '079_faq' => [
  "CREATE TABLE IF NOT EXISTS faq_topics (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) NOT NULL, pos INT NOT NULL DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS faq_items (id INT AUTO_INCREMENT PRIMARY KEY, topic_id INT NOT NULL, question VARCHAR(240) NOT NULL, answer TEXT NOT NULL, pos INT NOT NULL DEFAULT 0, KEY (topic_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
 '078_ical_cats' => [
  "ALTER TABLE users ADD COLUMN ical_cats VARCHAR(60) NULL",
 ],
 '077_stefanh_film_clear' => [
  "DELETE d FROM user_done d JOIN subjects s ON s.id=d.subject_id JOIN users u ON u.id=d.user_id WHERE u.username='stefanh' AND s.type='film'",
  "DELETE e FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id WHERE u.username='stefanh' AND s.type='film'",
 ],
 '076_stefanh_music_clear' => [
  "DELETE d FROM user_done d JOIN subjects s ON s.id=d.subject_id JOIN users u ON u.id=d.user_id WHERE u.username='stefanh' AND s.type='artist'",
  "DELETE e FROM user_entries e JOIN subjects s ON s.id=e.subject_id JOIN users u ON u.id=e.user_id WHERE u.username='stefanh' AND s.type='artist'",
 ],
 '075_moderator' => ["ALTER TABLE users ADD COLUMN moderator TINYINT(1) NOT NULL DEFAULT 0"],
  '074_feedback_category' => [
  "ALTER TABLE feedback ADD COLUMN category VARCHAR(30) NULL",
 ],
 '062_mail_stats' => [
  "CREATE TABLE IF NOT EXISTS mail_stats (kind VARCHAR(12) NOT NULL PRIMARY KEY, n INT UNSIGNED NOT NULL DEFAULT 0, since DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "INSERT IGNORE INTO mail_stats (kind,n) VALUES ('all',0),('new',0),('day',0),('week',0)",
 ],
 '061_email_change' => [
  "ALTER TABLE email_tokens MODIFY kind ENUM('verify','reset','newmail') NOT NULL, ADD COLUMN payload VARCHAR(190) NULL",
 ],
 '060_avatar_wishes' => [
  "CREATE TABLE IF NOT EXISTS avatar_wishes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, wish VARCHAR(500) NOT NULL, status ENUM('open','ready','declined') NOT NULL DEFAULT 'open', note VARCHAR(300) NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_user (user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS avatar_options (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, wish_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, svg MEDIUMTEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_user (user_id), KEY idx_wish (wish_id), FOREIGN KEY (wish_id) REFERENCES avatar_wishes(id) ON DELETE CASCADE, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
 '073_alpha_dm' => [
  "CREATE TABLE IF NOT EXISTS alpha_dm (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, from_id INT UNSIGNED NOT NULL, to_id INT UNSIGNED NOT NULL, body VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL, KEY idx_to (to_id, read_at), KEY idx_pair (from_id, to_id)) DEFAULT CHARSET=utf8mb4",
 ],
 '072_feedback_comments' => [
  "CREATE TABLE IF NOT EXISTS feedback_comments (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, feedback_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL, body VARCHAR(1000) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_fb (feedback_id), FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
 '071_news_items' => [
  "UPDATE news_days SET content_text='Streaming: Am 12.11. starten unter anderem „Die Kaiserin“ (Staffel 3) und „Bros“ (Staffel 2) bei Netflix; bei Disney+ läuft „VisionQuest“ weiter.\nQuelle: Streaming-Guide (KinoCheck) | https://kinocheck.de/news/0i18cj/alle-neuheiten-im-streaming-november-2026-kinocheck-streaming-guide\nQuelle: Streaming im November (TheWrap) | https://www.thewrap.com/creative-content/movies/everything-new-on-streaming-november-2026/\n---\nKino: Neu ab 12.11. sind „Beware Boiúna“ (Riesenschlangen-Horror, Constantin Film) und „Ebenezer – Eine Weihnachtsgeschichte“.\nQuelle: Filmstarts der Woche (The Spot) | https://the-spot-mediafilm.com/\n---\nBücher: Für den Preis der Leipziger Buchmesse 2027 haben 194 Verlage 457 Werke eingereicht; die Jury liest sie in den kommenden Monaten.\nQuelle: buchmarkt.de | https://buchmarkt.de/\n---\nVerlage: Kiepenheuer & Witsch macht seinen Veranstaltungsbereich zu einer eigenen Abteilung (Leitung: Anna-Lena Giershausen).\nQuelle: buchmarkt.de | https://buchmarkt.de/\n---\nFilm und TV: Italien hat ein neues Kinogesetz mit einer eigenen Agentur für Film- und Fernsehförderung beschlossen.\nQuelle: Branchen-Roundup | https://furtherandbetter.substack.com/p/global-tv-film-and-media-industry-00c', content_sources=NULL WHERE day='2026-11-12'",
 ],
 '070_images_team_scope' => [
  "ALTER TABLE images MODIFY scope ENUM('chat','feedback','team') NOT NULL",
 ],
 '069_team_poll_close' => [
  "ALTER TABLE team_messages ADD COLUMN closed_at DATETIME NULL",
 ],
 '068_news_sources' => [
  "ALTER TABLE news_days ADD COLUMN site_sources TEXT NULL, ADD COLUMN content_sources TEXT NULL",
  "UPDATE news_days SET content_sources='Netflix-Streaming-Guide (KinoCheck) | https://kinocheck.de/news/0i18cj/alle-neuheiten-im-streaming-november-2026-kinocheck-streaming-guide\nStreaming im November (TheWrap) | https://www.thewrap.com/creative-content/movies/everything-new-on-streaming-november-2026/\nFilmstarts der Woche (The Spot) | https://the-spot-mediafilm.com/\nbuchmarkt.de | https://buchmarkt.de/\nGlobal TV, Film & Media Roundup | https://furtherandbetter.substack.com/p/global-tv-film-and-media-industry-00c', content_text=TRIM(TRAILING FROM SUBSTRING_INDEX(content_text, '\\nQuellen:', 1)) WHERE day='2026-11-12'",
 ],
 '067_team_chat' => [
  "CREATE TABLE IF NOT EXISTS team_messages (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, body VARCHAR(1000) NOT NULL, is_poll TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS team_poll_options (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, msg_id INT UNSIGNED NOT NULL, label VARCHAR(120) NOT NULL, KEY (msg_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS team_poll_votes (msg_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL, opt_id INT UNSIGNED NOT NULL, PRIMARY KEY (msg_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE IF NOT EXISTS team_chat_seen (user_id INT UNSIGNED NOT NULL PRIMARY KEY, seen_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
 '066_news' => [
  "CREATE TABLE IF NOT EXISTS news_days (day DATE NOT NULL PRIMARY KEY, site_title VARCHAR(160) NULL, site_text TEXT NULL, content_title VARCHAR(160) NULL, content_text TEXT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "INSERT IGNORE INTO news_days (day, site_title, site_text, content_title, content_text) VALUES ('2026-11-12', 'News-Seite im Test', 'Das ist ein erster Entwurf der News-Seite. Hier stehen künftig kurze Meldungen zur Seite selbst, zum Beispiel neue Funktionen oder geplante Wartungen.
Ältere Meldungen findest du eingeklappt weiter unten.', 'Medienbranche heute (Test)', 'Streaming: Am 12.11. starten unter anderem „Die Kaiserin“ (Staffel 3) und „Bros“ (Staffel 2) bei Netflix; bei Disney+ läuft „VisionQuest“ weiter.
Kino: Neu ab 12.11. sind „Beware Boiúna“ (Riesenschlangen-Horror, Constantin Film) und „Ebenezer – Eine Weihnachtsgeschichte“.
Bücher: Für den Preis der Leipziger Buchmesse 2027 haben 194 Verlage 457 Werke eingereicht; die Jury liest sie in den kommenden Monaten.
Verlage: Kiepenheuer & Witsch macht seinen Veranstaltungsbereich zu einer eigenen Abteilung (Leitung: Anna-Lena Giershausen).
Film und TV: Italien hat ein neues Kinogesetz mit einer eigenen Agentur für Film- und Fernsehförderung beschlossen.
Quellen: kinocheck.de, thewrap.com, the-spot-mediafilm.com, buchmarkt.de, Branchenüberblick „Global TV, Film & Media Industry News Roundup“ (12.11.2026). Testeintrag.')",
 ],
 '065_default_usernames' => [
  "UPDATE IGNORE users SET username=CONCAT('User-', id) WHERE username IS NULL OR username=''",
 ],
 '064_avatar_dicebear' => [
  "ALTER TABLE avatar_options MODIFY wish_id INT UNSIGNED NULL, ADD COLUMN src VARCHAR(900) NULL",
 ],
 '063_work_summary' => [
  "ALTER TABLE works ADD COLUMN summary VARCHAR(700) NULL",
 ],
 '059_profile_birthdate' => [
  "ALTER TABLE users ADD COLUMN birth_date DATE NULL, ADD COLUMN age_fixed TINYINT UNSIGNED NULL",
 ],
 '058_profile_card' => [
  "ALTER TABLE users ADD COLUMN display_name VARCHAR(40) NULL, ADD COLUMN birth_year SMALLINT UNSIGNED NULL, ADD COLUMN age_show ENUM('none','age','year') NOT NULL DEFAULT 'none', ADD COLUMN avatar VARCHAR(12) NULL, ADD COLUMN fav_genres VARCHAR(1500) NULL",
 ],
 '057_episode_summary' => [
  "ALTER TABLE episodes ADD COLUMN summary VARCHAR(700) NULL, ADD COLUMN summary_de VARCHAR(900) NULL",
 ],
 '056_hide_cats' => [
  "ALTER TABLE users ADD COLUMN hide_cats VARCHAR(60) NULL",
 ],
 '055_podcast' => [
  "ALTER TABLE subjects MODIFY type ENUM('author','artist','film','series','podcast') NOT NULL",
 ],
 '054_account_exits' => [
  "CREATE TABLE IF NOT EXISTS account_exits (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NULL, registered_on DATE NOT NULL, exited_on DATE NOT NULL, days_active INT UNSIGNED NOT NULL, by_admin TINYINT(1) NOT NULL DEFAULT 0, reason VARCHAR(20) NULL, reason_text TEXT NULL, KEY idx_exit_user (user_id), KEY idx_exit_on (exited_on)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "ALTER TABLE admin_notices MODIFY body TEXT NOT NULL",
  "INSERT INTO account_exits (user_id,registered_on,exited_on,days_active,by_admin) SELECT id, DATE(created_at), DATE(delete_requested_at), GREATEST(0, DATEDIFF(DATE(delete_requested_at), DATE(created_at))), admin_deleted FROM users WHERE delete_requested_at IS NOT NULL AND role<>'admin'",
 ],
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
  '053_anon_accounts' => [
  "ALTER TABLE users MODIFY email VARCHAR(190) NULL",
  "ALTER TABLE users ADD COLUMN recovery_hash VARCHAR(255) NULL",
  "CREATE TABLE IF NOT EXISTS admin_notices (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(30) NOT NULL, body VARCHAR(500) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL, KEY idx_kind (kind, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '052_app_texts' => [
  "CREATE TABLE IF NOT EXISTS app_texts (k VARCHAR(40) PRIMARY KEY, v TEXT NOT NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '051_feedback_note' => [
  "ALTER TABLE feedback ADD COLUMN status_note VARCHAR(400) NULL, ADD COLUMN status_note_by VARCHAR(60) NULL, ADD COLUMN status_note_tier VARCHAR(8) NULL, ADD COLUMN status_note_at DATETIME NULL",
 ],
  '050_feedback_seen' => [
  "ALTER TABLE users ADD COLUMN fb_seen_feature DATETIME NULL, ADD COLUMN fb_seen_bug DATETIME NULL",
 ],
  '049_community_reset' => [
  "UPDATE users SET community=NULL WHERE role<>'admin'",
 ],
  '048_community' => [
  "ALTER TABLE users ADD COLUMN community TINYINT(1) NULL DEFAULT NULL",
 ],
  '047_feedback_withdrawn' => [
  "ALTER TABLE feedback ADD COLUMN withdrawn_at DATETIME NULL",
 ],
  '046_feedback_phase' => [
  "ALTER TABLE feedback ADD COLUMN phase VARCHAR(12) NULL",
 ],
  '045_api_errors' => [
  "CREATE TABLE IF NOT EXISTS api_errors (id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(40) NOT NULL, kind VARCHAR(40) NOT NULL, http_code SMALLINT NOT NULL DEFAULT 0, message VARCHAR(255) NOT NULL DEFAULT '', cnt INT NOT NULL DEFAULT 1, first_at DATETIME NOT NULL, last_at DATETIME NOT NULL, UNIQUE KEY u_src_kind (source,kind)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '044_nerd_default' => [
  "ALTER TABLE users MODIFY nerd_changes TINYINT(1) NULL DEFAULT NULL",
  "UPDATE users SET nerd_changes=NULL WHERE nerd_changes=0",
 ],
  '042_refetch_cut_summaries' => [
  "UPDATE subjects SET fetched_at=NULL WHERE type IN ('author','artist') AND CHAR_LENGTH(summary) >= 399",
 ],
  '041_summary_text' => [
  "ALTER TABLE subjects MODIFY summary TEXT NULL",
 ],
  '040_announcements' => [
  "CREATE TABLE announcements (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(120) NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
  "CREATE TABLE announcement_reads (user_id INT NOT NULL, ann_id INT NOT NULL, PRIMARY KEY (user_id, ann_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '039_work_seen' => [
  "CREATE TABLE work_seen (subject_id INT NOT NULL, k CHAR(40) NOT NULL, first_seen DATE NOT NULL, PRIMARY KEY (subject_id, k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '038_news_cache' => [
  "CREATE TABLE news_cache (k CHAR(40) PRIMARY KEY, data MEDIUMTEXT NOT NULL, fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '037_feedback_edits' => [
  "ALTER TABLE feedback ADD COLUMN edited_at DATETIME NULL",
  "CREATE TABLE feedback_edits (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, feedback_id INT UNSIGNED NOT NULL, field ENUM('title','body') NOT NULL, old_text TEXT NOT NULL, edited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_fb (feedback_id), FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '036_invites' => [
  "CREATE TABLE invites (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, token_hash CHAR(64) NOT NULL, email VARCHAR(190) NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, expires_at DATETIME NOT NULL, used_at DATETIME NULL, UNIQUE KEY uq_tok (token_hash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '035_username_log' => [
  "CREATE TABLE username_log (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, old_name VARCHAR(40) NULL, new_name VARCHAR(40) NOT NULL, changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_user (user_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
 ],
  '034_nerd_changes' => [
  "ALTER TABLE users ADD COLUMN nerd_changes TINYINT(1) NOT NULL DEFAULT 0",
 ],
  '033_media_removed' => [
  "ALTER TABLE images ADD COLUMN removed_by VARCHAR(60) NULL, ADD COLUMN removed_at DATETIME NULL",
 ],
  '032_feedback_released' => [
  "ALTER TABLE feedback ADD COLUMN released_at DATETIME NULL",
 ],
  '031_attach_text' => [
  "ALTER TABLE images ADD COLUMN kind ENUM('image','text') NOT NULL DEFAULT 'image', ADD COLUMN name VARCHAR(120) NULL",
 ],
  '030_feedback_rejected' => [
  "ALTER TABLE feedback ADD COLUMN rejected TINYINT(1) NOT NULL DEFAULT 0",
 ],
  '029_admin_deleted' => [
  "ALTER TABLE users ADD COLUMN admin_deleted TINYINT(1) NOT NULL DEFAULT 0",
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
// Neue (anonymisierte) Bilder der Anmeldeseite: ersetzen die beiden mitgelieferten Bilder, eigene Bilder bleiben dahinter erhalten
$q = $pdo->prepare('SELECT 1 FROM migrations WHERE name=?'); $q->execute(['043_site_images_v2']);
if (!$q->fetch()) {
    try {
        $new = [['v2_kalender.webp', 'Kalender mit allen angekündigten Veröffentlichungen'], ['v2_musik.webp', 'Musikübersicht mit Liste, Alben und kommenden Veröffentlichungen'], ['v2_serie.webp', 'Serie mit Beschreibung auf Deutsch, Staffeln, Folgen und Sendeterminen']];
        $ok = true; foreach ($new as $n) if (!is_file(__DIR__ . '/shots/' . $n[0])) $ok = false;
        if ($ok) {
            $pdo->prepare('DELETE FROM site_images WHERE title IN (?,?)')->execute(['Kalender mit allen angekündigten Veröffentlichungen', 'Serienübersicht mit Liste und kommenden Veröffentlichungen']);
            $pdo->exec('UPDATE site_images SET position=position+3');
            foreach ($new as $i => $n) $pdo->prepare('INSERT INTO site_images (title,position,mime,data) VALUES (?,?,?,?)')->execute([$n[1], $i + 1, 'image/webp', file_get_contents(__DIR__ . '/shots/' . $n[0])]);
            $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute(['043_site_images_v2']);
            echo "eingespielt: 043_site_images_v2\n";
        }
    } catch (Throwable $e) { echo "Fehler bei 043_site_images_v2: " . $e->getMessage() . "\n"; }
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

// FAQ: erste Fragen und Antworten einmalig anlegen (danach im Admin-Bereich unter „FAQ“ bearbeiten)
$q = $pdo->prepare('SELECT 1 FROM migrations WHERE name=?'); $q->execute(['079_faq_seed']);
if (!$q->fetch()) {
    try {
        $F = [
         'Los geht’s' => [
          ['Was ist Schondraussen.de?', "Eine Seite, die dir sagt, wann es Neues von deinen Lieblings-Autor:innen, Musiker:innen, Serien, Filmen und Podcasts gibt. Du legst fest, wem du folgst, und die Seite zeigt Termine im Kalender und informiert dich per Mail.\nSie ist kostenlos und werbefrei."],
          ['Brauche ich eine E-Mail-Adresse?', "Nein. Du kannst dich nur mit Benutzername und Passwort anmelden. Ohne E-Mail bekommst du aber keine Benachrichtigungs-Mails, und beim Passwort-Zurücksetzen hilft dir nur dein Wiederherstellungscode. Notiere ihn dir gut: Er wird nur einmal angezeigt.\nDie E-Mail-Adresse kannst du jederzeit unter Einstellungen → Daten nachtragen."],
          ['Wie füge ich etwas hinzu?', "Wähle oben eine Kategorie, tippe in „Neu hinzufügen“ einen Namen ein und drücke Enter oder „Suchen“. Wähle dann den passenden Treffer aus.\nSchon beim Tippen siehst du Treffer, die es auf der Seite schon gibt. Passt keiner, sucht Enter im Netz."],
          ['Ich finde meinen Titel nicht. Was kann ich tun?', "Probiere eine andere Schreibweise oder den Originaltitel, bei Serien zum Beispiel den englischen Namen. Bei Büchern kannst du auch nach dem Buchtitel oder der Reihe suchen. Mit „trotzdem so hinzufügen“ legst du den Eintrag so an, wie du ihn eingegeben hast. Bei bereits angelegten Serien hilft im Menü des Eintrags „Treffer ändern“."],
          ['Woher kommen die Daten?', "Bücher von Open Library, Musik von MusicBrainz, Serien von TVmaze und Podcasts über Apple. Für Filme wird die Datenquelle noch angebunden. Die Angaben können Lücken haben. Alle Quellen findest du auf der Info-Seite."],
         ],
         'Kalender und Benachrichtigungen' => [
          ['Wie funktioniert die Glocke?', "Mails bekommst du nur zu Einträgen, bei denen die Glocke an ist. Sie sitzt im Kopf jedes Eintrags. Unter Einstellungen → Optik/Optionen kannst du festlegen, ob neue Einträge standardmäßig eine Glocke bekommen."],
          ['Wann bekomme ich Mails und wie stelle ich sie ein?', "Unter Einstellungen → Optik/Optionen → E-Mail-Benachrichtigungen. Dort wählst du, ob du morgens bei Neuem informiert wirst, am Erscheinungstag und/oder mit einer Wochenübersicht, und ob als Sammelmail oder sofort. Mit „Vorschau-Mail an mich senden“ siehst du, wie eine Mail aussieht."],
          ['Wie abonniere ich den Kalender auf dem Handy?', "Unter Einstellungen → Optik/Optionen → Kalender-Abo erzeugst du einen privaten Link und öffnest ihn in deiner Kalender-App (Google, Apple, Outlook, Thunderbird). Der Kalender aktualisiert sich dann selbst, in der Regel nach einigen Stunden.\nGib den Link nicht weiter: Wer ihn kennt, sieht deine Termine."],
          ['Kann ich im Kalender-Abo nur bestimmte Kategorien anzeigen?', "Ja. Unter Kalender-Abo wählst du die Kategorien, die im Abo erscheinen sollen. Die Auswahl gilt nur für das Abo."],
          ['Wie blende ich Kategorien aus, die ich nicht nutze?', "Unter Einstellungen → Optik/Optionen → Oberflächenanpassungen. Ausgeblendete Kategorien verschwinden aus Menü, Kalender und Mails. Deine Einträge bleiben gespeichert, und in der Community siehst du weiterhin alle Kategorien."],
          ['Wie oft werden die Termine aktualisiert?', "Automatisch alle 12 Stunden. Termine ohne genaues Datum erscheinen nicht im Kalender-Abo."],
         ],
         'Listen und Einträge' => [
          ['Wie markiere ich etwas als gesehen, gehört oder gelesen?', "Bei Serien gibt es „Staffel als gesehen markieren“ und „Alles als gesehen markieren“. Bei Musik, Podcasts und Büchern gibt es „Alles gehört“ bzw. „Alles gelesen“. Ein zweiter Klick setzt zurück."],
          ['Kann ich eine Liste importieren?', "Ja, unter Einstellungen → Daten → Import. Eingelesen werden unter anderem IMDb-Exporte (CSV). Filme und Serien landen dabei automatisch in der passenden Liste."],
          ['Wie viele Einträge passen in eine Liste?', "Pro Liste (Bücher, Musik, Filme, Serien) bis zu 1.000 Einträge."],
          ['Kann ich meine Daten exportieren?', "Ja, unter Einstellungen → Profil → Meine Daten als JSON (alle Daten) oder als CSV (nur die Liste)."],
         ],
         'Community' => [
          ['Was sehen andere von mir?', "Nur, was du freigibst: Wenn du der Nutzung deiner Einträge für die Community zustimmst, sehen andere deinen Benutzernamen und deine Listen und können Einträge übernehmen. Die Einstellung findest du unter Einstellungen → Profil. Ohne Zustimmung bleiben deine Details verborgen."],
         ],
         'Konto und Datenschutz' => [
          ['Ich habe mein Passwort vergessen.', "Auf der Anmeldeseite unter „Passwort vergessen“. Dort kannst du dein Passwort mit deinem Wiederherstellungscode zurücksetzen, oder per Mail, wenn du eine E-Mail-Adresse hinterlegt hast."],
          ['Kann ich meinen Benutzernamen ändern?', "Ja, unter Einstellungen → Profil → Benutzername, alle 30 Tage. Direkt nach dem Ändern kannst du einen Tippfehler kurz noch einmal korrigieren."],
          ['Wie lösche ich mein Konto?', "Unter Einstellungen → Datenschutz → Konto löschen. Deine Daten bleiben noch eine Woche gespeichert, falls du es dir anders überlegst. Danach werden sie endgültig gelöscht."],
          ['Was sieht der Admin von mir?', "Deinen Benutzernamen, deine Mitgliedsnummer und die Zahl deiner Einträge. Was genau, kannst du unter Einstellungen → Datenschutz nachlesen. Mehr dazu steht in der Datenschutzerklärung."],
         ],
         'Ideen, Fehler und Kontakt' => [
          ['Wie melde ich eine Idee oder einen Fehler?', "Unter „Ideen“ bzw. „Bugs“ gibt es oben rechts den Reiter „Idee melden“ bzw. „Bug melden“. Das Team schaut sich deine Meldung an, und nach der Freigabe erscheint sie in der Liste. Unter „Freischaltungen“ siehst du deine eigenen Beiträge, die noch warten."],
          ['Wie erreiche ich den Betreiber direkt?', "Über die Seite „Kontakt“: Dort kannst du eine Nachricht schreiben."],
         ],
        ];
        $pdo->exec('DELETE FROM faq_items'); $pdo->exec('DELETE FROM faq_topics');
        $ti = $pdo->prepare('INSERT INTO faq_topics (title,pos) VALUES (?,?)'); $ii = $pdo->prepare('INSERT INTO faq_items (topic_id,question,answer,pos) VALUES (?,?,?,?)'); $tp = 0;
        foreach ($F as $title => $items) { $ti->execute([$title, ++$tp]); $tid = (int) $pdo->lastInsertId(); foreach ($items as $k => $it) $ii->execute([$tid, $it[0], $it[1], $k + 1]); }
        $pdo->prepare('INSERT INTO migrations (name) VALUES (?)')->execute(['079_faq_seed']);
        echo "eingespielt: 079_faq_seed\n";
    } catch (Throwable $e) { echo "Fehler bei 079_faq_seed: " . $e->getMessage() . "\n"; }
}
echo "Fertig.\n";
