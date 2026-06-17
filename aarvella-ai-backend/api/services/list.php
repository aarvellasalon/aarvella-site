<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(false, 'Invalid request method.', [], 405);
}

try {
    $stmt = $pdo->query("
        SELECT
            s.id,
            s.name,
            s.slug,
            s.short_description,
            s.duration_minutes,
            s.buffer_minutes,
            s.price,
            s.sale_price,
            s.service_type,
            c.name AS category_name,
            c.slug AS category_slug
        FROM services s
        LEFT JOIN service_categories c ON s.category_id = c.id
        WHERE s.is_active = 1
          AND s.is_bookable_online = 1
        ORDER BY c.display_order ASC, s.display_order ASC, s.name ASC
    ");

    $services = $stmt->fetchAll();

    respond_json(true, 'Services loaded successfully.', [
        'services' => $services
    ]);

} catch (Throwable $e) {
    respond_json(false, 'Unable to load services.', [], 500);
}