<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(false, 'Invalid request method.', [], 405);
}

$input = get_json_input();

$email = strtolower(clean_string($input['email'] ?? ''));
$fullName = clean_string($input['full_name'] ?? '');
$phone = clean_string($input['phone'] ?? '');
$source = clean_string($input['source'] ?? 'footer_form');

if (!valid_email($email)) {
    respond_json(false, 'Please enter a valid email address.', [], 422);
}

$allowedSources = ['footer_form', 'popup', 'booking_form', 'manual_import', 'campaign'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'footer_form';
}

try {
    /**
     * If email already exists, reactivate subscription.
     */
    $stmt = $pdo->prepare("
        INSERT INTO newsletter_subscribers
            (email, full_name, phone, source, status, subscribed_at, ip_address, user_agent)
        VALUES
            (:email, :full_name, :phone, :source, 'subscribed', NOW(), :ip_address, :user_agent)
        ON DUPLICATE KEY UPDATE
            full_name = COALESCE(NULLIF(VALUES(full_name), ''), full_name),
            phone = COALESCE(NULLIF(VALUES(phone), ''), phone),
            source = VALUES(source),
            status = 'subscribed',
            subscribed_at = NOW(),
            unsubscribed_at = NULL,
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent)
    ");

    $stmt->execute([
        ':email' => $email,
        ':full_name' => $fullName ?: null,
        ':phone' => $phone ?: null,
        ':source' => $source,
        ':ip_address' => get_client_ip(),
        ':user_agent' => get_user_agent()
    ]);

    respond_json(true, 'You have been added to the Aarvella Gold List.');

} catch (Throwable $e) {
    respond_json(false, 'Unable to subscribe right now. Please try again.', [], 500);
}