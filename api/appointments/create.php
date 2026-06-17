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

$serviceId = (int) ($input['service_id'] ?? 0);
$stylistId = isset($input['stylist_id']) && $input['stylist_id'] !== '' ? (int) $input['stylist_id'] : null;

$appointmentDate = clean_string($input['appointment_date'] ?? '');
$appointmentTime = clean_string($input['appointment_time'] ?? '');

$customerMessage = clean_string($input['message'] ?? '');
$sourceSlug = clean_string($input['source'] ?? 'website');

$emailConsent = !empty($input['email_marketing']);
$smsConsent = !empty($input['sms_marketing']);
$whatsappConsent = !empty($input['whatsapp_marketing']);
$newsletterOptIn = !empty($input['newsletter_opt_in']);

if ($fullName === '') {
    respond_json(false, 'Please enter your full name.', [], 422);
}

if (!valid_phone($phone)) {
    respond_json(false, 'Please enter a valid phone number.', [], 422);
}

if ($email !== '' && !valid_email($email)) {
    respond_json(false, 'Please enter a valid email address.', [], 422);
}

if ($serviceId <= 0) {
    respond_json(false, 'Please select a valid service.', [], 422);
}

if ($appointmentDate === '' || $appointmentTime === '') {
    respond_json(false, 'Please select appointment date and time.', [], 422);
}

$appointmentStartString = $appointmentDate . ' ' . $appointmentTime . ':00';
$appointmentStart = DateTime::createFromFormat('Y-m-d H:i:s', $appointmentStartString);

if (!$appointmentStart) {
    respond_json(false, 'Invalid appointment date or time.', [], 422);
}

/**
 * Prevent booking in the past.
 */
$now = new DateTime();

if ($appointmentStart <= $now) {
    respond_json(false, 'Please select a future appointment time.', [], 422);
}

try {
    $pdo->beginTransaction();

    /**
     * 1. Get service details.
     */
    $serviceStmt = $pdo->prepare("
        SELECT id, name, duration_minutes, buffer_minutes, price
        FROM services
        WHERE id = :service_id
          AND is_active = 1
          AND is_bookable_online = 1
        LIMIT 1
    ");

    $serviceStmt->execute([
        ':service_id' => $serviceId
    ]);

    $service = $serviceStmt->fetch();

    if (!$service) {
        $pdo->rollBack();
        respond_json(false, 'Selected service is not available for online booking.', [], 422);
    }

    $duration = (int) $service['duration_minutes'];
    $buffer = (int) $service['buffer_minutes'];
    $totalMinutes = $duration + $buffer;

    $appointmentEnd = clone $appointmentStart;
    $appointmentEnd->modify("+{$totalMinutes} minutes");

    /**
     * 2. Validate stylist if selected.
     */
    if ($stylistId !== null) {
        $stylistStmt = $pdo->prepare("
            SELECT id
            FROM stylists
            WHERE id = :stylist_id
              AND is_available = 1
            LIMIT 1
        ");

        $stylistStmt->execute([
            ':stylist_id' => $stylistId
        ]);

        if (!$stylistStmt->fetch()) {
            $pdo->rollBack();
            respond_json(false, 'Selected stylist is not available.', [], 422);
        }

        /**
         * Optional: validate if stylist provides the selected service.
         */
        $mapStmt = $pdo->prepare("
            SELECT id
            FROM stylist_services
            WHERE stylist_id = :stylist_id
              AND service_id = :service_id
              AND is_active = 1
            LIMIT 1
        ");

        $mapStmt->execute([
            ':stylist_id' => $stylistId,
            ':service_id' => $serviceId
        ]);

        if (!$mapStmt->fetch()) {
            $pdo->rollBack();
            respond_json(false, 'Selected stylist is not assigned to this service.', [], 422);
        }

        /**
         * 3. Check appointment overlap for the same stylist.
         */
        $overlapStmt = $pdo->prepare("
            SELECT id
            FROM appointments
            WHERE stylist_id = :stylist_id
              AND status IN ('pending', 'confirmed', 'rescheduled')
              AND appointment_start < :appointment_end
              AND appointment_end > :appointment_start
            LIMIT 1
        ");

        $overlapStmt->execute([
            ':stylist_id' => $stylistId,
            ':appointment_start' => $appointmentStart->format('Y-m-d H:i:s'),
            ':appointment_end' => $appointmentEnd->format('Y-m-d H:i:s')
        ]);

        if ($overlapStmt->fetch()) {
            $pdo->rollBack();
            respond_json(false, 'This stylist is already booked for the selected time. Please choose another slot.', [], 409);
        }
    }

    /**
     * 4. Get booking source.
     */
    $sourceStmt = $pdo->prepare("
        SELECT id
        FROM booking_sources
        WHERE slug = :slug
          AND is_active = 1
        LIMIT 1
    ");

    $sourceStmt->execute([
        ':slug' => $sourceSlug ?: 'website'
    ]);

    $source = $sourceStmt->fetch();
    $bookingSourceId = $source ? (int) $source['id'] : null;

    /**
     * 5. Create or update customer by phone.
     */
    $customerStmt = $pdo->prepare("
        SELECT id
        FROM customers
        WHERE phone = :phone
        LIMIT 1
    ");

    $customerStmt->execute([
        ':phone' => $phone
    ]);

    $existingCustomer = $customerStmt->fetch();

    if ($existingCustomer) {
        $customerId = (int) $existingCustomer['id'];

        $updateCustomerStmt = $pdo->prepare("
            UPDATE customers
            SET
                full_name = :full_name,
                email = NULLIF(:email, ''),
                customer_type = IF(customer_type = 'website_lead', 'repeat', customer_type),
                updated_at = NOW()
            WHERE id = :customer_id
        ");

        $updateCustomerStmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':customer_id' => $customerId
        ]);
    } else {
        $insertCustomerStmt = $pdo->prepare("
            INSERT INTO customers
                (full_name, email, phone, customer_type)
            VALUES
                (:full_name, NULLIF(:email, ''), :phone, 'website_lead')
        ");

        $insertCustomerStmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':phone' => $phone
        ]);

        $customerId = (int) $pdo->lastInsertId();
    }

    /**
     * 6. Insert or update consent.
     */
    $consentStmt = $pdo->prepare("
        INSERT INTO customer_consents
            (
                customer_id,
                email_marketing,
                sms_marketing,
                whatsapp_marketing,
                consent_source,
                consent_ip,
                consent_user_agent
            )
        VALUES
            (
                :customer_id,
                :email_marketing,
                :sms_marketing,
                :whatsapp_marketing,
                'website_booking',
                :consent_ip,
                :consent_user_agent
            )
        ON DUPLICATE KEY UPDATE
            email_marketing = VALUES(email_marketing),
            sms_marketing = VALUES(sms_marketing),
            whatsapp_marketing = VALUES(whatsapp_marketing),
            consent_source = VALUES(consent_source),
            consent_ip = VALUES(consent_ip),
            consent_user_agent = VALUES(consent_user_agent),
            updated_at = NOW()
    ");

    $consentStmt->execute([
        ':customer_id' => $customerId,
        ':email_marketing' => $emailConsent ? 1 : 0,
        ':sms_marketing' => $smsConsent ? 1 : 0,
        ':whatsapp_marketing' => $whatsappConsent ? 1 : 0,
        ':consent_ip' => get_client_ip(),
        ':consent_user_agent' => get_user_agent()
    ]);

    /**
     * 7. Insert appointment.
     */
    $appointmentCode = generate_appointment_code();

    $appointmentStmt = $pdo->prepare("
        INSERT INTO appointments
            (
                appointment_code,
                customer_id,
                service_id,
                stylist_id,
                booking_source_id,
                appointment_start,
                appointment_end,
                status,
                customer_message,
                total_price,
                payment_status,
                created_by
            )
        VALUES
            (
                :appointment_code,
                :customer_id,
                :service_id,
                :stylist_id,
                :booking_source_id,
                :appointment_start,
                :appointment_end,
                'pending',
                :customer_message,
                :total_price,
                'unpaid',
                'customer'
            )
    ");

    $appointmentStmt->execute([
        ':appointment_code' => $appointmentCode,
        ':customer_id' => $customerId,
        ':service_id' => $serviceId,
        ':stylist_id' => $stylistId,
        ':booking_source_id' => $bookingSourceId,
        ':appointment_start' => $appointmentStart->format('Y-m-d H:i:s'),
        ':appointment_end' => $appointmentEnd->format('Y-m-d H:i:s'),
        ':customer_message' => $customerMessage ?: null,
        ':total_price' => $service['price']
    ]);

    $appointmentId = (int) $pdo->lastInsertId();

    /**
     * 8. Add status history.
     */
    $historyStmt = $pdo->prepare("
        INSERT INTO appointment_status_history
            (appointment_id, old_status, new_status, changed_by, note)
        VALUES
            (:appointment_id, NULL, 'pending', 'customer', 'Appointment created from website.')
    ");

    $historyStmt->execute([
        ':appointment_id' => $appointmentId
    ]);

    /**
     * 9. Add to newsletter if opted in and email is available.
     */
    if ($newsletterOptIn && $email !== '') {
        $newsletterStmt = $pdo->prepare("
            INSERT INTO newsletter_subscribers
                (email, full_name, phone, source, status, subscribed_at, ip_address, user_agent)
            VALUES
                (:email, :full_name, :phone, 'booking_form', 'subscribed', NOW(), :ip_address, :user_agent)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                phone = VALUES(phone),
                source = 'booking_form',
                status = 'subscribed',
                subscribed_at = NOW(),
                unsubscribed_at = NULL,
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent)
        ");

        $newsletterStmt->execute([
            ':email' => $email,
            ':full_name' => $fullName,
            ':phone' => $phone,
            ':ip_address' => get_client_ip(),
            ':user_agent' => get_user_agent()
        ]);
    }

    $pdo->commit();

    respond_json(true, 'Your appointment request has been received. Aarvella will confirm your slot shortly.', [
        'appointment_id' => $appointmentId,
        'appointment_code' => $appointmentCode,
        'status' => 'pending'
    ], 201);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    respond_json(false, 'Unable to create appointment right now. Please try again.', [], 500);
}