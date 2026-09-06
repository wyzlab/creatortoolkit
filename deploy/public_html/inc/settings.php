<?php
/**
 * inc/settings.php — a tiny key/value settings store in the database.
 *
 * Some things (the wyzcore webhook tokens) need to be editable by the admin
 * from the browser, because the server filesystem above web root is not always
 * reachable in Hostinger's File Manager. Storing them in the database — which is
 * never web-reachable — lets the admin paste them into the admin console.
 *
 * The table auto-creates on first write, so no manual migration is needed; if
 * the database user lacks CREATE rights the write fails cleanly and the caller
 * can fall back to the secrets file.
 */

declare(strict_types=1);

/** Does the app_settings table exist? Cached per request. */
function app_settings_supported(PDO $pdo): bool
{
    static $has = null;
    if ($has !== null) { return $has; }
    try {
        $has = (bool)$pdo->query("SHOW TABLES LIKE 'app_settings'")->fetchColumn();
    } catch (\Throwable $e) {
        $has = false;
    }
    return $has;
}

/**
 * Create app_settings if it is missing. Returns true if the table exists
 * afterwards. Safe to call repeatedly. Never throws.
 */
function ensure_app_settings_table(PDO $pdo): bool
{
    if (app_settings_supported($pdo)) { return true; }
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
               name       VARCHAR(64) NOT NULL PRIMARY KEY,
               value      TEXT NULL,
               updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (\Throwable $e) {
        return false;
    }
    // Bust the cached miss so the next read sees the new table.
    // (app_settings_supported caches per request; re-query directly.)
    try {
        return (bool)$pdo->query("SHOW TABLES LIKE 'app_settings'")->fetchColumn();
    } catch (\Throwable $e) {
        return false;
    }
}

/** Read a setting, or $default if the table/row is missing. Never throws. */
function setting_get(PDO $pdo, string $name, ?string $default = null): ?string
{
    if (!app_settings_supported($pdo)) { return $default; }
    try {
        $st = $pdo->prepare('SELECT value FROM app_settings WHERE name = ? LIMIT 1');
        $st->execute([$name]);
        $v = $st->fetchColumn();
        return ($v === false || $v === null) ? $default : (string)$v;
    } catch (\Throwable $e) {
        return $default;
    }
}

/**
 * Write a setting, creating the table if needed. Throws on a real DB error so
 * the admin sees why a save failed (e.g. no CREATE privilege).
 */
function setting_set(PDO $pdo, string $name, ?string $value): void
{
    if (!ensure_app_settings_table($pdo)) {
        throw new \RuntimeException('Settings storage is unavailable. Run the app_settings migration on the database.');
    }
    $st = $pdo->prepare(
        'INSERT INTO app_settings (name, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );
    $st->execute([$name, $value]);
}

/** Is a token value actually configured (non-empty and not a REPLACE... placeholder)? */
function token_is_set(?string $v): bool
{
    return $v !== null && $v !== '' && strpos($v, 'REPLACE') !== 0;
}

/**
 * A secret's effective value: the admin-managed database setting if one is set,
 * otherwise the value from the secrets file. Lets the admin console override the
 * file without touching the filesystem.
 */
function token_setting(PDO $pdo, string $name, string $fileFallback): string
{
    $db = setting_get($pdo, $name);
    return token_is_set($db) ? (string)$db : $fileFallback;
}
