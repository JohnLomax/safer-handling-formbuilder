<?php
declare(strict_types=1);

/**
 * Safer Handling Forge CRM booking-intake webhook.
 *
 * Sends a full booking snapshot to Forge. `booking.booking_status` (and
 * `booking.delivery_stage`) use Forge Booking Delivery Stage values:
 *
 *   provisional_book  — reserve / limited data, not formally agreed
 *   confirmed_book    — formal Accept Quote / booking process completed
 *   on_hold           — paused until confirmed_book or cancelled
 *   to_rearrange      — needs new dates (session dates cleared in payload)
 *   cancelled         — not proceeding
 *
 * Invoicing / Kajabi are not delivery stages — those events keep confirmed_book.
 * Payloads are staged for admin review in Forge — they are not applied live.
 */

/** @var list<string> */
const FORGE_DELIVERY_STAGES = [
    'provisional_book',
    'confirmed_book',
    'on_hold',
    'to_rearrange',
    'cancelled',
];

function forgeEnabled(): bool
{
    // Admin → Settings is the source of truth once settings are loaded.
    if (array_key_exists('forgeEnabled', $GLOBALS)) {
        return (bool)$GLOBALS['forgeEnabled'];
    }

    $env = getenv('FORGE_WEBHOOK_ENABLED');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    return false;
}

function forgeWebhookUrl(): string
{
    return appConfigValue(
        'FORGE_WEBHOOK_URL',
        'forgeWebhookUrl',
        'https://saferhandling.forgecrm.co.uk/safer_production/webhooks/bookings/'
    );
}

function forgeWebhookToken(): string
{
    return appConfigValue('FORGE_WEBHOOK_TOKEN', 'forgeWebhookToken');
}

function forgeExternalRef(int $enquiryId): string
{
    return 'SH-ENQUIRY-' . $enquiryId;
}

/**
 * Human-readable Forge delivery stage for journey messages.
 */
function forgeDeliveryStageLabel(string $stage): string
{
    return match (forgeNormaliseDeliveryStage($stage)) {
        'provisional_book' => 'provisional book (reserved, not formally agreed)',
        'confirmed_book' => 'confirmed book (formally booked)',
        'on_hold' => 'on hold',
        'to_rearrange' => 'to rearrange',
        'cancelled' => 'cancelled',
        default => $stage !== '' ? str_replace('_', ' ', $stage) : 'unknown',
    };
}

/**
 * Plain-English reason when Forge HTTP responds with a non-202 status.
 */
function forgeFailureReasonFromHttp(int $status, string $snippet = ''): string
{
    $base = match (true) {
        $status === 0 => 'Forge did not respond. Check the webhook URL and network connection.',
        $status === 401, $status === 403 => 'Forge rejected the request. The webhook token may be wrong or expired.',
        $status === 404 => 'Forge could not find the webhook endpoint. Check the webhook URL in Settings.',
        $status === 408, $status === 504 => 'Forge took too long to respond. Try again in a moment.',
        $status === 422 => 'Forge rejected the booking data as invalid.',
        $status === 429 => 'Forge asked us to slow down (too many requests). Try again shortly.',
        $status >= 500 && $status <= 599 => 'Forge had a server problem and could not accept the booking snapshot.',
        default => 'Forge did not accept the booking snapshot (HTTP ' . $status . ').',
    };

    $clean = trim(preg_replace('/\s+/', ' ', $snippet) ?? '');
    if ($clean !== '' && strlen($clean) <= 220 && !str_starts_with($clean, '<')) {
        return $base . ' Details from Forge: ' . $clean;
    }

    return $base;
}

/**
 * Plain-English reason from a transport / config exception.
 */
function forgeFailureReasonFromException(Throwable $e): string
{
    $msg = trim($e->getMessage());

    if (str_contains($msg, 'URL or token is not configured')) {
        return 'Forge is not fully configured. Add the webhook URL and token under Settings → Integrations.';
    }
    if (str_contains($msg, 'encode Forge booking payload')) {
        return 'The booking data could not be prepared for Forge. Check the booking details and try again.';
    }
    if (str_contains($msg, 'initialise Forge webhook request')) {
        return 'Could not start the connection to Forge. Try again shortly.';
    }
    if (str_contains($msg, 'Forge webhook request failed')) {
        $detail = trim(str_replace('Forge webhook request failed:', '', $msg));
        if ($detail !== '' && $detail !== 'unknown error') {
            return 'Could not reach Forge (' . $detail . '). Check the webhook URL and network connection.';
        }

        return 'Could not reach Forge. Check the webhook URL and network connection.';
    }
    if (preg_match('/Forge webhook returned HTTP\s+(\d+)\s*:?\s*(.*)$/i', $msg, $matches)) {
        return forgeFailureReasonFromHttp((int) $matches[1], trim((string) ($matches[2] ?? '')));
    }
    if (str_starts_with($msg, 'FORGE_LOGGED:')) {
        return trim(substr($msg, strlen('FORGE_LOGGED:')));
    }
    if ($msg !== '') {
        return 'Forge sync failed: ' . $msg;
    }

    return 'Forge sync failed for an unknown reason.';
}

/**
 * @param array<string, mixed> $metadata
 */
function forgeLogJourneyEvent(int $enquiryId, string $eventType, string $message, array $metadata = []): void
{
    require_once __DIR__ . '/enquiry_logger.php';
    enquiryLoggerEvent($enquiryId, $eventType, $message, $metadata);
}

/**
 * Log a failure, then throw so callers can show a warning without double-logging.
 *
 * @param array<string, mixed> $metadata
 */
function forgeFailAndThrow(int $enquiryId, string $plainMessage, array $metadata = []): never
{
    forgeLogJourneyEvent($enquiryId, 'forge_booking_sync_failed', $plainMessage, $metadata);
    throw new RuntimeException('FORGE_LOGGED: ' . $plainMessage);
}

function forgeExceptionAlreadyLogged(Throwable $e): bool
{
    return str_starts_with($e->getMessage(), 'FORGE_LOGGED:');
}

/**
 * Normalise legacy Forge labels and aliases to delivery-stage snake_case.
 */
function forgeNormaliseDeliveryStage(?string $status): string
{
    $raw = strtolower(trim((string) $status));
    if ($raw === '') {
        return '';
    }

    $aliases = [
        'pending' => 'provisional_book',
        'provisional' => 'provisional_book',
        'provisional_book' => 'provisional_book',
        'prov_booking_delivery' => 'provisional_book',
        'accepted' => 'confirmed_book',
        'confirmed' => 'confirmed_book',
        'confirmed_book' => 'confirmed_book',
        'booking_delivery' => 'confirmed_book',
        'invoice sent' => 'confirmed_book',
        'invoice_sent' => 'confirmed_book',
        'quote won' => 'confirmed_book',
        'quote_won' => 'confirmed_book',
        'kajabi_enrolled' => 'confirmed_book',
        'on_hold' => 'on_hold',
        'on hold' => 'on_hold',
        'to_rearrange' => 'to_rearrange',
        'to rearrange' => 'to_rearrange',
        'cancelled' => 'cancelled',
        'canceled' => 'cancelled',
    ];

    if (isset($aliases[$raw])) {
        return $aliases[$raw];
    }

    $snake = str_replace([' ', '-'], '_', $raw);
    if (in_array($snake, FORGE_DELIVERY_STAGES, true)) {
        return $snake;
    }

    return $snake;
}

/**
 * Map local enquiry / booking state to a Forge delivery stage.
 *
 * Scenarios (webhook booking_status / delivery_stage):
 * - Limited reserve / not formally agreed → provisional_book
 * - Accept Quote / terms accepted → confirmed_book
 * - Invoice sent / Kajabi enroll → stay confirmed_book (not separate stages)
 * - Explicit overrides: on_hold, to_rearrange, cancelled
 */
function forgeBookingStatusLabel(
    ?string $enquiryStatus,
    bool $termsAccepted = false,
    bool $invoiceSent = false,
    bool $quoteWon = false
): string {
    $status = strtolower(trim((string) $enquiryStatus));
    $normalised = forgeNormaliseDeliveryStage($status);

    if (in_array($normalised, ['on_hold', 'to_rearrange', 'cancelled'], true)) {
        return $normalised;
    }

    // Formal agreement / post-accept delivery — including invoice & Kajabi.
    if (
        $termsAccepted
        || $invoiceSent
        || $quoteWon
        || in_array($status, ['quote_accepted', 'accepted', 'quote_won', 'invoice_sent', 'kajabi_enrolled'], true)
        || $normalised === 'confirmed_book'
    ) {
        return 'confirmed_book';
    }

    // Pre-agreement reserve / limited data.
    if (
        in_array($status, ['quote_sent', 'pending', 'submitted', 'contacted', 'in_progress'], true)
        || $normalised === 'provisional_book'
        || $status === ''
    ) {
        return 'provisional_book';
    }

    return $normalised !== '' ? $normalised : 'provisional_book';
}

/**
 * Rank for avoiding accidental regressions (higher = further along / stronger).
 */
function forgeDeliveryStageRank(string $stage): int
{
    return match (forgeNormaliseDeliveryStage($stage)) {
        'provisional_book' => 10,
        'on_hold', 'to_rearrange' => 20,
        'confirmed_book' => 30,
        'cancelled' => 100,
        default => 0,
    };
}

function forgeSessionDateId(int $enquiryId, int $index = 1): string
{
    return forgeExternalRef($enquiryId) . '-DATE-' . $index;
}

/**
 * @return array{date:string,start_time:string}|null
 */
function forgeParsePreferredDateTime(?string $preferredDateTime): ?array
{
    $preferredDateTime = trim((string)$preferredDateTime);
    if ($preferredDateTime === '') {
        return null;
    }

    $normalised = str_replace(' ', 'T', $preferredDateTime);
    if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:T(\d{2}):(\d{2}))?/', $normalised, $matches)) {
        return null;
    }

    $date = $matches[1];
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/London'));
    if ($dt === false || $dt->format('Y-m-d') !== $date) {
        return null;
    }

    $startTime = '09:00';
    if (isset($matches[2], $matches[3]) && $matches[2] !== '' && $matches[3] !== '') {
        $startTime = sprintf('%02d:%02d', (int)$matches[2], (int)$matches[3]);
    }

    return [
        'date' => $date,
        'start_time' => $startTime,
    ];
}

/**
 * @param array<string, mixed> $parts
 * @return array{line_1:string,line_2:string,city:string,postcode:string}
 */
function forgeAddressFieldsFromParts(array $parts): array
{
    return [
        'line_1' => trim((string)($parts['addressLine1'] ?? '')),
        'line_2' => trim((string)($parts['addressLine2'] ?? '')),
        'city' => trim((string)($parts['addressTown'] ?? '')),
        'postcode' => trim((string)($parts['addressPostcode'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $bookingDetails
 * @return array{line_1:string,line_2:string,city:string,postcode:string}
 */
function forgeVenueAddressFields(array $bookingDetails): array
{
    $venue = trim((string)($bookingDetails['venueAddress'] ?? ''));
    if ($venue === '') {
        return forgeAddressFieldsFromParts([]);
    }

    require_once __DIR__ . '/xero.php';

    return forgeAddressFieldsFromParts(xeroAddressPartsFromFreeText($venue));
}

/**
 * @param array<string, mixed> $formData
 * @return array{line_1:string,line_2:string,city:string,postcode:string}
 */
function forgeOrgAddressFields(array $formData): array
{
    $line1 = trim((string)($formData['addressLine1'] ?? $formData['tmAddressLine1'] ?? ''));
    $line2 = trim((string)($formData['addressLine2'] ?? $formData['tmAddressLine2'] ?? ''));
    $city = trim((string)($formData['addressTown'] ?? $formData['tmAddressTown'] ?? ''));
    $postcode = trim((string)($formData['addressPostcode'] ?? $formData['tmAddressPostcode'] ?? ''));

    if ($line1 !== '' || $postcode !== '') {
        return [
            'line_1' => $line1,
            'line_2' => $line2,
            'city' => $city,
            'postcode' => $postcode,
        ];
    }

    return forgeAddressFieldsFromParts([]);
}

/**
 * @param array<string, mixed> $bookingDetails
 * @param array<string, mixed> $enquiry
 * @return array<string, mixed>
 */
function forgeBuildBookingPayload(
    int $enquiryId,
    array $bookingDetails,
    array $enquiry,
    string $action,
    ?string $bookingStatusOverride = null
): array
{
    $formData = [];
    $rawForm = trim((string)($enquiry['form_data_json'] ?? ''));
    if ($rawForm !== '') {
        $decoded = json_decode($rawForm, true);
        if (is_array($decoded)) {
            $formData = $decoded;
        }
    }

    $organisation = trim((string)($bookingDetails['organisation'] ?? ''));
    if ($organisation === '') {
        $organisation = trim((string)($enquiry['organisation_company'] ?? ''));
    }

    $courseCode = trim((string)($enquiry['org_course'] ?? ''));
    if ($courseCode === '') {
        $courseCode = trim((string)($enquiry['trainer_course_select'] ?? ''));
    }

    $delegatesIn = is_array($bookingDetails['delegates'] ?? null) ? $bookingDetails['delegates'] : [];
    $delegates = [];
    foreach ($delegatesIn as $delegate) {
        if (!is_array($delegate)) {
            continue;
        }
        $fullName = trim((string)($delegate['name'] ?? $delegate['full_name'] ?? ''));
        $email = trim((string)($delegate['email'] ?? ''));
        if ($fullName === '' && $email === '') {
            continue;
        }
        $entry = [];
        if ($fullName !== '') {
            $entry['full_name'] = $fullName;
        }
        if ($email !== '') {
            $entry['email'] = $email;
        }
        $delegates[] = $entry;
    }

    $expectedDelegates = count($delegates);
    if ($expectedDelegates === 0) {
        foreach (['attendees', 'matrix_attendees', 'trainer_attendees'] as $attendeeKey) {
            $value = (int)($enquiry[$attendeeKey] ?? 0);
            if ($value > 0) {
                $expectedDelegates = $value;
                break;
            }
        }
    }

    $orgAddress = forgeOrgAddressFields($formData);
    $venueAddress = forgeVenueAddressFields($bookingDetails);
    $venueLocation = trim((string)($bookingDetails['venueAddress'] ?? ''));
    if ($venueLocation !== '') {
        $venueLines = preg_split('/\r\n|\r|\n/', $venueLocation) ?: [];
        $firstLine = trim((string)($venueLines[0] ?? ''));
        if ($firstLine !== '') {
            $venueLocation = $firstLine;
        }
    }

    $bookerName = trim((string)($bookingDetails['bookerName'] ?? $enquiry['name'] ?? ''));
    $bookerEmail = trim((string)($bookingDetails['email'] ?? $enquiry['email'] ?? ''));
    $bookerPhone = trim((string)($bookingDetails['phone'] ?? $formData['phone'] ?? $formData['tmPhone'] ?? ''));
    $termsAccepted = !empty($bookingDetails['termsAccepted'])
        || trim((string)($enquiry['status'] ?? '')) === 'quote_accepted'
        || trim((string)($enquiry['status'] ?? '')) === 'quote_won';
    $invoiceSent = !empty($bookingDetails['invoiceSent'])
        || trim((string)($enquiry['xero_invoice_sent_at'] ?? '')) !== ''
        || trim((string)($enquiry['status'] ?? '')) === 'quote_won';
    $quoteWon = !empty($bookingDetails['quoteWon'])
        || trim((string)($enquiry['status'] ?? '')) === 'quote_won';

    $bookingStatus = forgeNormaliseDeliveryStage((string) $bookingStatusOverride);
    if ($bookingStatus === '') {
        $bookingStatus = forgeBookingStatusLabel(
            $enquiry['status'] ?? null,
            $termsAccepted,
            $invoiceSent,
            $quoteWon
        );
    }

    $booking = [
        // Forge Booking Delivery Stage (same value in both keys for compatibility).
        'booking_status' => $bookingStatus,
        'delivery_stage' => $bookingStatus,
        'organisation_name' => $organisation,
        'course_code' => $courseCode,
        'expected_delegates' => $expectedDelegates,
        'booker' => [
            'name' => $bookerName,
            'email' => $bookerEmail,
            'phone' => $bookerPhone,
        ],
    ];

    if ($orgAddress['line_1'] !== '') {
        $booking['org_address_line_1'] = $orgAddress['line_1'];
    }
    if ($orgAddress['line_2'] !== '') {
        $booking['org_address_line_2'] = $orgAddress['line_2'];
    }
    if ($orgAddress['city'] !== '') {
        $booking['org_address_city'] = $orgAddress['city'];
    }
    if ($orgAddress['postcode'] !== '') {
        $booking['org_address_postcode'] = $orgAddress['postcode'];
    }

    $sessionDates = [];
    // to_rearrange / cancelled: clear session date-times so Forge shows nothing active to rearrange/cancel.
    $clearSessions = in_array($bookingStatus, ['to_rearrange', 'cancelled'], true);
    $dateNotSure = !empty($enquiry['date_not_sure']);
    $parsedDate = ($clearSessions || $dateNotSure)
        ? null
        : forgeParsePreferredDateTime($enquiry['preferred_date_time'] ?? null);
    if ($parsedDate !== null) {
        $session = [
            'id' => forgeSessionDateId($enquiryId, 1),
            'date' => $parsedDate['date'],
            'start_time' => $parsedDate['start_time'],
            'location' => $venueLocation !== '' ? $venueLocation : $organisation,
        ];
        if ($venueAddress['line_1'] !== '') {
            $session['loc_address_line_1'] = $venueAddress['line_1'];
        }
        if ($venueAddress['line_2'] !== '') {
            $session['loc_address_line_2'] = $venueAddress['line_2'];
        }
        if ($venueAddress['city'] !== '') {
            $session['loc_address_city'] = $venueAddress['city'];
        }
        if ($venueAddress['postcode'] !== '') {
            $session['loc_address_postcode'] = $venueAddress['postcode'];
        }
        $sessionDates[] = $session;
    }

    $payload = [
        'action' => $action,
        'external_ref' => forgeExternalRef($enquiryId),
        'booking' => $booking,
        'session_dates' => $sessionDates,
    ];

    if ($delegates !== []) {
        $payload['delegates'] = $delegates;
    }

    return $payload;
}

/**
 * @param array<string, mixed> $payload
 * @return array{status:int,body:string,json:?array}
 */
function forgeHttpPostBooking(array $payload): array
{
    $url = forgeWebhookUrl();
    $token = forgeWebhookToken();
    if ($url === '' || $token === '') {
        throw new RuntimeException('Forge webhook URL or token is not configured.');
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        throw new RuntimeException('Could not encode Forge booking payload as JSON.');
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialise Forge webhook request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Webhook-Token: ' . $token,
        ],
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Forge webhook request failed: ' . ($err !== '' ? $err : 'unknown error'));
    }
    curl_close($ch);

    $json = json_decode((string)$raw, true);

    return [
        'status' => $status,
        'body' => (string)$raw,
        'json' => is_array($json) ? $json : null,
    ];
}

function enquiryLoggerEnsureForgeColumns(): void
{
    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'forge_synced_at', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'forge_event_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'forge_last_action', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'forge_booking_status', 'TEXT');
}

function enquiryLoggerForgeAlreadySynced(int $enquiryId): bool
{
    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT forge_synced_at FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();

    return trim((string)($row['forge_synced_at'] ?? '')) !== '';
}

function enquiryLoggerMarkForgeSynced(
    int $enquiryId,
    string $action,
    ?string $eventId,
    ?string $bookingStatus = null
): void {
    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();
    $bookingStatus = trim((string)$bookingStatus);

    if ($bookingStatus !== '') {
        $stmt = $pdo->prepare(
            'UPDATE enquiries SET
                forge_synced_at = COALESCE(forge_synced_at, :forge_synced_at),
                forge_event_id = :forge_event_id,
                forge_last_action = :forge_last_action,
                forge_booking_status = :forge_booking_status,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $enquiryId,
            ':forge_synced_at' => $now,
            ':forge_event_id' => $eventId !== null && $eventId !== '' ? $eventId : null,
            ':forge_last_action' => $action,
            ':forge_booking_status' => $bookingStatus,
            ':updated_at' => $now,
        ]);
    } else {
        $stmt = $pdo->prepare(
            'UPDATE enquiries SET
                forge_synced_at = COALESCE(forge_synced_at, :forge_synced_at),
                forge_event_id = :forge_event_id,
                forge_last_action = :forge_last_action,
                updated_at = :updated_at
             WHERE id = :id'
        );
        $stmt->execute([
            ':id' => $enquiryId,
            ':forge_synced_at' => $now,
            ':forge_event_id' => $eventId !== null && $eventId !== '' ? $eventId : null,
            ':forge_last_action' => $action,
            ':updated_at' => $now,
        ]);
    }
}

/**
 * Send booking snapshot to Forge.
 *
 * - confirmed_book: venue + terms accepted (formal Accept Quote / admin booking)
 * - provisional_book: limited reserve data without formal terms (org/booker present)
 * - on_hold / to_rearrange / cancelled: explicit overrides (edit of an existing Forge booking)
 *
 * Never throws for expected skip paths; throws on HTTP/config errors so callers can warn
 * without rolling back the local booking save.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array<string, mixed>|null Forge 202 response body, or null when skipped
 */
function forgeMaybeSyncBooking(
    int $enquiryId,
    array $bookingDetails = [],
    ?string $bookingStatusOverride = null
): ?array {
    require_once __DIR__ . '/enquiry_logger.php';

    if (!forgeEnabled()) {
        return null;
    }

    $venueAddress = trim((string)($bookingDetails['venueAddress'] ?? ''));
    $termsAccepted = !empty($bookingDetails['termsAccepted']);
    $organisation = trim((string)($bookingDetails['organisation'] ?? ''));
    $bookerName = trim((string)($bookingDetails['bookerName'] ?? ''));
    $override = forgeNormaliseDeliveryStage($bookingStatusOverride);
    $isLifecycleOverride = in_array($override, ['on_hold', 'to_rearrange', 'cancelled'], true);

    // Formal booking needs venue + terms. Provisional reserve needs limited identifying data.
    if (!$isLifecycleOverride) {
        if ($termsAccepted && $venueAddress === '') {
            forgeLogJourneyEvent(
                $enquiryId,
                'forge_booking_sync_skipped',
                'Forge was not updated because the venue address is missing. Add the venue, then save the booking again.',
                ['reason' => 'missing_venue', 'terms_accepted' => true]
            );

            return null;
        }
        if (!$termsAccepted && $organisation === '' && $bookerName === '' && $venueAddress === '') {
            forgeLogJourneyEvent(
                $enquiryId,
                'forge_booking_sync_skipped',
                'Forge was not updated because there is not enough booking information yet (organisation, booker, or venue).',
                ['reason' => 'incomplete_booking_data']
            );

            return null;
        }
    }

    $token = forgeWebhookToken();
    $url = forgeWebhookUrl();
    if ($token === '' || $url === '') {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Forge is turned on, but the webhook URL or token is missing in Settings. Nothing was sent.',
            ['reason' => 'missing_config']
        );

        return null;
    }

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureForgeColumns();
    $stmt = $pdo->prepare(
        'SELECT id, name, email, status, organisation_company, org_course, trainer_course_select,
                preferred_date_time, date_not_sure, attendees, matrix_attendees, trainer_attendees,
                form_data_json, forge_synced_at, forge_booking_status, xero_invoice_sent_at
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $enquiry = $stmt->fetch();
    if (!$enquiry) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_failed',
            'Forge could not be updated because this enquiry could not be found in the database.',
            ['reason' => 'enquiry_not_found']
        );

        return null;
    }

    $alreadySynced = trim((string)($enquiry['forge_synced_at'] ?? '')) !== '';
    // Lifecycle holds/cancels only apply to bookings already in Forge.
    if ($isLifecycleOverride && !$alreadySynced) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Forge could not be set to ' . forgeDeliveryStageLabel($override)
                . ' because this enquiry has not been sent to Forge yet.',
            ['reason' => 'lifecycle_before_first_sync', 'requested_stage' => $override]
        );

        return null;
    }

    // First successful Forge push is create; later booking edits are edit.
    $action = $alreadySynced ? 'edit' : 'create';

    if ($override === '' && !$termsAccepted) {
        $override = 'provisional_book';
    } elseif ($override === '' && $termsAccepted) {
        $override = 'confirmed_book';
    }

    $previousStatus = forgeNormaliseDeliveryStage((string)($enquiry['forge_booking_status'] ?? ''));
    // Do not silently regress confirmed_book → provisional_book on later edits.
    if (
        $override === 'provisional_book'
        && $previousStatus === 'confirmed_book'
        && !$isLifecycleOverride
    ) {
        $override = 'confirmed_book';
    }

    $payload = forgeBuildBookingPayload(
        $enquiryId,
        $bookingDetails,
        $enquiry,
        $action,
        $override
    );
    $bookingStatus = forgeNormaliseDeliveryStage((string)($payload['booking']['booking_status'] ?? ''));

    // Skip no-op status edits once Forge already has this delivery stage.
    if (
        $action === 'edit'
        && $bookingStatusOverride !== null
        && $previousStatus !== ''
        && $previousStatus === $bookingStatus
    ) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Forge already has this booking as ' . forgeDeliveryStageLabel($bookingStatus)
                . '. No update was sent because nothing changed.',
            [
                'reason' => 'same_stage_noop',
                'action' => $action,
                'booking_status' => $bookingStatus,
                'delivery_stage' => $bookingStatus,
            ]
        );

        return [
            'status' => 'pending',
            'action' => $action,
            'booking_status' => $bookingStatus,
            'skipped' => true,
        ];
    }

    try {
        $response = forgeHttpPostBooking($payload);
    } catch (Throwable $e) {
        forgeFailAndThrow(
            $enquiryId,
            forgeFailureReasonFromException($e),
            [
                'reason' => 'transport_error',
                'action' => $action,
                'external_ref' => $payload['external_ref'] ?? null,
                'booking_status' => $bookingStatus !== '' ? $bookingStatus : null,
                'delivery_stage' => $bookingStatus !== '' ? $bookingStatus : null,
                'error' => $e->getMessage(),
            ]
        );
    }

    $status = (int)$response['status'];
    $json = $response['json'];

    if ($status !== 202) {
        $detail = is_array($json) ? trim((string)($json['detail'] ?? $json['message'] ?? '')) : '';
        $snippet = $detail !== '' ? $detail : substr($response['body'], 0, 300);
        $plain = forgeFailureReasonFromHttp($status, $snippet);
        forgeFailAndThrow(
            $enquiryId,
            $plain,
            [
                'reason' => 'http_error',
                'http_status' => $status,
                'action' => $action,
                'external_ref' => $payload['external_ref'],
                'booking_status' => $bookingStatus !== '' ? $bookingStatus : null,
                'delivery_stage' => $bookingStatus !== '' ? $bookingStatus : null,
                'response' => $snippet,
            ]
        );
    }

    $eventId = is_array($json) && isset($json['event_id']) ? (string)$json['event_id'] : null;
    enquiryLoggerMarkForgeSynced($enquiryId, $action, $eventId, $bookingStatus);

    $syncMessage = 'Booking snapshot sent to Forge for admin review.';
    if ($bookingStatus !== '') {
        $syncMessage = 'Booking snapshot sent to Forge as ' . forgeDeliveryStageLabel($bookingStatus) . '.';
    }

    forgeLogJourneyEvent(
        $enquiryId,
        'forge_booking_synced',
        $syncMessage,
        [
            'action' => $action,
            'external_ref' => $payload['external_ref'],
            'event_id' => $eventId,
            'status' => is_array($json) ? ($json['status'] ?? 'pending') : 'pending',
            'booking_status' => $bookingStatus !== '' ? $bookingStatus : null,
            'delivery_stage' => $bookingStatus !== '' ? $bookingStatus : null,
            'previous_booking_status' => $previousStatus !== '' ? $previousStatus : null,
            'change_count' => is_array($json) ? ($json['change_count'] ?? null) : null,
            'target_not_found' => is_array($json) ? ($json['target_not_found'] ?? null) : null,
            'session_date_count' => count($payload['session_dates'] ?? []),
            'delegate_count' => count($payload['delegates'] ?? []),
        ]
    );

    if (
        $bookingStatus !== ''
        && $previousStatus !== ''
        && $previousStatus !== $bookingStatus
    ) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_status_updated',
            'Forge delivery stage changed from '
                . forgeDeliveryStageLabel($previousStatus)
                . ' to '
                . forgeDeliveryStageLabel($bookingStatus)
                . '.',
            [
                'external_ref' => $payload['external_ref'],
                'event_id' => $eventId,
                'from' => $previousStatus,
                'to' => $bookingStatus,
                'action' => $action,
            ]
        );
    }

    return is_array($json) ? $json : ['status' => 'pending', 'action' => $action, 'booking_status' => $bookingStatus];
}

/**
 * After accept-form completion, push so Forge shows confirmed_book.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array<string, mixed>|null
 */
function forgeMaybeMarkBookingAccepted(int $enquiryId, array $bookingDetails = []): ?array
{
    require_once __DIR__ . '/enquiry_logger.php';

    if (!forgeEnabled()) {
        return null;
    }

    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT forge_synced_at, forge_booking_status, booking_details_json, xero_invoice_sent_at
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($bookingDetails === []) {
        $raw = trim((string)($row['booking_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $bookingDetails = $decoded;
            }
        }
    }
    if ($bookingDetails === [] || empty($bookingDetails['termsAccepted'])) {
        return null;
    }

    $current = forgeNormaliseDeliveryStage((string)($row['forge_booking_status'] ?? ''));
    if (in_array($current, ['confirmed_book', 'cancelled'], true)) {
        return null;
    }

    $bookingDetails['termsAccepted'] = true;

    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, 'confirmed_book');
}

/**
 * After Xero invoice is sent, refresh Forge snapshot but keep confirmed_book
 * (invoicing is not a Booking Delivery Stage).
 *
 * @param array<string, mixed> $bookingDetails
 * @return array<string, mixed>|null
 */
function forgeMaybeMarkInvoiceSent(int $enquiryId, array $bookingDetails = []): ?array
{
    require_once __DIR__ . '/enquiry_logger.php';

    if (!forgeEnabled()) {
        return null;
    }

    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT forge_synced_at, forge_booking_status, booking_details_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($bookingDetails === []) {
        $raw = trim((string)($row['booking_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $bookingDetails = $decoded;
            }
        }
    }
    if ($bookingDetails === []) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'The invoice was sent, but Forge was not updated because booking details are missing on this enquiry.',
            ['reason' => 'missing_booking_details', 'trigger' => 'invoice_sent']
        );

        return null;
    }

    $current = forgeNormaliseDeliveryStage((string)($row['forge_booking_status'] ?? ''));
    if ($current === 'cancelled') {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'The invoice was sent, but Forge was left unchanged because this booking is already cancelled in Forge.',
            ['reason' => 'cancelled_booking', 'trigger' => 'invoice_sent', 'booking_status' => 'cancelled']
        );

        return [
            'status' => 'pending',
            'action' => 'edit',
            'booking_status' => 'cancelled',
            'skipped' => true,
        ];
    }

    $bookingDetails['termsAccepted'] = true;
    $bookingDetails['invoiceSent'] = true;

    // Stay on confirmed_book — invoice timing is independent of delivery stage.
    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, 'confirmed_book');
}

/**
 * After Kajabi enrollment, refresh Forge snapshot but keep confirmed_book.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array<string, mixed>|null
 */
function forgeMaybeMarkQuoteWon(int $enquiryId, array $bookingDetails = []): ?array
{
    require_once __DIR__ . '/enquiry_logger.php';

    if (!forgeEnabled()) {
        return null;
    }

    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT forge_synced_at, forge_booking_status, booking_details_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($bookingDetails === []) {
        $raw = trim((string)($row['booking_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $bookingDetails = $decoded;
            }
        }
    }
    if ($bookingDetails === []) {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Kajabi enrollment completed, but Forge was not updated because booking details are missing on this enquiry.',
            ['reason' => 'missing_booking_details', 'trigger' => 'kajabi_enroll']
        );

        return null;
    }

    $current = forgeNormaliseDeliveryStage((string)($row['forge_booking_status'] ?? ''));
    if ($current === 'cancelled') {
        forgeLogJourneyEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Kajabi enrollment completed, but Forge was left unchanged because this booking is already cancelled in Forge.',
            ['reason' => 'cancelled_booking', 'trigger' => 'kajabi_enroll', 'booking_status' => 'cancelled']
        );

        return [
            'status' => 'pending',
            'action' => 'edit',
            'booking_status' => 'cancelled',
            'skipped' => true,
        ];
    }

    $bookingDetails['termsAccepted'] = true;
    $bookingDetails['invoiceSent'] = true;
    $bookingDetails['quoteWon'] = true;

    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, 'confirmed_book');
}

/**
 * Push an explicit Forge delivery-stage change (on_hold, to_rearrange, cancelled, etc.).
 *
 * @param array<string, mixed> $bookingDetails
 * @return array<string, mixed>|null
 */
function forgeMaybeMarkDeliveryStage(
    int $enquiryId,
    string $stage,
    array $bookingDetails = []
): ?array {
    $stage = forgeNormaliseDeliveryStage($stage);
    if (!in_array($stage, FORGE_DELIVERY_STAGES, true)) {
        return null;
    }

    require_once __DIR__ . '/enquiry_logger.php';

    if (!forgeEnabled()) {
        return null;
    }

    enquiryLoggerEnsureForgeColumns();
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT forge_synced_at, forge_booking_status, booking_details_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    if ($bookingDetails === []) {
        $raw = trim((string)($row['booking_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $bookingDetails = $decoded;
            }
        }
    }

    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, $stage);
}
