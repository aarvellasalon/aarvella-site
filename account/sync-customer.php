<?php

declare(strict_types=1);

require_once dirname(__DIR__)
    . '/api/config/database.php';

/**
 * Create or update the local Aarvella customer linked
 * to an authenticated Auth0 user.
 */
function syncAuth0Customer(array $auth0User): int
{
    $authUserId = trim(
        (string) ($auth0User['sub'] ?? '')
    );

    $email = strtolower(
        trim((string) ($auth0User['email'] ?? ''))
    );

    /*
     * Auth0 may return "name", or separate given/family names.
     */
    $fullName = trim(
        (string) ($auth0User['name'] ?? '')
    );

    if ($fullName === '') {
        $givenName = trim(
            (string) ($auth0User['given_name'] ?? '')
        );

        $familyName = trim(
            (string) ($auth0User['family_name'] ?? '')
        );

        $fullName = trim(
            $givenName . ' ' . $familyName
        );
    }

    /*
     * Google normally does not return a phone number.
     * It may be available for other Auth0 connections.
     */
    $phone = trim(
        (string) ($auth0User['phone_number'] ?? '')
    );

    $profileImage = trim(
        (string) ($auth0User['picture'] ?? '')
    );

    $emailVerified =
        !empty($auth0User['email_verified']) ? 1 : 0;

    if ($authUserId === '') {
        throw new RuntimeException(
            'Auth0 did not provide a user identifier.'
        );
    }

    if ($email === '') {
        throw new RuntimeException(
            'Auth0 did not provide an email address.'
        );
    }

    if ($emailVerified !== 1) {
        throw new RuntimeException(
            'A verified email address is required.'
        );
    }

    /*
     * full_name is NOT NULL in your database.
     * Generate a sensible fallback when Auth0 provides no name.
     */
    if ($fullName === '') {
        $emailUsername = explode('@', $email)[0];

        $emailUsername = str_replace(
            ['.', '_', '-'],
            ' ',
            $emailUsername
        );

        $fullName = ucwords(
            trim($emailUsername)
        );
    }

    if ($fullName === '') {
        $fullName = 'Aarvella Customer';
    }

    $database = getDatabase();
    $database->beginTransaction();

    try {
        /*
         * First, find the customer using the immutable Auth0
         * subject identifier.
         */
        $findByAuthId = $database->prepare(
            "SELECT
                id,
                full_name,
                phone
             FROM customers
             WHERE auth_user_id = :auth_user_id
             LIMIT 1
             FOR UPDATE"
        );

        $findByAuthId->execute([
            'auth_user_id' => $authUserId,
        ]);

        $customer = $findByAuthId->fetch();

        /*
         * Existing Auth0-linked customer:
         * update profile and login information.
         */
        if ($customer) {
            $customerId = (int) $customer['id'];

            $update = $database->prepare(
                "UPDATE customers
                 SET
                    full_name = COALESCE(
                        NULLIF(:full_name, ''),
                        full_name
                    ),

                    email = :email,

                    phone = COALESCE(
                        NULLIF(:phone, ''),
                        phone
                    ),

                    email_verified = :email_verified,

                    profile_image_url = COALESCE(
                        NULLIF(:profile_image_url, ''),
                        profile_image_url
                    ),

                    last_login_at = NOW(),
                    account_status = 'active'

                 WHERE id = :customer_id"
            );

            $update->execute([
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'email_verified' => $emailVerified,
                'profile_image_url' => $profileImage,
                'customer_id' => $customerId,
            ]);

            $database->commit();

            return $customerId;
        }

        /*
         * No Auth0-linked record was found.
         *
         * Look for an existing guest/website customer using the
         * same verified email so previous appointments can be
         * associated with the login.
         */
        $findByEmail = $database->prepare(
            "SELECT
                id,
                auth_user_id,
                full_name,
                phone
             FROM customers
             WHERE LOWER(email) = :email
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE"
        );

        $findByEmail->execute([
            'email' => $email,
        ]);

        $existingCustomer = $findByEmail->fetch();

        if ($existingCustomer) {
            $existingAuthId = trim(
                (string) (
                    $existingCustomer['auth_user_id']
                    ?? ''
                )
            );

            /*
             * Do not overwrite an existing different Auth0 account.
             */
            if (
                $existingAuthId !== '' &&
                $existingAuthId !== $authUserId
            ) {
                throw new RuntimeException(
                    'This email is already linked to another Aarvella account.'
                );
            }

            $customerId =
                (int) $existingCustomer['id'];

            $attach = $database->prepare(
                "UPDATE customers
                 SET
                    auth_user_id = :auth_user_id,

                    full_name = COALESCE(
                        NULLIF(:full_name, ''),
                        full_name
                    ),

                    phone = COALESCE(
                        NULLIF(:phone, ''),
                        phone
                    ),

                    email_verified = 1,

                    profile_image_url = COALESCE(
                        NULLIF(:profile_image_url, ''),
                        profile_image_url
                    ),

                    last_login_at = NOW(),
                    account_status = 'active'

                 WHERE id = :customer_id"
            );

            $attach->execute([
                'auth_user_id' => $authUserId,
                'full_name' => $fullName,
                'phone' => $phone,
                'profile_image_url' => $profileImage,
                'customer_id' => $customerId,
            ]);

            $database->commit();

            return $customerId;
        }

        /*
         * No existing Aarvella customer was found.
         * Create a new local customer record.
         *
         * city and customer_type will use their database defaults:
         * city          = Dehradun
         * customer_type = website_lead
         */
        $insert = $database->prepare(
            "INSERT INTO customers (
                full_name,
                email,
                phone,
                auth_user_id,
                email_verified,
                profile_image_url,
                marketing_consent,
                account_status,
                last_login_at
             ) VALUES (
                :full_name,
                :email,
                NULLIF(:phone, ''),
                :auth_user_id,
                :email_verified,
                NULLIF(:profile_image_url, ''),
                0,
                'active',
                NOW()
             )"
        );

        $insert->execute([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'auth_user_id' => $authUserId,
            'email_verified' => $emailVerified,
            'profile_image_url' => $profileImage,
        ]);

        $customerId =
            (int) $database->lastInsertId();

        $database->commit();

        return $customerId;
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        error_log(
            'Auth0 customer synchronisation failed: '
            . $error->getMessage()
        );

        throw $error;
    }
}