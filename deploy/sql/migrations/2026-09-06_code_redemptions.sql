-- Track each use of an access code (especially the shared universal code), so
-- the admin can see how many sign-ups a code produced and which emails used it.
-- Run once on the live database (phpMyAdmin -> SQL tab). Safe to re-run.
CREATE TABLE IF NOT EXISTS code_redemptions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code_id      INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  email        VARCHAR(190) NOT NULL,
  batch_label  VARCHAR(80) NULL,
  redeemed_at  DATETIME NOT NULL,
  INDEX idx_redemption_batch (batch_label, redeemed_at),
  INDEX idx_redemption_code (code_id),
  INDEX idx_redemption_email (email),
  FOREIGN KEY (code_id) REFERENCES access_codes(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
