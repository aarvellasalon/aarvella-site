<?php

declare(strict_types=1);

require_once __DIR__ . '/require-auth.php';

$identity = requireAuthenticatedCustomer($auth0);
$customer = $identity['customer'];
$auth0User = $identity['auth0_user'] ?? [];

function e(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function portalAppointmentDate(string $value): DateTimeImmutable
{
    return new DateTimeImmutable(
        $value,
        new DateTimeZone('Asia/Kolkata')
    );
}

function portalStatusLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'in_progress' => 'In progress',
        'rescheduled' => 'Rescheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function portalStatusClass(string $status): string
{
    return match ($status) {
        'confirmed', 'checked_in', 'in_progress', 'completed' => 'is-success',
        'pending', 'rescheduled', 'draft' => 'is-warning',
        'cancelled', 'no_show' => 'is-danger',
        default => 'is-neutral',
    };
}

function portalMoney(null|string|float|int $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return '₹' . number_format((float) $value, 0);
}


function portalServiceThumbnail(string $serviceName): string
{
    $name = mb_strtolower(trim($serviceName));

    $skinKeywords = [
        'facial',
        'skin',
        'glow',
        'detan',
        'wax',
        'thread',
        'cleanup',
        'clean up',
        'hydra',
    ];

    foreach ($skinKeywords as $keyword) {
        if (str_contains($name, $keyword)) {
            return '/assets/images/customer-portal/thumb-skin.webp';
        }
    }

    $groomingKeywords = [
        'beard',
        'shave',
        'moustache',
        'men',
        'male',
        'grooming',
    ];

    foreach ($groomingKeywords as $keyword) {
        if (str_contains($name, $keyword)) {
            return '/assets/images/customer-portal/thumb-grooming.webp';
        }
    }

    return '/assets/images/customer-portal/thumb-hair.webp';
}

$displayName = trim(
    (string) ($customer['full_name'] ?? '')
);

if ($displayName === '') {
    $displayName = 'Aarvella Customer';
}

$firstName = trim(
    explode(' ', $displayName)[0] ?? $displayName
);

$profileImage = trim(
    (string) ($customer['profile_image_url'] ?? '')
);

if ($profileImage === '') {
    $profileImage = trim(
        (string) ($auth0User['picture'] ?? '')
    );
}

$email = trim(
    (string) ($customer['email'] ?? '')
);

$initial = mb_strtoupper(
    mb_substr($displayName, 0, 1)
);

/*
 * Phase 1 profile-completion score.
 * The score is calculated only from fields already present
 * in the Aarvella customers table.
 */
$profileFields = [
    $customer['full_name'] ?? null,
    $customer['email'] ?? null,
    $customer['phone'] ?? null,
    $customer['date_of_birth'] ?? null,
    $customer['gender'] ?? null,
    $customer['preferred_contact_method'] ?? null,
    $customer['profile_image_url'] ?? null,
];

$completedFields = count(
    array_filter(
        $profileFields,
        static fn ($value): bool =>
            $value !== null &&
            trim((string) $value) !== ''
    )
);

$profileCompletion = (int) round(
    ($completedFields / count($profileFields)) * 100
);

/*
 * Customer data is always derived from the authenticated
 * Auth0 session. No customer ID is accepted from the browser.
 */
$customerId = (int) ($customer['id'] ?? 0);

$upcomingAppointment = null;
$recentAppointments = [];
$appointmentDataError = false;
$loyaltyPoints = 0;

if ($customerId > 0) {
    try {
        $database = getDatabase();

        /*
         * appointments.appointment_start stores Aarvella's
         * local appointment time. Use the India offset for
         * the current database session.
         */
        $database->exec("SET time_zone = '+05:30'");

        /*
         * DBv2 stores one or more services against an appointment in
         * appointment_services. The correlated subqueries avoid
         * ONLY_FULL_GROUP_BY issues and preserve one row per appointment.
         */
        $upcomingQuery = $database->prepare(
            "SELECT
                a.id,
                a.appointment_code,
                a.appointment_start,
                a.appointment_end,
                a.status,
                a.total_price,
                a.advance_paid,
                a.payment_status,
                b.name AS branch_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            aps.service_name_snapshot
                            ORDER BY aps.id
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        WHERE aps.appointment_id = a.id
                          AND aps.status <> 'cancelled'
                    ),
                    'Aarvella service'
                ) AS service_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT st.public_name
                            ORDER BY st.public_name
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        INNER JOIN stylists AS st
                            ON st.id = aps.stylist_id
                        WHERE aps.appointment_id = a.id
                          AND aps.status <> 'cancelled'
                    ),
                    (
                        SELECT st2.public_name
                        FROM stylists AS st2
                        WHERE st2.id = a.primary_stylist_id
                        LIMIT 1
                    )
                ) AS stylist_name
             FROM appointments AS a
             INNER JOIN branches AS b
                ON b.id = a.branch_id
             WHERE a.customer_id = :customer_id
               AND a.appointment_start >= NOW()
               AND a.status IN (
                    'pending',
                    'confirmed',
                    'checked_in',
                    'in_progress',
                    'rescheduled'
               )
             ORDER BY a.appointment_start ASC
             LIMIT 1"
        );

        $upcomingQuery->execute([
            'customer_id' => $customerId,
        ]);

        $upcomingAppointment =
            $upcomingQuery->fetch() ?: null;

        $recentQuery = $database->prepare(
            "SELECT
                a.id,
                a.appointment_code,
                a.appointment_start,
                a.appointment_end,
                a.status,
                a.total_price,
                a.payment_status,
                b.name AS branch_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            aps.service_name_snapshot
                            ORDER BY aps.id
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        WHERE aps.appointment_id = a.id
                    ),
                    'Aarvella service'
                ) AS service_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT st.public_name
                            ORDER BY st.public_name
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        INNER JOIN stylists AS st
                            ON st.id = aps.stylist_id
                        WHERE aps.appointment_id = a.id
                    ),
                    (
                        SELECT st2.public_name
                        FROM stylists AS st2
                        WHERE st2.id = a.primary_stylist_id
                        LIMIT 1
                    )
                ) AS stylist_name
             FROM appointments AS a
             INNER JOIN branches AS b
                ON b.id = a.branch_id
             WHERE a.customer_id = :customer_id
               AND (
                    a.appointment_start < NOW()
                    OR a.status IN (
                        'completed',
                        'cancelled',
                        'no_show'
                    )
               )
             ORDER BY a.appointment_start DESC
             LIMIT 3"
        );

        $recentQuery->execute([
            'customer_id' => $customerId,
        ]);

        $recentAppointments =
            $recentQuery->fetchAll();

        /*
         * Loyalty is optional in Phase 1. No account simply means zero points.
         */
        $loyaltyQuery = $database->prepare(
            "SELECT COALESCE(SUM(points_balance), 0)
             FROM loyalty_accounts
             WHERE customer_id = :customer_id
               AND status = 'active'"
        );

        $loyaltyQuery->execute([
            'customer_id' => $customerId,
        ]);

        $loyaltyPoints = (int) $loyaltyQuery->fetchColumn();
    } catch (Throwable $error) {
        $appointmentDataError = true;

        error_log(
            'Aarvella dashboard appointment query failed: '
            . $error->getMessage()
        );
    }
}

$cssPath = $_SERVER['DOCUMENT_ROOT']
    . '/assets/css/customer-portal.css';

$jsPath = $_SERVER['DOCUMENT_ROOT']
    . '/assets/js/customer-portal.js';

$cssVersion = is_file($cssPath)
    ? (string) filemtime($cssPath)
    : '1';

$jsVersion = is_file($jsPath)
    ? (string) filemtime($jsPath)
    : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0, viewport-fit=cover"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <meta
        name="theme-color"
        content="#090909"
    >

    <title>My Account | Aarvella</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/customer-portal.css?v=<?= e($cssVersion) ?>"
    >
</head>

<body class="portal-body">
    <div
        class="portal-mobile-overlay"
        data-portal-overlay
        aria-hidden="true"
    ></div>

    <div class="portal-app">
        <aside
            id="portalSidebar"
            class="portal-sidebar"
            aria-label="Customer portal navigation"
            data-portal-sidebar
        >
            <a
                href="/"
                class="sidebar-brand"
                aria-label="Return to Aarvella homepage"
            >
                <span class="sidebar-brand-name">AARVELLA</span>
                <span class="sidebar-brand-caption">Customer Portal</span>
            </a>

            <nav class="portal-nav">
                <p class="portal-nav-label">My account</p>

                <a
                    href="/account/dashboard.php"
                    class="portal-nav-link is-active"
                    aria-current="page"
                >
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                <a
                    href="/account/appointments.php"
                    class="portal-nav-link"
                >
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>My appointments</span>
                </a>

                <a
                    href="/#booking"
                    class="portal-nav-link"
                >
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Book appointment</span>
                </a>

                <a
                    href="#"
                    class="portal-nav-link"
                    data-coming-soon="Rewards"
                >
                    <i class="fa-regular fa-star" aria-hidden="true"></i>
                    <span>Loyalty points</span>
                </a>

                <a
                    href="#"
                    class="portal-nav-link"
                    data-coming-soon="Member offers"
                >
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <span>My offers</span>
                </a>

                <p class="portal-nav-label portal-nav-label-secondary">
                    Account
                </p>

                <a
                    href="/account/profile.php"
                    class="portal-nav-link"
                >
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>Profile</span>
                </a>

                <a
                    href="#"
                    class="portal-nav-link"
                    data-coming-soon="Saved addresses"
                >
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span>Addresses</span>
                </a>

                <a
                    href="#"
                    class="portal-nav-link"
                    data-coming-soon="Payment methods"
                >
                    <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                    <span>Payment methods</span>
                </a>

                <a
                    href="#"
                    class="portal-nav-link"
                    data-coming-soon="Account settings"
                >
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <a
                href="/account/logout.php"
                class="portal-nav-link portal-sidebar-logout js-logout"
            >
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Log out</span>
            </a>
        </aside>

        <div class="portal-main-shell">
            <header class="portal-topbar">
                <div class="portal-topbar-leading">
                    <button
                        class="portal-menu-toggle portal-menu-button"
                        type="button"
                        aria-label="Open account menu"
                        aria-controls="portalSidebar"
                        aria-expanded="false"
                        data-portal-menu-button
                    >
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                    </button>

                    <a
                        href="/"
                        class="mobile-brand"
                        aria-label="Return to Aarvella homepage"
                    >
                        AARVELLA
                    </a>
                </div>

                <div class="portal-topbar-actions">
                    <button
                        class="topbar-icon-button"
                        type="button"
                        aria-label="Notifications"
                        data-coming-soon="Notifications"
                    >
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        <span class="notification-dot" aria-hidden="true"></span>
                    </button>

                    <div class="profile-menu">
                        <button
                            class="profile-menu-trigger"
                            type="button"
                            aria-expanded="false"
                            aria-controls="profileDropdown"
                            data-profile-menu-trigger
                        >
                            <span class="portal-avatar portal-avatar-small">
                                <?php if ($profileImage !== ''): ?>
                                    <img
                                        src="<?= e($profileImage) ?>"
                                        alt=""
                                        referrerpolicy="no-referrer"
                                        data-profile-image
                                    >
                                <?php endif; ?>

                                <span
                                    class="portal-avatar-fallback"
                                    aria-hidden="true"
                                >
                                    <?= e($initial) ?>
                                </span>
                            </span>

                            <span class="profile-menu-name">
                                <?= e($firstName) ?>
                            </span>

                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>

                        <div
                            id="profileDropdown"
                            class="profile-dropdown"
                            hidden
                            data-profile-dropdown
                        >
                            <div class="profile-dropdown-header">
                                <strong><?= e($displayName) ?></strong>
                                <span><?= e($email) ?></span>
                            </div>

                            <a href="/account/profile.php">
                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                Profile
                            </a>

                            <a href="/account/logout.php" class="js-logout">
                                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                Log out
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="portal-content">
                <section class="dashboard-heading">
                    <div>
                        <p
                            class="dashboard-eyebrow"
                            data-greeting
                        >
                            Welcome back,
                        </p>

                        <h1><?= e($displayName) ?></h1>

                        <p class="dashboard-subtitle">
                            Here is what is happening with your Aarvella account.
                        </p>
                    </div>

                    <a
                        href="/#booking"
                        class="btn-gold portal-primary-button dashboard-book-button"
                    >
                        <span class="btn-text">
                            <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                            Book appointment
                        </span>
                        <span class="btn-ripple-container" aria-hidden="true"></span>
                    </a>
                </section>

                <div class="dashboard-layout">
                    <div class="dashboard-primary-column">
                        <section class="portal-panel portal-card upcoming-panel">
                            <div class="portal-panel-heading">
                                <div>
                                    <p class="panel-kicker">Next visit</p>
                                    <h2>Upcoming appointment</h2>
                                </div>

                                <a href="/account/appointments.php">
                                    View all appointments
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>

                            <?php if ($upcomingAppointment !== null): ?>
                                <?php
                                $upcomingStart = portalAppointmentDate(
                                    (string) $upcomingAppointment['appointment_start']
                                );

                                $upcomingEnd = portalAppointmentDate(
                                    (string) $upcomingAppointment['appointment_end']
                                );

                                $upcomingStatus = (string) $upcomingAppointment['status'];
                                $upcomingPrice = portalMoney(
                                    $upcomingAppointment['total_price']
                                );
                                ?>

                                <article
                                    id="appointment-<?= (int) $upcomingAppointment['id'] ?>"
                                    class="appointment-card"
                                >
                                    <div class="appointment-date-tile">
                                        <strong><?= e($upcomingStart->format('d')) ?></strong>
                                        <span><?= e(strtoupper($upcomingStart->format('M'))) ?></span>
                                    </div>

                                    <div class="appointment-card-content">
                                        <div class="appointment-card-title-row">
                                            <div>
                                                <p class="appointment-code">
                                                    <?= e((string) $upcomingAppointment['appointment_code']) ?>
                                                </p>

                                                <h3>
                                                    <?= e((string) $upcomingAppointment['service_name']) ?>
                                                </h3>
                                            </div>

                                            <span
                                                class="appointment-status <?= e(portalStatusClass($upcomingStatus)) ?>"
                                            >
                                                <?= e(portalStatusLabel($upcomingStatus)) ?>
                                            </span>
                                        </div>

                                        <div class="appointment-meta">
                                            <span>
                                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                                <?= e($upcomingStart->format('D, d M Y')) ?>
                                                ·
                                                <?= e($upcomingStart->format('h:i A')) ?>
                                                –
                                                <?= e($upcomingEnd->format('h:i A')) ?>
                                            </span>

                                            <span>
                                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                                <?= e(
                                                    $upcomingAppointment['stylist_name']
                                                        ? 'With ' . (string) $upcomingAppointment['stylist_name']
                                                        : 'Stylist to be assigned'
                                                ) ?>
                                            </span>

                                            <span>
                                                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                                <?= e(
                                                    (string) (
                                                        $upcomingAppointment['branch_name']
                                                        ?? 'Aarvella Karanpur'
                                                    )
                                                ) ?>
                                            </span>

                                            <?php if ($upcomingPrice !== null): ?>
                                                <span>
                                                    <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                                    <?= e($upcomingPrice) ?>
                                                    ·
                                                    <?= e(ucwords(str_replace('_', ' ', (string) $upcomingAppointment['payment_status']))) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="appointment-card-actions">
                                            <a
                                                href="/account/appointments.php#appointment-<?= (int) $upcomingAppointment['id'] ?>"
                                                class="btn-outline portal-secondary-button portal-button-compact"
                                            >
                                                <span class="btn-text">View details</span>
                                                <span class="btn-ripple-container" aria-hidden="true"></span>
                                            </a>

                                            <button
                                                type="button"
                                                class="appointment-text-action"
                                                data-coming-soon="Online rescheduling"
                                            >
                                                Reschedule
                                            </button>

                                            <button
                                                type="button"
                                                class="appointment-text-action is-danger"
                                                data-coming-soon="Online cancellation"
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            <?php elseif ($appointmentDataError): ?>
                                <div class="portal-empty-state is-error">
                                    <span class="empty-state-icon">
                                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <h3>Appointments are temporarily unavailable</h3>

                                        <p>
                                            Your account is safe. Please refresh the page or contact Aarvella if the issue continues.
                                        </p>
                                    </div>

                                    <a
                                        href="https://wa.me/919742049990"
                                        class="btn-outline portal-secondary-button portal-button-compact"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <span class="btn-text">Contact salon</span>
                                        <span class="btn-ripple-container" aria-hidden="true"></span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="portal-empty-state">
                                    <span class="empty-state-icon">
                                        <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <h3>No upcoming appointment</h3>

                                        <p>
                                            Book your next Aarvella experience and it will appear here.
                                        </p>
                                    </div>

                                    <a
                                        href="/#booking"
                                        class="btn-gold portal-primary-button portal-button-compact"
                                    >
                                        <span class="btn-text">Book now</span>
                                        <span class="btn-ripple-container" aria-hidden="true"></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="portal-panel portal-card recent-panel">
                            <div class="portal-panel-heading">
                                <div>
                                    <p class="panel-kicker">Your history</p>
                                    <h2>Recent appointments</h2>
                                </div>

                                <a href="/account/appointments.php">
                                    View all
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>

                            <?php if ($recentAppointments !== []): ?>
                                <div class="recent-appointment-list">
                                    <?php foreach ($recentAppointments as $recentAppointment): ?>
                                        <?php
                                        $recentStart = portalAppointmentDate(
                                            (string) $recentAppointment['appointment_start']
                                        );

                                        $recentStatus = (string) $recentAppointment['status'];
                                        ?>

                                        <a
                                            id="appointment-<?= (int) $recentAppointment['id'] ?>"
                                            href="/account/appointments.php#appointment-<?= (int) $recentAppointment['id'] ?>"
                                            class="recent-appointment-item"
                                        >
                                            <span class="recent-appointment-thumb">
                                                <img
                                                    src="<?= e(portalServiceThumbnail((string) $recentAppointment['service_name'])) ?>"
                                                    alt=""
                                                    loading="lazy"
                                                    decoding="async"
                                                >
                                            </span>

                                            <span class="recent-appointment-copy">
                                                <strong>
                                                    <?= e((string) $recentAppointment['service_name']) ?>
                                                </strong>

                                                <small>
                                                    <?= e($recentStart->format('D, d M Y · h:i A')) ?>
                                                </small>

                                                <small>
                                                    <?= e(
                                                        $recentAppointment['stylist_name']
                                                            ? 'With ' . (string) $recentAppointment['stylist_name']
                                                            : 'Aarvella stylist'
                                                    ) ?>
                                                </small>
                                            </span>

                                            <span
                                                class="appointment-status <?= e(portalStatusClass($recentStatus)) ?>"
                                            >
                                                <?= e(portalStatusLabel($recentStatus)) ?>
                                            </span>

                                            <i class="fa-solid fa-chevron-right recent-row-arrow" aria-hidden="true"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif (!$appointmentDataError): ?>
                                <div class="compact-empty-state compact-empty-state-with-image">
                                    <span class="compact-empty-thumbnail" aria-hidden="true">
                                        <img
                                            src="/assets/images/customer-portal/thumb-hair.webp"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                        >
                                    </span>

                                    <div>
                                        <strong>Your service history will appear here</strong>

                                        <span>
                                            Completed appointments can be quickly rebooked.
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="portal-panel portal-card portal-card-feature referral-banner">
                            <div class="referral-copy">
                                <p class="panel-kicker">Aarvella community</p>
                                <h2>Refer and earn rewards</h2>

                                <p>
                                    Invite someone you care about. Referral benefits will be activated in the rewards phase.
                                </p>

                                <button
                                    type="button"
                                    class="btn-gold portal-primary-button"
                                    data-coming-soon="Referral rewards"
                                >
                                    <span class="btn-text">Refer a friend</span>
                                    <span class="btn-ripple-container" aria-hidden="true"></span>
                                </button>
                            </div>

                            <div class="feature-card-media feature-card-media-community" aria-hidden="true">
                                <img
                                    src="/assets/images/customer-portal/community-thumb.webp"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                                <span class="feature-card-media-icon">
                                    <i class="fa-solid fa-gift"></i>
                                </span>
                            </div>
                        </section>
                    </div>

                    <aside class="dashboard-secondary-column">
                        <section class="portal-panel portal-card loyalty-card">
                            <div class="loyalty-card-copy">
                                <p class="panel-kicker">Loyalty points</p>

                                <strong>
                                    <?= number_format($loyaltyPoints) ?>
                                    <span>pts</span>
                                </strong>

                                <p>
                                    Rewards will grow with eligible Aarvella visits.
                                </p>

                                <button
                                    type="button"
                                    class="text-link-button"
                                    data-coming-soon="Loyalty rewards"
                                >
                                    View rewards
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </button>
                            </div>

                            <i
                                class="fa-regular fa-star loyalty-watermark"
                                aria-hidden="true"
                            ></i>
                        </section>

                        <section class="portal-panel portal-card quick-actions-card">
                            <div class="portal-panel-heading portal-panel-heading-compact">
                                <div>
                                    <p class="panel-kicker">Shortcuts</p>
                                    <h2>Quick actions</h2>
                                </div>
                            </div>

                            <div class="quick-action-list">
                                <a href="/#booking">
                                    <span>
                                        <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <strong>Book appointment</strong>
                                        <small>Schedule your next visit</small>
                                    </div>
                                </a>

                                <a href="/account/appointments.php">
                                    <span>
                                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <strong>My appointments</strong>
                                        <small>View and manage bookings</small>
                                    </div>
                                </a>

                                <a href="/account/profile.php">
                                    <span>
                                        <i class="fa-regular fa-user" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <strong>Complete profile</strong>
                                        <small>Personalise your experience</small>
                                    </div>
                                </a>
                            </div>
                        </section>

                        <section class="portal-panel portal-card portal-card-feature member-offer-card">
                            <div>
                                <p class="panel-kicker">Member benefits</p>
                                <h2>Exclusive offers</h2>

                                <p>
                                    Personalised account offers will appear here when available.
                                </p>

                                <button
                                    type="button"
                                    class="btn-gold portal-primary-button"
                                    data-coming-soon="Member offers"
                                >
                                    <span class="btn-text">Explore offers</span>
                                    <span class="btn-ripple-container" aria-hidden="true"></span>
                                </button>
                            </div>

                            <div class="feature-card-media feature-card-media-offer" aria-hidden="true">
                                <img
                                    src="/assets/images/customer-portal/offer-thumb.webp"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                >
                                <span class="feature-card-media-icon">
                                    <i class="fa-solid fa-sparkles"></i>
                                </span>
                            </div>
                        </section>

                        <section class="portal-panel portal-card profile-completion-card">
                            <div class="progress-ring" style="--progress: <?= $profileCompletion ?>%;">
                                <span><?= $profileCompletion ?>%</span>
                            </div>

                            <div>
                                <p class="panel-kicker">Profile completion</p>

                                <h2>
                                    <?= $profileCompletion >= 100
                                        ? 'Profile complete'
                                        : 'Complete your profile'
                                    ?>
                                </h2>

                                <p>
                                    Add your preferences to make future bookings faster.
                                </p>

                                <a href="/account/profile.php">
                                    Complete now
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </div>
                        </section>
                    </aside>
                </div>
            </main>

            <footer class="portal-footer">
                <span>
                    <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
                    Premium products
                </span>

                <span>
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                    Certified stylists
                </span>

                <span>
                    <i class="fa-regular fa-gem" aria-hidden="true"></i>
                    Luxury care
                </span>

                <span>
                    <i class="fa-regular fa-heart" aria-hidden="true"></i>
                    Personalised service
                </span>
            </footer>
        </div>
    </div>

    <nav
        class="portal-mobile-nav"
        aria-label="Mobile customer portal navigation"
    >
        <a
            href="/account/dashboard.php"
            class="is-active"
            aria-current="page"
        >
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Home</span>
        </a>

        <a href="/account/appointments.php">
            <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            <span>Visits</span>
        </a>

        <a
            href="/#booking"
            class="mobile-book-action"
            aria-label="Book an appointment"
        >
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </a>

        <a
            href="#"
            data-coming-soon="Rewards"
        >
            <i class="fa-regular fa-star" aria-hidden="true"></i>
            <span>Rewards</span>
        </a>

        <a href="/account/profile.php">
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            <span>Profile</span>
        </a>
    </nav>

    <div
        class="portal-toast"
        role="status"
        aria-live="polite"
        hidden
        data-portal-toast
    ></div>

    <script
        src="/assets/js/customer-portal.js?v=<?= e($jsVersion) ?>"
        defer
    ></script>
</body>
</html>
