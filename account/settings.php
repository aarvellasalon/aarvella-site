<?php

declare(strict_types=1);

require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/portal-common.php';
require_once __DIR__ . '/portal-layout.php';

portalStartSession();

$identity = requireAuthenticatedCustomer($auth0);
$identityCustomer = is_array($identity['customer'] ?? null) ? $identity['customer'] : [];
$auth0User = is_array($identity['auth0_user'] ?? null) ? $identity['auth0_user'] : [];
$customerId = (int) ($identityCustomer['id'] ?? 0);

if ($customerId < 1) {
    throw new RuntimeException('The authenticated customer record is unavailable.');
}

$database = getDatabase();
$database->exec("SET time_zone = '+05:30'");
$customer = portalLoadCustomer($database, $customerId);

$contactOptions = [
    'whatsapp' => 'WhatsApp',
    'email' => 'Email',
    'sms' => 'SMS',
    'phone' => 'Phone call',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        portalVerifyCsrf();
        $action = portalPostString('action', 60);

        if ($action === 'save_communication') {
            $preferredContact = portalPostString('preferred_contact_method', 30);

            if ($preferredContact !== '' && !array_key_exists($preferredContact, $contactOptions)) {
                throw new InvalidArgumentException('Select a valid preferred contact method.');
            }

            $reminderChannels = portalPostList(
                'reminder_channels',
                ['email', 'sms', 'whatsapp'],
                3
            );
            $remindersEnabled = portalPostBool('appointment_reminders');

            if ($remindersEnabled && $reminderChannels === []) {
                throw new InvalidArgumentException('Choose at least one appointment reminder channel.');
            }

            $database->beginTransaction();

            $update = $database->prepare(
                'UPDATE customers
                 SET preferred_contact_method = :preferred_contact_method
                 WHERE id = :customer_id'
            );
            $update->execute([
                'preferred_contact_method' => $preferredContact !== '' ? $preferredContact : null,
                'customer_id' => $customerId,
            ]);

            portalRecordConsent(
                $database,
                $customerId,
                'appointment_reminders',
                $remindersEnabled,
                ['channels' => $reminderChannels]
            );
            portalRecordConsent($database, $customerId, 'email_marketing', portalPostBool('email_marketing'));
            portalRecordConsent($database, $customerId, 'sms_marketing', portalPostBool('sms_marketing'));
            portalRecordConsent($database, $customerId, 'whatsapp_marketing', portalPostBool('whatsapp_marketing'));
            portalLogActivity($database, $customerId, 'settings.communication_updated', 'customer_portal');

            $database->commit();
            portalFlash('success', 'Communication preferences saved.');
        } elseif ($action === 'save_booking_defaults') {
            $branchId = portalPostNullableInt('preferred_branch_id');
            $stylistId = portalPostNullableInt('preferred_stylist_id');
            $dayValue = trim((string) ($_POST['preferred_day_of_week'] ?? ''));
            $dayOfWeek = $dayValue === '' ? null : (int) $dayValue;
            [$timeFrom, $timeTo] = portalValidateSalonPreferenceWindow(
                portalPostString('preferred_time_from', 5),
                portalPostString('preferred_time_to', 5),
                $dayOfWeek
            );
            $currentPreferences = portalLoadPreferences($database, $customerId);
            $language = (string) ($currentPreferences['language_code'] ?? 'en');

            $database->beginTransaction();
            portalStorePreferences(
                $database,
                $customerId,
                $branchId,
                $stylistId,
                $dayOfWeek,
                $timeFrom,
                $timeTo,
                $language,
                (string) ($currentPreferences['notes'] ?? '')
            );
            portalLogActivity($database, $customerId, 'settings.booking_defaults_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Booking defaults saved.');
        } elseif ($action === 'save_personalisation') {
            $database->beginTransaction();
            portalRecordConsent($database, $customerId, 'ai_personalisation', portalPostBool('ai_personalisation'));
            portalRecordConsent($database, $customerId, 'before_after_photos', portalPostBool('before_after_photos'));
            portalLogActivity($database, $customerId, 'settings.personalisation_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Personalisation choices saved.');
        } elseif ($action === 'save_appearance') {
            if (!portalTableExists($database, 'customer_portal_settings')) {
                throw new RuntimeException('Portal display settings are not installed yet. Run the included database migration first.');
            }

            $currentPortalSettings = portalLoadPortalSettings($database, $customerId);
            $timezone = (string) ($currentPortalSettings['timezone'] ?? 'Asia/Kolkata');

            $database->beginTransaction();
            $query = $database->prepare(
                'INSERT INTO customer_portal_settings (
                    customer_id,
                    compact_mode,
                    reduce_motion,
                    high_contrast,
                    timezone
                 ) VALUES (
                    :customer_id,
                    :compact_mode,
                    :reduce_motion,
                    :high_contrast,
                    :timezone
                 )
                 ON DUPLICATE KEY UPDATE
                    compact_mode = VALUES(compact_mode),
                    reduce_motion = VALUES(reduce_motion),
                    high_contrast = VALUES(high_contrast),
                    timezone = VALUES(timezone)'
            );
            $query->execute([
                'customer_id' => $customerId,
                'compact_mode' => portalPostBool('compact_mode') ? 1 : 0,
                'reduce_motion' => portalPostBool('reduce_motion') ? 1 : 0,
                'high_contrast' => portalPostBool('high_contrast') ? 1 : 0,
                'timezone' => $timezone,
            ]);
            portalLogActivity($database, $customerId, 'settings.appearance_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Display preferences saved.');
        } elseif ($action === 'create_privacy_request') {
            if (!portalTableExists($database, 'customer_privacy_requests')) {
                throw new RuntimeException('Privacy requests are not installed yet. Run the included database migration first.');
            }

            $requestType = portalPostString('request_type', 40);
            $notes = portalPostString('request_notes', 1000);
            $allowedTypes = ['data_export', 'account_anonymisation'];

            if (!in_array($requestType, $allowedTypes, true)) {
                throw new InvalidArgumentException('Select a valid privacy request.');
            }

            $database->beginTransaction();

            // Serialize privacy requests for this customer to prevent duplicate
            // open requests from concurrent browser submissions.
            $customerLock = $database->prepare(
                'SELECT id FROM customers WHERE id = :customer_id FOR UPDATE'
            );
            $customerLock->execute(['customer_id' => $customerId]);

            $existing = $database->prepare(
                'SELECT COUNT(*)
                 FROM customer_privacy_requests
                 WHERE customer_id = :customer_id
                   AND request_type = :request_type
                   AND status IN (\'pending\', \'in_review\')'
            );
            $existing->execute([
                'customer_id' => $customerId,
                'request_type' => $requestType,
            ]);

            if ((int) $existing->fetchColumn() > 0) {
                throw new InvalidArgumentException('You already have an open request of this type.');
            }

            $insert = $database->prepare(
                'INSERT INTO customer_privacy_requests (
                    customer_id,
                    request_type,
                    status,
                    request_notes,
                    requested_at
                 ) VALUES (
                    :customer_id,
                    :request_type,
                    \'pending\',
                    :request_notes,
                    NOW()
                 )'
            );
            $insert->execute([
                'customer_id' => $customerId,
                'request_type' => $requestType,
                'request_notes' => $notes !== '' ? $notes : null,
            ]);
            portalLogActivity(
                $database,
                $customerId,
                'privacy.request_created',
                'customer_portal',
                ['request_type' => $requestType]
            );
            $database->commit();
            portalFlash(
                'success',
                $requestType === 'data_export'
                    ? 'Your data export request has been submitted.'
                    : 'Your account anonymisation request has been submitted for review.'
            );
        } elseif ($action === 'cancel_privacy_request') {
            if (!portalTableExists($database, 'customer_privacy_requests')) {
                throw new RuntimeException('Privacy requests are not installed yet.');
            }

            $requestId = portalPostNullableInt('privacy_request_id');

            if ($requestId === null) {
                throw new InvalidArgumentException('The privacy request is invalid.');
            }

            $database->beginTransaction();
            $update = $database->prepare(
                'UPDATE customer_privacy_requests
                 SET status = \'cancelled\', cancelled_at = NOW()
                 WHERE id = :request_id
                   AND customer_id = :customer_id
                   AND status = \'pending\''
            );
            $update->execute([
                'request_id' => $requestId,
                'customer_id' => $customerId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new InvalidArgumentException('Only pending requests can be cancelled.');
            }

            portalLogActivity($database, $customerId, 'privacy.request_cancelled', 'customer_portal', ['request_id' => $requestId]);
            $database->commit();
            portalFlash('success', 'The pending privacy request was cancelled.');
        } else {
            throw new InvalidArgumentException('Unsupported settings action.');
        }

        portalRedirect('/account/settings.php');
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        error_log('Aarvella settings update failed: ' . $error->getMessage());
        portalFlash('error', $error instanceof InvalidArgumentException || $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Your settings could not be saved. Please try again.');
        portalRedirect('/account/settings.php');
    }
}

$customer = portalLoadCustomer($database, $customerId);
$preferences = portalLoadPreferences($database, $customerId);
$portalSettings = portalLoadPortalSettings($database, $customerId);
$consents = portalLoadConsents($database, $customerId);
$branches = portalLoadBranches($database);
$stylists = portalLoadStylists($database);
$authIdentity = portalLoadAuthIdentity($database, $customerId);
$flash = portalTakeFlash();
$csrfToken = portalCsrfToken();

$privacyRequests = [];
if (portalTableExists($database, 'customer_privacy_requests')) {
    $privacyQuery = $database->prepare(
        'SELECT id, request_type, status, request_notes, requested_at, completed_at, cancelled_at
         FROM customer_privacy_requests
         WHERE customer_id = :customer_id
         ORDER BY requested_at DESC
         LIMIT 6'
    );
    $privacyQuery->execute(['customer_id' => $customerId]);
    $privacyRequests = $privacyQuery->fetchAll(PDO::FETCH_ASSOC);
}

$displayName = trim((string) ($customer['full_name'] ?? '')) ?: 'Aarvella Customer';
$firstName = trim(explode(' ', $displayName)[0] ?? $displayName);
$email = trim((string) ($customer['email'] ?? $auth0User['email'] ?? ''));
$profileImage = trim((string) ($customer['profile_image_url'] ?? ''));
if ($profileImage === '') {
    $profileImage = trim((string) ($auth0User['picture'] ?? ''));
}

$emailVerified = !empty($auth0User['email_verified']) || !empty($authIdentity['email_verified']);
$provider = (string) ($authIdentity['provider'] ?? 'auth0');
$lastLogin = (string) ($authIdentity['last_login_at'] ?? $customer['last_login_at'] ?? '');
$lastLoginLabel = 'Not available';
if ($lastLogin !== '') {
    try {
        $lastLoginLabel = (new DateTimeImmutable($lastLogin, new DateTimeZone('Asia/Kolkata')))->format('d M Y · h:i A');
    } catch (Throwable) {
        $lastLoginLabel = 'Not available';
    }
}

$reminderConsent = $consents['appointment_reminders'] ?? ['granted' => true, 'metadata' => ['channels' => ['email']]];
$reminderChannels = is_array($reminderConsent['metadata']['channels'] ?? null)
    ? $reminderConsent['metadata']['channels']
    : ['email'];

portalRenderShellStart([
    'display_name' => $displayName,
    'first_name' => $firstName,
    'email' => $email,
    'profile_image' => $profileImage,
    'portal_settings' => $portalSettings,
], 'settings', 'Settings');
?>
<section class="portal-page-heading">
    <div>
        <p class="dashboard-eyebrow">Account controls</p>
        <h1>Settings that stay understandable.</h1>
        <p class="dashboard-subtitle">
            Choose how Aarvella contacts you, prepares bookings and personalises your experience.
        </p>
    </div>
    <a href="/account/profile.php" class="av-btn av-btn--secondary av-btn--sm">
        <span class="av-btn__label"><i class="fa-regular fa-user" aria-hidden="true"></i> View profile</span>
    </a>
</section>

<?php if ($flash !== null): ?>
    <?php $flashType = (string) ($flash['type'] ?? 'info'); ?>
    <div
        class="portal-alert portal-flash-toast portal-alert--<?= portalE($flashType) ?>"
        role="<?= $flashType === 'error' ? 'alert' : 'status' ?>"
        aria-live="<?= $flashType === 'error' ? 'assertive' : 'polite' ?>"
        data-flash-toast
        data-flash-type="<?= portalE($flashType) ?>"
    >
        <i class="fa-solid <?= $flashType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>" aria-hidden="true"></i>
        <span><?= portalE((string) ($flash['message'] ?? '')) ?></span>
        <button type="button" class="portal-flash-toast__close" aria-label="Dismiss notification" data-flash-dismiss>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
        <span class="portal-flash-toast__timer" aria-hidden="true"></span>
    </div>
<?php endif; ?>

<div class="settings-layout">
    <aside class="settings-sidebar portal-panel portal-card">
        <p class="portal-nav-label">Settings</p>
        <nav class="settings-anchor-nav" aria-label="Settings sections" data-settings-nav>
            <a href="#communication" class="is-active"><i class="fa-regular fa-bell" aria-hidden="true"></i><span>Communication</span></a>
            <a href="#booking"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i><span>Booking defaults</span></a>
            <a href="#personalisation"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i><span>Personalisation</span></a>
            <a href="#appearance"><i class="fa-solid fa-universal-access" aria-hidden="true"></i><span>Display & access</span></a>
            <a href="#security"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i><span>Login & security</span></a>
            <a href="#privacy"><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>Privacy</span></a>
        </nav>
    </aside>

    <div class="settings-main">
        <section id="communication" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-regular fa-bell" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Useful, not noisy</p>
                    <h2>Communication</h2>
                    <p>Appointment updates are separate from marketing, so you can keep essential messages without promotional messages.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_communication">

                <label class="portal-field portal-field--compact-select">
                    <span>Preferred contact method</span>
                    <select name="preferred_contact_method">
                        <option value="">No preference</option>
                        <?php foreach ($contactOptions as $value => $label): ?>
                            <option value="<?= portalE($value) ?>"<?= ($customer['preferred_contact_method'] ?? '') === $value ? ' selected' : '' ?>><?= portalE($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="settings-group">
                    <div class="settings-group-heading">
                        <div><h3>Appointment reminders</h3><p>Confirmations, reminders and important changes to your visit.</p></div>
                        <label class="portal-switch">
                            <input type="checkbox" name="appointment_reminders" value="1" aria-label="Appointment reminders"<?= !empty($reminderConsent['granted']) ? ' checked' : '' ?> data-toggle-target="#reminderChannels">
                            <span aria-hidden="true"></span>
                            <em><?= !empty($reminderConsent['granted']) ? 'On' : 'Off' ?></em>
                        </label>
                    </div>

                    <fieldset id="reminderChannels" class="portal-choice-fieldset settings-channel-fieldset">
                        <legend>Send reminders by</legend>
                        <div class="portal-choice-grid portal-choice-grid--channels">
                            <?php foreach (['email' => ['fa-regular fa-envelope', 'Email'], 'sms' => ['fa-solid fa-comment-sms', 'SMS'], 'whatsapp' => ['fa-brands fa-whatsapp', 'WhatsApp']] as $channel => [$icon, $label]): ?>
                                <label class="portal-choice-chip portal-choice-chip--icon">
                                    <input type="checkbox" name="reminder_channels[]" value="<?= portalE($channel) ?>"<?= in_array($channel, $reminderChannels, true) ? ' checked' : '' ?>>
                                    <span><i class="<?= portalE($icon) ?>" aria-hidden="true"></i><?= portalE($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="settings-group">
                    <div class="settings-group-heading settings-group-heading--stacked">
                        <div><h3>Offers and inspiration</h3><p>Choose each channel independently. Turning these off does not affect appointment messages.</p></div>
                    </div>

                    <div class="settings-switch-list">
                        <label class="settings-switch-row">
                            <span class="settings-switch-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></span>
                            <span><strong>Email offers</strong><small>News, seasonal services and member offers.</small></span>
                            <span class="portal-switch"><input type="checkbox" name="email_marketing" value="1"<?= !empty($consents['email_marketing']['granted']) ? ' checked' : '' ?>><span aria-hidden="true"></span></span>
                        </label>
                        <label class="settings-switch-row">
                            <span class="settings-switch-icon"><i class="fa-solid fa-comment-sms" aria-hidden="true"></i></span>
                            <span><strong>SMS offers</strong><small>Occasional short promotional messages.</small></span>
                            <span class="portal-switch"><input type="checkbox" name="sms_marketing" value="1"<?= !empty($consents['sms_marketing']['granted']) ? ' checked' : '' ?>><span aria-hidden="true"></span></span>
                        </label>
                        <label class="settings-switch-row">
                            <span class="settings-switch-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
                            <span><strong>WhatsApp offers</strong><small>Personalised offers and salon updates.</small></span>
                            <span class="portal-switch"><input type="checkbox" name="whatsapp_marketing" value="1"<?= !empty($consents['whatsapp_marketing']['granted']) ? ' checked' : '' ?>><span aria-hidden="true"></span></span>
                        </label>
                    </div>
                </div>

                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary"><span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save communication</span></button>
                </div>
            </form>
        </section>

        <section id="booking" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Book with fewer taps</p>
                    <h2>Booking defaults</h2>
                    <p>These are suggestions, never restrictions. You can change them on every appointment.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_booking_defaults">

                <div class="portal-form-grid portal-form-grid--two">
                    <label class="portal-field">
                        <span>Default branch</span>
                        <select name="preferred_branch_id" data-branch-select>
                            <option value="">Ask every time</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int) $branch['id'] ?>"<?= (int) ($preferences['preferred_branch_id'] ?? 0) === (int) $branch['id'] ? ' selected' : '' ?>><?= portalE((string) $branch['name']) ?><?= !empty($branch['area']) ? ' · ' . portalE((string) $branch['area']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field">
                        <span>Default stylist</span>
                        <select name="preferred_stylist_id" data-stylist-select>
                            <option value="">No preference</option>
                            <?php foreach ($stylists as $stylist): ?>
                                <option value="<?= (int) $stylist['id'] ?>" data-branch-ids="<?= portalE((string) ($stylist['branch_ids'] ?? '')) ?>"<?= (int) ($preferences['preferred_stylist_id'] ?? 0) === (int) $stylist['id'] ? ' selected' : '' ?>><?= portalE((string) $stylist['public_name']) ?><?= !empty($stylist['specialty']) ? ' · ' . portalE((string) $stylist['specialty']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field">
                        <span>Preferred day</span>
                        <select name="preferred_day_of_week">
                            <option value="">Any day</option>
                            <?php foreach ([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'] as $day => $label): ?>
                                <option
                                    value="<?= $day ?>"
                                    <?= (int) ($preferences['preferred_day_of_week'] ?? 0) === $day ? ' selected' : '' ?>
                                    <?= $day === 1 ? ' disabled' : '' ?>
                                ><?= portalE($day === 1 ? 'Monday · Closed' : $label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="portal-field">
                        <span>Preferred time window</span>
                        <div
                            class="time-range-fields"
                            data-salon-time-range
                            data-open-time="10:00"
                            data-close-time="20:00"
                            data-closed-day="1"
                        >
                            <label>
                                <span class="sr-only">From</span>
                                <input
                                    type="time"
                                    name="preferred_time_from"
                                    value="<?= portalE(substr((string) ($preferences['preferred_time_from'] ?? ''), 0, 5)) ?>"
                                    min="10:00"
                                    max="19:30"
                                    step="1800"
                                    autocomplete="off"
                                    data-time-role="from"
                                >
                            </label>
                            <span>to</span>
                            <label>
                                <span class="sr-only">To</span>
                                <input
                                    type="time"
                                    name="preferred_time_to"
                                    value="<?= portalE(substr((string) ($preferences['preferred_time_to'] ?? ''), 0, 5)) ?>"
                                    min="10:30"
                                    max="20:00"
                                    step="1800"
                                    autocomplete="off"
                                    data-time-role="to"
                                >
                            </label>
                        </div>
                    </div>

                </div>

                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary"><span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save booking defaults</span></button>
                </div>
            </form>
        </section>

        <section id="personalisation" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Always optional</p>
                    <h2>Personalisation</h2>
                    <p>Control whether Aarvella may use your profile and service history for recommendations or portfolio content.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_personalisation">

                <div class="settings-switch-list">
                    <label class="settings-switch-row settings-switch-row--large">
                        <span class="settings-switch-icon"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></span>
                        <span><strong>Personalised recommendations</strong><small>Allow Aarvella to use your beauty profile and service history to suggest relevant services. Recommendations remain advisory.</small></span>
                        <span class="portal-switch"><input type="checkbox" name="ai_personalisation" value="1"<?= !empty($consents['ai_personalisation']['granted']) ? ' checked' : '' ?>><span aria-hidden="true"></span></span>
                    </label>

                    <label class="settings-switch-row settings-switch-row--large">
                        <span class="settings-switch-icon"><i class="fa-regular fa-images" aria-hidden="true"></i></span>
                        <span><strong>Before-and-after portfolio permission</strong><small>Allow Aarvella to request use of service photos. A stylist must still ask before taking or publishing identifiable photos.</small></span>
                        <span class="portal-switch"><input type="checkbox" name="before_after_photos" value="1"<?= !empty($consents['before_after_photos']['granted']) ? ' checked' : '' ?>><span aria-hidden="true"></span></span>
                    </label>
                </div>

                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary"><span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save personalisation</span></button>
                </div>
            </form>
        </section>

        <section id="appearance" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-solid fa-universal-access" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Comfort and clarity</p>
                    <h2>Display and accessibility</h2>
                    <p>These choices apply to the customer portal after saving.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form data-appearance-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_appearance">

                <div class="settings-switch-list">
                    <label class="settings-switch-row">
                        <span class="settings-switch-icon"><i class="fa-solid fa-compress" aria-hidden="true"></i></span>
                        <span><strong>Compact layout</strong><small>Fit more content on larger screens.</small></span>
                        <span class="portal-switch"><input type="checkbox" name="compact_mode" value="1"<?= !empty($portalSettings['compact_mode']) ? ' checked' : '' ?> data-appearance-control="portal-compact-mode"><span aria-hidden="true"></span></span>
                    </label>
                    <label class="settings-switch-row">
                        <span class="settings-switch-icon"><i class="fa-solid fa-person-walking-arrow-loop-left" aria-hidden="true"></i></span>
                        <span><strong>Reduce motion</strong><small>Minimise decorative movement and transitions.</small></span>
                        <span class="portal-switch"><input type="checkbox" name="reduce_motion" value="1"<?= !empty($portalSettings['reduce_motion']) ? ' checked' : '' ?> data-appearance-control="portal-reduce-motion"><span aria-hidden="true"></span></span>
                    </label>
                    <label class="settings-switch-row">
                        <span class="settings-switch-icon"><i class="fa-solid fa-circle-half-stroke" aria-hidden="true"></i></span>
                        <span><strong>Higher contrast</strong><small>Strengthen text, borders and form controls.</small></span>
                        <span class="portal-switch"><input type="checkbox" name="high_contrast" value="1"<?= !empty($portalSettings['high_contrast']) ? ' checked' : '' ?> data-appearance-control="portal-high-contrast"><span aria-hidden="true"></span></span>
                    </label>
                </div>


                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary"><span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save display settings</span></button>
                </div>
            </form>
        </section>

        <section id="security" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Auth0 protected</p>
                    <h2>Login and security</h2>
                    <p>Your password and sign-in method are managed by Aarvella’s secure identity provider.</p>
                </div>
            </div>

            <div class="security-summary-list">
                <div class="security-summary-row">
                    <span class="settings-switch-icon"><i class="fa-regular fa-envelope" aria-hidden="true"></i></span>
                    <div><strong><?= portalE($email) ?></strong><small>Login email</small></div>
                    <span class="profile-badge <?= $emailVerified ? 'is-verified' : 'is-pending' ?>"><i class="fa-solid <?= $emailVerified ? 'fa-circle-check' : 'fa-circle-info' ?>" aria-hidden="true"></i><?= $emailVerified ? 'Verified' : 'Pending' ?></span>
                </div>
                <div class="security-summary-row">
                    <span class="settings-switch-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></span>
                    <div><strong><?= portalE(strtoupper($provider)) ?></strong><small>Identity provider</small></div>
                    <span class="security-state">Protected</span>
                </div>
                <div class="security-summary-row">
                    <span class="settings-switch-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span>
                    <div><strong><?= portalE($lastLoginLabel) ?></strong><small>Last recorded sign-in</small></div>
                    <span class="security-state">India time</span>
                </div>
            </div>

            <div class="security-actions">
                <div><strong>Change or reset password</strong><p>Use “Forgot password” on the Aarvella sign-in screen. This keeps password changes inside Auth0 rather than the salon database.</p></div>
                <a href="/account/logout.php" class="av-btn av-btn--secondary js-logout"><span class="av-btn__label"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Log out</span></a>
            </div>
        </section>

        <section id="privacy" class="portal-panel portal-card portal-settings-card scroll-margin-top" data-settings-section>
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Your information</p>
                    <h2>Privacy requests</h2>
                    <p>Request a copy of your data or ask Aarvella to anonymise your account. Financial and legally required transaction records are retained where applicable.</p>
                </div>
            </div>

            <div class="privacy-action-grid">
                <form method="post" class="privacy-action-card">
                    <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_privacy_request">
                    <input type="hidden" name="request_type" value="data_export">
                    <span><i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i></span>
                    <h3>Request my data</h3>
                    <p>Ask for an export of profile, preference, consent and service-account information linked to your account.</p>
                    <label class="portal-field"><span>Optional note</span><textarea name="request_notes" maxlength="1000" rows="2" placeholder="Anything specific you need?"></textarea></label>
                    <button type="submit" class="av-btn av-btn--secondary av-btn--sm"><span class="av-btn__label">Submit export request</span></button>
                </form>

                <form method="post" class="privacy-action-card privacy-action-card--danger" data-confirm="Submit an account anonymisation request? Aarvella will review active appointments, balances and records before completing it.">
                    <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                    <input type="hidden" name="action" value="create_privacy_request">
                    <input type="hidden" name="request_type" value="account_anonymisation">
                    <span><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                    <h3>Anonymise my account</h3>
                    <p>Request removal or anonymisation of personal profile data. This is reviewed instead of performing an unsafe hard delete.</p>
                    <label class="portal-field"><span>Optional reason</span><textarea name="request_notes" maxlength="1000" rows="2" placeholder="Tell us how we can help."></textarea></label>
                    <button type="submit" class="av-btn av-btn--danger av-btn--sm"><span class="av-btn__label">Request anonymisation</span></button>
                </form>
            </div>

            <?php if ($privacyRequests !== []): ?>
                <div class="privacy-request-history">
                    <h3>Recent requests</h3>
                    <?php foreach ($privacyRequests as $request): ?>
                        <?php
                        $status = (string) $request['status'];
                        $typeLabel = $request['request_type'] === 'data_export' ? 'Data export' : 'Account anonymisation';
                        $requestedAt = new DateTimeImmutable((string) $request['requested_at'], new DateTimeZone('Asia/Kolkata'));
                        ?>
                        <article class="privacy-request-row">
                            <span class="privacy-request-type"><i class="fa-solid <?= $request['request_type'] === 'data_export' ? 'fa-file-arrow-down' : 'fa-user-slash' ?>" aria-hidden="true"></i></span>
                            <div><strong><?= portalE($typeLabel) ?></strong><small>Requested <?= portalE($requestedAt->format('d M Y · h:i A')) ?></small></div>
                            <span class="privacy-status privacy-status--<?= portalE($status) ?>"><?= portalE(ucwords(str_replace('_', ' ', $status))) ?></span>
                            <?php if ($status === 'pending'): ?>
                                <form method="post" data-confirm="Cancel this pending privacy request?">
                                    <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                                    <input type="hidden" name="action" value="cancel_privacy_request">
                                    <input type="hidden" name="privacy_request_id" value="<?= (int) $request['id'] ?>">
                                    <button type="submit" class="portal-text-button">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php portalRenderShellEnd('settings'); ?>
