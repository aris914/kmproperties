-- 1.1.0: admin security (audit log, sign-in lockouts, IP/country rules, users online).
-- New installs get these from schema.sql. Existing databases: run this file once in phpMyAdmin.

CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT UNSIGNED NULL,
  user_email VARCHAR(190) NULL,
  action VARCHAR(20) NOT NULL,
  area VARCHAR(40) NOT NULL,
  record VARCHAR(255) NULL,
  details TEXT NULL,
  ip VARCHAR(45) NULL,
  KEY idx_audit_at (at),
  KEY idx_audit_area (area, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  k VARCHAR(200) PRIMARY KEY,
  fails INT UNSIGNED NOT NULL DEFAULT 0,
  last_at DATETIME NOT NULL,
  locked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_blocks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip VARCHAR(45) NOT NULL,
  cc CHAR(2) NULL,
  reason VARCHAR(200) NULL,
  path VARCHAR(120) NULL,
  KEY idx_blocks_ip (ip, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_geo (
  ip VARCHAR(45) PRIMARY KEY,
  cc CHAR(2) NULL,
  country VARCHAR(120) NULL,
  place VARCHAR(120) NULL,
  isp VARCHAR(120) NULL,
  fetched_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_sessions (
  session_key CHAR(64) PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  signed_in_at DATETIME NOT NULL,
  last_seen DATETIME NOT NULL,
  kicked_at DATETIME NULL,
  KEY idx_sess_user (user_id),
  KEY idx_sess_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
