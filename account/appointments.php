<?php

declare(strict_types=1);

require_once __DIR__ . '/require-auth.php';
require_once __DIR__ . '/portal-common.php';
require_once __DIR__ . '/portal-layout.php';

$identity = requireAuthenticatedCustomer($auth0);
$customer = $identity['customer'];
$auth0User = $identity['auth0_user'] ?? [];

function e(?string $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function appointmentDate(string $value): DateTimeImmutable
{
    return new DateTimeImmutable(
        $value,
        new DateTimeZone('Asia/Kolkata')
    );
}

function statusLabel(string $status): string
{
    return match ($status) {
        'draft' => 'Draft',
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'checked_in' => 'Checked in',
        'in_progress' => 'In progress',
        'rescheduled' => 'Rescheduled',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show' => 'No show',
        default => ucfirst(str_replace('_', ' ', $status)),
    };
}

function statusClass(string $status): string
{
    return match ($status) {
        'confirmed',
        'checked_in',
        'in_progress',
        'completed' => 'is-success',

        'draft',
        'pending',
        'rescheduled' => 'is-warning',

        'cancelled',
        'no_show' => 'is-danger',

        default => 'is-neutral',
    };
}

function money(null|string|float|int $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return '₹' . number_format((float) $value, 0);
}

function appointmentThumbnail(string $serviceName): string
{
    $name = mb_strtolower(trim($serviceName));

    foreach (
        [
            'facial',
            'skin',
            'glow',
            'detan',
            'wax',
            'thread',
            'cleanup',
            'clean up',
            'hydra',
        ] as $keyword
    ) {
        if (str_contains($name, $keyword)) {
            return '/assets/images/customer-portal/thumb-skin.webp';
        }
    }

    foreach (
        [
            'beard',
            'shave',
            'moustache',
            'men',
            'male',
            'grooming',
        ] as $keyword
    ) {
        if (str_contains($name, $keyword)) {
            return '/assets/images/customer-portal/thumb-grooming.webp';
        }
    }

    return '/assets/images/customer-portal/thumb-hair.webp';
}

$displayName = trim(
    (string) ($customer['full_name'] ?? '')
);

if ($displayName === '') {
    $displayName = 'Aarvella Customer';
}

$firstName = trim(
    explode(' ', $displayName)[0] ?? $displayName
);

$profileImage = trim(
    (string) ($customer['profile_image_url'] ?? '')
);

$email = trim(
    (string) ($customer['email'] ?? '')
);

$initial = mb_strtoupper(
    mb_substr($displayName, 0, 1)
);

$customerId = (int) ($customer['id'] ?? 0);

$upcomingAppointments = [];
$pastAppointments = [];
$appointmentDataError = false;

if ($customerId > 0) {
    try {
        $database = getDatabase();
        $database->exec("SET time_zone = '+05:30'");

        /*
         * DBv2 stores the appointment header in appointments and one or more
         * booked services in appointment_services.
         */
        $baseSelect = "
            SELECT
                a.id,
                a.appointment_code,
                a.appointment_start,
                a.appointment_end,
                a.status,
                a.total_price,
                a.advance_paid,
                a.payment_status,
                a.customer_message,
                a.cancellation_reason,
                b.name AS branch_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            aps.service_name_snapshot
                            ORDER BY aps.id
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        WHERE aps.appointment_id = a.id
                          AND aps.status <> 'cancelled'
                    ),
                    'Aarvella service'
                ) AS service_name,
                COALESCE(
                    (
                        SELECT GROUP_CONCAT(
                            DISTINCT st.public_name
                            ORDER BY st.public_name
                            SEPARATOR ', '
                        )
                        FROM appointment_services AS aps
                        INNER JOIN stylists AS st
                            ON st.id = aps.stylist_id
                        WHERE aps.appointment_id = a.id
                          AND aps.status <> 'cancelled'
                    ),
                    (
                        SELECT st2.public_name
                        FROM stylists AS st2
                        WHERE st2.id = a.primary_stylist_id
                        LIMIT 1
                    )
                ) AS stylist_name,
                COALESCE(
                    a.total_price,
                    (
                        SELECT SUM(aps.line_total)
                        FROM appointment_services AS aps
                        WHERE aps.appointment_id = a.id
                          AND aps.status <> 'cancelled'
                    )
                ) AS display_total
            FROM appointments AS a
            INNER JOIN branches AS b
                ON b.id = a.branch_id
        ";

        $upcomingQuery = $database->prepare(
            $baseSelect . "
            WHERE a.customer_id = :customer_id
              AND a.appointment_start >= NOW()
              AND a.status IN (
                    'draft',
                    'pending',
                    'confirmed',
                    'checked_in',
                    'in_progress',
                    'rescheduled'
              )
            ORDER BY a.appointment_start ASC
            LIMIT 50"
        );

        $upcomingQuery->execute([
            'customer_id' => $customerId,
        ]);

        $upcomingAppointments = $upcomingQuery->fetchAll();

        $pastQuery = $database->prepare(
            $baseSelect . "
            WHERE a.customer_id = :customer_id
              AND (
                    a.appointment_start < NOW()
                    OR a.status IN (
                        'completed',
                        'cancelled',
                        'no_show'
                    )
              )
            ORDER BY a.appointment_start DESC
            LIMIT 50"
        );

        $pastQuery->execute([
            'customer_id' => $customerId,
        ]);

        $pastAppointments = $pastQuery->fetchAll();
    } catch (Throwable $error) {
        $appointmentDataError = true;

        error_log(
            'Aarvella DBv2 appointments query failed: '
            . $error->getMessage()
        );
    }
}

portalRenderShellStart([
    'display_name' => $displayName,
    'first_name' => $firstName,
    'email' => $email,
    'profile_image' => $profileImage,
], 'appointments', 'My Appointments', ['appointments-page']);
?>
                <section class="dashboard-heading">
                    <div>
                        <p class="dashboard-eyebrow">Your visits</p>
                        <h1>My appointments</h1>

                        <p class="dashboard-subtitle">
                            View upcoming visits and your Aarvella service history.
                        </p>
                    </div>

                    <a
                        href="/#booking"
                        class="btn-gold portal-primary-button dashboard-book-button js-book"
                    >
                        <span class="btn-text">
                            <i
                                class="fa-regular fa-calendar-plus"
                                aria-hidden="true"
                            ></i>
                            Book appointment
                        </span>
                        <span
                            class="btn-ripple-container"
                            aria-hidden="true"
                        ></span>
                    </a>
                </section>

                <?php if ($appointmentDataError): ?>
                    <section class="portal-panel portal-card">
                        <div class="portal-empty-state is-error">
                            <span class="empty-state-icon">
                                <i
                                    class="fa-solid fa-triangle-exclamation"
                                    aria-hidden="true"
                                ></i>
                            </span>

                            <div>
                                <h3>We could not load your appointments</h3>
                                <p>
                                    Please refresh or contact Aarvella for assistance.
                                </p>
                            </div>

                            <a
                                href="https://wa.me/919742049990"
                                class="btn-outline portal-secondary-button"
                            >
                                <span class="btn-text">Contact salon</span>
                                <span
                                    class="btn-ripple-container"
                                    aria-hidden="true"
                                ></span>
                            </a>
                        </div>
                    </section>
                <?php else: ?>
                    <div class="appointments-overview">
                        <section class="appointments-section">
                            <div class="appointments-section-heading">
                                <div>
                                    <p class="panel-kicker">Next visits</p>
                                    <h2>Upcoming appointments</h2>
                                </div>

                                <span class="appointments-count">
                                    <?= count($upcomingAppointments) ?>
                                </span>
                            </div>

                            <?php if ($upcomingAppointments === []): ?>
                                <section class="portal-panel portal-card">
                                    <div class="portal-empty-state">
                                        <span class="empty-state-icon">
                                            <i
                                                class="fa-regular fa-calendar-check"
                                                aria-hidden="true"
                                            ></i>
                                        </span>

                                        <div>
                                            <h3>No upcoming appointment</h3>
                                            <p>
                                                Book your next Aarvella experience and it will appear here.
                                            </p>
                                        </div>

                                        <a
                                            href="/#booking"
                                            class="btn-gold portal-primary-button js-book"
                                        >
                                            <span class="btn-text">Book now</span>
                                            <span
                                                class="btn-ripple-container"
                                                aria-hidden="true"
                                            ></span>
                                        </a>
                                    </div>
                                </section>
                            <?php else: ?>
                                <div class="appointments-card-list">
                                    <?php foreach ($upcomingAppointments as $appointment): ?>
                                        <?php
                                        $start = appointmentDate(
                                            (string) $appointment['appointment_start']
                                        );

                                        $end = appointmentDate(
                                            (string) $appointment['appointment_end']
                                        );

                                        $status = (string) $appointment['status'];
                                        $serviceName = (string) $appointment['service_name'];
                                        $displayPrice = money(
                                            $appointment['display_total'] ?? null
                                        );
                                        ?>

                                        <article
                                            id="appointment-<?= (int) $appointment['id'] ?>"
                                            class="portal-panel portal-card full-appointment-card"
                                        >
                                            <div class="appointment-card-visual">
                                                <div class="appointment-date-tile">
                                                    <strong><?= e($start->format('d')) ?></strong>
                                                    <span>
                                                        <?= e(strtoupper($start->format('M'))) ?>
                                                    </span>
                                                </div>

                                                <div class="appointment-service-thumb">
                                                    <img
                                                        src="<?= e(appointmentThumbnail($serviceName)) ?>"
                                                        alt=""
                                                        loading="lazy"
                                                        decoding="async"
                                                    >
                                                </div>
                                            </div>

                                            <div class="full-appointment-main">
                                                <div class="appointment-card-title-row">
                                                    <div>
                                                        <p class="appointment-code">
                                                            <?= e((string) $appointment['appointment_code']) ?>
                                                        </p>

                                                        <h3><?= e($serviceName) ?></h3>
                                                    </div>

                                                    <span
                                                        class="appointment-status <?= e(statusClass($status)) ?>"
                                                    >
                                                        <?= e(statusLabel($status)) ?>
                                                    </span>
                                                </div>

                                                <div class="appointment-meta">
                                                    <span>
                                                        <i
                                                            class="fa-regular fa-clock"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e($start->format('D, d M Y · h:i A')) ?>
                                                        –
                                                        <?= e($end->format('h:i A')) ?>
                                                    </span>

                                                    <span>
                                                        <i
                                                            class="fa-regular fa-user"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e(
                                                            !empty($appointment['stylist_name'])
                                                                ? 'With ' . (string) $appointment['stylist_name']
                                                                : 'Aarvella stylist'
                                                        ) ?>
                                                    </span>

                                                    <span class="appointment-location">
                                                        <i
                                                            class="fa-solid fa-location-dot"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e((string) $appointment['branch_name']) ?>
                                                    </span>

                                                    <?php if ($displayPrice !== null): ?>
                                                        <span>
                                                            <i
                                                                class="fa-solid fa-indian-rupee-sign"
                                                                aria-hidden="true"
                                                            ></i>
                                                            <?= e($displayPrice) ?>
                                                            ·
                                                            <?= e(
                                                                ucwords(
                                                                    str_replace(
                                                                        '_',
                                                                        ' ',
                                                                        (string) $appointment['payment_status']
                                                                    )
                                                                )
                                                            ) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (!empty($appointment['customer_message'])): ?>
                                                    <p class="appointment-customer-note">
                                                        <strong>Your note:</strong>
                                                        <?= e((string) $appointment['customer_message']) ?>
                                                    </p>
                                                <?php endif; ?>

                                                <div class="appointment-card-actions">
                                                    <button
                                                        type="button"
                                                        class="btn-outline portal-secondary-button"
                                                        data-coming-soon="Appointment rescheduling"
                                                    >
                                                        <span class="btn-text">Reschedule</span>
                                                        <span
                                                            class="btn-ripple-container"
                                                            aria-hidden="true"
                                                        ></span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="appointment-text-action is-danger"
                                                        data-coming-soon="Appointment cancellation"
                                                    >
                                                        Cancel appointment
                                                    </button>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>

                        <section class="appointments-section">
                            <div class="appointments-section-heading">
                                <div>
                                    <p class="panel-kicker">Your history</p>
                                    <h2>Past appointments</h2>
                                </div>

                                <span class="appointments-count">
                                    <?= count($pastAppointments) ?>
                                </span>
                            </div>

                            <?php if ($pastAppointments === []): ?>
                                <section class="portal-panel portal-card">
                                    <div class="portal-empty-state">
                                        <span class="empty-state-icon">
                                            <i
                                                class="fa-solid fa-clock-rotate-left"
                                                aria-hidden="true"
                                            ></i>
                                        </span>

                                        <div>
                                            <h3>No past appointments yet</h3>
                                            <p>
                                                Completed visits and service history will appear here.
                                            </p>
                                        </div>

                                        <a
                                            href="/#booking"
                                            class="btn-outline portal-secondary-button js-book"
                                        >
                                            <span class="btn-text">Explore services</span>
                                            <span
                                                class="btn-ripple-container"
                                                aria-hidden="true"
                                            ></span>
                                        </a>
                                    </div>
                                </section>
                            <?php else: ?>
                                <div class="appointments-card-list">
                                    <?php foreach ($pastAppointments as $appointment): ?>
                                        <?php
                                        $start = appointmentDate(
                                            (string) $appointment['appointment_start']
                                        );

                                        $end = appointmentDate(
                                            (string) $appointment['appointment_end']
                                        );

                                        $status = (string) $appointment['status'];
                                        $serviceName = (string) $appointment['service_name'];
                                        $displayPrice = money(
                                            $appointment['display_total'] ?? null
                                        );
                                        ?>

                                        <article
                                            id="appointment-<?= (int) $appointment['id'] ?>"
                                            class="portal-panel portal-card full-appointment-card"
                                        >
                                            <div class="appointment-card-visual">
                                                <div class="appointment-date-tile">
                                                    <strong><?= e($start->format('d')) ?></strong>
                                                    <span>
                                                        <?= e(strtoupper($start->format('M'))) ?>
                                                    </span>
                                                </div>

                                                <div class="appointment-service-thumb">
                                                    <img
                                                        src="<?= e(appointmentThumbnail($serviceName)) ?>"
                                                        alt=""
                                                        loading="lazy"
                                                        decoding="async"
                                                    >
                                                </div>
                                            </div>

                                            <div class="full-appointment-main">
                                                <div class="appointment-card-title-row">
                                                    <div>
                                                        <p class="appointment-code">
                                                            <?= e((string) $appointment['appointment_code']) ?>
                                                        </p>

                                                        <h3><?= e($serviceName) ?></h3>
                                                    </div>

                                                    <span
                                                        class="appointment-status <?= e(statusClass($status)) ?>"
                                                    >
                                                        <?= e(statusLabel($status)) ?>
                                                    </span>
                                                </div>

                                                <div class="appointment-meta">
                                                    <span>
                                                        <i
                                                            class="fa-regular fa-clock"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e($start->format('D, d M Y · h:i A')) ?>
                                                        –
                                                        <?= e($end->format('h:i A')) ?>
                                                    </span>

                                                    <span>
                                                        <i
                                                            class="fa-regular fa-user"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e(
                                                            !empty($appointment['stylist_name'])
                                                                ? 'With ' . (string) $appointment['stylist_name']
                                                                : 'Aarvella stylist'
                                                        ) ?>
                                                    </span>

                                                    <span class="appointment-location">
                                                        <i
                                                            class="fa-solid fa-location-dot"
                                                            aria-hidden="true"
                                                        ></i>
                                                        <?= e((string) $appointment['branch_name']) ?>
                                                    </span>

                                                    <?php if ($displayPrice !== null): ?>
                                                        <span>
                                                            <i
                                                                class="fa-solid fa-indian-rupee-sign"
                                                                aria-hidden="true"
                                                            ></i>
                                                            <?= e($displayPrice) ?>
                                                            ·
                                                            <?= e(
                                                                ucwords(
                                                                    str_replace(
                                                                        '_',
                                                                        ' ',
                                                                        (string) $appointment['payment_status']
                                                                    )
                                                                )
                                                            ) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if (
                                                    $status === 'cancelled'
                                                    && !empty($appointment['cancellation_reason'])
                                                ): ?>
                                                    <p class="appointment-customer-note is-cancelled">
                                                        <strong>Cancellation:</strong>
                                                        <?= e((string) $appointment['cancellation_reason']) ?>
                                                    </p>
                                                <?php endif; ?>

                                                <div class="appointment-card-actions">
                                                    <a
                                                        href="/#booking"
                                                        class="btn-outline portal-secondary-button js-book"
                                                    >
                                                        <span class="btn-text">Rebook service</span>
                                                        <span
                                                            class="btn-ripple-container"
                                                            aria-hidden="true"
                                                        ></span>
                                                    </a>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </section>
                    </div>
                <?php endif; ?>
<?php portalRenderShellEnd('appointments'); ?>
