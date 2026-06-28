<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';
require_once __DIR__ . '/sync-customer.php';

try {
    $exchangeParameters = $auth0->getExchangeParameters();

    /*
     * A Post-Login Action can deny access for an unverified email.
     * In that case Auth0 returns error=access_denied instead of a code,
     * so no authenticated session or credentials will be created.
     */
    $authError = trim((string) ($_GET['error'] ?? ''));
    $authErrorDescription = trim(
        (string) ($_GET['error_description'] ?? '')
    );

    if ($authError !== '') {
        $isVerificationRequired =
            $authError === 'access_denied'
            && (
                stripos($authErrorDescription, 'verify') !== false
                || stripos($authErrorDescription, 'verification') !== false
                || stripos($authErrorDescription, 'email address') !== false
            );

        if ($isVerificationRequired) {
            header(
                'Location: /account/verify-email.php',
                true,
                302
            );
            exit;
        }

        throw new RuntimeException(
            'Auth0 authorization failed: '
            . $authError
            . (
                $authErrorDescription !== ''
                    ? ' - ' . $authErrorDescription
                    : ''
            )
        );
    }

    if ($exchangeParameters !== null) {
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

    $email = trim(
        (string) ($auth0User['email'] ?? '')
    );

    $emailVerified = (bool) (
        $auth0User['email_verified'] ?? false
    );

    if ($email === '') {
        throw new RuntimeException(
            'No email address was returned by Auth0.'
        );
    }

    /*
     * This is a fallback in case the Auth0 Action is later removed
     * or changed and an unverified user reaches the callback.
     */
    if (!$emailVerified) {
        header(
            'Location: /account/verify-email.php'
                . '?email=' . rawurlencode($email),
            true,
            302
        );
        exit;
    }

    syncAuth0Customer($auth0User);

    header(
        'Location: /account/dashboard.php',
        true,
        302
    );
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