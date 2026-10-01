-- Pending email confirmations for universal-code sign-ups. A shared webinar
-- code lets anyone type any email, so before an account is created we email a
-- one-time link to prove the person owns that address. Individual codes and the
-- purchase flow already bind the email (code delivered to it / purchase made
-- with it), so they do not use this.
CREATE TABLE IF NOT EXISTS signup_verifications (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  code_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at    DATETIME NULL,
  created_at DATETIME NOT NULL,
  KEY idx_token (token_hash),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
