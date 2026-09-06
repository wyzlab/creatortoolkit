-- Key/value settings the admin can edit from the browser (e.g. wyzcore webhook
-- tokens), so secrets never require filesystem access. The app also auto-creates
-- this table on first save, so running this by hand is optional.
CREATE TABLE IF NOT EXISTS app_settings (
  name       VARCHAR(64) NOT NULL PRIMARY KEY,
  value      TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
