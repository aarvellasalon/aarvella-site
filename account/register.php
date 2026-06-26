<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';

$credentials = $auth0->getCredentials();

if (
    $credentials !== null &&
    !$credentials->accessTokenExpired
) {
    header('Location: /account/dashboard.php');
    exit;
}

$signupUrl = $auth0->login(
    null,
    [
        'screen_hint' => 'signup',
        'prompt' => 'login',
    ]
);

header('Location: ' . $signupUrl);
exit;