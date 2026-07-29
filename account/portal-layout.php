<?php

declare(strict_types=1);

require_once __DIR__ . '/portal-common.php';

function portalAssetVersion(string $relativePath): string
{
    $path = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . $relativePath;
    return is_file($path) ? (string) filemtime($path) : '1';
}

function portalRenderShellStart(array $context, string $activePage, string $title, array $extraMainClasses = []): void
{
    $displayName = trim((string) ($context['display_name'] ?? 'Aarvella Customer'));
    $firstName = trim((string) ($context['first_name'] ?? $displayName));
    $email = trim((string) ($context['email'] ?? ''));
    $profileImage = trim((string) ($context['profile_image'] ?? ''));
    $initial = mb_strtoupper(mb_substr($displayName !== '' ? $displayName : 'A', 0, 1));
    $settings = is_array($context['portal_settings'] ?? null) ? $context['portal_settings'] : [];

    $bodyClasses = ['portal-body'];

    if (!empty($settings['compact_mode'])) {
        $bodyClasses[] = 'portal-compact-mode';
    }

    if (!empty($settings['reduce_motion'])) {
        $bodyClasses[] = 'portal-reduce-motion';
    }

    if (!empty($settings['high_contrast'])) {
        $bodyClasses[] = 'portal-high-contrast';
    }

    $buttonsCssVersion = portalAssetVersion('/assets/css/buttons.css');
    $baseCssVersion = portalAssetVersion('/assets/css/customer-portal.css');
    $pagesCssVersion = portalAssetVersion('/assets/css/customer-portal-pages.css');
    $bookingCssVersion = portalAssetVersion('/assets/css/booking.css');

    $mainClasses = array_merge(['portal-content', 'portal-account-content'], $extraMainClasses);
    ?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260727">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=20260727">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=20260727">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260727">
    <link rel="manifest" href="/site.webmanifest?v=20260727">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=20260727">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#090909">
    <title><?= portalE($title) ?> | Aarvella</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/buttons.css?v=<?= portalE($buttonsCssVersion) ?>">
    <link rel="stylesheet" href="/assets/css/customer-portal.css?v=<?= portalE($baseCssVersion) ?>">
    <link rel="stylesheet" href="/assets/css/customer-portal-pages.css?v=<?= portalE($pagesCssVersion) ?>">
    <link rel="stylesheet" href="/assets/css/booking.css?v=<?= portalE($bookingCssVersion) ?>">
</head>
<body class="<?= portalE(implode(' ', $bodyClasses)) ?>">
    <div class="portal-app">
        <div class="portal-mobile-overlay" data-portal-overlay aria-hidden="true"></div>

        <aside id="portalSidebar" class="portal-sidebar" aria-label="Customer portal navigation" data-portal-sidebar>
            <a href="/" class="sidebar-brand" aria-label="Return to Aarvella homepage">
                <span class="sidebar-brand-name">AARVELLA</span>
                <span class="sidebar-brand-caption">Customer Portal</span>
            </a>

            <nav class="portal-nav">
                <p class="portal-nav-label">My account</p>

                <a href="/account/dashboard.php" class="portal-nav-link<?= $activePage === 'dashboard' ? ' is-active' : '' ?>"<?= $activePage === 'dashboard' ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>

                <a href="/account/appointments.php" class="portal-nav-link<?= $activePage === 'appointments' ? ' is-active' : '' ?>"<?= $activePage === 'appointments' ? ' aria-current="page"' : '' ?>>
                    <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
                    <span>My appointments</span>
                </a>

                <a href="/#booking" class="portal-nav-link js-book">
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

                <p class="portal-nav-label portal-nav-label-secondary">Account</p>

                <a href="/account/profile.php" class="portal-nav-link<?= $activePage === 'profile' ? ' is-active' : '' ?>"<?= $activePage === 'profile' ? ' aria-current="page"' : '' ?>>
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

                <a href="/account/settings.php" class="portal-nav-link<?= $activePage === 'settings' ? ' is-active' : '' ?>"<?= $activePage === 'settings' ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid fa-gear" aria-hidden="true"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <a href="/account/logout.php" class="portal-nav-link portal-sidebar-logout js-logout">
                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                <span>Log out</span>
            </a>
        </aside>

        <div class="portal-main-shell">
            <header class="portal-topbar">
                <div class="portal-topbar-leading">
                    <button class="portal-menu-toggle portal-menu-button" type="button" aria-label="Open account menu" aria-controls="portalSidebar" aria-expanded="false" data-portal-menu-button>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                    </button>

                    <a href="/" class="mobile-brand" aria-label="Return to Aarvella homepage">AARVELLA</a>
                </div>

                <div class="portal-topbar-actions">
                    <button class="topbar-icon-button av-btn av-btn--icon av-btn--ghost" type="button" aria-label="Notifications" data-coming-soon="Notifications">
                        <i class="fa-regular fa-bell" aria-hidden="true"></i>
                        <span class="notification-dot" aria-hidden="true"></span>
                    </button>

                    <div class="profile-menu">
                        <button class="profile-menu-trigger" type="button" aria-expanded="false" aria-controls="profileDropdown" data-profile-menu-trigger>
                            <span class="portal-avatar portal-avatar-small">
                                <?php if ($profileImage !== ''): ?>
                                    <img src="<?= portalE($profileImage) ?>" alt="" referrerpolicy="no-referrer" data-profile-image>
                                <?php endif; ?>
                                <span class="portal-avatar-fallback" aria-hidden="true"><?= portalE($initial) ?></span>
                            </span>
                            <span class="profile-menu-name"><?= portalE($firstName) ?></span>
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                        </button>

                        <div id="profileDropdown" class="profile-dropdown" hidden data-profile-dropdown>
                            <div class="profile-dropdown-header">
                                <strong><?= portalE($displayName) ?></strong>
                                <span><?= portalE($email) ?></span>
                            </div>
                            <a href="/account/profile.php">
                                <i class="fa-regular fa-user" aria-hidden="true"></i>
                                Profile
                            </a>
                            <a href="/account/settings.php">
                                <i class="fa-solid fa-gear" aria-hidden="true"></i>
                                Settings
                            </a>
                            <a href="/account/logout.php" class="js-logout">
                                <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                                Log out
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="<?= portalE(implode(' ', $mainClasses)) ?>">
    <?php
}

function portalRenderShellEnd(string $activePage): void
{
    $buttonsJsVersion = portalAssetVersion('/assets/js/buttons.js');
    $baseJsVersion = portalAssetVersion('/assets/js/customer-portal.js');
    $pagesJsVersion = portalAssetVersion('/assets/js/customer-portal-pages.js');
    $bookingJsVersion = portalAssetVersion('/assets/js/booking.js');
    $siteConfigJsVersion = portalAssetVersion('/assets/js/site-config.js');
    ?>
            </main>

            <footer class="portal-footer">
                <span><i class="fa-solid fa-shield-heart" aria-hidden="true"></i> Your data, your choices</span>
                <span><i class="fa-regular fa-gem" aria-hidden="true"></i> Personalised care</span>
                <span><i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Faster bookings</span>
                <span><i class="fa-regular fa-heart" aria-hidden="true"></i> Aarvella service</span>
            </footer>
        </div>
    </div>

    <nav class="portal-mobile-nav" aria-label="Mobile customer portal navigation">
        <a href="/account/dashboard.php">
            <i class="fa-solid fa-house" aria-hidden="true"></i>
            <span>Home</span>
        </a>
        <a href="/account/appointments.php">
            <i class="fa-regular fa-calendar-days" aria-hidden="true"></i>
            <span>Visits</span>
        </a>
        <a href="/#booking" class="mobile-book-action av-btn av-btn--icon av-btn--primary js-book" aria-label="Book an appointment">
            <i class="fa-solid fa-plus" aria-hidden="true"></i>
        </a>
        <a href="#" data-coming-soon="Rewards">
            <i class="fa-regular fa-star" aria-hidden="true"></i>
            <span>Rewards</span>
        </a>
        <a href="/account/profile.php" class="<?= in_array($activePage, ['profile', 'settings'], true) ? 'is-active' : '' ?>"<?= in_array($activePage, ['profile', 'settings'], true) ? ' aria-current="page"' : '' ?>>
            <i class="fa-regular fa-user" aria-hidden="true"></i>
            <span>Profile</span>
        </a>
    </nav>

    <div class="portal-toast" role="status" aria-live="polite" hidden data-portal-toast></div>

    <div id="booking-popup-placeholder"></div>

    <script src="/assets/js/buttons.js?v=<?= portalE($buttonsJsVersion) ?>" defer></script>
    <script src="/assets/js/customer-portal.js?v=<?= portalE($baseJsVersion) ?>" defer></script>
    <script src="/assets/js/customer-portal-pages.js?v=<?= portalE($pagesJsVersion) ?>" defer></script>
    <script src="/assets/js/site-config.js?v=<?= portalE($siteConfigJsVersion) ?>"></script>
    <script src="/assets/js/booking.js?v=<?= portalE($bookingJsVersion) ?>" defer></script>
</body>
</html>
    <?php
}
