<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';
require_once dirname(__DIR__)
    . '/api/config/database.php';

function requireAuthenticatedCustomer(
    \Auth0\SDK\Auth0 $auth0
): array {
    $credentials = $auth0->getCredentials();

    if (
        $credentials === null ||
        $credentials->accessTokenExpired
    ) {
        header('Location: /account/login.php');
        exit;
    }

    $user = $credentials->user;

    if (
        empty($user['sub']) ||
        empty($user['email_verified'])
    ) {
        header(
            'Location: /account/account-error.php'
        );
        exit;
    }

    $database = getDatabase();

    $query = $database->prepare(
        'SELECT *
         FROM customers
         WHERE auth_user_id = :auth_user_id
           AND account_status = "active"
         LIMIT 1'
    );

    $query->execute([
        'auth_user_id' => $user['sub'],
    ]);

    $customer = $query->fetch();

    if (!$customer) {
        header(
            'Location: /account/account-error.php'
        );
        exit;
    }

    return [
        'auth0_user' => $user,
        'customer' => $customer,
    ];
}