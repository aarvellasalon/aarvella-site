<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';

require_once dirname(__DIR__)
    . '/api/config/database.php';

require_once __DIR__ . '/sync-customer.php';

/**
 * Require a valid Auth0 customer session and return the linked
 * Aarvella DBv2 customer.
 *
 * @return array{
 *     auth0_user: array<string, mixed>,
 *     customer: array<string, mixed>
 * }
 */
function requireAuthenticatedCustomer(
    \Auth0\SDK\Auth0 $auth0
): array {
    try {
        $credentials = $auth0->getCredentials();

        if (
            $credentials === null
            || $credentials->accessTokenExpired
        ) {
            header(
                'Location: /account/login.php',
                true,
                302
            );

            exit;
        }

        $user = $credentials->user ?? null;

        /*
         * Auth0 normally returns an array, but safely handle
         * an object result as well.
         */
        if (is_object($user)) {
            $user = get_object_vars($user);
        }

        if (!is_array($user)) {
            throw new RuntimeException(
                'Auth0 returned an invalid user profile.'
            );
        }

        $providerSubject = trim(
            (string) ($user['sub'] ?? '')
        );

        $email = trim(
            (string) ($user['email'] ?? '')
        );

        if ($providerSubject === '') {
            throw new RuntimeException(
                'Auth0 user subject is missing.'
            );
        }

        if ($email === '') {
            throw new RuntimeException(
                'Auth0 user email is missing.'
            );
        }

        /*
         * DBv2 lookup:
         *
         * customer_auth_identities.provider_subject
         *     ->
         * customers.id
         */
        $customer = findCustomerByAuth0Subject(
            $providerSubject
        );

        /*
         * First login after signup, or first login after the
         * DBv2 rebuild: create/synchronise the local customer.
         */
        if ($customer === null) {
            syncAuth0Customer($user);

            $customer = findCustomerByAuth0Subject(
                $providerSubject
            );
        }

        if ($customer === null) {
            throw new RuntimeException(
                'Authenticated customer could not be loaded.'
            );
        }

        return [
            'auth0_user' => $user,
            'customer' => $customer,
        ];
    } catch (Throwable $error) {
        error_log(
            'Aarvella require-auth failure: '
            . $error->getMessage()
        );

        header(
            'Location: /account/account-error.php',
            true,
            302
        );

        exit;
    }
}
