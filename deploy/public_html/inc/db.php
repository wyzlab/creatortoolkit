<?php
/**
 * db.php — one lazily-created PDO connection, shared for the request.
 * Prepared statements only, emulation off. Technical Spec section 6.
 */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require CONFIG_DIR . '/db.php';

    // Most Hostinger setups use host + default socket. A non-standard port or
    // an explicit unix socket are supported when the config provides them.
    if (!empty($cfg['unix_socket'])) {
        $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s',
            $cfg['unix_socket'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
    } else {
        $port = !empty($cfg['port']) ? ';port=' . (int)$cfg['port'] : '';
        $dsn = sprintf('mysql:host=%s%s;dbname=%s;charset=%s',
            $cfg['host'], $port, $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
    }

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // Match the app's timezone so NOW() and DATETIME comparisons line up.
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        error_log('DB connect failed: ' . $e->getMessage());
        http_response_code(500);
        // Never leak credentials or DSN detail to the client.
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Content-Type: application/json');
        }
        exit(json_encode(['ok' => false, 'error' => 'A server error occurred. Please try again.']));
    }

    return $pdo;
}
