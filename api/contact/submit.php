<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond_json(false, 'Invalid request method.', [], 405);
}

$input = get_json_input();

$fullName = clean_string($input['full_name'] ?? '');
$email = strtolower(clean_string($input['email'] ?? ''));
$phone = clean_string($input['phone'] ?? '');
$enquiryType = clean_string($input['enquiry_type'] ?? 'general');
$subject = clean_string($input['subject'] ?? '');
$message = clean_string($input['message'] ?? '');
$sourcePage = clean_string($input['source_page'] ?? '');

if ($fullName === '') {
    respond_json(false, 'Please enter your full name.', [], 422);
}

if ($email !== '' && !valid_email($email)) {
    respond_json(false, 'Please enter a valid email address.', [], 422);
}

if ($phone !== '' && !valid_phone($phone)) {
    respond_json(false, 'Please enter a valid phone number.', [], 422);
}

if ($message === '') {
    respond_json(false, 'Please enter your message.', [], 422);
}

$allowedTypes = ['general', 'service', 'bridal', 'career', 'complaint', 'partnership'];

if (!in_array($enquiryType, $allowedTypes, true)) {
    $enquiryType = 'general';
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO contact_enquiries
            (
                full_name,
                email,
                phone,
                enquiry_type,
                subject,
                message,
                source_page,
                status,
                ip_address,
                user_agent
            )
        VALUES
            (
                :full_name,
                NULLIF(:email, ''),
                NULLIF(:phone, ''),
                :enquiry_type,
                NULLIF(:subject, ''),
                :message,
                NULLIF(:source_page, ''),
                'new',
                :ip_address,
                :user_agent
            )
    ");

    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':enquiry_type' => $enquiryType,
        ':subject' => $subject,
        ':message' => $message,
        ':source_page' => $sourcePage,
        ':ip_address' => get_client_ip(),
        ':user_agent' => get_user_agent()
    ]);

    respond_json(true, 'Your enquiry has been received. Aarvella will get back to you shortly.', [
        'enquiry_id' => (int) $pdo->lastInsertId()
    ], 201);

} catch (Throwable $e) {
    respond_json(false, 'Unable to submit enquiry right now. Please try again.', [], 500);
}