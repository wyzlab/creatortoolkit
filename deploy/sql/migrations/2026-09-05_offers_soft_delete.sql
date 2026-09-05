-- Offers trash (soft delete). Run once on the live database (phpMyAdmin -> SQL tab).
-- Adds a deleted_at column so deleting an offer moves it to the trash (from where
-- it can be restored) instead of erasing it. The app works with or without this
-- column; run it so "My Offers" gets the Deleted section and Restore.
--
-- If your MySQL/MariaDB rejects "IF NOT EXISTS" on ALTER, drop that clause and
-- run the ALTER once (it errors harmlessly if the column already exists).
ALTER TABLE offers ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL DEFAULT NULL;
