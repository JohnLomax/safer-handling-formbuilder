<?php
declare(strict_types=1);

/**
 * Safer Handling Forge CRM booking-intake webhook.
 *
 * Sends a full booking snapshot when accept-form + venue details are saved.
 * Payloads are staged for admin review in Forge — they are not applied live.
 */

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
 * Map local enquiry / booking state to the Forge booking_status label.
 * Accept-form → Accepted; after Xero invoice is sent → Invoice Sent.
 */
function forgeBookingStatusLabel(
    ?string $enquiryStatus,
    bool $termsAccepted = false,
    bool $invoiceSent = false
): string {
    $status = strtolower(trim((string)$enquiryStatus));

    if ($invoiceSent || $status === 'quote_won' || $status === 'invoice_sent') {
        return 'Invoice Sent';
    }

    if (
        $termsAccepted
        || in_array($status, ['quote_accepted', 'accepted'], true)
    ) {
        return 'Accepted';
    }

    if (in_array($status, ['quote_sent', 'pending'], true)) {
        return 'Pending';
    }

    if ($status === '') {
        return $termsAccepted ? 'Accepted' : 'Pending';
    }

    return ucwords(str_replace('_', ' ', $status));
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

    $bookingStatus = trim((string)$bookingStatusOverride);
    if ($bookingStatus === '') {
        $bookingStatus = forgeBookingStatusLabel(
            $enquiry['status'] ?? null,
            $termsAccepted,
            $invoiceSent
        );
    }

    $booking = [
        'booking_status' => $bookingStatus,
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
    $dateNotSure = !empty($enquiry['date_not_sure']);
    $parsedDate = $dateNotSure ? null : forgeParsePreferredDateTime($enquiry['preferred_date_time'] ?? null);
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
 * Send booking snapshot to Forge after accept form + venue details are saved.
 * Never throws out of the helper for expected skip paths; throws on HTTP/config errors
 * so callers can log a warning without rolling back the local booking save.
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
    if ($venueAddress === '' || !$termsAccepted) {
        return null;
    }

    $token = forgeWebhookToken();
    $url = forgeWebhookUrl();
    if ($token === '' || $url === '') {
        enquiryLoggerEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Forge webhook is enabled but URL or token is not configured.'
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
        return null;
    }

    // First successful Forge push is create; later booking edits are edit.
    $action = trim((string)($enquiry['forge_synced_at'] ?? '')) !== '' ? 'edit' : 'create';

    $payload = forgeBuildBookingPayload(
        $enquiryId,
        $bookingDetails,
        $enquiry,
        $action,
        $bookingStatusOverride
    );
    $bookingStatus = trim((string)($payload['booking']['booking_status'] ?? ''));
    $previousStatus = trim((string)($enquiry['forge_booking_status'] ?? ''));

    // Skip no-op status edits once Forge already has this status.
    if (
        $action === 'edit'
        && $bookingStatusOverride !== null
        && $previousStatus !== ''
        && strcasecmp($previousStatus, $bookingStatus) === 0
    ) {
        return [
            'status' => 'pending',
            'action' => $action,
            'booking_status' => $bookingStatus,
            'skipped' => true,
        ];
    }

    $response = forgeHttpPostBooking($payload);
    $status = (int)$response['status'];
    $json = $response['json'];

    if ($status !== 202) {
        $detail = is_array($json) ? trim((string)($json['detail'] ?? $json['message'] ?? '')) : '';
        $snippet = $detail !== '' ? $detail : substr($response['body'], 0, 300);
        enquiryLoggerEvent(
            $enquiryId,
            'forge_booking_sync_failed',
            'Booking details were saved, but Forge webhook failed.',
            [
                'http_status' => $status,
                'action' => $action,
                'external_ref' => $payload['external_ref'],
                'booking_status' => $bookingStatus !== '' ? $bookingStatus : null,
                'response' => $snippet,
            ]
        );
        throw new RuntimeException(
            'Forge webhook returned HTTP ' . $status . ($snippet !== '' ? ': ' . $snippet : '')
        );
    }

    $eventId = is_array($json) && isset($json['event_id']) ? (string)$json['event_id'] : null;
    enquiryLoggerMarkForgeSynced($enquiryId, $action, $eventId, $bookingStatus);

    $syncMessage = 'Booking snapshot sent to Forge for admin review.';
    if ($bookingStatus === 'Accepted') {
        $syncMessage = 'Booking snapshot sent to Forge with status Accepted.';
    } elseif ($bookingStatus === 'Invoice Sent') {
        $syncMessage = 'Booking snapshot sent to Forge with status Invoice Sent.';
    }

    enquiryLoggerEvent(
        $enquiryId,
        'forge_booking_synced',
        $syncMessage,
        [
            'action' => $action,
            'external_ref' => $payload['external_ref'],
            'event_id' => $eventId,
            'status' => is_array($json) ? ($json['status'] ?? 'pending') : 'pending',
            'booking_status' => $bookingStatus !== '' ? $bookingStatus : null,
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
        && strcasecmp($previousStatus, $bookingStatus) !== 0
    ) {
        enquiryLoggerEvent(
            $enquiryId,
            'forge_status_updated',
            'Forge client booking status updated from ' . $previousStatus . ' to ' . $bookingStatus . '.',
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
 * After accept-form completion, push so Forge shows Accepted (Pending → Accepted).
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

    // Invoice-sent wins over Accepted — do not regress status.
    if (trim((string)($row['xero_invoice_sent_at'] ?? '')) !== '') {
        return forgeMaybeMarkInvoiceSent($enquiryId, $bookingDetails);
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

    $current = trim((string)($row['forge_booking_status'] ?? ''));
    if (strcasecmp($current, 'Accepted') === 0 || strcasecmp($current, 'Invoice Sent') === 0) {
        return null;
    }

    $bookingDetails['termsAccepted'] = true;

    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, 'Accepted');
}

/**
 * After Xero invoice is sent, push an edit so Forge moves Accepted → Invoice Sent.
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
        enquiryLoggerEvent(
            $enquiryId,
            'forge_booking_sync_skipped',
            'Xero invoice sent, but Forge status could not be updated because booking details are missing.'
        );

        return null;
    }

    $current = trim((string)($row['forge_booking_status'] ?? ''));
    if (strcasecmp($current, 'Invoice Sent') === 0) {
        return [
            'status' => 'pending',
            'action' => 'edit',
            'booking_status' => 'Invoice Sent',
            'skipped' => true,
        ];
    }

    $bookingDetails['termsAccepted'] = true;
    $bookingDetails['invoiceSent'] = true;

    return forgeMaybeSyncBooking($enquiryId, $bookingDetails, 'Invoice Sent');
}
