<?php
declare(strict_types=1);

/**
 * Kajabi Public API helpers — create/find contacts and grant course offers
 * after an enquiry reaches Quote Won (Xero invoice sent).
 */

const KAJABI_API_BASE = 'https://api.kajabi.com/v1';
const KAJABI_DEFAULT_SITE_ID = '157262';
/** Offer that unlocks the 2026 Legal Briefing course. */
const KAJABI_DEFAULT_OFFER_ID = '2150036193';
/** Course/product unlocked by the default offer. */
const KAJABI_DEFAULT_COURSE_ID = '2148855078';
const KAJABI_DEFAULT_OFFER_TITLE = '2026 Legal Briefing on the use of Reasonable Force';
const KAJABI_DEFAULT_COURSE_TITLE = '2026 Legal Briefing on the use of Reasonable Force';

function kajabiClientId(): string
{
    return appConfigValue('KAJABI_CLIENT_ID', 'kajabiClientId');
}

function kajabiClientSecret(): string
{
    return appConfigValue('KAJABI_CLIENT_SECRET', 'kajabiClientSecret');
}

function kajabiSiteId(): string
{
    $id = appConfigValue('KAJABI_SITE_ID', 'kajabiSiteId', KAJABI_DEFAULT_SITE_ID);

    return $id !== '' ? $id : KAJABI_DEFAULT_SITE_ID;
}

function kajabiOfferIdConfigured(): string
{
    return appConfigValue('KAJABI_OFFER_ID', 'kajabiOfferId', KAJABI_DEFAULT_OFFER_ID);
}

function kajabiOfferTitle(): string
{
    $title = appConfigValue('KAJABI_OFFER_TITLE', 'kajabiOfferTitle', KAJABI_DEFAULT_OFFER_TITLE);

    return $title !== '' ? $title : KAJABI_DEFAULT_OFFER_TITLE;
}

function kajabiEnabled(): bool
{
    if (kajabiClientId() === '' || kajabiClientSecret() === '') {
        return false;
    }

    $env = getenv('KAJABI_ENABLED');
    if ($env === false || $env === '') {
        $env = $_ENV['KAJABI_ENABLED'] ?? '';
    }
    if ($env !== '' && $env !== false) {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    if (function_exists('appSetting')) {
        $raw = appSetting('kajabi_enabled', '');
        if ($raw !== null && $raw !== '') {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }
    }

    // Credentials present and not explicitly disabled.
    return true;
}

/**
 * @return array{access_token:string,expires_at:int}
 */
function kajabiStoredToken(): array
{
    $access = '';
    $expiresAt = 0;

    if (function_exists('appSetting')) {
        $access = trim((string) appSetting('kajabi_access_token', ''));
        $rawExpires = trim((string) appSetting('kajabi_token_expires_at', ''));
        $expiresAt = is_numeric($rawExpires) ? (int) $rawExpires : 0;
    }

    if ($access === '' && class_exists(\App\Models\Setting::class)) {
        try {
            if (function_exists('app') && app()->bound('db')) {
                $access = trim((string) \App\Models\Setting::getValue('kajabi_access_token', ''));
                $rawExpires = trim((string) \App\Models\Setting::getValue('kajabi_token_expires_at', ''));
                $expiresAt = is_numeric($rawExpires) ? (int) $rawExpires : 0;
            }
        } catch (Throwable $e) {
            // Fall through.
        }
    }

    if ($access === '') {
        $access = trim((string) ($GLOBALS['kajabiAccessToken'] ?? ''));
        $rawExpires = trim((string) ($GLOBALS['kajabiTokenExpiresAt'] ?? ''));
        $expiresAt = is_numeric($rawExpires) ? (int) $rawExpires : 0;
    }

    return [
        'access_token' => $access,
        'expires_at' => $expiresAt,
    ];
}

function kajabiPersistToken(string $accessToken, int $expiresAt): void
{
    $GLOBALS['kajabiAccessToken'] = $accessToken;
    $GLOBALS['kajabiTokenExpiresAt'] = (string) $expiresAt;

    $pairs = [
        'kajabi_access_token' => $accessToken,
        'kajabi_token_expires_at' => (string) $expiresAt,
    ];

    if (class_exists(\App\Models\Setting::class)) {
        try {
            if (function_exists('app') && app()->bound('db')) {
                foreach ($pairs as $key => $value) {
                    \App\Models\Setting::setValue($key, $value);
                }
                if (function_exists('appSettingsFlushCache')) {
                    appSettingsFlushCache();
                }

                return;
            }
        } catch (Throwable $e) {
            // Fall through to PDO settings write if available.
        }
    }

    if (! function_exists('appSettingSet')) {
        require_once __DIR__.'/database_bridge.php';
    }
    if (! function_exists('appSettingSet')) {
        return;
    }

    foreach ($pairs as $key => $value) {
        appSettingSet($key, $value);
    }
}

/**
 * @return array{access_token:string,expires_at:int}
 */
function kajabiEnsureAccessToken(): array
{
    $tokens = kajabiStoredToken();
    if ($tokens['access_token'] !== '' && $tokens['expires_at'] > (time() + 120)) {
        return $tokens;
    }

    $clientId = kajabiClientId();
    $clientSecret = kajabiClientSecret();
    if ($clientId === '' || $clientSecret === '') {
        throw new RuntimeException('Kajabi is not configured (missing client id/secret).');
    }

    $ch = curl_init(KAJABI_API_BASE.'/oauth/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Kajabi token request failed: '.$curlError);
    }

    $decoded = json_decode((string) $body, true);
    if ($status >= 400 || ! is_array($decoded) || empty($decoded['access_token'])) {
        $detail = is_array($decoded) ? (string) ($decoded['error'] ?? $body) : (string) $body;
        throw new RuntimeException('Kajabi authentication failed (HTTP '.$status.'): '.$detail);
    }

    $expiresIn = isset($decoded['expires_in']) ? (int) $decoded['expires_in'] : 3600;
    $expiresAt = time() + max(60, $expiresIn);
    $accessToken = (string) $decoded['access_token'];
    kajabiPersistToken($accessToken, $expiresAt);

    return [
        'access_token' => $accessToken,
        'expires_at' => $expiresAt,
    ];
}

/**
 * @param array<string, scalar|null> $query
 * @param array<string, mixed>|null $jsonBody
 * @return array{status:int,body:mixed,raw:string}
 */
function kajabiRequest(string $method, string $path, ?array $query = null, ?array $jsonBody = null): array
{
    $auth = kajabiEnsureAccessToken();
    $url = KAJABI_API_BASE.$path;
    if ($query !== null && $query !== []) {
        $parts = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = rawurlencode((string) $key).'='.rawurlencode((string) $value);
        }
        if ($parts !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').implode('&', $parts);
        }
    }

    $headers = [
        'Authorization: Bearer '.$auth['access_token'],
        'Accept: application/vnd.api+json',
    ];
    $payload = null;
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/vnd.api+json';
        $payload = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 45,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    }

    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Kajabi API request failed: '.$curlError);
    }

    $decoded = json_decode((string) $raw, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => (string) $raw,
    ];
}

function kajabiErrorMessage(array $response): string
{
    $body = $response['body'] ?? null;
    if (is_array($body) && isset($body['errors']) && is_array($body['errors'])) {
        $parts = [];
        foreach ($body['errors'] as $error) {
            if (! is_array($error)) {
                continue;
            }
            $parts[] = trim((string) ($error['detail'] ?? $error['title'] ?? ''));
        }
        $joined = trim(implode('; ', array_filter($parts)));
        if ($joined !== '') {
            return $joined;
        }
    }

    $raw = trim((string) ($response['raw'] ?? ''));

    return $raw !== '' ? $raw : ('HTTP '.(int) ($response['status'] ?? 0));
}

function kajabiResolveOfferId(): array
{
    $configured = trim(kajabiOfferIdConfigured());
    $displayTitle = kajabiOfferTitle();

    if ($configured !== '') {
        $title = $displayTitle;
        $courseId = KAJABI_DEFAULT_COURSE_ID;
        $courseTitle = KAJABI_DEFAULT_COURSE_TITLE;

        try {
            $offerResp = kajabiRequest('GET', '/offers/'.rawurlencode($configured));
            if ($offerResp['status'] < 400 && is_array($offerResp['body']['data'] ?? null)) {
                $fetchedTitle = trim((string) ($offerResp['body']['data']['attributes']['title'] ?? ''));
                if ($fetchedTitle !== '') {
                    $title = $fetchedTitle;
                }
            }

            $productsResp = kajabiRequest('GET', '/offers/'.rawurlencode($configured).'/relationships/products');
            $productIds = [];
            foreach (($productsResp['body']['data'] ?? []) as $product) {
                if (is_array($product) && ! empty($product['id'])) {
                    $productIds[] = (string) $product['id'];
                }
            }
            if ($productIds !== []) {
                $courseId = $productIds[0];
                $courseResp = kajabiRequest('GET', '/courses/'.rawurlencode($courseId));
                $fetchedCourseTitle = trim((string) ($courseResp['body']['data']['attributes']['title'] ?? ''));
                if ($fetchedCourseTitle !== '') {
                    $courseTitle = $fetchedCourseTitle;
                    // Prefer the course product title in journey messages.
                    $title = $fetchedCourseTitle;
                }
            }
        } catch (Throwable $e) {
            // Keep configured defaults when lookup fails.
        }

        return [
            'id' => $configured,
            'title' => $title !== '' ? $title : $displayTitle,
            'course_id' => $courseId,
            'course_title' => $courseTitle,
        ];
    }

    $title = $displayTitle;
    $searchTerms = array_values(array_unique(array_filter([
        $title,
        '2026 Legal Briefing',
        '2026 Legal Brief',
    ])));

    $data = [];
    foreach ($searchTerms as $term) {
        $response = kajabiRequest('GET', '/offers', [
            'page[size]' => 25,
            'filter[site_id]' => kajabiSiteId(),
            'filter[title_cont]' => $term,
        ]);
        if ($response['status'] < 400 && is_array($response['body']['data'] ?? null) && $response['body']['data'] !== []) {
            $data = $response['body']['data'];
            break;
        }
    }

    if ($data === []) {
        $response = kajabiRequest('GET', '/offers', [
            'page[size]' => 25,
            'filter[title_cont]' => '2026 Legal Brief',
        ]);
        $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    }

    foreach ($data as $offer) {
        if (! is_array($offer)) {
            continue;
        }
        $offerTitle = trim((string) ($offer['attributes']['title'] ?? ''));
        $normalized = strtolower($offerTitle);
        if (
            $offerTitle === $title
            || str_contains($normalized, '2026 legal briefing')
            || str_contains($normalized, '2026 legal brief')
        ) {
            return [
                'id' => (string) ($offer['id'] ?? ''),
                'title' => $offerTitle !== '' ? $offerTitle : $title,
                'course_id' => KAJABI_DEFAULT_COURSE_ID,
                'course_title' => KAJABI_DEFAULT_COURSE_TITLE,
            ];
        }
    }

    if (isset($data[0]) && is_array($data[0]) && ! empty($data[0]['id'])) {
        return [
            'id' => (string) $data[0]['id'],
            'title' => trim((string) ($data[0]['attributes']['title'] ?? $title)),
            'course_id' => KAJABI_DEFAULT_COURSE_ID,
            'course_title' => KAJABI_DEFAULT_COURSE_TITLE,
        ];
    }

    // Hard fallback to the known Safer Handling 2026 Legal Briefing offer.
    return [
        'id' => KAJABI_DEFAULT_OFFER_ID,
        'title' => KAJABI_DEFAULT_COURSE_TITLE,
        'course_id' => KAJABI_DEFAULT_COURSE_ID,
        'course_title' => KAJABI_DEFAULT_COURSE_TITLE,
    ];
}

/**
 * @return list<string>
 */
function kajabiContactOfferIds(string $contactId): array
{
    $response = kajabiRequest('GET', '/contacts/'.rawurlencode($contactId).'/relationships/offers');
    if ($response['status'] >= 400 || ! is_array($response['body'])) {
        throw new RuntimeException('Kajabi contact offers lookup failed: '.kajabiErrorMessage($response));
    }

    $ids = [];
    foreach (($response['body']['data'] ?? []) as $item) {
        if (is_array($item) && ! empty($item['id'])) {
            $ids[] = (string) $item['id'];
        }
    }

    return $ids;
}

function kajabiContactHasOffer(string $contactId, string $offerId): bool
{
    $offerId = trim($offerId);
    if ($offerId === '') {
        return false;
    }

    return in_array($offerId, kajabiContactOfferIds($contactId), true);
}

/**
 * Grant the course offer and verify it is attached to the contact.
 *
 * @return array{ok:bool,offer_ids:list<string>,already_had:bool}
 */
function kajabiEnsureOfferGranted(string $contactId, string $offerId, bool $sendWelcomeEmail = true): array
{
    $offerId = trim($offerId);
    if ($contactId === '' || $offerId === '') {
        throw new RuntimeException('Kajabi contact id or offer id is missing.');
    }

    if (kajabiContactHasOffer($contactId, $offerId)) {
        return [
            'ok' => true,
            'offer_ids' => [$offerId],
            'already_had' => true,
        ];
    }

    $granted = kajabiGrantOffer($contactId, $offerId, $sendWelcomeEmail);

    // Confirm the course offer is actually assigned after grant.
    if (! kajabiContactHasOffer($contactId, $offerId)) {
        throw new RuntimeException(
            'Kajabi offer grant did not assign course offer '.$offerId.' to contact '.$contactId.'.'
        );
    }

    return [
        'ok' => true,
        'offer_ids' => $granted['offer_ids'] !== [] ? $granted['offer_ids'] : [$offerId],
        'already_had' => false,
    ];
}

/**
 * @return array{id:string,email:string,name:string}|null
 */
function kajabiFindContactByEmail(string $siteId, string $email): ?array
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return null;
    }

    $response = kajabiRequest('GET', '/contacts', [
        'page[size]' => 25,
        'filter[site_id]' => $siteId,
        'filter[email_contains]' => $email,
    ]);

    if ($response['status'] >= 400 || ! is_array($response['body'])) {
        throw new RuntimeException('Kajabi contact lookup failed: '.kajabiErrorMessage($response));
    }

    $data = $response['body']['data'] ?? [];
    if (! is_array($data)) {
        return null;
    }

    foreach ($data as $contact) {
        if (! is_array($contact)) {
            continue;
        }
        $contactEmail = strtolower(trim((string) ($contact['attributes']['email'] ?? '')));
        if ($contactEmail === $email && ! empty($contact['id'])) {
            return [
                'id' => (string) $contact['id'],
                'email' => $contactEmail,
                'name' => trim((string) ($contact['attributes']['name'] ?? '')),
            ];
        }
    }

    // Broader search fallback.
    $response = kajabiRequest('GET', '/contacts', [
        'page[size]' => 25,
        'filter[site_id]' => $siteId,
        'filter[search]' => $email,
    ]);
    $data = is_array($response['body']['data'] ?? null) ? $response['body']['data'] : [];
    foreach ($data as $contact) {
        if (! is_array($contact)) {
            continue;
        }
        $contactEmail = strtolower(trim((string) ($contact['attributes']['email'] ?? '')));
        if ($contactEmail === $email && ! empty($contact['id'])) {
            return [
                'id' => (string) $contact['id'],
                'email' => $contactEmail,
                'name' => trim((string) ($contact['attributes']['name'] ?? '')),
            ];
        }
    }

    return null;
}

/**
 * @return array{id:string,email:string,name:string,created:bool}
 */
function kajabiEnsureContact(string $siteId, string $name, string $email): array
{
    $existing = kajabiFindContactByEmail($siteId, $email);
    if ($existing !== null) {
        return [
            'id' => $existing['id'],
            'email' => $existing['email'],
            'name' => $existing['name'] !== '' ? $existing['name'] : $name,
            'created' => false,
        ];
    }

    $response = kajabiRequest('POST', '/contacts', null, [
        'data' => [
            'type' => 'contacts',
            'attributes' => [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
            ],
            'relationships' => [
                'site' => [
                    'data' => [
                        'type' => 'sites',
                        'id' => $siteId,
                    ],
                ],
            ],
        ],
    ]);

    if ($response['status'] === 422 || $response['status'] === 409) {
        $existing = kajabiFindContactByEmail($siteId, $email);
        if ($existing !== null) {
            return [
                'id' => $existing['id'],
                'email' => $existing['email'],
                'name' => $existing['name'] !== '' ? $existing['name'] : $name,
                'created' => false,
            ];
        }
    }

    if ($response['status'] >= 400 || ! is_array($response['body']['data'] ?? null)) {
        throw new RuntimeException('Kajabi contact create failed: '.kajabiErrorMessage($response));
    }

    $data = $response['body']['data'];

    return [
        'id' => (string) ($data['id'] ?? ''),
        'email' => strtolower(trim((string) ($data['attributes']['email'] ?? $email))),
        'name' => trim((string) ($data['attributes']['name'] ?? $name)),
        'created' => true,
    ];
}

/**
 * @return array{ok:bool,offer_ids:list<string>}
 */
function kajabiGrantOffer(string $contactId, string $offerId, bool $sendWelcomeEmail = true): array
{
    $response = kajabiRequest('POST', '/contacts/'.rawurlencode($contactId).'/relationships/offers', null, [
        'data' => [
            [
                'type' => 'offers',
                'id' => $offerId,
            ],
        ],
        'meta' => [
            'send_customer_welcome_email' => $sendWelcomeEmail,
        ],
    ]);

    if ($response['status'] >= 400) {
        throw new RuntimeException('Kajabi offer grant failed: '.kajabiErrorMessage($response));
    }

    $ids = [];
    $data = $response['body']['data'] ?? [];
    if (is_array($data)) {
        foreach ($data as $item) {
            if (is_array($item) && ! empty($item['id'])) {
                $ids[] = (string) $item['id'];
            }
        }
    }

    return [
        'ok' => true,
        'offer_ids' => $ids,
    ];
}

function kajabiMarkEnrolled(int $enquiryId, string $contactId, string $offerId): void
{
    require_once __DIR__.'/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_contact_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_offer_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_enrolled_at', 'TEXT');

    $now = enquiryLoggerNow();
    $stmt = $pdo->prepare(
        'UPDATE enquiries
         SET kajabi_contact_id = COALESCE(NULLIF(TRIM(kajabi_contact_id), \'\'), :contact_id),
             kajabi_offer_id = :offer_id,
             kajabi_enrolled_at = COALESCE(kajabi_enrolled_at, :enrolled_at),
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':contact_id' => $contactId,
        ':offer_id' => $offerId,
        ':enrolled_at' => $now,
        ':updated_at' => $now,
        ':id' => $enquiryId,
    ]);
}

/**
 * Create/find a Kajabi contact and enroll them on the Legal Brief offer.
 *
 * @return array{
 *   attempted:bool,
 *   enrolled:bool,
 *   already_enrolled:bool,
 *   skipped:bool,
 *   contact_id:?string,
 *   offer_id:?string,
 *   offer_title:?string,
 *   created_contact:bool,
 *   forge:?array,
 *   monday:?array,
 *   error:?string
 * }
 */
function kajabiMaybeEnrollAfterQuoteWon(int $enquiryId, bool $force = false): array
{
    require_once __DIR__.'/enquiry_logger.php';

    $result = [
        'attempted' => false,
        'enrolled' => false,
        'already_enrolled' => false,
        'skipped' => false,
        'contact_id' => null,
        'offer_id' => null,
        'offer_title' => null,
        'created_contact' => false,
        'forge' => null,
        'monday' => null,
        'error' => null,
    ];

    if (! kajabiEnabled()) {
        $result['skipped'] = true;
        $result['error'] = 'Kajabi not configured';

        return $result;
    }

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_contact_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_offer_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'kajabi_enrolled_at', 'TEXT');

    $stmt = $pdo->prepare(
        'SELECT id, name, email, kajabi_contact_id, kajabi_offer_id, kajabi_enrolled_at
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (! $row) {
        $result['skipped'] = true;
        $result['error'] = 'Enquiry not found';

        return $result;
    }

    $alreadyEnrolledAt = trim((string) ($row['kajabi_enrolled_at'] ?? ''));
    if ($alreadyEnrolledAt !== '' && ! $force) {
        $result['already_enrolled'] = true;
        $result['skipped'] = true;
        $result['contact_id'] = trim((string) ($row['kajabi_contact_id'] ?? '')) ?: null;
        $result['offer_id'] = trim((string) ($row['kajabi_offer_id'] ?? '')) ?: null;

        // Ensure the Legal Briefing course is still assigned even if we enrolled earlier.
        $contactId = trim((string) ($result['contact_id'] ?? ''));
        $offerId = trim((string) ($result['offer_id'] ?? kajabiOfferIdConfigured()));
        if ($contactId !== '' && $offerId !== '') {
            try {
                $ensure = kajabiEnsureOfferGranted($contactId, $offerId, false);
                if (empty($ensure['already_had'])) {
                    enquiryLoggerEvent(
                        $enquiryId,
                        'kajabi_enrolled',
                        'Assigned Kajabi course: '.KAJABI_DEFAULT_COURSE_TITLE.'.',
                        [
                            'channel' => 'kajabi',
                            'kajabi_contact_id' => $contactId,
                            'kajabi_offer_id' => $offerId,
                            'kajabi_course_id' => KAJABI_DEFAULT_COURSE_ID,
                            'kajabi_course_title' => KAJABI_DEFAULT_COURSE_TITLE,
                            'repaired' => true,
                        ]
                    );
                }
            } catch (Throwable $e) {
                enquiryLoggerEvent(
                    $enquiryId,
                    'kajabi_enroll_failed',
                    'Kajabi course assignment check failed: '.$e->getMessage(),
                    [
                        'channel' => 'kajabi',
                        'error' => $e->getMessage(),
                        'kajabi_contact_id' => $contactId,
                        'kajabi_offer_id' => $offerId,
                    ]
                );
            }
        }

        $result['forge'] = kajabiMaybeUpdateForgeQuoteWon($enquiryId);
        $result['monday'] = kajabiMaybeMoveMondayExported($enquiryId);

        return $result;
    }

    $email = strtolower(trim((string) ($row['email'] ?? '')));
    $name = trim((string) ($row['name'] ?? ''));
    if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result['attempted'] = true;
        $result['error'] = 'Enquiry has no valid email for Kajabi enrollment';
        enquiryLoggerEvent(
            $enquiryId,
            'kajabi_enroll_failed',
            'Kajabi enrollment skipped — enquiry email is missing or invalid.',
            ['channel' => 'kajabi', 'error' => $result['error']]
        );

        return $result;
    }

    $result['attempted'] = true;

    try {
        $siteId = kajabiSiteId();
        $offer = kajabiResolveOfferId();
        $offerId = trim((string) ($offer['id'] ?? ''));
        $offerTitle = trim((string) ($offer['title'] ?? kajabiOfferTitle()));
        $courseId = trim((string) ($offer['course_id'] ?? KAJABI_DEFAULT_COURSE_ID));
        $courseTitle = trim((string) ($offer['course_title'] ?? KAJABI_DEFAULT_COURSE_TITLE));
        if ($offerId === '') {
            throw new RuntimeException('Kajabi offer id is empty.');
        }

        $result['offer_id'] = $offerId;
        $result['offer_title'] = $courseTitle !== '' ? $courseTitle : $offerTitle;

        $contact = kajabiEnsureContact($siteId, $name, $email);
        $result['contact_id'] = $contact['id'];
        $result['created_contact'] = $contact['created'];

        if ($contact['created']) {
            enquiryLoggerEvent(
                $enquiryId,
                'kajabi_account_created',
                'Kajabi account created for '.$email.'.',
                [
                    'channel' => 'kajabi',
                    'kajabi_contact_id' => $contact['id'],
                    'kajabi_site_id' => $siteId,
                    'email' => $email,
                ]
            );
        } else {
            enquiryLoggerEvent(
                $enquiryId,
                'kajabi_account_found',
                'Existing Kajabi account found for '.$email.'.',
                [
                    'channel' => 'kajabi',
                    'kajabi_contact_id' => $contact['id'],
                    'kajabi_site_id' => $siteId,
                    'email' => $email,
                ]
            );
        }

        // Always assign the Legal Briefing course offer immediately after account create/find.
        $grant = kajabiEnsureOfferGranted($contact['id'], $offerId, true);
        kajabiMarkEnrolled($enquiryId, $contact['id'], $offerId);

        enquiryLoggerEvent(
            $enquiryId,
            'kajabi_enrolled',
            ($grant['already_had'] ? 'Confirmed' : 'Assigned')
                .' Kajabi course: '.$result['offer_title'].'.',
            [
                'channel' => 'kajabi',
                'kajabi_contact_id' => $contact['id'],
                'kajabi_offer_id' => $offerId,
                'kajabi_offer_title' => $offerTitle,
                'kajabi_course_id' => $courseId !== '' ? $courseId : null,
                'kajabi_course_title' => $courseTitle !== '' ? $courseTitle : null,
                'kajabi_site_id' => $siteId,
                'created_contact' => $contact['created'],
                'already_had_offer' => $grant['already_had'],
                'email' => $email,
            ]
        );

        $result['enrolled'] = true;
        $result['forge'] = kajabiMaybeUpdateForgeQuoteWon($enquiryId);
        $result['monday'] = kajabiMaybeMoveMondayExported($enquiryId);
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
        enquiryLoggerEvent(
            $enquiryId,
            'kajabi_enroll_failed',
            'Kajabi enrollment failed: '.$e->getMessage(),
            [
                'channel' => 'kajabi',
                'error' => $e->getMessage(),
                'kajabi_contact_id' => $result['contact_id'],
                'kajabi_offer_id' => $result['offer_id'],
            ]
        );
    }

    return $result;
}

/**
 * After Kajabi enrollment, move Forge booking status to Quote Won.
 *
 * @return array<string, mixed>|null
 */
function kajabiMaybeUpdateForgeQuoteWon(int $enquiryId): ?array
{
    try {
        require_once __DIR__.'/forge_webhook.php';

        return forgeMaybeMarkQuoteWon($enquiryId);
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'forge_booking_sync_failed',
            'Kajabi enrollment completed, but Forge booking snapshot could not be updated.',
            [
                'channel' => 'forge',
                'error' => $e->getMessage(),
                'booking_status' => 'confirmed_book',
            ]
        );

        return [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * After Kajabi enrollment, move the Monday enquiry item to Exported.
 *
 * @return array<string, mixed>|null
 */
function kajabiMaybeMoveMondayExported(int $enquiryId): ?array
{
    try {
        require_once __DIR__.'/monday_helpers.php';

        return mondayMoveEnquiryToExportedAfterKajabi($enquiryId);
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_failed',
            'Kajabi enrollment completed, but the Monday item could not be moved to Exported.',
            [
                'error' => $e->getMessage(),
                'trigger' => 'kajabi_enrolled',
                'target_group' => 'Exported',
            ]
        );

        return [
            'moved' => false,
            'error' => $e->getMessage(),
        ];
    }
}
