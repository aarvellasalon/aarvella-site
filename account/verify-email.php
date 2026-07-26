<?php

declare(strict_types=1);

$email = isset($_GET['email'])
    ? trim((string) $_GET['email'])
    : '';

$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
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

    <title>Verify your email | Aarvella</title>

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

        .verification-card {
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

        .email {
            color: var(--text);
            font-weight: 700;
            overflow-wrap: anywhere;
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
    </style>
</head>

<body>
    <main class="verification-card">
        <div class="icon" aria-hidden="true">✉</div>

        <h1>Check your email</h1>

        <p>
            We have sent you a verification email
            <?php if ($safeEmail !== ''): ?>
                at <span class="email"><?= $safeEmail ?></span>
            <?php endif; ?>.
        </p>

        <p>
            Open the email and click the verification link before signing in
            to your Aarvella account.
        </p>

        <div class="actions">
            <a class="button button-primary" href="/account/login.php">
                Continue to login
            </a>

            <a class="button button-secondary" href="/">
                Return home
            </a>
        </div>

        <p class="note">
            Can’t find the email? Check your Spam or Junk folder.
        </p>
    </main>
</body>
</html>