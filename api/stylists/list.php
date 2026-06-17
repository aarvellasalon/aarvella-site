<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond_json(false, 'Invalid request method.', [], 405);
}

$serviceId = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 0;

try {
    if ($serviceId > 0) {
        $stmt = $pdo->prepare("
            SELECT
                st.id,
                st.name,
                st.slug,
                st.specialty,
                st.bio,
                st.experience_years,
                st.profile_image
            FROM stylists st
            INNER JOIN stylist_services ss ON ss.stylist_id = st.id
            WHERE st.is_available = 1
              AND ss.service_id = :service_id
              AND ss.is_active = 1
            ORDER BY ss.is_primary DESC, st.display_order ASC, st.name ASC
        ");

        $stmt->execute([
            ':service_id' => $serviceId
        ]);
    } else {
        $stmt = $pdo->query("
            SELECT
                id,
                name,
                slug,
                specialty,
                bio,
                experience_years,
                profile_image
            FROM stylists
            WHERE is_available = 1
            ORDER BY display_order ASC, name ASC
        ");
    }

    $stylists = $stmt->fetchAll();

    respond_json(true, 'Stylists loaded successfully.', [
        'stylists' => $stylists
    ]);

} catch (Throwable $e) {
    respond_json(false, 'Unable to load stylists.', [], 500);
}