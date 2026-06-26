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

function appointmentDate(string $value): DateTimeImmutable
{
    return new DateTimeImmutable(
        $value,
        new DateTimeZone('Asia/Kolkata')
    );
}

function statusLabel(string $status): string
{
    return match ($status) {
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'rescheduled' => 'Rescheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function statusClass(string $status): string
{
    return match ($status) {
        'confirmed', 'completed' => 'is-success',
        'pending', 'rescheduled' => 'is-warning',
        'cancelled', 'no_show' => 'is-danger',
        default => 'is-neutral',
    };
}

function money(null|string|float|int $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return '₹' . number_format((float) $value, 0);
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

$customerId = (int) ($customer['id'] ?? 0);

$upcomingAppointments = [];
$pastAppointments = [];
$appointmentDataError = false;

if ($customerId > 0) {
    try {
        $database = getDatabase();
        $database->exec("SET time_zone = '+05:30'");

        $baseSelect = "
            SELECT
                a.id,
                a.appointment_code,
                a.appointment_start,
                a.appointment_end,
                a.status,
                a.total_price,
                a.advance_paid,
                a.payment_status,
                a.customer_message,
                a.cancellation_reason,
                s.name AS service_name,
                s.duration_minutes,
                s.price AS service_price,
                s.sale_price,
                st.name AS stylist_name,
                st.specialty AS stylist_specialty
            FROM appointments AS a
            INNER JOIN services AS s
                ON s.id = a.service_id
            LEFT JOIN stylists AS st
                ON st.id = a.stylist_id
        ";

        $upcomingQuery = $database->prepare(
            $baseSelect . "
            WHERE a.customer_id = :customer_id
              AND a.appointment_start >= NOW()
              AND a.status IN (
                    'pending',
                    'confirmed',
                    'rescheduled'
              )
            ORDER BY a.appointment_start ASC"
        );

        $upcomingQuery->execute([
            'customer_id' => $customerId,
        ]);

        $upcomingAppointments =
            $upcomingQuery->fetchAll();

        $pastQuery = $database->prepare(
            $baseSelect . "
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
            LIMIT 50"
        );

        $pastQuery->execute([
            'customer_id' => $customerId,
        ]);

        $pastAppointments =
            $pastQuery->fetchAll();
    } catch (Throwable $error) {
        $appointmentDataError = true;

        error_log(
            'Aarvella appointments page query failed: '
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

    <title>My Appointments | Aarvella</title>

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

<body>
    <div class="portal-app">
        <aside
            class="portal-sidebar"
            aria-label="Customer portal navigation"
        >
            <a href="/" class="sidebar-brand">
                <span class="sidebar-brand-name">AARVELLA</span>
                <span class="sidebar-brand-caption">Customer Portal</span>
            </a>

            <nav class="portal-nav">
                <p class="portal-nav-label">My account</p>

                <a
                    href="/account/dashboard.php"
                    class="portal-nav-link"
                >
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                <a
                    href="/account/appointments.php"
                    class="portal-nav-link is-active"
                    aria-current="page"
                >
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>My appointments</span>
                </a>

                <a href="/#booking" class="portal-nav-link">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                    <span>Book appointment</span>
                </a>

                <a href="#" class="portal-nav-link" data-coming-soon="Rewards">
                    <i class="fa-regular fa-star" aria-hidden="true"></i>
                    <span>Loyalty points</span>
                </a>

                <a href="#" class="portal-nav-link" data-coming-soon="Member offers">
                    <i class="fa-solid fa-tag" aria-hidden="true"></i>
                    <span>My offers</span>
                </a>

                <p class="portal-nav-label portal-nav-label-secondary">
                    Account
                </p>

                <a href="/account/profile.php" class="portal-nav-link">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <span>Profile</span>
                </a>

                <a href="#" class="portal-nav-link" data-coming-soon="Saved addresses">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                    <span>Addresses</span>
                </a>

                <a href="#" class="portal-nav-link" data-coming-soon="Payment methods">
                    <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                    <span>Payment methods</span>
                </a>

                <a href="#" class="portal-nav-link" data-coming-soon="Account settings">
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
                <a href="/" class="mobile-brand">
                    AARVELLA
                </a>

                <div class="portal-topbar-actions">
                    <button
                        class="topbar-icon-button"
                        type="button"
                        aria-label="Notifications"
                        data-coming-soon="Notifications"
                    >
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
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

                                <span class="portal-avatar-fallback">
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

            <main class="portal-content appointments-page">
                <section class="dashboard-heading">
                    <div>
                        <p class="dashboard-eyebrow">Your visits</p>
                        <h1>My appointments</h1>

                        <p class="dashboard-subtitle">
                            View upcoming visits and your Aarvella service history.
                        </p>
                    </div>

                    <a href="/#booking" class="portal-primary-button dashboard-book-button">
                        <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                        Book appointment
                    </a>
                </section>

                <?php if ($appointmentDataError): ?>
                    <section class="portal-panel">
                        <div class="portal-empty-state is-error">
                            <span class="empty-state-icon">
                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            </span>

                            <div>
                                <h3>We could not load your appointments</h3>
                                <p>Please refresh or contact Aarvella for assistance.</p>
                            </div>

                            <a
                                href="https://wa.me/919742049990"
                                class="portal-secondary-button"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Contact salon
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="appointments-section">
                        <div class="appointments-section-heading">
                            <div>
                                <p class="panel-kicker">Scheduled</p>
                                <h2>Upcoming appointments</h2>
                            </div>

                            <span><?= count($upcomingAppointments) ?></span>
                        </div>

                        <?php if ($upcomingAppointments === []): ?>
                            <div class="portal-panel">
                                <div class="portal-empty-state">
                                    <span class="empty-state-icon">
                                        <i class="fa-regular fa-calendar-check" aria-hidden="true"></i>
                                    </span>

                                    <div>
                                        <h3>No upcoming appointment</h3>
                                        <p>Your next confirmed booking will appear here.</p>
                                    </div>

                                    <a href="/#booking" class="portal-secondary-button">
                                        Book now
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="appointments-card-list">
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <?php
                                    $start = appointmentDate(
                                        (string) $appointment['appointment_start']
                                    );

                                    $end = appointmentDate(
                                        (string) $appointment['appointment_end']
                                    );

                                    $status = (string) $appointment['status'];
                                    $displayPrice = money(
                                        $appointment['total_price']
                                            ?? $appointment['sale_price']
                                            ?? $appointment['service_price']
                                    );
                                    ?>

                                    <article
                                        id="appointment-<?= (int) $appointment['id'] ?>"
                                        class="full-appointment-card"
                                    >
                                        <div class="appointment-date-tile">
                                            <strong><?= e($start->format('d')) ?></strong>
                                            <span><?= e(strtoupper($start->format('M'))) ?></span>
                                        </div>

                                        <div class="full-appointment-main">
                                            <div class="appointment-card-title-row">
                                                <div>
                                                    <p class="appointment-code">
                                                        <?= e((string) $appointment['appointment_code']) ?>
                                                    </p>

                                                    <h3><?= e((string) $appointment['service_name']) ?></h3>
                                                </div>

                                                <span
                                                    class="appointment-status <?= e(statusClass($status)) ?>"
                                                >
                                                    <?= e(statusLabel($status)) ?>
                                                </span>
                                            </div>

                                            <div class="appointment-meta">
                                                <span>
                                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                                    <?= e($start->format('D, d M Y · h:i A')) ?>
                                                    –
                                                    <?= e($end->format('h:i A')) ?>
                                                </span>

                                                <span>
                                                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                                                    <?= e(
                                                        $appointment['stylist_name']
                                                            ? 'With ' . (string) $appointment['stylist_name']
                                                            : 'Stylist to be assigned'
                                                    ) ?>
                                                </span>

                                                <span>
                                                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                                                    Aarvella · Karanpur, Dehradun
                                                </span>

                                                <?php if ($displayPrice !== null): ?>
                                                    <span>
                                                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                                        <?= e($displayPrice) ?>
                                                        ·
                                                        <?= e(ucwords(str_replace('_', ' ', (string) $appointment['payment_status']))) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (!empty($appointment['customer_message'])): ?>
                                                <p class="appointment-customer-note">
                                                    <strong>Your note:</strong>
                                                    <?= e((string) $appointment['customer_message']) ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="appointment-card-actions">
                                                <a
                                                    href="/#booking"
                                                    class="portal-secondary-button"
                                                >
                                                    Book another
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
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="appointments-section">
                        <div class="appointments-section-heading">
                            <div>
                                <p class="panel-kicker">History</p>
                                <h2>Past appointments</h2>
                            </div>

                            <span><?= count($pastAppointments) ?></span>
                        </div>

                        <?php if ($pastAppointments === []): ?>
                            <div class="portal-panel">
                                <div class="compact-empty-state">
                                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>

                                    <div>
                                        <strong>No past appointments yet</strong>
                                        <span>Your completed service history will appear here.</span>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="appointments-card-list">
                                <?php foreach ($pastAppointments as $appointment): ?>
                                    <?php
                                    $start = appointmentDate(
                                        (string) $appointment['appointment_start']
                                    );

                                    $end = appointmentDate(
                                        (string) $appointment['appointment_end']
                                    );

                                    $status = (string) $appointment['status'];
                                    $displayPrice = money(
                                        $appointment['total_price']
                                            ?? $appointment['sale_price']
                                            ?? $appointment['service_price']
                                    );
                                    ?>

                                    <article
                                        id="appointment-<?= (int) $appointment['id'] ?>"
                                        class="full-appointment-card"
                                    >
                                        <div class="appointment-date-tile">
                                            <strong><?= e($start->format('d')) ?></strong>
                                            <span><?= e(strtoupper($start->format('M'))) ?></span>
                                        </div>

                                        <div class="full-appointment-main">
                                            <div class="appointment-card-title-row">
                                                <div>
                                                    <p class="appointment-code">
                                                        <?= e((string) $appointment['appointment_code']) ?>
                                                    </p>

                                                    <h3><?= e((string) $appointment['service_name']) ?></h3>
                                                </div>

                                                <span
                                                    class="appointment-status <?= e(statusClass($status)) ?>"
                                                >
                                                    <?= e(statusLabel($status)) ?>
                                                </span>
                                            </div>

                                            <div class="appointment-meta">
                                                <span>
                                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                                    <?= e($start->format('D, d M Y · h:i A')) ?>
                                                    –
                                                    <?= e($end->format('h:i A')) ?>
                                                </span>

                                                <span>
                                                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                                                    <?= e(
                                                        $appointment['stylist_name']
                                                            ? 'With ' . (string) $appointment['stylist_name']
                                                            : 'Aarvella stylist'
                                                    ) ?>
                                                </span>

                                                <?php if ($displayPrice !== null): ?>
                                                    <span>
                                                        <i class="fa-solid fa-indian-rupee-sign" aria-hidden="true"></i>
                                                        <?= e($displayPrice) ?>
                                                        ·
                                                        <?= e(ucwords(str_replace('_', ' ', (string) $appointment['payment_status']))) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if (
                                                $status === 'cancelled'
                                                && !empty($appointment['cancellation_reason'])
                                            ): ?>
                                                <p class="appointment-customer-note is-cancelled">
                                                    <strong>Cancellation:</strong>
                                                    <?= e((string) $appointment['cancellation_reason']) ?>
                                                </p>
                                            <?php endif; ?>

                                            <div class="appointment-card-actions">
                                                <a
                                                    href="/#booking"
                                                    class="portal-secondary-button"
                                                >
                                                    Rebook service
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <nav
        class="portal-mobile-nav"
        aria-label="Mobile customer portal navigation"
    >
        <a href="/account/dashboard.php">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Home</span>
        </a>

        <a
            href="/account/appointments.php"
            class="is-active"
            aria-current="page"
        >
            <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            <span>Visits</span>
        </a>

        <a href="/#booking" class="mobile-book-action">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </a>

        <a href="#" data-coming-soon="Rewards">
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
