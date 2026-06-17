<?php
declare(strict_types=1);

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        return [];
    }

    return $data;
}

function respond_json(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

function clean_string(?string $value): string
{
    return trim((string) $value);
}

function valid_email(?string $email): bool
{
    if (!$email) {
        return false;
    }

    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(?string $phone): bool
{
    if (!$phone) {
        return false;
    }

    /**
     * Allows Indian and international phone formats:
     * +919742049990, 9742049990, 0135xxxxxxx etc.
     */
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) === 1;
}

function get_client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

function get_user_agent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function generate_appointment_code(): string
{
    return 'AAR-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}