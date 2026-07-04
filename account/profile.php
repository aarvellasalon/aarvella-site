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

$hairTypes = ['Straight', 'Wavy', 'Curly', 'Coily', 'Not sure'];
$hairTextures = ['Fine', 'Medium', 'Thick', 'Not sure'];
$hairLengths = ['Short', 'Medium', 'Long', 'Very long'];
$scalpConcernOptions = ['Dryness', 'Oiliness', 'Dandruff', 'Sensitivity', 'Hair fall', 'None'];
$chemicalHistoryOptions = ['Hair colour', 'Highlights / balayage', 'Smoothening / keratin', 'Perm', 'Bleach', 'None'];
$skinTypes = ['Normal', 'Dry', 'Oily', 'Combination', 'Sensitive', 'Not sure'];
$skinConcernOptions = ['Acne', 'Pigmentation', 'Dryness', 'Sensitivity', 'Fine lines', 'Dullness', 'None'];
$allergyOptions = ['Hair colour / PPD', 'Latex', 'Adhesives / lashes', 'Wax / resin', 'Fragrance', 'None known'];
$sensitivityOptions = ['Fragrance', 'Bleach', 'Wax', 'Essential oils', 'Strong exfoliants', 'None known'];
$styleOptions = ['Low-maintenance', 'Classic', 'Natural', 'Polished', 'Trend-led', 'Bold colour', 'Soft glam'];
$genderOptions = [
    'female' => 'Female',
    'male' => 'Male',
    'non_binary' => 'Non-binary',
    'other' => 'Other',
    'prefer_not_to_say' => 'Prefer not to say',
];
$contactOptions = [
    'whatsapp' => 'WhatsApp',
    'email' => 'Email',
    'sms' => 'SMS',
    'phone' => 'Phone call',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        portalVerifyCsrf();
        $action = portalPostString('action', 50);

        if ($action === 'save_identity') {
            $fullName = portalPostString('full_name', 120);
            $phone = portalNormalisePhone(portalPostString('phone', 30));
            $dateOfBirth = portalValidateDateOfBirth(portalPostString('date_of_birth', 10));
            $gender = portalPostString('gender', 30);
            $preferredContact = portalPostString('preferred_contact_method', 30);

            if (mb_strlen($fullName) < 2) {
                throw new InvalidArgumentException('Enter your full name.');
            }

            if ($gender !== '' && !array_key_exists($gender, $genderOptions)) {
                throw new InvalidArgumentException('Select a valid gender option.');
            }

            if ($preferredContact !== '' && !array_key_exists($preferredContact, $contactOptions)) {
                throw new InvalidArgumentException('Select a valid preferred contact method.');
            }

            $database->beginTransaction();

            $update = $database->prepare(
                'UPDATE customers
                 SET
                    full_name = :full_name,
                    phone = :phone,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    preferred_contact_method = :preferred_contact_method
                 WHERE id = :customer_id'
            );
            $update->execute([
                'full_name' => $fullName,
                'phone' => $phone !== '' ? $phone : null,
                'date_of_birth' => $dateOfBirth,
                'gender' => $gender !== '' ? $gender : null,
                'preferred_contact_method' => $preferredContact !== '' ? $preferredContact : null,
                'customer_id' => $customerId,
            ]);

            portalLogActivity($database, $customerId, 'profile.identity_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Personal details saved.');
        } elseif ($action === 'upload_avatar') {
            $database->beginTransaction();
            portalHandleAvatarUpload($database, $customerId, $customer);
            portalLogActivity($database, $customerId, 'profile.avatar_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Profile photo updated.');
        } elseif ($action === 'remove_avatar') {
            $database->beginTransaction();
            $currentPath = (string) ($customer['profile_image_url'] ?? '');
            $update = $database->prepare(
                'UPDATE customers
                 SET profile_image_url = NULL
                 WHERE id = :customer_id'
            );
            $update->execute(['customer_id' => $customerId]);
            portalLogActivity($database, $customerId, 'profile.avatar_removed', 'customer_portal');
            $database->commit();
            portalDeleteOwnedAvatar($currentPath);
            portalFlash('success', 'Your uploaded profile photo was removed.');
        } elseif ($action === 'save_preferences') {
            $branchId = portalPostNullableInt('preferred_branch_id');
            $stylistId = portalPostNullableInt('preferred_stylist_id');
            $dayValue = trim((string) ($_POST['preferred_day_of_week'] ?? ''));
            $dayOfWeek = $dayValue === '' ? null : (int) $dayValue;
            [$timeFrom, $timeTo] = portalValidateSalonPreferenceWindow(
                portalPostString('preferred_time_from', 5),
                portalPostString('preferred_time_to', 5),
                $dayOfWeek
            );
            $notes = portalPostString('booking_notes', 1000);
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
                $notes
            );
            portalLogActivity($database, $customerId, 'profile.booking_preferences_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Booking preferences saved.');
        } elseif ($action === 'save_beauty_profile') {
            $hairType = portalPostString('hair_type', 80);
            $hairTexture = portalPostString('hair_texture', 80);
            $hairLength = portalPostString('hair_length', 80);
            $skinType = portalPostString('skin_type', 80);
            $goals = portalPostString('goals', 2000);

            if ($hairType !== '' && !in_array($hairType, $hairTypes, true)) {
                throw new InvalidArgumentException('Select a valid hair type.');
            }
            if ($hairTexture !== '' && !in_array($hairTexture, $hairTextures, true)) {
                throw new InvalidArgumentException('Select a valid hair texture.');
            }
            if ($hairLength !== '' && !in_array($hairLength, $hairLengths, true)) {
                throw new InvalidArgumentException('Select a valid hair length.');
            }
            if ($skinType !== '' && !in_array($skinType, $skinTypes, true)) {
                throw new InvalidArgumentException('Select a valid skin type.');
            }

            $scalpConcerns = portalPostList('scalp_concerns', $scalpConcernOptions, 6);
            $chemicalHistory = portalPostList('chemical_history', $chemicalHistoryOptions, 6);
            $skinConcerns = portalPostList('skin_concerns', $skinConcernOptions, 7);
            $allergies = portalPostList('allergies', $allergyOptions, 6);
            $sensitivities = portalPostList('product_sensitivities', $sensitivityOptions, 6);
            $preferredStyles = portalPostList('preferred_styles', $styleOptions, 7);

            $database->beginTransaction();
            $query = $database->prepare(
                'INSERT INTO customer_beauty_profiles (
                    customer_id,
                    hair_type,
                    hair_texture,
                    hair_length,
                    scalp_concerns_json,
                    chemical_history_json,
                    skin_type,
                    skin_concerns_json,
                    allergies_json,
                    product_sensitivities_json,
                    preferred_styles_json,
                    goals
                 ) VALUES (
                    :customer_id,
                    :hair_type,
                    :hair_texture,
                    :hair_length,
                    :scalp_concerns_json,
                    :chemical_history_json,
                    :skin_type,
                    :skin_concerns_json,
                    :allergies_json,
                    :product_sensitivities_json,
                    :preferred_styles_json,
                    :goals
                 )
                 ON DUPLICATE KEY UPDATE
                    hair_type = VALUES(hair_type),
                    hair_texture = VALUES(hair_texture),
                    hair_length = VALUES(hair_length),
                    scalp_concerns_json = VALUES(scalp_concerns_json),
                    chemical_history_json = VALUES(chemical_history_json),
                    skin_type = VALUES(skin_type),
                    skin_concerns_json = VALUES(skin_concerns_json),
                    allergies_json = VALUES(allergies_json),
                    product_sensitivities_json = VALUES(product_sensitivities_json),
                    preferred_styles_json = VALUES(preferred_styles_json),
                    goals = VALUES(goals)'
            );
            $query->execute([
                'customer_id' => $customerId,
                'hair_type' => $hairType !== '' ? $hairType : null,
                'hair_texture' => $hairTexture !== '' ? $hairTexture : null,
                'hair_length' => $hairLength !== '' ? $hairLength : null,
                'scalp_concerns_json' => portalJsonEncode($scalpConcerns),
                'chemical_history_json' => portalJsonEncode($chemicalHistory),
                'skin_type' => $skinType !== '' ? $skinType : null,
                'skin_concerns_json' => portalJsonEncode($skinConcerns),
                'allergies_json' => portalJsonEncode($allergies),
                'product_sensitivities_json' => portalJsonEncode($sensitivities),
                'preferred_styles_json' => portalJsonEncode($preferredStyles),
                'goals' => $goals !== '' ? $goals : null,
            ]);
            portalLogActivity($database, $customerId, 'profile.beauty_profile_updated', 'customer_portal');
            $database->commit();
            portalFlash('success', 'Beauty profile saved. Your stylist can use it as a consultation starting point.');
        } else {
            throw new InvalidArgumentException('Unsupported profile action.');
        }

        portalRedirect('/account/profile.php');
    } catch (Throwable $error) {
        if ($database->inTransaction()) {
            $database->rollBack();
        }

        error_log('Aarvella profile update failed: ' . $error->getMessage());
        portalFlash('error', $error instanceof InvalidArgumentException || $error instanceof RuntimeException
            ? $error->getMessage()
            : 'Your profile could not be saved. Please try again.');
        portalRedirect('/account/profile.php');
    }
}

$customer = portalLoadCustomer($database, $customerId);
$preferences = portalLoadPreferences($database, $customerId);
$beauty = portalLoadBeautyProfile($database, $customerId);
$portalSettings = portalLoadPortalSettings($database, $customerId);
$branches = portalLoadBranches($database);
$stylists = portalLoadStylists($database);
$authIdentity = portalLoadAuthIdentity($database, $customerId);
$flash = portalTakeFlash();

$displayName = trim((string) ($customer['full_name'] ?? '')) ?: 'Aarvella Customer';
$firstName = trim(explode(' ', $displayName)[0] ?? $displayName);
$email = trim((string) ($customer['email'] ?? $auth0User['email'] ?? ''));
$profileImage = trim((string) ($customer['profile_image_url'] ?? ''));

$emailVerified = !empty($auth0User['email_verified']) || !empty($authIdentity['email_verified']);
$completion = portalProfileCompletion($customer, $preferences, $beauty);
$initial = mb_strtoupper(mb_substr($displayName, 0, 1));
$csrfToken = portalCsrfToken();

$selectedScalpConcerns = portalJsonArray($beauty['scalp_concerns_json'] ?? null);
$selectedChemicalHistory = portalJsonArray($beauty['chemical_history_json'] ?? null);
$selectedSkinConcerns = portalJsonArray($beauty['skin_concerns_json'] ?? null);
$selectedAllergies = portalJsonArray($beauty['allergies_json'] ?? null);
$selectedSensitivities = portalJsonArray($beauty['product_sensitivities_json'] ?? null);
$selectedStyles = portalJsonArray($beauty['preferred_styles_json'] ?? null);

portalRenderShellStart([
    'display_name' => $displayName,
    'first_name' => $firstName,
    'email' => $email,
    'profile_image' => $profileImage,
    'portal_settings' => $portalSettings,
], 'profile', 'My Profile');
?>
<section class="portal-page-heading">
    <div>
        <p class="dashboard-eyebrow">Your Aarvella profile</p>
        <h1>Make every visit feel familiar.</h1>
        <p class="dashboard-subtitle">
            Add only what helps us prepare for your appointment. You can change or remove these details at any time.
        </p>
    </div>
    <a href="/account/settings.php" class="av-btn av-btn--secondary av-btn--sm">
        <span class="av-btn__label"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Account settings</span>
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

<section class="profile-overview portal-panel portal-card">
    <div class="profile-overview-main">
        <div class="profile-avatar-editor" data-avatar-editor>
            <span class="portal-avatar profile-avatar-large" data-avatar-preview>
                <?php if ($profileImage !== ''): ?>
                    <img src="<?= portalE($profileImage) ?>" alt="Profile photo for <?= portalE($displayName) ?>" referrerpolicy="no-referrer">
                <?php endif; ?>
                <span class="portal-avatar-fallback" aria-hidden="true"><?= portalE($initial) ?></span>
            </span>

            <form method="post" enctype="multipart/form-data" class="profile-avatar-actions" data-auto-upload-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="upload_avatar">
                <label class="av-btn av-btn--secondary av-btn--sm profile-photo-picker">
                    <input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" data-avatar-input>
                    <span class="av-btn__label"><i class="fa-solid fa-camera" aria-hidden="true"></i> Change photo</span>
                </label>
                <span class="profile-photo-filename" data-avatar-filename>JPG, PNG or WebP · up to 4 MB</span>
                <button type="submit" class="av-btn av-btn--primary av-btn--sm">
                    <span class="av-btn__label"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i> Upload photo</span>
                </button>
            </form>
        </div>

        <div class="profile-overview-copy">
            <div class="profile-name-line">
                <h2><?= portalE($displayName) ?></h2>
                <?php if ($emailVerified): ?>
                    <span class="profile-badge is-verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Verified</span>
                <?php endif; ?>
            </div>
            <p><?= portalE($email) ?></p>
            <span><?= portalE(portalFormatCustomerSince($customer)) ?></span>

            <?php if (!empty($customer['profile_image_url'])): ?>
                <form method="post" class="inline-action-form" data-confirm="Remove your uploaded profile photo? Your sign-in photo may still be shown.">
                    <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                    <input type="hidden" name="action" value="remove_avatar">
                    <button type="submit" class="portal-text-button">Remove uploaded photo</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="profile-completion-summary">
        <div class="profile-completion-number"><strong><?= $completion ?>%</strong><span>complete</span></div>
        <div class="profile-completion-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $completion ?>">
            <span style="--completion: <?= $completion ?>%"></span>
        </div>
        <p><?= $completion >= 85 ? 'Your profile is ready for personalised booking.' : 'A few optional details can make future consultations quicker.' ?></p>
    </div>
</section>

<nav class="portal-section-nav" aria-label="Profile sections">
    <a href="#personal-details"><i class="fa-regular fa-user" aria-hidden="true"></i> Personal</a>
    <a href="#booking-preferences"><i class="fa-regular fa-calendar" aria-hidden="true"></i> Booking</a>
    <a href="#beauty-profile"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Beauty profile</a>
</nav>

<div class="portal-account-grid">
    <div class="portal-account-main">
        <section id="personal-details" class="portal-panel portal-card portal-form-card scroll-margin-top">
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-regular fa-address-card" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Essential details</p>
                    <h2>Personal information</h2>
                    <p>Used for appointments, receipts and salon communication.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_identity">

                <div class="portal-form-grid portal-form-grid--two">
                    <label class="portal-field portal-field--wide">
                        <span>Full name <em>Required</em></span>
                        <input type="text" name="full_name" value="<?= portalE((string) ($customer['full_name'] ?? '')) ?>" maxlength="120" autocomplete="name" required>
                    </label>

                    <label class="portal-field">
                        <span>Mobile number</span>
                        <input type="tel" name="phone" value="<?= portalE((string) ($customer['phone'] ?? '')) ?>" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="+91 98765 43210">
                    </label>

                    <label class="portal-field">
                        <span>Date of birth <small>Optional</small></span>
                        <input type="date" name="date_of_birth" value="<?= portalE((string) ($customer['date_of_birth'] ?? '')) ?>" max="<?= portalE((new DateTimeImmutable('today'))->format('Y-m-d')) ?>" autocomplete="bday">
                        <small>Helps us recognise birthdays and age-appropriate services.</small>
                    </label>

                    <label class="portal-field">
                        <span>Gender <small>Optional</small></span>
                        <select name="gender">
                            <option value="">Select</option>
                            <?php foreach ($genderOptions as $value => $label): ?>
                                <option value="<?= portalE($value) ?>"<?= ($customer['gender'] ?? '') === $value ? ' selected' : '' ?>><?= portalE($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field">
                        <span>Preferred contact</span>
                        <select name="preferred_contact_method">
                            <option value="">No preference</option>
                            <?php foreach ($contactOptions as $value => $label): ?>
                                <option value="<?= portalE($value) ?>"<?= ($customer['preferred_contact_method'] ?? '') === $value ? ' selected' : '' ?>><?= portalE($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field portal-field--wide portal-field--locked">
                        <span>Email address</span>
                        <div class="locked-field-value">
                            <span><?= portalE($email) ?></span>
                            <span class="profile-badge <?= $emailVerified ? 'is-verified' : 'is-pending' ?>">
                                <i class="fa-solid <?= $emailVerified ? 'fa-circle-check' : 'fa-circle-info' ?>" aria-hidden="true"></i>
                                <?= $emailVerified ? 'Verified' : 'Verification pending' ?>
                            </span>
                        </div>
                        <small>Your login email is managed through Auth0 to prevent account mismatch.</small>
                    </label>
                </div>

                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary">
                        <span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save personal details</span>
                    </button>
                </div>
            </form>
        </section>

        <section id="booking-preferences" class="portal-panel portal-card portal-form-card scroll-margin-top">
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-regular fa-calendar-check" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Less repetition</p>
                    <h2>Booking preferences</h2>
                    <p>We will preselect these where possible. You can always choose something different for a booking.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_preferences">

                <div class="portal-form-grid portal-form-grid--two">
                    <label class="portal-field">
                        <span>Preferred branch</span>
                        <select name="preferred_branch_id" data-branch-select>
                            <option value="">Any Aarvella branch</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int) $branch['id'] ?>"<?= (int) ($preferences['preferred_branch_id'] ?? 0) === (int) $branch['id'] ? ' selected' : '' ?>>
                                    <?= portalE((string) $branch['name']) ?><?= !empty($branch['area']) ? ' · ' . portalE((string) $branch['area']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field">
                        <span>Preferred stylist</span>
                        <select name="preferred_stylist_id" data-stylist-select>
                            <option value="">No preference</option>
                            <?php foreach ($stylists as $stylist): ?>
                                <option value="<?= (int) $stylist['id'] ?>" data-branch-ids="<?= portalE((string) ($stylist['branch_ids'] ?? '')) ?>"<?= (int) ($preferences['preferred_stylist_id'] ?? 0) === (int) $stylist['id'] ? ' selected' : '' ?>>
                                    <?= portalE((string) $stylist['public_name']) ?><?= !empty($stylist['specialty']) ? ' · ' . portalE((string) $stylist['specialty']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="portal-field">
                        <span>Best day</span>
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

                    <label class="portal-field portal-field--wide">
                        <span>Anything that makes visits easier? <small>Optional</small></span>
                        <textarea name="booking_notes" maxlength="1000" rows="3" placeholder="For example: quieter appointments, extra consultation time, step-free access, or a preferred finish."><?= portalE((string) ($preferences['notes'] ?? '')) ?></textarea>
                        <small>Do not include payment details or medical documents here.</small>
                    </label>
                </div>

                <div class="portal-form-actions">
                    <button type="submit" class="av-btn av-btn--primary">
                        <span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save booking preferences</span>
                    </button>
                </div>
            </form>
        </section>

        <section id="beauty-profile" class="portal-panel portal-card portal-form-card scroll-margin-top">
            <div class="portal-form-card-heading">
                <div class="portal-heading-icon"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i></div>
                <div>
                    <p class="panel-kicker">Consultation shortcut</p>
                    <h2>Beauty profile</h2>
                    <p>This is optional and never replaces an in-salon consultation or patch test.</p>
                </div>
            </div>

            <form method="post" class="portal-form" data-dirty-form>
                <input type="hidden" name="csrf_token" value="<?= portalE($csrfToken) ?>">
                <input type="hidden" name="action" value="save_beauty_profile">

                <div class="beauty-profile-section">
                    <div class="beauty-profile-section-heading">
                        <span><i class="fa-solid fa-scissors" aria-hidden="true"></i></span>
                        <div><h3>Hair</h3><p>Choose what best describes your hair today.</p></div>
                    </div>

                    <div class="portal-form-grid portal-form-grid--three">
                        <label class="portal-field">
                            <span>Hair pattern</span>
                            <select name="hair_type">
                                <option value="">Select</option>
                                <?php foreach ($hairTypes as $option): ?><option value="<?= portalE($option) ?>"<?= ($beauty['hair_type'] ?? '') === $option ? ' selected' : '' ?>><?= portalE($option) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="portal-field">
                            <span>Strand thickness</span>
                            <select name="hair_texture">
                                <option value="">Select</option>
                                <?php foreach ($hairTextures as $option): ?><option value="<?= portalE($option) ?>"<?= ($beauty['hair_texture'] ?? '') === $option ? ' selected' : '' ?>><?= portalE($option) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label class="portal-field">
                            <span>Hair length</span>
                            <select name="hair_length">
                                <option value="">Select</option>
                                <?php foreach ($hairLengths as $option): ?><option value="<?= portalE($option) ?>"<?= ($beauty['hair_length'] ?? '') === $option ? ' selected' : '' ?>><?= portalE($option) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                    </div>

                    <fieldset class="portal-choice-fieldset">
                        <legend>Scalp or hair concerns</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($scalpConcernOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="scalp_concerns[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedScalpConcerns, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="portal-choice-fieldset">
                        <legend>Recent chemical services</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($chemicalHistoryOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="chemical_history[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedChemicalHistory, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>

                <div class="beauty-profile-section">
                    <div class="beauty-profile-section-heading">
                        <span><i class="fa-regular fa-face-smile" aria-hidden="true"></i></span>
                        <div><h3>Skin and sensitivities</h3><p>These details help the team ask better questions before a service.</p></div>
                    </div>

                    <label class="portal-field portal-field--compact-select">
                        <span>Skin type</span>
                        <select name="skin_type">
                            <option value="">Select</option>
                            <?php foreach ($skinTypes as $option): ?><option value="<?= portalE($option) ?>"<?= ($beauty['skin_type'] ?? '') === $option ? ' selected' : '' ?>><?= portalE($option) ?></option><?php endforeach; ?>
                        </select>
                    </label>

                    <fieldset class="portal-choice-fieldset">
                        <legend>Skin concerns</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($skinConcernOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="skin_concerns[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedSkinConcerns, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="portal-choice-fieldset portal-choice-fieldset--important">
                        <legend>Known allergies</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($allergyOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="allergies[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedAllergies, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="portal-choice-fieldset portal-choice-fieldset--important">
                        <legend>Product sensitivities</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($sensitivityOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="product_sensitivities[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedSensitivities, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="portal-field-note"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> Tell your stylist directly about severe reactions, prescriptions or changing health information before every relevant service.</p>
                    </fieldset>
                </div>

                <div class="beauty-profile-section">
                    <div class="beauty-profile-section-heading">
                        <span><i class="fa-regular fa-heart" aria-hidden="true"></i></span>
                        <div><h3>Your style</h3><p>What should an Aarvella result feel like for you?</p></div>
                    </div>

                    <fieldset class="portal-choice-fieldset">
                        <legend>Style preferences</legend>
                        <div class="portal-choice-grid">
                            <?php foreach ($styleOptions as $option): ?>
                                <label class="portal-choice-chip"><input type="checkbox" name="preferred_styles[]" value="<?= portalE($option) ?>"<?= in_array($option, $selectedStyles, true) ? ' checked' : '' ?>><span><?= portalE($option) ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <label class="portal-field">
                        <span>Current beauty goals <small>Optional</small></span>
                        <textarea name="goals" maxlength="2000" rows="4" placeholder="For example: grow out colour gracefully, reduce frizz, create an easy workday style, or prepare skin for an event."><?= portalE((string) ($beauty['goals'] ?? '')) ?></textarea>
                    </label>
                </div>

                <div class="portal-form-actions portal-form-actions--split">
                    <p><i class="fa-solid fa-lock" aria-hidden="true"></i> Visible only to authorised Aarvella staff who support your service.</p>
                    <button type="submit" class="av-btn av-btn--primary">
                        <span class="av-btn__label"><i class="fa-solid fa-check" aria-hidden="true"></i> Save beauty profile</span>
                    </button>
                </div>
            </form>
        </section>
    </div>

    <aside class="portal-account-sidebar">
        <section class="portal-panel portal-card profile-help-card">
            <span class="profile-help-icon"><i class="fa-solid fa-shield-heart" aria-hidden="true"></i></span>
            <p class="panel-kicker">You stay in control</p>
            <h2>Only useful details.</h2>
            <p>Your profile is designed to reduce repeated questions, not collect information Aarvella does not need.</p>
            <a href="/account/settings.php#privacy" class="portal-inline-link">Review privacy choices <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </section>

        <section class="portal-panel portal-card profile-next-steps-card">
            <p class="panel-kicker">Fast setup</p>
            <h2>Best next steps</h2>
            <ol class="profile-step-list">
                <li class="<?= !empty($customer['phone']) ? 'is-complete' : '' ?>"><span><?= !empty($customer['phone']) ? '<i class="fa-solid fa-check"></i>' : '1' ?></span><div><strong>Add a mobile number</strong><small>For booking updates</small></div></li>
                <li class="<?= !empty($preferences['preferred_branch_id']) ? 'is-complete' : '' ?>"><span><?= !empty($preferences['preferred_branch_id']) ? '<i class="fa-solid fa-check"></i>' : '2' ?></span><div><strong>Choose a branch</strong><small>Speeds up booking</small></div></li>
                <li class="<?= !empty($beauty['hair_type']) || !empty($beauty['skin_type']) ? 'is-complete' : '' ?>"><span><?= !empty($beauty['hair_type']) || !empty($beauty['skin_type']) ? '<i class="fa-solid fa-check"></i>' : '3' ?></span><div><strong>Add one beauty detail</strong><small>Improves consultation context</small></div></li>
            </ol>
        </section>

        <section class="portal-panel portal-card profile-support-card">
            <p class="panel-kicker">Need help?</p>
            <h2>Prefer to tell us in person?</h2>
            <p>Leave optional fields blank. Your stylist can update service notes during your consultation.</p>
            <a href="https://wa.me/919742049990" class="av-btn av-btn--secondary av-btn--sm" target="_blank" rel="noopener noreferrer"><span class="av-btn__label"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Contact Aarvella</span></a>
        </section>
    </aside>
</div>
<?php portalRenderShellEnd('profile'); ?>
