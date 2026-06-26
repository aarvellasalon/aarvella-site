<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';
require_once __DIR__ . '/sync-customer.php';

try {
    if ($auth0->getExchangeParameters() !== null) {
        $auth0->exchange();
    }

    $credentials = $auth0->getCredentials();

    if (
        $credentials === null ||
        $credentials->accessTokenExpired
    ) {
        throw new RuntimeException(
            'Authentication session was not created.'
        );
    }

    $auth0User = $credentials->user;

    if (
        empty($auth0User['email']) ||
        empty($auth0User['email_verified'])
    ) {
        throw new RuntimeException(
            'A verified email address is required.'
        );
    }

    syncAuth0Customer($auth0User);

    header('Location: /account/dashboard.php');
    exit;
} catch (Throwable $error) {
    error_log(
        'Auth0 callback failed: '
        . $error->getMessage()
    );

    header(
        'Location: /account/account-error.php'
    );

    exit;
}