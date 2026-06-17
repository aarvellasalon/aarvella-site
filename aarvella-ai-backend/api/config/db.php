<?php
declare(strict_types=1);

/**
 * Aarvella DB Connection
 * Keep this file outside public_html later if possible.
 */

$DB_HOST = 'localhost';
$DB_NAME = 'aarvyeqt_salon_db';
$DB_USER = 'aarvyeqt_db_user';
$DB_PASS = 'I{ZbA)rymMCR*-*^';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.'
    ]);
    exit;
}