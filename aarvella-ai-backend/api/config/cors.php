<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/**
 * Since frontend and API are on same domain, this can be strict.
 * Change https://aarvella.com to your final domain.
 */
$allowedOrigins = [
    'https://aarvella.com',
    'https://www.aarvella.com',
    'http://localhost',
    'http://127.0.0.1'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}