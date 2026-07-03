<?php

declare(strict_types=1);

/**
 * Shared, customer-facing portal helpers.
 *
 * Keep this file free of page output so it can be reused by dashboard,
 * appointments, profile, settings and future customer-portal pages.
 */

function portalE(null|string|int|float $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function portalStartSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'path' => '/',
        ]);

        session_start();
    }
}

function portalCsrfToken(): string
{
    portalStartSession();

    if (empty($_SESSION['portal_csrf_token'])) {
        $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['portal_csrf_token'];
}

function portalVerifyCsrf(): void
{
    portalStartSession();

    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $expected = (string) ($_SESSION['portal_csrf_token'] ?? '');

    if ($submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}

function portalFlash(string $type, string $message): void
{
    portalStartSession();
    $_SESSION['portal_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function portalTakeFlash(): ?array
{
    portalStartSession();

    $flash = $_SESSION['portal_flash'] ?? null;
    unset($_SESSION['portal_flash']);

    return is_array($flash) ? $flash : null;
}

function portalRedirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function portalPostString(string $name, int $maxLength = 255): string
{
    $value = trim((string) ($_POST[$name] ?? ''));

    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function portalPostNullableInt(string $name): ?int
{
    $value = trim((string) ($_POST[$name] ?? ''));

    if ($value === '') {
        return null;
    }

    if (!ctype_digit($value) || (int) $value < 1) {
        throw new InvalidArgumentException('One of the selected options is invalid.');
    }

    return (int) $value;
}

function portalPostBool(string $name): bool
{
    return isset($_POST[$name]) && in_array((string) $_POST[$name], ['1', 'on', 'true'], true);
}

function portalPostList(string $name, array $allowedValues, int $maximumItems = 12): array
{
    $values = $_POST[$name] ?? [];

    if (!is_array($values)) {
        return [];
    }

    $clean = [];

    foreach ($values as $value) {
        $value = trim((string) $value);

        if ($value !== '' && in_array($value, $allowedValues, true)) {
            $clean[$value] = $value;
        }

        if (count($clean) >= $maximumItems) {
            break;
        }
    }

    return array_values($clean);
}

function portalNormalisePhone(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $hasPlus = str_starts_with($value, '+');
    $digits = preg_replace('/\D+/', '', $value) ?? '';

    if (strlen($digits) < 8 || strlen($digits) > 15) {
        throw new InvalidArgumentException('Enter a valid mobile number, including the country code when applicable.');
    }

    return ($hasPlus ? '+' : '') . $digits;
}

function portalValidateDateOfBirth(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Kolkata'));
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('Enter a valid date of birth.');
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata'));
    $oldest = $today->modify('-120 years');

    if ($date > $today || $date < $oldest) {
        throw new InvalidArgumentException('Enter a valid date of birth.');
    }

    return $date->format('Y-m-d');
}

function portalValidateTime(?string $value): ?string
{
    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        throw new InvalidArgumentException('Enter a valid preferred time.');
    }

    return $value . ':00';
}

/**
 * Validate a preferred salon visit window against Aarvella's published
 * customer hours: Tuesday-Sunday, 10:00 AM-8:00 PM; Monday closed.
 *
 * Values are stored in MySQL TIME format while the UI presents a 12-hour
 * wheel selector. Both endpoints must be supplied together and use the
 * portal's 30-minute booking preference increments.
 *
 * @return array{0: ?string, 1: ?string}
 */
function portalValidateSalonPreferenceWindow(
    ?string $fromValue,
    ?string $toValue,
    ?int $dayOfWeek
): array {
    if ($dayOfWeek !== null && ($dayOfWeek < 1 || $dayOfWeek > 7)) {
        throw new InvalidArgumentException('Select a valid preferred day.');
    }

    if ($dayOfWeek === 1) {
        throw new InvalidArgumentException(
            'Aarvella is closed on Mondays. Choose Tuesday through Sunday.'
        );
    }

    $from = portalValidateTime($fromValue);
    $to = portalValidateTime($toValue);

    if (($from === null) !== ($to === null)) {
        throw new InvalidArgumentException(
            'Select both the start and end of your preferred time window.'
        );
    }

    if ($from === null && $to === null) {
        return [null, null];
    }

    $fromHm = substr((string) $from, 0, 5);
    $toHm = substr((string) $to, 0, 5);

    foreach ([$fromHm, $toHm] as $time) {
        $minute = (int) substr($time, 3, 2);
        if (!in_array($minute, [0, 30], true)) {
            throw new InvalidArgumentException(
                'Choose a time in 30-minute intervals.'
            );
        }
    }

    if ($fromHm < '10:00' || $fromHm >= '20:00') {
        throw new InvalidArgumentException(
            'Preferred start time must be between 10:00 AM and 7:30 PM.'
        );
    }

    if ($toHm <= '10:00' || $toHm > '20:00') {
        throw new InvalidArgumentException(
            'Preferred end time must be between 10:30 AM and 8:00 PM.'
        );
    }

    if ($fromHm >= $toHm) {
        throw new InvalidArgumentException(
            'The preferred end time must be later than the start time.'
        );
    }

    return [$from, $to];
}

function portalJsonArray(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (is_array($value)) {
        return array_values(array_filter(array_map('strval', $value)));
    }

    $decoded = json_decode((string) $value, true);

    if (!is_array($decoded)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $decoded)));
}

function portalJsonEncode(array $value): string
{
    return (string) json_encode(
        array_values($value),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

function portalTableExists(PDO $database, string $table): bool
{
    static $cache = [];
    $key = spl_object_id($database) . ':' . $table;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $query = $database->prepare(
        'SELECT COUNT(*)
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name'
    );
    $query->execute(['table_name' => $table]);

    return $cache[$key] = (int) $query->fetchColumn() > 0;
}

function portalLoadCustomer(PDO $database, int $customerId): array
{
    $query = $database->prepare(
        'SELECT *
         FROM customers
         WHERE id = :customer_id
         LIMIT 1'
    );
    $query->execute(['customer_id' => $customerId]);

    $customer = $query->fetch(PDO::FETCH_ASSOC);

    if (!$customer) {
        throw new RuntimeException('The customer profile could not be found.');
    }

    return $customer;
}

function portalLoadPreferences(PDO $database, int $customerId): array
{
    if (!portalTableExists($database, 'customer_preferences')) {
        return [];
    }

    $query = $database->prepare(
        'SELECT *
         FROM customer_preferences
         WHERE customer_id = :customer_id
         LIMIT 1'
    );
    $query->execute(['customer_id' => $customerId]);

    return $query->fetch(PDO::FETCH_ASSOC) ?: [];
}

function portalLoadBeautyProfile(PDO $database, int $customerId): array
{
    if (!portalTableExists($database, 'customer_beauty_profiles')) {
        return [];
    }

    $query = $database->prepare(
        'SELECT *
         FROM customer_beauty_profiles
         WHERE customer_id = :customer_id
         LIMIT 1'
    );
    $query->execute(['customer_id' => $customerId]);

    return $query->fetch(PDO::FETCH_ASSOC) ?: [];
}

function portalLoadPortalSettings(PDO $database, int $customerId): array
{
    $defaults = [
        'compact_mode' => 0,
        'reduce_motion' => 0,
        'high_contrast' => 0,
        'timezone' => 'Asia/Kolkata',
    ];

    if (!portalTableExists($database, 'customer_portal_settings')) {
        return $defaults;
    }

    $query = $database->prepare(
        'SELECT compact_mode, reduce_motion, high_contrast, timezone
         FROM customer_portal_settings
         WHERE customer_id = :customer_id
         LIMIT 1'
    );
    $query->execute(['customer_id' => $customerId]);

    $stored = $query->fetch(PDO::FETCH_ASSOC) ?: [];

    return array_merge($defaults, $stored);
}

function portalLoadConsents(PDO $database, int $customerId): array
{
    if (!portalTableExists($database, 'customer_consents')) {
        return [];
    }

    $query = $database->prepare(
        'SELECT c.consent_type, c.consent_status, c.metadata_json, c.recorded_at
         FROM customer_consents AS c
         INNER JOIN (
            SELECT consent_type, MAX(id) AS latest_id
            FROM customer_consents
            WHERE customer_id = :customer_id
            GROUP BY consent_type
         ) AS latest
            ON latest.latest_id = c.id'
    );
    $query->execute(['customer_id' => $customerId]);

    $result = [];

    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $metadata = [];

        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string) $row['metadata_json'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $result[(string) $row['consent_type']] = [
            'granted' => ($row['consent_status'] ?? '') === 'granted',
            'metadata' => $metadata,
            'recorded_at' => $row['recorded_at'] ?? null,
        ];
    }

    return $result;
}

function portalRecordConsent(
    PDO $database,
    int $customerId,
    string $type,
    bool $granted,
    array $metadata = []
): void {
    $allowedTypes = [
        'email_marketing',
        'sms_marketing',
        'whatsapp_marketing',
        'appointment_reminders',
        'ai_personalisation',
        'before_after_photos',
    ];

    if (!in_array($type, $allowedTypes, true)) {
        throw new InvalidArgumentException('Unsupported consent type.');
    }

    $query = $database->prepare(
        'INSERT INTO customer_consents (
            customer_id,
            consent_type,
            consent_status,
            source,
            policy_version,
            metadata_json,
            recorded_at
         ) VALUES (
            :customer_id,
            :consent_type,
            :consent_status,
            \'customer_portal\',
            :policy_version,
            :metadata_json,
            NOW()
         )'
    );

    $query->execute([
        'customer_id' => $customerId,
        'consent_type' => $type,
        'consent_status' => $granted ? 'granted' : 'revoked',
        'policy_version' => 'portal-2026-07',
        'metadata_json' => json_encode(
            $metadata,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ),
    ]);
}

function portalLoadBranches(PDO $database): array
{
    if (!portalTableExists($database, 'branches')) {
        return [];
    }

    $query = $database->query(
        'SELECT id, name, area, city
         FROM branches
         WHERE is_active = 1
         ORDER BY name ASC'
    );

    return $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];
}

function portalLoadStylists(PDO $database): array
{
    if (!portalTableExists($database, 'stylists')) {
        return [];
    }

    try {
        $query = $database->query(
            'SELECT
                st.id,
                st.public_name,
                st.specialty,
                GROUP_CONCAT(DISTINCT sba.branch_id ORDER BY sba.branch_id) AS branch_ids
             FROM stylists AS st
             LEFT JOIN staff_branch_assignments AS sba
                ON sba.staff_id = st.staff_id
               AND (sba.starts_on IS NULL OR sba.starts_on <= CURRENT_DATE)
               AND (sba.ends_on IS NULL OR sba.ends_on >= CURRENT_DATE)
             WHERE st.is_available = 1
             GROUP BY st.id, st.public_name, st.specialty, st.display_order
             ORDER BY st.display_order ASC, st.public_name ASC'
        );
    } catch (Throwable) {
        $query = $database->query(
            'SELECT id, public_name, specialty, NULL AS branch_ids
             FROM stylists
             WHERE is_available = 1
             ORDER BY display_order ASC, public_name ASC'
        );
    }

    return $query ? $query->fetchAll(PDO::FETCH_ASSOC) : [];
}

function portalLoadAuthIdentity(PDO $database, int $customerId): array
{
    if (!portalTableExists($database, 'customer_auth_identities')) {
        return [];
    }

    $query = $database->prepare(
        'SELECT provider, email_snapshot, email_verified, last_login_at
         FROM customer_auth_identities
         WHERE customer_id = :customer_id
         ORDER BY last_login_at DESC, id DESC
         LIMIT 1'
    );
    $query->execute(['customer_id' => $customerId]);

    return $query->fetch(PDO::FETCH_ASSOC) ?: [];
}

function portalProfileCompletion(array $customer, array $preferences, array $beauty): int
{
    $fields = [
        trim((string) ($customer['full_name'] ?? '')),
        trim((string) ($customer['email'] ?? '')),
        trim((string) ($customer['phone'] ?? '')),
        trim((string) ($customer['date_of_birth'] ?? '')),
        trim((string) ($customer['gender'] ?? '')),
        trim((string) ($customer['preferred_contact_method'] ?? '')),
        trim((string) ($customer['profile_image_url'] ?? '')),
        trim((string) ($preferences['preferred_branch_id'] ?? '')),
        trim((string) ($preferences['preferred_stylist_id'] ?? '')),
        trim((string) ($beauty['hair_type'] ?? '')),
        trim((string) ($beauty['skin_type'] ?? '')),
        trim((string) ($beauty['goals'] ?? '')),
    ];

    $completed = count(array_filter($fields, static fn (string $value): bool => $value !== ''));

    return (int) round(($completed / count($fields)) * 100);
}

function portalStorePreferences(
    PDO $database,
    int $customerId,
    ?int $branchId,
    ?int $stylistId,
    ?int $dayOfWeek,
    ?string $timeFrom,
    ?string $timeTo,
    string $language,
    string $notes
): void {
    if ($dayOfWeek !== null && ($dayOfWeek < 1 || $dayOfWeek > 7)) {
        throw new InvalidArgumentException('Select a valid preferred day.');
    }

    if ($timeFrom !== null && $timeTo !== null && $timeTo <= $timeFrom) {
        throw new InvalidArgumentException('Preferred end time must be later than the start time.');
    }

    if ($branchId !== null) {
        $branchQuery = $database->prepare('SELECT COUNT(*) FROM branches WHERE id = :id AND is_active = 1');
        $branchQuery->execute(['id' => $branchId]);

        if ((int) $branchQuery->fetchColumn() !== 1) {
            throw new InvalidArgumentException('The selected branch is unavailable.');
        }
    }

    if ($stylistId !== null) {
        $stylistQuery = $database->prepare('SELECT COUNT(*) FROM stylists WHERE id = :id AND is_available = 1');
        $stylistQuery->execute(['id' => $stylistId]);

        if ((int) $stylistQuery->fetchColumn() !== 1) {
            throw new InvalidArgumentException('The selected stylist is unavailable.');
        }
    }

    if (
        $branchId !== null &&
        $stylistId !== null &&
        portalTableExists($database, 'staff_branch_assignments')
    ) {
        $assignmentCount = $database->prepare(
            'SELECT COUNT(*)
             FROM stylists AS st
             INNER JOIN staff_branch_assignments AS sba
                ON sba.staff_id = st.staff_id
             WHERE st.id = :stylist_id
               AND (sba.starts_on IS NULL OR sba.starts_on <= CURRENT_DATE)
               AND (sba.ends_on IS NULL OR sba.ends_on >= CURRENT_DATE)'
        );
        $assignmentCount->execute(['stylist_id' => $stylistId]);

        if ((int) $assignmentCount->fetchColumn() > 0) {
            $branchAssignment = $database->prepare(
                'SELECT COUNT(*)
                 FROM stylists AS st
                 INNER JOIN staff_branch_assignments AS sba
                    ON sba.staff_id = st.staff_id
                 WHERE st.id = :stylist_id
                   AND sba.branch_id = :branch_id
                   AND (sba.starts_on IS NULL OR sba.starts_on <= CURRENT_DATE)
                   AND (sba.ends_on IS NULL OR sba.ends_on >= CURRENT_DATE)'
            );
            $branchAssignment->execute([
                'stylist_id' => $stylistId,
                'branch_id' => $branchId,
            ]);

            if ((int) $branchAssignment->fetchColumn() !== 1) {
                throw new InvalidArgumentException('The selected stylist is not currently assigned to that branch.');
            }
        }
    }

    $query = $database->prepare(
        'INSERT INTO customer_preferences (
            customer_id,
            preferred_branch_id,
            preferred_stylist_id,
            preferred_day_of_week,
            preferred_time_from,
            preferred_time_to,
            language_code,
            notes
         ) VALUES (
            :customer_id,
            :preferred_branch_id,
            :preferred_stylist_id,
            :preferred_day_of_week,
            :preferred_time_from,
            :preferred_time_to,
            :language_code,
            :notes
         )
         ON DUPLICATE KEY UPDATE
            preferred_branch_id = VALUES(preferred_branch_id),
            preferred_stylist_id = VALUES(preferred_stylist_id),
            preferred_day_of_week = VALUES(preferred_day_of_week),
            preferred_time_from = VALUES(preferred_time_from),
            preferred_time_to = VALUES(preferred_time_to),
            language_code = VALUES(language_code),
            notes = VALUES(notes)'
    );

    $query->execute([
        'customer_id' => $customerId,
        'preferred_branch_id' => $branchId,
        'preferred_stylist_id' => $stylistId,
        'preferred_day_of_week' => $dayOfWeek,
        'preferred_time_from' => $timeFrom,
        'preferred_time_to' => $timeTo,
        'language_code' => $language,
        'notes' => $notes !== '' ? $notes : null,
    ]);
}

function portalLogActivity(
    PDO $database,
    int $customerId,
    string $action,
    string $module,
    array $metadata = []
): void {
    if (!portalTableExists($database, 'activity_logs')) {
        return;
    }

    try {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $ipHash = $ip !== '' ? hash('sha256', $ip) : null;

        $query = $database->prepare(
            'INSERT INTO activity_logs (
                actor_type,
                actor_id,
                action,
                module,
                entity_type,
                entity_id,
                request_id,
                ip_hash,
                user_agent,
                metadata_json,
                created_at
             ) VALUES (
                \'customer\',
                :actor_id,
                :action,
                :module,
                \'customer\',
                :entity_id,
                :request_id,
                :ip_hash,
                :user_agent,
                :metadata_json,
                NOW()
             )'
        );

        $query->execute([
            'actor_id' => $customerId,
            'action' => $action,
            'module' => $module,
            'entity_id' => $customerId,
            'request_id' => bin2hex(random_bytes(8)),
            'ip_hash' => $ipHash,
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            'metadata_json' => json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
        ]);
    } catch (Throwable $error) {
        error_log('Aarvella portal activity log failed: ' . $error->getMessage());
    }
}

function portalHandleAvatarUpload(PDO $database, int $customerId, array $currentCustomer): string
{
    $file = $_FILES['profile_image'] ?? null;

    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Choose a profile photo first.');
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The profile photo could not be uploaded.');
    }

    if ((int) ($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new InvalidArgumentException('Profile photos must be smaller than 4 MB.');
    }

    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    $imageInfo = @getimagesize($temporaryPath);

    if ($imageInfo === false) {
        throw new InvalidArgumentException('Upload a valid JPG, PNG or WebP image.');
    }

    [$width, $height] = $imageInfo;

    if ($width < 120 || $height < 120 || $width > 8000 || $height > 8000) {
        throw new InvalidArgumentException('Use an image between 120 × 120 and 8000 × 8000 pixels.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($extensions[$mime])) {
        throw new InvalidArgumentException('Upload a JPG, PNG or WebP image.');
    }

    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/');
    $relativeDirectory = '/assets/uploads/customer-avatars';
    $targetDirectory = $documentRoot . $relativeDirectory;

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0755, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('The avatar upload directory could not be created.');
    }

    $hardeningFile = $targetDirectory . '/.htaccess';
    if (!is_file($hardeningFile)) {
        @file_put_contents(
            $hardeningFile,
            "Options -Indexes\n<FilesMatch \"\.(?:php|phtml|phar|cgi|pl|py|sh)$\">\n    Require all denied\n</FilesMatch>\n",
            LOCK_EX
        );
        @chmod($hardeningFile, 0644);
    }

    $filename = sprintf(
        'customer-%d-%s.%s',
        $customerId,
        bin2hex(random_bytes(10)),
        $extensions[$mime]
    );
    $targetPath = $targetDirectory . '/' . $filename;

    if (!move_uploaded_file($temporaryPath, $targetPath)) {
        throw new RuntimeException('The profile photo could not be saved.');
    }

    @chmod($targetPath, 0644);
    $relativePath = $relativeDirectory . '/' . $filename;

    $update = $database->prepare(
        'UPDATE customers
         SET profile_image_url = :profile_image_url
         WHERE id = :customer_id'
    );
    $update->execute([
        'profile_image_url' => $relativePath,
        'customer_id' => $customerId,
    ]);

    portalDeleteOwnedAvatar((string) ($currentCustomer['profile_image_url'] ?? ''), $relativePath);

    return $relativePath;
}

function portalDeleteOwnedAvatar(string $currentPath, string $replacementPath = ''): void
{
    if ($currentPath === '' || $currentPath === $replacementPath) {
        return;
    }

    $prefix = '/assets/uploads/customer-avatars/';

    if (!str_starts_with($currentPath, $prefix)) {
        return;
    }

    $filename = basename($currentPath);

    if ($filename === '' || $filename === '.' || $filename === '..') {
        return;
    }

    $documentRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/');
    $fullPath = $documentRoot . $prefix . $filename;

    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

function portalFormatCustomerSince(array $customer): string
{
    $value = (string) ($customer['customer_since'] ?? $customer['created_at'] ?? '');

    if ($value === '') {
        return 'Aarvella member';
    }

    try {
        $date = new DateTimeImmutable($value, new DateTimeZone('Asia/Kolkata'));
        return 'Member since ' . $date->format('M Y');
    } catch (Throwable) {
        return 'Aarvella member';
    }
}
