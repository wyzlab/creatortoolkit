-- Universal code slots. Run once on the live database (phpMyAdmin -> SQL tab).
-- Adds a per-code slot cap so a shared universal code (e.g. for a webinar) can
-- be limited to N sign-ups; when full, new sign-ups are refused and the admin
-- is emailed to rotate. NULL = unlimited (the default, unchanged behaviour).
--
-- If your MySQL/MariaDB rejects "IF NOT EXISTS" on ALTER, drop that clause.
ALTER TABLE access_codes ADD COLUMN IF NOT EXISTS max_uses INT UNSIGNED NULL AFTER status;
