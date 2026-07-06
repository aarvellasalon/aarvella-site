<?php
declare(strict_types=1);

/**
 * Copy this file to api/config/db.php (gitignored) and fill in real
 * credentials. Every endpoint under api/*.php does
 * `require_once __DIR__ . '/../config/db.php';` and then uses the
 * global $pdo instance directly (e.g. $pdo->prepare(...)), so this
 * file must define a ready-to-use PDO connection, not just credentials.
 */

$DB_HOST = 'localhost';
$DB_PORT = 3306;
$DB_NAME = 'your_database_name';
$DB_USER = 'your_database_user';
$DB_PASS = 'your_database_password';
$DB_CHARSET = 'utf8mb4';

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
    $DB_HOST,
    $DB_PORT,
    $DB_NAME,
    $DB_CHARSET
);

$pdo = new PDO(
    $dsn,
    $DB_USER,
    $DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$pdo->exec("SET time_zone = '+05:30'");