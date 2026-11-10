-- Схема на базата данни (MySQL 5.7+ / MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  position VARCHAR(120) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','technician','viewer') NOT NULL DEFAULT 'technician',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dossier (
  id TINYINT UNSIGNED NOT NULL,
  status ENUM('draft','submitted','reviewed') NOT NULL DEFAULT 'draft',
  status_changed_by INT UNSIGNED NULL,
  status_changed_at DATETIME NULL,
  review_note TEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answers (
  question_id SMALLINT UNSIGNED NOT NULL,
  answer_text MEDIUMTEXT NULL,
  confidence ENUM('','confirmed','estimated','unknown','na') NOT NULL DEFAULT '',
  version INT UNSIGNED NOT NULL DEFAULT 0,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answer_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id SMALLINT UNSIGNED NOT NULL,
  answer_text MEDIUMTEXT NULL,
  confidence VARCHAR(20) NOT NULL DEFAULT '',
  version INT UNSIGNED NOT NULL,
  changed_by INT UNSIGNED NULL,
  changed_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_q (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attachments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question_id SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  uploaded_by INT UNSIGNED NULL,
  uploaded_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_q (question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  details VARCHAR(500) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY ix_user_time (username, attempted_at),
  KEY ix_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO dossier (id, status) VALUES (1, 'draft');
