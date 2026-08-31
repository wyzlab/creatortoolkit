<?php
/**
 * gen-codes.php — mint access codes and insert their HMAC lookups.
 *
 * Run from the command line (SSH or Hostinger's cron/terminal), NEVER over
 * the web. It lives above public_html for that reason.
 *
 *   php gen-codes.php [count] [batch_label]
 *   php gen-codes.php 20 dev-test
 *
 * It prints the plaintext codes ONCE. Copy them now: only the HMAC is stored,
 * so they cannot be recovered later. For development, 20 test codes is the
 * default. For a real batch, pass the count and a batch label.
 *
 * Format: three groups of three from an unambiguous alphabet, e.g. ABC-2KD-9MN.
 * normalize() (uppercase, strip spaces and hyphens) means the buyer can type
 * it any way. Technical Spec 2.1.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line only.\n");
}

define('CONFIG_DIR', realpath(__DIR__ . '/../config'));
$db      = require CONFIG_DIR . '/db.php';
$secrets = require CONFIG_DIR . '/secrets.php';

if (strpos($secrets['code_pepper'], 'REPLACE') !== false) {
    exit("Set a real code_pepper in config/secrets.php first (see the comment there).\n");
}

$count = isset($argv[1]) ? max(1, (int)$argv[1]) : 20;
$batch = $argv[2] ?? 'dev-test';

$alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';  // no I, L, O, 0, 1
$alen = strlen($alphabet);

function make_code(string $alphabet, int $alen): string
{
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $s = '';
        for ($i = 0; $i < 3; $i++) {
            $s .= $alphabet[random_int(0, $alen - 1)];
        }
        $groups[] = $s;
    }
    return implode('-', $groups);
}

function normalize_code(string $code): string
{
    return strtoupper(preg_replace('/[\s\-]+/', '', $code) ?? '');
}

if (!empty($db['unix_socket'])) {
    $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $db['unix_socket'], $db['name'], $db['charset'] ?? 'utf8mb4');
} else {
    $port = !empty($db['port']) ? ';port=' . (int)$db['port'] : '';
    $dsn = sprintf('mysql:host=%s%s;dbname=%s;charset=%s', $db['host'], $port, $db['name'], $db['charset'] ?? 'utf8mb4');
}
$pdo = new PDO($dsn, $db['user'], $db['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
);

$ins = $pdo->prepare(
    'INSERT INTO access_codes (code_lookup, code_display, batch_label, status, created_at)
     VALUES (?, ?, ?, "unclaimed", ?)'
);

$now = (new DateTimeImmutable('now', new DateTimeZone('+08:00')))->format('Y-m-d H:i:s');
$made = [];
$attempts = 0;

echo "Minting $count codes (batch: $batch)\n";
echo str_repeat('-', 40) . "\n";

while (count($made) < $count && $attempts < $count * 20) {
    $attempts++;
    $code = make_code($alphabet, $alen);
    $lookup = hash_hmac('sha256', normalize_code($code), $secrets['code_pepper']);
    $display = substr(normalize_code($code), -4);
    try {
        $ins->execute([$lookup, $display, $batch, $now]);
        $made[] = $code;
        echo $code . "\n";
    } catch (PDOException $e) {
        // Unique collision on code_lookup: just try another.
        if ($e->getCode() !== '23000') {
            throw $e;
        }
    }
}

echo str_repeat('-', 40) . "\n";
echo count($made) . " codes inserted. Copy the list above now, it will not be shown again.\n";
