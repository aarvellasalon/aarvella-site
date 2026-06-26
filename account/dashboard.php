<?php

declare(strict_types=1);

require_once __DIR__ . '/require-auth.php';

$identity = requireAuthenticatedCustomer($auth0);
$customer = $identity['customer'];

$displayName = htmlspecialchars(
    (string) (
        $customer['full_name']
        ?: 'Aarvella Customer'
    ),
    ENT_QUOTES,
    'UTF-8'
);

$profileImage = htmlspecialchars(
    (string) (
        $customer['profile_image_url']
        ?: '/assets/images/default-profile.webp'
    ),
    ENT_QUOTES,
    'UTF-8'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>My Account | Aarvella</title>

    <link
        rel="stylesheet"
        href="/assets/css/customer-portal.css"
    >
</head>

<body>
    <header class="portal-header">
        <a href="/" class="portal-logo">
            AARVELLA
        </a>

        <a
            href="/account/logout.php"
            class="portal-logout"
        >
            Log out
        </a>
    </header>

    <main class="portal-dashboard">
        <section class="portal-welcome">
            <img
                src="<?= $profileImage ?>"
                alt=""
                width="72"
                height="72"
            >

            <div>
                <p>Welcome back</p>
                <h1><?= $displayName ?></h1>
            </div>
        </section>

        <section class="portal-card">
            <h2>Upcoming appointment</h2>

            <p>
                Your next appointment will appear here.
            </p>

            <button
                class="btn-gold js-book"
                type="button"
            >
                Book an appointment
            </button>
        </section>

        <section class="portal-card">
            <h2>Quick actions</h2>

            <a href="/account/appointments.php">
                My appointments
            </a>

            <a href="/account/profile.php">
                Complete my profile
            </a>
        </section>
    </main>
</body>
</html>