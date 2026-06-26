<?php

declare(strict_types=1);

require_once __DIR__ . '/auth0-bootstrap.php';

$config = require '/home/aarvyeqt/private/aarvella-auth0.php';

$logoutUrl = $auth0->logout(
    $config['auth0']['logout_uri']
);

header('Location: ' . $logoutUrl);
exit;