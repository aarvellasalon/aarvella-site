<?php

declare(strict_types=1);

/**
 * Shared site-wide config for account/*.php pages — currently just the
 * WhatsApp contact number, duplicated across 4 portal pages before this.
 * Deliberately has zero dependencies (no DB, no Auth0) so it's safe to
 * include from account-error.php, which must keep working even when
 * those fail.
 */

const AARVELLA_WHATSAPP_NUMBER = '919742049990';
