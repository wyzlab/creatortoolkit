-- =====================================================================
--  DIY Creator Starter Toolkit  ·  Database Schema
--  WyzLab Studio Originals, presented under WyzCore Academy
--  MySQL 8, InnoDB, utf8mb4_unicode_ci throughout
--  Source of truth: 2026-08-30 Technical Spec, section 3
-- =====================================================================
--  Run this once against a fresh Hostinger MySQL 8 database.
--  Then run seed.sql for the fee table and WyzAI code placeholders.
--  Then run:  php ../tools/gen-codes.php 20   to mint test access codes.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+08:00';   -- Philippine time, matches the audience

-- ─── ACCOUNTS ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email           VARCHAR(190) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NULL,
  role            ENUM('learner','admin') NOT NULL DEFAULT 'learner',
  status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_at      DATETIME NOT NULL,
  last_login_at   DATETIME NULL,
  INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS access_codes (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_lookup        CHAR(64) NOT NULL UNIQUE,   -- HMAC-SHA256 of normalize(code), see Technical Spec 2.1
  code_display       VARCHAR(20) NULL,           -- last 4 only, for the admin view
  product_slug       VARCHAR(64) NOT NULL DEFAULT 'diy-creator-starter-toolkit',
  batch_label        VARCHAR(80) NULL,
  issued_to_email    VARCHAR(190) NULL,
  claimed_by_user_id INT UNSIGNED NULL,
  claimed_at         DATETIME NULL,
  expires_at         DATETIME NULL,
  status             ENUM('unclaimed','claimed','revoked','expired') NOT NULL DEFAULT 'unclaimed',
  created_at         DATETIME NOT NULL,
  INDEX idx_ac_status (status),
  FOREIGN KEY (claimed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL UNIQUE,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── THE CARRY-FORWARD OBJECT ────────────────────────────────

CREATE TABLE IF NOT EXISTS user_profile (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL UNIQUE,
  profile_json JSON NOT NULL,
  version      INT UNSIGNED NOT NULL DEFAULT 1,   -- increments on every write, optimistic lock
  updated_at   DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PROGRESS ────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS tool_sessions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  gate_number  TINYINT UNSIGNED NOT NULL,
  tool_slug    VARCHAR(64) NOT NULL,
  current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
  answers_json JSON NOT NULL,
  status       ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  started_at   DATETIME NOT NULL,
  updated_at   DATETIME NOT NULL,
  completed_at DATETIME NULL,
  UNIQUE KEY uq_user_tool (user_id, tool_slug),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gate_progress (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  gate_number     TINYINT UNSIGNED NOT NULL,
  tools_required  TINYINT UNSIGNED NOT NULL,
  tools_completed TINYINT UNSIGNED NOT NULL DEFAULT 0,
  unlocked_at     DATETIME NULL,
  completed_at    DATETIME NULL,
  UNIQUE KEY uq_user_gate (user_id, gate_number),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tool_results (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id   INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  tool_slug    VARCHAR(64) NOT NULL,
  result_json  JSON NOT NULL,
  result_html  MEDIUMTEXT NOT NULL,
  emailed_at   DATETIME NULL,
  created_at   DATETIME NOT NULL,
  INDEX idx_tr_user (user_id),
  FOREIGN KEY (session_id) REFERENCES tool_sessions(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gate_results (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  gate_number   TINYINT UNSIGNED NOT NULL,   -- 0 = full package
  summary_json  JSON NOT NULL,
  summary_html  MEDIUMTEXT NOT NULL,
  ai_paragraph  TEXT NULL,
  emailed_at    DATETIME NULL,
  created_at    DATETIME NOT NULL,
  UNIQUE KEY uq_user_gate_result (user_id, gate_number),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pdf_unlocks (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NOT NULL,
  tool_slug          VARCHAR(64) NOT NULL,
  unlocked_at        DATETIME NOT NULL,
  download_count     INT UNSIGNED NOT NULL DEFAULT 0,
  last_downloaded_at DATETIME NULL,
  UNIQUE KEY uq_user_pdf (user_id, tool_slug),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CONFIGURABLE DATA, NOT HARD-CODED ───────────────────────

CREATE TABLE IF NOT EXISTS payment_fees (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  method_key   VARCHAR(32) NOT NULL UNIQUE,
  label        VARCHAR(80) NOT NULL,
  rate_percent DECIMAL(6,3) NOT NULL,
  min_fee      DECIMAL(10,2) NULL,
  fixed_fee    DECIMAL(10,2) NOT NULL,
  sort_order   TINYINT UNSIGNED NOT NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  updated_at   DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wyzai_codes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trigger_key    ENUM('login','gate_1','gate_2','gate_3','package') NOT NULL,
  code           VARCHAR(40) NOT NULL,
  coach_name     VARCHAR(80) NOT NULL,
  slot_capacity  INT UNSIGNED NOT NULL DEFAULT 500,
  slots_issued   INT UNSIGNED NOT NULL DEFAULT 0,
  warn_threshold INT UNSIGNED NOT NULL DEFAULT 450,
  warned_at      DATETIME NULL,
  is_pooled      TINYINT(1) NOT NULL DEFAULT 0,
  status         ENUM('active','exhausted','retired') NOT NULL DEFAULT 'active',
  replaced_by_id INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL,
  INDEX idx_wc_trigger_status (trigger_key, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS wyzai_code_claims (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NOT NULL,
  wyzai_code_id  INT UNSIGNED NOT NULL,
  trigger_key    VARCHAR(20) NOT NULL,
  claimed_at     DATETIME NOT NULL,
  UNIQUE KEY uq_user_trigger (user_id, trigger_key),   -- a revisit never burns a second slot
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (wyzai_code_id) REFERENCES wyzai_codes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── OPERATIONS ──────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS email_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  email_type  VARCHAR(40) NOT NULL,
  to_address  VARCHAR(190) NOT NULL,
  subject     VARCHAR(255) NOT NULL,
  status      ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
  attempts    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sent_at     DATETIME NULL,
  error       TEXT NULL,
  created_at  DATETIME NOT NULL,
  INDEX idx_el_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  trigger_key VARCHAR(20) NOT NULL,
  model       VARCHAR(80) NULL,
  tokens_in   INT UNSIGNED NULL,
  tokens_out  INT UNSIGNED NULL,
  status      ENUM('ok','failed','skipped') NOT NULL,
  error       TEXT NULL,
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uq_ai_user_trigger (user_id, trigger_key)  -- hard ceiling: 4 rows per user maximum
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identifier   VARCHAR(64) NOT NULL,   -- hashed ip, or hashed email
  action       VARCHAR(32) NOT NULL,
  attempts     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  locked_until DATETIME NULL,
  UNIQUE KEY uq_rl (identifier, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
