<?php

declare(strict_types=1);

require_once __DIR__ . '/site-config.php';

/**
 * Generic landing page for authentication failures. Reached from:
 * account/callback.php (source=callback), account/require-auth.php
 * (no source), account/register.php (source=signup).
 *
 * Deliberately does not depend on the Auth0 SDK or the database, since
 * either of those failing is exactly what can land a visitor here.
 */

$source = isset($_GET['source']) ? trim((string) $_GET['source']) : '';

$copy = match ($source) {
    'callback' => [
        'heading' => 'We could not complete your sign-in',
        'body' => 'Something interrupted the sign-in process. This is usually temporary — please try again.',
    ],
    'signup' => [
        'heading' => 'We could not start your sign-up',
        'body' => 'Something interrupted the sign-up process. This is usually temporary — please try again.',
    ],
    default => [
        'heading' => 'Something went wrong',
        'body' => 'We could not verify your account session. Please sign in again to continue.',
    ],
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=20260727">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png?v=20260727">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png?v=20260727">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v=20260727">
    <link rel="manifest" href="/site.webmanifest?v=20260727">
    <link rel="icon" type="image/x-icon" href="/favicon.ico?v=20260727">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="robots" content="noindex, nofollow">

    <title>Account error | Aarvella</title>

    <style>
        :root {
            color-scheme: dark;
            --background: #111111;
            --panel: rgba(255, 255, 255, 0.06);
            --border: rgba(255, 255, 255, 0.12);
            --text: #f7f3eb;
            --muted: #b9b2a7;
            --gold: #d8b56d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(
                    circle at top,
                    rgba(216, 181, 109, 0.14),
                    transparent 36%
                ),
                var(--background);
            color: var(--text);
            font-family: Arial, sans-serif;
        }

        .error-card {
            width: min(100%, 560px);
            padding: 44px 34px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 24px;
            background: var(--panel);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.34);
            backdrop-filter: blur(18px);
        }

        .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(216, 181, 109, 0.45);
            border-radius: 50%;
            color: var(--gold);
            font-size: 34px;
        }

        h1 {
            margin: 0 0 16px;
            font-size: clamp(30px, 6vw, 44px);
            line-height: 1.1;
        }

        p {
            margin: 0 auto 14px;
            max-width: 470px;
            color: var(--muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .actions {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .button {
            min-height: 48px;
            padding: 13px 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
        }

        .button-primary {
            background: var(--gold);
            color: #111111;
        }

        .button-secondary {
            border: 1px solid var(--border);
            color: var(--text);
        }

        .note {
            margin-top: 26px;
            font-size: 14px;
        }

        .note a {
            color: var(--gold);
        }
    </style>
</head>

<body>
    <main class="error-card">
        <div class="icon" aria-hidden="true">!</div>

        <h1><?= htmlspecialchars($copy['heading'], ENT_QUOTES, 'UTF-8') ?></h1>

        <p><?= htmlspecialchars($copy['body'], ENT_QUOTES, 'UTF-8') ?></p>

        <div class="actions">
            <a class="button button-primary" href="/account/login.php">
                Try signing in again
            </a>

            <a class="button button-secondary" href="/">
                Return home
            </a>
        </div>

        <p class="note">
            Still stuck? Message Aarvella on
            <a href="https://wa.me/<?= AARVELLA_WHATSAPP_NUMBER ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
            and we'll help you get in.
        </p>
    </main>
</body>
</html>
