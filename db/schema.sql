-- Erster Entwurf (MySQL/MariaDB, utf8mb4). Wird beim Ausbau erweitert.
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  city VARCHAR(60) NULL,
  skin VARCHAR(20) NULL,
  email_verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gemeinsamer Zwischenspeicher je Titel, damit Quellen nur einmal abgefragt werden
CREATE TABLE subjects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('author','artist','film','series','podcast') NOT NULL,
  name VARCHAR(190) NOT NULL,
  external_ids JSON NULL,
  summary VARCHAR(400) NULL,
  genres JSON NULL,
  total_works INT NULL,
  fetched_at DATETIME NULL,
  UNIQUE KEY uq_subject (type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE works (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  year SMALLINT NULL,
  kind VARCHAR(40) NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE releases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  subject_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  release_date VARCHAR(10) NULL,  -- YYYY-MM-DD, YYYY-MM, YYYY oder leer (Termin offen)
  kind VARCHAR(40) NULL,
  note VARCHAR(160) NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Die Liste jedes Nutzers
CREATE TABLE user_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_sample TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_subject (user_id, subject_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vorgemerkte Mails (bei Neuem / zum Termin)
CREATE TABLE alerts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL,
  release_id INT UNSIGNED NULL,
  kind ENUM('follow','release') NOT NULL,
  unsubscribe_token CHAR(40) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Strichcodes von DVDs, Blu-rays und CDs: Zuordnung durch Mitglieder (Migration 089)
CREATE TABLE barcode_votes (
  code VARCHAR(14) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(10) NOT NULL,
  ext_key VARCHAR(60) NOT NULL,
  name VARCHAR(190) NOT NULL,
  ext VARCHAR(400) NOT NULL,
  product VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (code, user_id, type),
  KEY idx_code (code, type),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
