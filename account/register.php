<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/auth0.php';

try {
    $redirectUrl = 'https://aarvella.com/account/callback.php';

    $signupUrl = $auth0->signup(
        $redirectUrl,
        [
            'screen_hint' => 'signup',
            'prompt' => 'login',
        ]
    );

    header('Location: ' . $signupUrl);
    exit;
} catch (Throwable $exception) {
    error_log(
        'Aarvella Auth0 signup start failed: ' .
        $exception->getMessage()
    );

    header(
        'Location: /account/account-error.php?source=signup',
        true,
        302
    );
    exit;
}