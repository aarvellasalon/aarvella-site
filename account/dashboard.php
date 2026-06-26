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
    $customer['city'] ?? null,
    $customer['area'] ?? null,
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
 * Appointment and reward values are intentionally empty
 * until the live appointments/rewards table structures are
 * connected. The UI already supports populated data later.
 */
$upcomingAppointment = null;
$recentAppointments = [];
$loyaltyPoints = 0;

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

<body>
    <div class="portal-app">
        <aside
            class="portal-sidebar"
            aria-label="Customer portal navigation"
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
                <a
                    href="/"
                    class="mobile-brand"
                    aria-label="Return to Aarvella homepage"
                >
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
                        class="portal-primary-button dashboard-book-button"
                    >
                        <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>
                        Book appointment
                    </a>
                </section>

                <div class="dashboard-layout">
                    <div class="dashboard-primary-column">
                        <section class="portal-panel upcoming-panel">
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
                                <article class="appointment-card">
                                    <!-- Live appointment data will be inserted here. -->
                                </article>
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
                                        class="portal-secondary-button"
                                    >
                                        Book now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="portal-panel recent-panel">
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
                                    <!-- Live recent appointments will be inserted here. -->
                                </div>
                            <?php else: ?>
                                <div class="compact-empty-state">
                                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>

                                    <div>
                                        <strong>Your service history will appear here</strong>

                                        <span>
                                            Completed appointments can be quickly rebooked.
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="referral-banner">
                            <div class="referral-copy">
                                <p class="panel-kicker">Aarvella community</p>
                                <h2>Refer and earn rewards</h2>

                                <p>
                                    Invite someone you care about. Referral benefits will be activated in the rewards phase.
                                </p>

                                <button
                                    type="button"
                                    class="portal-primary-button"
                                    data-coming-soon="Referral rewards"
                                >
                                    Refer a friend
                                </button>
                            </div>

                            <div class="referral-art" aria-hidden="true">
                                <i class="fa-solid fa-gift"></i>
                                <i class="fa-regular fa-heart"></i>
                            </div>
                        </section>
                    </div>

                    <aside class="dashboard-secondary-column">
                        <section class="portal-panel loyalty-card">
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

                        <section class="portal-panel quick-actions-card">
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

                        <section class="member-offer-card">
                            <div>
                                <p class="panel-kicker">Member benefits</p>
                                <h2>Exclusive offers</h2>

                                <p>
                                    Personalised account offers will appear here when available.
                                </p>

                                <button
                                    type="button"
                                    class="portal-primary-button"
                                    data-coming-soon="Member offers"
                                >
                                    Explore offers
                                </button>
                            </div>

                            <i class="fa-solid fa-sparkles" aria-hidden="true"></i>
                        </section>

                        <section class="portal-panel profile-completion-card">
                            <div class="progress-ring" style="--progress: <?= $profileCompletion ?>;">
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
