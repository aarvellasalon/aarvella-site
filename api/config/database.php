<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

function getDatabase(): PDO
{
    static $database = null;

    if ($database instanceof PDO) {
        return $database;
    }

    $config = require '/home/aarvyeqt/private/aarvella-auth0.php';
    $db = $config['database'];

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['name'],
        $db['charset'] ?? 'utf8mb4'
    );

    $database = new PDO(
        $dsn,
        $db['user'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    /*
     * This must be executed after creating the PDO connection
     * and before returning it.
     */
    $database->exec("SET SESSION time_zone = '+05:30'");

    return $database;
}