-- Saved One-Page Offers. Run once on the live database (phpMyAdmin -> SQL tab).
-- Safe to run more than once: CREATE TABLE IF NOT EXISTS.
CREATE TABLE IF NOT EXISTS offers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  title         VARCHAR(190) NOT NULL,
  answers_json  JSON NOT NULL,
  result_json   JSON NOT NULL,
  result_html   MEDIUMTEXT NOT NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  INDEX idx_offers_user (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
