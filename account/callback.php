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

    $email = trim((string) ($auth0User['email'] ?? ''));
    $emailVerified = (bool) ($auth0User['email_verified'] ?? false);

    if ($email === '') {
        throw new RuntimeException(
            'No email address was returned by Auth0.'
        );
    }

    /*
     * A newly registered Auth0 user normally reaches this callback before
     * verifying their email. This is an expected signup state, not an error.
     */
    if (!$emailVerified) {
        $verificationPage = '/account/verify-email.php'
            . '?email=' . rawurlencode($email);

        header('Location: ' . $verificationPage, true, 302);
        exit;
    }

    /*
     * Only create or update the local customer record after Auth0 confirms
     * that the user's email address has been verified.
     */
    syncAuth0Customer($auth0User);

    header('Location: /account/dashboard.php', true, 302);
    exit;
} catch (Throwable $error) {
    error_log(
        'Auth0 callback failed: '
        . $error->getMessage()
    );

    header(
        'Location: /account/account-error.php?source=callback',
        true,
        302
    );

    exit;
}
