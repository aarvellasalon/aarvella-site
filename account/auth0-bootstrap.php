<?php

declare(strict_types=1);

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$config = require '/home/aarvyeqt/private/aarvella-auth0.php';
$auth0Config = $config['auth0'];

$configuration = new SdkConfiguration(
    domain: $auth0Config['domain'],
    clientId: $auth0Config['client_id'],
    clientSecret: $auth0Config['client_secret'],
    cookieSecret: $auth0Config['cookie_secret'],
    redirectUri: $auth0Config['redirect_uri']
);

$auth0 = new Auth0($configuration);

/*
 * Authentication responses and portal pages must never be cached.
 */
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('Expires: 0');