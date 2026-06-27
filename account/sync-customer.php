<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/api/config/database.php';

function syncAuth0Customer(array $auth0User): int
{
    $provider = 'auth0';
    $providerSubject = trim((string) ($auth0User['sub'] ?? ''));
    $email = strtolower(trim((string) ($auth0User['email'] ?? '')));
    $emailVerified = !empty($auth0User['email_verified']) ? 1 : 0;
    $fullName = trim((string) ($auth0User['name'] ?? $auth0User['nickname'] ?? ''));
    $profileImage = trim((string) ($auth0User['picture'] ?? ''));

    if ($fullName === '' && $email !== '') {
        $localPart = strstr($email, '@', true);
        $fullName = $localPart !== false ? trim((string) $localPart) : '';
    }
    if ($fullName === '') {
        $fullName = 'Aarvella Customer';
    }
    if ($providerSubject === '') {
        throw new RuntimeException('Auth0 did not provide a user subject.');
    }
    if ($email === '') {
        throw new RuntimeException('Auth0 did not provide an email address.');
    }

    $database = getDatabase();
    $database->beginTransaction();

    try {
        $findIdentity = $database->prepare(
            'SELECT cai.id AS identity_id, cai.customer_id
             FROM customer_auth_identities AS cai
             WHERE cai.provider = :provider
               AND cai.provider_subject = :provider_subject
             LIMIT 1
             FOR UPDATE'
        );
        $findIdentity->execute([
            'provider' => $provider,
            'provider_subject' => $providerSubject,
        ]);
        $identity = $findIdentity->fetch();

        if ($identity) {
            $customerId = (int) $identity['customer_id'];

            $updateCustomer = $database->prepare(
                'UPDATE customers
                 SET full_name = :full_name,
                     email = CASE
                         WHEN :email_verified = 1 THEN :verified_email
                         ELSE email
                     END,
                     profile_image_url = :profile_image_url,
                     account_status = "active",
                     customer_since = COALESCE(customer_since, CURRENT_DATE),
                     last_login_at = NOW()
                 WHERE id = :customer_id'
            );
            $updateCustomer->execute([
                'full_name' => $fullName,
                'email_verified' => $emailVerified,
                'verified_email' => $email,
                'profile_image_url' => $profileImage !== '' ? $profileImage : null,
                'customer_id' => $customerId,
            ]);

            $updateIdentity = $database->prepare(
                'UPDATE customer_auth_identities
                 SET email_snapshot = :email,
                     email_verified = :email_verified,
                     last_login_at = NOW()
                 WHERE id = :identity_id'
            );
            $updateIdentity->execute([
                'email' => $email,
                'email_verified' => $emailVerified,
                'identity_id' => (int) $identity['identity_id'],
            ]);

            $database->commit();
            return $customerId;
        }

        $customerId = null;

        if ($emailVerified === 1) {
            $findCustomerByEmail = $database->prepare(
                'SELECT id
                 FROM customers
                 WHERE LOWER(email) = :email
                 LIMIT 1
                 FOR UPDATE'
            );
            $findCustomerByEmail->execute(['email' => $email]);
            $existingCustomer = $findCustomerByEmail->fetch();

            if ($existingCustomer) {
                $customerId = (int) $existingCustomer['id'];

                $existingIdentity = $database->prepare(
                    'SELECT provider_subject
                     FROM customer_auth_identities
                     WHERE customer_id = :customer_id
                       AND provider = :provider
                     LIMIT 1
                     FOR UPDATE'
                );
                $existingIdentity->execute([
                    'customer_id' => $customerId,
                    'provider' => $provider,
                ]);
                $linked = $existingIdentity->fetch();

                if ($linked && (string) $linked['provider_subject'] !== $providerSubject) {
                    throw new RuntimeException('This email is already linked to another Auth0 identity.');
                }

                $updateCustomer = $database->prepare(
                    'UPDATE customers
                     SET full_name = :full_name,
                         profile_image_url = :profile_image_url,
                         account_status = "active",
                         customer_since = COALESCE(customer_since, CURRENT_DATE),
                         last_login_at = NOW()
                     WHERE id = :customer_id'
                );
                $updateCustomer->execute([
                    'full_name' => $fullName,
                    'profile_image_url' => $profileImage !== '' ? $profileImage : null,
                    'customer_id' => $customerId,
                ]);
            }
        }

        if ($customerId === null) {
            $customerCode = createUniqueCustomerCode($database);
            $insertCustomer = $database->prepare(
                'INSERT INTO customers (
                    customer_code, full_name, email, customer_type,
                    profile_image_url, account_status, customer_since, last_login_at
                 ) VALUES (
                    :customer_code, :full_name, :email, "website_lead",
                    :profile_image_url, "active", CURRENT_DATE, NOW()
                 )'
            );
            $insertCustomer->execute([
                'customer_code' => $customerCode,
                'full_name' => $fullName,
                'email' => $emailVerified === 1 ? $email : null,
                'profile_image_url' => $profileImage !== '' ? $profileImage : null,
            ]);
            $customerId = (int) $database->lastInsertId();
        }

        $insertIdentity = $database->prepare(
            'INSERT INTO customer_auth_identities (
                customer_id, provider, provider_subject,
                email_snapshot, email_verified, last_login_at
             ) VALUES (
                :customer_id, :provider, :provider_subject,
                :email, :email_verified, NOW()
             )'
        );
        $insertIdentity->execute([
            'customer_id' => $customerId,
            'provider' => $provider,
            'provider_subject' => $providerSubject,
            'email' => $email,
            'email_verified' => $emailVerified,
        ]);

        $database->commit();
        return $customerId;
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }
        error_log('Aarvella Auth0 customer sync failed: ' . $error->getMessage());
        throw $error;
    }
}

function createUniqueCustomerCode(PDO $database): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = 'ARV-CUS-' . strtoupper(bin2hex(random_bytes(6)));
        $check = $database->prepare(
            'SELECT 1 FROM customers WHERE customer_code = :code LIMIT 1'
        );
        $check->execute(['code' => $code]);
        if (!$check->fetchColumn()) {
            return $code;
        }
    }
    throw new RuntimeException('Unable to generate a unique customer code.');
}

function findCustomerByAuth0Subject(string $providerSubject): ?array
{
    $providerSubject = trim($providerSubject);
    if ($providerSubject === '') {
        return null;
    }

    $database = getDatabase();
    $query = $database->prepare(
        'SELECT c.*,
                cai.provider,
                cai.provider_subject,
                cai.email_snapshot AS auth_email,
                cai.email_verified AS auth_email_verified,
                cai.last_login_at AS auth_last_login_at
         FROM customer_auth_identities AS cai
         INNER JOIN customers AS c ON c.id = cai.customer_id
         WHERE cai.provider = "auth0"
           AND cai.provider_subject = :provider_subject
           AND c.account_status = "active"
         LIMIT 1'
    );
    $query->execute(['provider_subject' => $providerSubject]);
    $customer = $query->fetch();
    return $customer ?: null;
}
