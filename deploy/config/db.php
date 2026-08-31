<?php
/**
 * Database credentials.  LIVES ABOVE public_html — never web-reachable.
 *
 * HOSTINGER: from hPanel > Databases > MySQL Databases, copy the four
 * values below. On Hostinger the host is almost always 'localhost'.
 *
 * Fill these four blanks, then this file is done:
 *   - DB_NAME : e.g. u123456789_toolkit
 *   - DB_USER : e.g. u123456789_toolkit
 *   - DB_PASS : the password you set for that database user
 *   - DB_HOST : 'localhost' on Hostinger shared hosting
 */

// GIT DEPLOY: put your real credentials in db.local.php (gitignored) so a
// git pull never overwrites them. If that file exists, it is used instead of
// the placeholders below. Copy this block into db.local.php and fill it in.
$local = __DIR__ . '/db.local.php';
if (is_file($local)) {
    return require $local;
}

return [
    'host'    => 'localhost',              // PLACEHOLDER — Hostinger shared hosting uses 'localhost'
    'name'    => 'REPLACE_DB_NAME',        // PLACEHOLDER — Hostinger MySQL database name
    'user'    => 'REPLACE_DB_USER',        // PLACEHOLDER — Hostinger MySQL username
    'pass'    => 'REPLACE_DB_PASSWORD',    // PLACEHOLDER — Hostinger MySQL password
    'charset' => 'utf8mb4',
];
