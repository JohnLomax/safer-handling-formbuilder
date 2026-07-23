<?php
declare(strict_types=1);

/**
 * Xero Accounting API helpers for contacts, quotes, and quote emails.
 */

function xeroEnabled(): bool
{
    // Admin → Settings is the source of truth once settings are loaded.
    // A Coolify/local XERO_ENABLED=false must not silently disable quotes
    // when the checkbox is on in the database.
    if (array_key_exists('xeroEnabled', $GLOBALS)) {
        return (bool)$GLOBALS['xeroEnabled'];
    }

    $env = getenv('XERO_ENABLED');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    return false;
}

function xeroClientId(): string
{
    return appConfigValue('XERO_CLIENT_ID', 'xeroClientId');
}

function xeroClientSecret(): string
{
    return appConfigValue('XERO_CLIENT_SECRET', 'xeroClientSecret');
}

function xeroRedirectUri(): string
{
    return appConfigValue('XERO_REDIRECT_URI', 'xeroRedirectUri');
}

function xeroTenantId(): string
{
    return appConfigValue('XERO_TENANT_ID', 'xeroTenantId');
}

/**
 * Rotating OAuth tokens must come from the DB/settings store — never from env.
 * A stale XERO_REFRESH_TOKEN in Coolify will keep replaying a consumed token.
 */
function xeroStoredTokenValue(string $settingKey, string $globalKey): string
{
    if (function_exists('appSetting')) {
        $fromDb = trim((string) appSetting($settingKey, ''));
        if ($fromDb !== '') {
            return $fromDb;
        }
    }

    if (class_exists(\App\Models\Setting::class)) {
        try {
            if (function_exists('app') && app()->bound('db')) {
                $fromEloquent = trim((string) \App\Models\Setting::getValue($settingKey, ''));
                if ($fromEloquent !== '') {
                    return $fromEloquent;
                }
            }
        } catch (Throwable $e) {
            // Fall through to globals.
        }
    }

    return trim((string) ($GLOBALS[$globalKey] ?? ''));
}

function xeroAccessToken(): string
{
    return xeroStoredTokenValue('xero_access_token', 'xeroAccessToken');
}

function xeroRefreshToken(): string
{
    return xeroStoredTokenValue('xero_refresh_token', 'xeroRefreshToken');
}

function xeroTokenExpiresAt(): int
{
    $raw = xeroStoredTokenValue('xero_token_expires_at', 'xeroTokenExpiresAt');

    return is_numeric($raw) ? (int) $raw : 0;
}

/**
 * Drop process/Laravel settings caches so we never refresh with a stale token
 * that another worker already consumed.
 */
function xeroReloadTokenSettings(): void
{
    if (function_exists('appSettingsFlushCache')) {
        appSettingsFlushCache();
    }

    if (class_exists(\App\Models\Setting::class)) {
        try {
            \App\Models\Setting::flushCache();
        } catch (Throwable $e) {
            // Ignore when Laravel is not fully booted.
        }
    }

    if (function_exists('applyAppSettingsToGlobals')) {
        applyAppSettingsToGlobals();
    }
}

/**
 * Serialize refresh across PHP-FPM workers / containers that share MySQL.
 *
 * @template T
 * @param  callable(): T  $callback
 * @return T
 */
function xeroWithRefreshLock(callable $callback): mixed
{
    $pdo = function_exists('appDatabasePdo') ? appDatabasePdo() : null;
    $locked = false;

    if ($pdo instanceof PDO) {
        try {
            $driver = strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $stmt = $pdo->query("SELECT GET_LOCK('safer_handling_xero_token_refresh', 20)");
                $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_NUM) : false;
                $locked = is_array($row) && (string) ($row[0] ?? '') === '1';
            }
        } catch (Throwable $e) {
            $locked = false;
        }
    }

    if (! $locked) {
        // Fallback for SQLite / local: flock is process-local but better than nothing.
        $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'safer-handling-xero-token.lock';
        $fp = @fopen($lockPath, 'c+');
        if (is_resource($fp) && flock($fp, LOCK_EX)) {
            try {
                return $callback();
            } finally {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        return $callback();
    }

    try {
        return $callback();
    } finally {
        try {
            $pdo->query("SELECT RELEASE_LOCK('safer_handling_xero_token_refresh')");
        } catch (Throwable $e) {
            // Best-effort release.
        }
    }
}

function xeroDefaultItemCode(): string
{
    return appConfigValue('XERO_DEFAULT_ITEM_CODE', 'xeroDefaultItemCode');
}

function xeroSalesAccountCode(): string
{
    $code = trim(appConfigValue('XERO_SALES_ACCOUNT_CODE', 'xeroSalesAccountCode', '200'));

    return $code !== '' ? $code : '200';
}

function xeroVatRate(): float
{
    $raw = appConfigValue('XERO_VAT_RATE', 'xeroVatRate', '20');
    if (!is_numeric($raw)) {
        return 20.0;
    }

    return (float)$raw;
}

function xeroBrandThemeId(): string
{
    return appConfigValue('XERO_BRANDING_THEME_ID', 'xeroBrandingThemeId');
}

/**
 * Persist Xero OAuth tokens into the shared settings table when available.
 */
function xeroPersistTokens(string $accessToken, string $refreshToken, int $expiresAt, string $tenantId = ''): void
{
    $GLOBALS['xeroAccessToken'] = $accessToken;
    $GLOBALS['xeroRefreshToken'] = $refreshToken;
    $GLOBALS['xeroTokenExpiresAt'] = (string)$expiresAt;
    if ($tenantId !== '') {
        $GLOBALS['xeroTenantId'] = $tenantId;
    }

    $pairs = [
        'xero_access_token' => $accessToken,
        'xero_refresh_token' => $refreshToken,
        'xero_token_expires_at' => (string)$expiresAt,
    ];
    if ($tenantId !== '') {
        $pairs['xero_tenant_id'] = $tenantId;
    }

    if (class_exists(\App\Models\Setting::class)) {
        try {
            // Only use Eloquent when the Laravel app container is available.
            if (function_exists('app') && app()->bound('db')) {
                foreach ($pairs as $key => $value) {
                    \App\Models\Setting::setValue($key, $value);
                }
                // Keep the legacy PDO settings cache in sync with Eloquent writes.
                if (function_exists('appSettingsFlushCache')) {
                    appSettingsFlushCache();
                }

                return;
            }
        } catch (Throwable $e) {
            // Fall through to direct PDO write for legacy form PHP / MySQL.
        }
    }

    if (!function_exists('appSettingSet')) {
        require_once __DIR__ . '/database_bridge.php';
    }

    if (!function_exists('appSettingSet')) {
        return;
    }

    // Use live PDO driver name (not env) — Coolify can leave DB_CONNECTION=sqlite
    // while the actual connection is MySQL.
    foreach ($pairs as $key => $value) {
        appSettingSet($key, $value);
    }
}

/**
 * @return array{access_token:string,refresh_token:string,expires_at:int,tenant_id:string}
 */
function xeroCurrentTokenBundle(): array
{
    return [
        'access_token' => xeroAccessToken(),
        'refresh_token' => xeroRefreshToken(),
        'expires_at' => xeroTokenExpiresAt(),
        'tenant_id' => xeroTenantId(),
    ];
}

/**
 * @return array{access_token:string,refresh_token:string,expires_at:int,tenant_id:string}
 */
function xeroEnsureAccessToken(): array
{
    // Always re-read from DB — PHP-FPM static settings cache can hold a refresh
    // token that another worker already exchanged.
    xeroReloadTokenSettings();
    $tokens = xeroCurrentTokenBundle();

    if ($tokens['access_token'] !== '' && $tokens['expires_at'] > (time() + 60)) {
        return $tokens;
    }

    return xeroWithRefreshLock(static function () use ($tokens): array {
        // Another worker may have refreshed while we waited for the lock.
        xeroReloadTokenSettings();
        $fresh = xeroCurrentTokenBundle();
        if ($fresh['access_token'] !== '' && $fresh['expires_at'] > (time() + 60)) {
            return $fresh;
        }

        $refreshToken = $fresh['refresh_token'] !== '' ? $fresh['refresh_token'] : $tokens['refresh_token'];
        $tenantId = $fresh['tenant_id'] !== '' ? $fresh['tenant_id'] : $tokens['tenant_id'];

        if ($refreshToken === '' || xeroClientId() === '' || xeroClientSecret() === '') {
            throw new RuntimeException('Xero is not connected. Connect Xero in Admin → Settings.');
        }

        $ch = curl_init('https://identity.xero.com/connect/token');
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL for Xero token refresh.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode(xeroClientId() . ':' . xeroClientSecret()),
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]),
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Xero token refresh failed: '.$err);
        }
        curl_close($ch);

        $decoded = json_decode($raw, true);
        if ($status >= 400 || ! is_array($decoded) || empty($decoded['access_token'])) {
            $message = is_array($decoded)
                ? trim((string) ($decoded['error_description'] ?? $decoded['error'] ?? ''))
                : '';

            // Another worker may have consumed this refresh token and already
            // persisted a new pair — reload and use those if they are still valid.
            if (stripos($message, 'consumed') !== false || stripos($message, 'invalid_grant') !== false) {
                xeroReloadTokenSettings();
                $recovered = xeroCurrentTokenBundle();
                if ($recovered['access_token'] !== '' && $recovered['expires_at'] > (time() + 60)) {
                    return $recovered;
                }

                throw new RuntimeException(
                    'Xero refresh token is no longer valid. Disconnect and reconnect Xero in Admin → Settings, then retry.'
                );
            }

            throw new RuntimeException($message !== '' ? $message : 'Xero token refresh failed (HTTP '.$status.').');
        }

        $accessToken = (string) $decoded['access_token'];
        $refreshToken = (string) ($decoded['refresh_token'] ?? $refreshToken);
        $expiresIn = (int) ($decoded['expires_in'] ?? 1800);
        $expiresAt = time() + max(60, $expiresIn);
        xeroPersistTokens($accessToken, $refreshToken, $expiresAt, $tenantId);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'tenant_id' => $tenantId,
        ];
    });
}

/**
 * @param array<string, mixed>|null $body
 * @return array{status:int,body:array<string,mixed>,raw:string}
 */
/**
 * @return array{status:int,body:array<string,mixed>,raw:string,content_type:string}
 */
function xeroApiRequest(
    string $method,
    string $path,
    ?array $body = null,
    array $query = [],
    string $accept = 'application/json'
): array {
    $auth = xeroEnsureAccessToken();
    $tenantId = $auth['tenant_id'];
    if ($tenantId === '') {
        throw new RuntimeException('Xero tenant ID is missing. Reconnect Xero in Admin → Settings.');
    }

    $url = 'https://api.xero.com/api.xro/2.0/' . ltrim($path, '/');
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL for Xero API.');
    }

    $headers = [
        'Authorization: Bearer ' . $auth['access_token'],
        'Xero-tenant-id: ' . $tenantId,
        'Accept: ' . $accept,
    ];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
    ];

    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Xero API request failed: ' . $err);
    }
    curl_close($ch);

    $raw = substr((string)$response, $headerSize);
    $decoded = [];
    if (str_contains(strtolower($contentType), 'json') || (str_starts_with(ltrim($raw), '{') || str_starts_with(ltrim($raw), '['))) {
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) {
            $decoded = $parsed;
        }
    }

    return [
        'status' => $status,
        'body' => $decoded,
        'raw' => (string)$raw,
        'content_type' => $contentType,
    ];
}

function xeroApiErrorMessage(array $body, string $fallback): string
{
    $validationMessages = [];
    foreach ($body['Elements'] ?? [] as $element) {
        if (!is_array($element)) {
            continue;
        }
        foreach ($element['ValidationErrors'] ?? [] as $error) {
            $message = trim((string)($error['Message'] ?? ''));
            if ($message !== '') {
                $validationMessages[] = $message;
            }
        }
        foreach ($element['LineItems'] ?? [] as $line) {
            if (!is_array($line)) {
                continue;
            }
            foreach ($line['ValidationErrors'] ?? [] as $error) {
                $message = trim((string)($error['Message'] ?? ''));
                if ($message !== '') {
                    $validationMessages[] = $message;
                }
            }
        }
    }
    if ($validationMessages !== []) {
        return implode(' ', array_unique($validationMessages));
    }

    if (!empty($body['Detail']) && is_string($body['Detail'])) {
        return trim($body['Detail']);
    }

    if (!empty($body['Message']) && is_string($body['Message'])) {
        return trim($body['Message']);
    }

    return $fallback;
}

/**
 * Quotes often return LineAmountTypes in UPPERCASE; Invoices require title case.
 */
function xeroNormalizeLineAmountTypes(string $value): string
{
    return match (strtoupper(trim($value))) {
        'INCLUSIVE' => 'Inclusive',
        'NOTAX' => 'NoTax',
        default => 'Exclusive',
    };
}

/**
 * True when address parts are enough for a Xero contact address.
 *
 * @param array<string, string> $address
 */
function xeroAddressHasContent(array $address): bool
{
    return trim((string)($address['addressLine1'] ?? '')) !== ''
        || trim((string)($address['addressPostcode'] ?? '')) !== '';
}

/**
 * Normalise structured address fields used by the enquiry form / Monday helpers.
 *
 * @param array<string, mixed> $address
 * @return array{addressLine1:string,addressLine2:string,addressTown:string,addressPostcode:string,country:string}
 */
function xeroNormalizeAddressParts(array $address): array
{
    $country = trim((string)($address['country'] ?? $address['addressCountry'] ?? ''));
    if ($country === '') {
        $country = 'United Kingdom';
    }

    return [
        'addressLine1' => trim((string)($address['addressLine1'] ?? '')),
        'addressLine2' => trim((string)($address['addressLine2'] ?? '')),
        'addressTown' => trim((string)($address['addressTown'] ?? $address['city'] ?? '')),
        'addressPostcode' => strtoupper(trim((string)($address['addressPostcode'] ?? $address['postalCode'] ?? ''))),
        'country' => $country,
    ];
}

/**
 * Parse a free-text booking invoice/venue address into structured parts.
 *
 * @return array{addressLine1:string,addressLine2:string,addressTown:string,addressPostcode:string,country:string}
 */
function xeroAddressPartsFromFreeText(string $text): array
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return xeroNormalizeAddressParts([]);
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $text)), static function (string $line): bool {
        return $line !== '';
    }));
    if ($lines === []) {
        // Single-line address separated by commas.
        $lines = array_values(array_filter(array_map('trim', explode(',', $text)), static function (string $line): bool {
            return $line !== '';
        }));
    }

    $postcode = '';
    $postcodePattern = '/\b([A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2})\b/i';
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        if (preg_match($postcodePattern, $lines[$i], $matches)) {
            $postcode = strtoupper(preg_replace('/\s+/', ' ', trim($matches[1])) ?? '');
            $lines[$i] = trim(preg_replace($postcodePattern, '', $lines[$i]) ?? '');
            if ($lines[$i] === '') {
                array_splice($lines, $i, 1);
            }
            break;
        }
    }

    $town = '';
    if (count($lines) >= 2) {
        $town = (string)array_pop($lines);
    }

    $line1 = $lines[0] ?? ($postcode !== '' ? $postcode : $text);
    $line2 = $lines[1] ?? '';
    if (count($lines) > 2) {
        $line2 = trim(implode(', ', array_slice($lines, 1)));
    }

    return xeroNormalizeAddressParts([
        'addressLine1' => $line1,
        'addressLine2' => $line2,
        'addressTown' => $town,
        'addressPostcode' => $postcode,
        'country' => 'United Kingdom',
    ]);
}

/**
 * Prefer invoice address, then venue address, from booking details.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array{addressLine1:string,addressLine2:string,addressTown:string,addressPostcode:string,country:string}
 */
function xeroAddressPartsFromBookingDetails(array $bookingDetails): array
{
    $invoiceAddress = trim((string)($bookingDetails['invoiceAddress'] ?? ''));
    if ($invoiceAddress !== '') {
        return xeroAddressPartsFromFreeText($invoiceAddress);
    }

    $venueAddress = trim((string)($bookingDetails['venueAddress'] ?? ''));
    if ($venueAddress !== '') {
        return xeroAddressPartsFromFreeText($venueAddress);
    }

    return xeroNormalizeAddressParts($bookingDetails);
}

/**
 * Build Xero contact Addresses (POBOX is used on tax invoices).
 *
 * @param array<string, mixed> $address
 * @return list<array<string, string>>
 */
function xeroContactAddressesPayload(array $address): array
{
    $parts = xeroNormalizeAddressParts($address);
    if (!xeroAddressHasContent($parts)) {
        return [];
    }

    $line1 = $parts['addressLine1'] !== '' ? $parts['addressLine1'] : $parts['addressPostcode'];
    $entry = [
        'AddressType' => 'POBOX',
        'AddressLine1' => substr($line1, 0, 500),
        'Country' => substr($parts['country'], 0, 50),
    ];
    if ($parts['addressLine2'] !== '') {
        $entry['AddressLine2'] = substr($parts['addressLine2'], 0, 500);
    }
    if ($parts['addressTown'] !== '') {
        $entry['City'] = substr($parts['addressTown'], 0, 255);
    }
    if ($parts['addressPostcode'] !== '') {
        $entry['PostalCode'] = substr($parts['addressPostcode'], 0, 50);
    }

    // Mirror onto STREET so both contact address slots are populated.
    $street = $entry;
    $street['AddressType'] = 'STREET';

    return [$entry, $street];
}

/**
 * Escape a value for Xero Accounting API `where` clauses.
 */
function xeroWhereString(string $value): string
{
    return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
}

/**
 * True when a Xero contact is usable (active / not archived).
 *
 * @param array<string, mixed> $contact
 */
function xeroContactIsActive(array $contact): bool
{
    $status = strtoupper(trim((string) ($contact['ContactStatus'] ?? 'ACTIVE')));

    return $status === '' || $status === 'ACTIVE';
}

/**
 * Pick the best matching contact from a Xero Contacts response.
 *
 * Prefer exact email, then exact name (case-insensitive), among active contacts.
 *
 * @param  list<array<string, mixed>>|mixed  $contacts
 * @return array<string, mixed>|null
 */
function xeroPickMatchingContact(mixed $contacts, string $name, string $email): ?array
{
    if (! is_array($contacts) || $contacts === []) {
        return null;
    }

    $name = trim($name);
    $email = strtolower(trim($email));
    $nameKey = strtolower($name);

    $byEmail = null;
    $byName = null;

    foreach ($contacts as $contact) {
        if (! is_array($contact) || empty($contact['ContactID']) || ! xeroContactIsActive($contact)) {
            continue;
        }

        $contactEmail = strtolower(trim((string) ($contact['EmailAddress'] ?? '')));
        $contactName = strtolower(trim((string) ($contact['Name'] ?? '')));

        if ($email !== '' && $contactEmail === $email) {
            $byEmail = $contact;
            break;
        }

        if ($byName === null && $nameKey !== '' && $contactName === $nameKey) {
            $byName = $contact;
        }
    }

    return $byEmail ?? $byName;
}

/**
 * Look up an existing Xero contact by email, then by name.
 *
 * @return array<string, mixed>|null
 */
function xeroFindExistingContact(string $name, string $email): ?array
{
    $name = trim($name);
    $email = trim($email);

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $where = 'EmailAddress!=null&&EmailAddress=="'.xeroWhereString($email).'"';
        $existing = xeroApiRequest('GET', 'Contacts', null, [
            'where' => $where,
            'page' => 1,
            'includeArchived' => 'false',
        ]);
        if ($existing['status'] < 400) {
            $match = xeroPickMatchingContact($existing['body']['Contacts'] ?? [], $name, $email);
            if ($match !== null) {
                return $match;
            }
        }
    }

    if ($name !== '') {
        $where = 'Name=="'.xeroWhereString($name).'"&&ContactStatus=="ACTIVE"';
        $existing = xeroApiRequest('GET', 'Contacts', null, [
            'where' => $where,
            'page' => 1,
            'includeArchived' => 'false',
        ]);
        if ($existing['status'] < 400) {
            $match = xeroPickMatchingContact($existing['body']['Contacts'] ?? [], $name, $email);
            if ($match !== null) {
                return $match;
            }
        }

        // Case / partial mismatches: searchTerm then exact name/email filter locally.
        $search = xeroApiRequest('GET', 'Contacts', null, [
            'searchTerm' => $name,
            'page' => 1,
            'includeArchived' => 'false',
        ]);
        if ($search['status'] < 400) {
            $match = xeroPickMatchingContact($search['body']['Contacts'] ?? [], $name, $email);
            if ($match !== null) {
                return $match;
            }
        }
    }

    return null;
}

/**
 * Update an existing Xero contact with billing address (and optional phone/email).
 * Does not rename the contact — Xero requires unique Names across active contacts.
 *
 * @param array<string, mixed> $address
 */
function xeroEnsureContactAddress(
    string $contactId,
    array $address,
    string $name = '',
    string $email = '',
    string $phone = ''
): void {
    $contactId = trim($contactId);
    if ($contactId === '') {
        return;
    }

    $addresses = xeroContactAddressesPayload($address);
    $email = trim($email);
    $phone = trim($phone);

    // Name is intentionally ignored: renaming can collide with another active contact.
    unset($name);

    if ($addresses === [] && ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) && $phone === '') {
        return;
    }

    $contact = [
        'ContactID' => $contactId,
    ];
    if ($addresses !== []) {
        $contact['Addresses'] = $addresses;
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contact['EmailAddress'] = $email;
    }
    if ($phone !== '') {
        $contact['Phones'] = [[
            'PhoneType' => 'DEFAULT',
            'PhoneNumber' => substr($phone, 0, 50),
        ]];
    }

    $resp = xeroApiRequest('POST', 'Contacts', [
        'Contacts' => [$contact],
    ]);
    if ($resp['status'] >= 400) {
        // Address/email updates are best-effort once we have a ContactID to bill.
        // Do not fail quote/invoice creation because Xero rejected a non-critical update.
        return;
    }
}

/**
 * @param array<string, mixed> $address Optional structured address for tax invoice compliance.
 * @return array{ContactID:string,Name:string}
 */
function xeroFindOrCreateContact(string $name, string $email, array $address = []): array
{
    $name = trim($name);
    $email = trim($email);
    if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid contact name and email are required for Xero.');
    }

    $existing = xeroFindExistingContact($name, $email);
    if ($existing !== null) {
        $contactId = (string) $existing['ContactID'];
        xeroEnsureContactAddress($contactId, $address, $name, $email);

        return [
            'ContactID' => $contactId,
            'Name' => (string) ($existing['Name'] ?? $name),
        ];
    }

    $contactPayload = [
        'Name' => $name,
        'EmailAddress' => $email,
        'IsCustomer' => true,
    ];
    // Prefer given/family split when the display name has a space; avoid stuffing
    // the full name into FirstName (which also surfaces oddly in Xero).
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        $contactPayload['FirstName'] = array_shift($parts);
        $contactPayload['LastName'] = implode(' ', $parts);
    } else {
        $contactPayload['FirstName'] = $name;
    }

    $addresses = xeroContactAddressesPayload($address);
    if ($addresses !== []) {
        $contactPayload['Addresses'] = $addresses;
    }

    $created = xeroApiRequest('POST', 'Contacts', [
        'Contacts' => [$contactPayload],
    ]);

    if ($created['status'] < 400 && ! empty($created['body']['Contacts'][0]['ContactID'])) {
        $contact = $created['body']['Contacts'][0];

        return [
            'ContactID' => (string) $contact['ContactID'],
            'Name' => (string) ($contact['Name'] ?? $name),
        ];
    }

    $error = xeroApiErrorMessage($created['body'], 'Could not create Xero contact.');

    // Name already taken (or create raced) — reuse the existing active contact.
    if (
        stripos($error, 'already assigned') !== false
        || stripos($error, 'must be unique') !== false
        || stripos($error, 'already exists') !== false
    ) {
        $fallback = xeroFindExistingContact($name, $email);
        if ($fallback !== null) {
            $contactId = (string) $fallback['ContactID'];
            xeroEnsureContactAddress($contactId, $address, $name, $email);

            return [
                'ContactID' => $contactId,
                'Name' => (string) ($fallback['Name'] ?? $name),
            ];
        }
    }

    throw new RuntimeException($error);
}

/**
 * @return array<string, mixed>|null
 */
function xeroFindItemByCodeOrName(string $codeOrName): ?array
{
    $needle = trim($codeOrName);
    if ($needle === '') {
        return null;
    }

    $escaped = str_replace('"', '', $needle);
    $queries = [
        'Code=="' . $escaped . '"',
        'Name=="' . $escaped . '"',
    ];

    foreach ($queries as $where) {
        $resp = xeroApiRequest('GET', 'Items', null, [
            'where' => $where,
            'page' => 1,
        ]);
        if ($resp['status'] >= 400) {
            continue;
        }
        $items = $resp['body']['Items'] ?? [];
        if (is_array($items) && isset($items[0]['ItemID'])) {
            return $items[0];
        }
    }

    // Fallback: search endpoint
    $resp = xeroApiRequest('GET', 'Items', null, [
        'page' => 1,
    ]);
    if ($resp['status'] < 400) {
        $wanted = strtolower($needle);
        foreach (($resp['body']['Items'] ?? []) as $item) {
            $code = strtolower(trim((string)($item['Code'] ?? '')));
            $name = strtolower(trim((string)($item['Name'] ?? '')));
            if ($code === $wanted || $name === $wanted) {
                return $item;
            }
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $quoteData
 * @return array{net:float,vat:float,gross:float,rate:float,description:string,item:?array<string,mixed>}
 */
function xeroBuildQuoteLineFromEnquiry(array $quoteData): array
{
    $attendees = trim((string)($quoteData['attendees'] ?? ''));
    $course = trim((string)($quoteData['course'] ?? ''));
    $format = trim((string)($quoteData['format'] ?? ''));
    $courseStyle = trim((string)($quoteData['courseStyle'] ?? ''));
    $trainingType = trim($course . (($format !== '' || $courseStyle !== '')
        ? ' (' . trim($format . ($courseStyle !== '' ? ' · ' . $courseStyle : '')) . ')'
        : ''));

    $netRaw = trim((string)($quoteData['quoteValue'] ?? ''));
    if ($netRaw === '' && !empty($quoteData['quoteDisplay'])) {
        $netRaw = preg_replace('/[^0-9.\-]/', '', (string)$quoteData['quoteDisplay']) ?? '';
    }
    if ($netRaw === '' || !is_numeric($netRaw)) {
        throw new RuntimeException('Quote amount is missing or invalid for Xero.');
    }

    // Form quoteValue is the customer-facing amount: Including Travel but Excluding VAT.
    $net = round((float)$netRaw, 2);
    if ($net <= 0) {
        throw new RuntimeException('Quote amount must be greater than zero for Xero.');
    }

    $rate = xeroVatRate();
    $vat = round($net * ($rate / 100), 2);
    $gross = round($net + $vat, 2);

    $descriptionParts = [];
    if ($attendees !== '') {
        $descriptionParts[] = $attendees . ' Attendee' . ((int)$attendees === 1 ? '' : 's');
    }
    if ($trainingType !== '') {
        $descriptionParts[] = 'Training: ' . $trainingType;
    }
    $descriptionParts[] = 'Including Travel but Excluding VAT: ' . number_format($net, 2, '.', '');
    $descriptionParts[] = 'VAT (' . rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') . '%): ' . number_format($vat, 2, '.', '');
    $descriptionParts[] = 'Total including VAT: ' . number_format($gross, 2, '.', '');
    $description = implode(' | ', $descriptionParts);

    $itemCode = trim((string)($quoteData['xeroItemCode'] ?? ''));
    if ($itemCode === '') {
        $itemCode = xeroDefaultItemCode();
    }
    if ($itemCode === '' && $course !== '') {
        $itemCode = $course;
    }

    $item = null;
    if ($itemCode !== '') {
        try {
            $item = xeroFindItemByCodeOrName($itemCode);
        } catch (Throwable $e) {
            // Item matching is optional; quote can still be created with a free-text line.
            $item = null;
        }
    }

    return [
        'net' => $net,
        'vat' => $vat,
        'gross' => $gross,
        'rate' => $rate,
        'description' => $description,
        'item' => $item,
        'itemCode' => $itemCode,
    ];
}

/**
 * @param array<string, mixed> $quoteData
 * @return array{QuoteID:string,QuoteNumber:string,Status:string,Total:float,TotalTax:float,SubTotal:float,OnlineQuoteUrl?:string}
 */
function xeroCreateQuote(string $contactId, array $quoteData): array
{
    $line = xeroBuildQuoteLineFromEnquiry($quoteData);
    // Form amounts are exclusive of VAT (Including Travel but Excluding VAT).
    // Send Exclusive so Xero SubTotal = form total and Total = form total + VAT.
    $lineItem = [
        'Description' => $line['description'],
        'Quantity' => 1,
        'UnitAmount' => $line['net'],
        'AccountCode' => xeroSalesAccountCode(),
        'TaxType' => 'OUTPUT2',
        'LineAmount' => $line['net'],
    ];

    if (is_array($line['item'])) {
        if (!empty($line['item']['Code'])) {
            $lineItem['ItemCode'] = (string)$line['item']['Code'];
        }
        if (!empty($line['item']['ItemID'])) {
            $lineItem['Item'] = ['ItemID' => (string)$line['item']['ItemID']];
        }
        // Keep UnitAmount from enquiry so price can differ from the catalogue item.
        $lineItem['UnitAmount'] = $line['net'];
        $lineItem['LineAmount'] = $line['net'];
    }

    $title = trim((string)($quoteData['course'] ?? 'Training Quote'));
    if ($title === '') {
        $title = 'Training Quote';
    }

    // Create as DRAFT first, then mark SENT. Xero has no Quotes/Email API —
    // we email the PDF ourselves via Brevo after download.
    $quotePayload = [
        'Contact' => ['ContactID' => $contactId],
        'Date' => gmdate('Y-m-d'),
        'ExpiryDate' => gmdate('Y-m-d', strtotime('+30 days')),
        'Title' => $title,
        'Summary' => 'Safer Handling training quote (Including Travel but Excluding VAT)',
        'LineAmountTypes' => 'Exclusive',
        'LineItems' => [$lineItem],
        'Status' => 'DRAFT',
        'CurrencyCode' => 'GBP',
    ];

    $brandingThemeId = xeroBrandThemeId();
    if ($brandingThemeId !== '') {
        $quotePayload['BrandingThemeID'] = $brandingThemeId;
    }

    $resp = xeroApiRequest('POST', 'Quotes', [
        'Quotes' => [$quotePayload],
    ]);

    if ($resp['status'] >= 400 || empty($resp['body']['Quotes'][0]['QuoteID'])) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not create Xero quote.'));
    }

    $quote = $resp['body']['Quotes'][0];
    $quoteId = (string)$quote['QuoteID'];

    $sentResp = xeroApiRequest('POST', 'Quotes', [
        'Quotes' => [[
            'QuoteID' => $quoteId,
            'Contact' => ['ContactID' => $contactId],
            'Date' => (string)($quote['DateString'] ?? gmdate('Y-m-d')),
            'Status' => 'SENT',
        ]],
    ]);
    if ($sentResp['status'] < 400 && !empty($sentResp['body']['Quotes'][0])) {
        $quote = $sentResp['body']['Quotes'][0];
    }

    return [
        'QuoteID' => $quoteId,
        'QuoteNumber' => (string)($quote['QuoteNumber'] ?? ''),
        'Status' => (string)($quote['Status'] ?? 'SENT'),
        'Total' => (float)($quote['Total'] ?? $line['gross']),
        'TotalTax' => (float)($quote['TotalTax'] ?? $line['vat']),
        'SubTotal' => (float)($quote['SubTotal'] ?? $line['net']),
        'OnlineQuoteUrl' => isset($quote['OnlineQuoteUrl']) ? (string)$quote['OnlineQuoteUrl'] : '',
    ];
}

/**
 * Download a quote PDF from Xero (Accept: application/pdf).
 *
 * @return array{content:string,filename:string}
 */
function xeroDownloadQuotePdf(string $quoteId, string $quoteNumber = ''): array
{
    $quoteId = trim($quoteId);
    if ($quoteId === '') {
        throw new RuntimeException('Xero quote ID is missing.');
    }

    $resp = xeroApiRequest('GET', 'Quotes/' . rawurlencode($quoteId), null, [], 'application/pdf');
    if ($resp['status'] >= 400 || $resp['raw'] === '') {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not download Xero quote PDF.'));
    }

    if (!str_starts_with($resp['raw'], '%PDF')) {
        throw new RuntimeException('Xero did not return a PDF for this quote.');
    }

    $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($quoteNumber)) ?: 'quote';
    $filename = 'Safer-Handling-Quote-' . $safeNumber . '.pdf';

    return [
        'content' => $resp['raw'],
        'filename' => $filename,
    ];
}

/**
 * Create/find contact and create quote in Xero (form total excluding VAT, including travel). Returns PDF bytes for emailing.
 *
 * @param array<string, mixed> $quoteData
 * @return array{
 *   contact:array{ContactID:string,Name:string},
 *   quote:array<string,mixed>,
 *   pdf:array{content:string,filename:string}
 * }
 */
function xeroSendQuoteToClient(string $name, string $email, array $quoteData): array
{
    if (!xeroEnabled()) {
        throw new RuntimeException('Xero quote sending is disabled.');
    }

    $contact = xeroFindOrCreateContact($name, $email, $quoteData);
    $quote = xeroCreateQuote($contact['ContactID'], $quoteData);
    $pdf = xeroDownloadQuotePdf($quote['QuoteID'], $quote['QuoteNumber'] ?? '');

    return [
        'contact' => $contact,
        'quote' => $quote,
        'pdf' => $pdf,
    ];
}

/**
 * @return array<string, mixed>
 */
function xeroGetQuote(string $quoteId): array
{
    $quoteId = trim($quoteId);
    if ($quoteId === '') {
        throw new RuntimeException('Xero quote ID is missing.');
    }

    $resp = xeroApiRequest('GET', 'Quotes/' . rawurlencode($quoteId));
    if ($resp['status'] >= 400 || empty($resp['body']['Quotes'][0])) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not load Xero quote.'));
    }

    return $resp['body']['Quotes'][0];
}

/**
 * Mark a sent quote as accepted in Xero (best-effort).
 */
function xeroMarkQuoteAccepted(string $quoteId): void
{
    $quoteId = trim($quoteId);
    if ($quoteId === '') {
        return;
    }

    $quote = xeroGetQuote($quoteId);
    $contactId = trim((string)($quote['Contact']['ContactID'] ?? ''));
    $date = trim((string)($quote['DateString'] ?? ''));
    if ($date === '') {
        $date = gmdate('Y-m-d');
    }
    $status = strtoupper(trim((string)($quote['Status'] ?? '')));

    // Xero only accepts ACCEPTED from SENT (not directly from DRAFT).
    if ($status === 'DRAFT') {
        $sentResp = xeroApiRequest('POST', 'Quotes', [
            'Quotes' => [[
                'QuoteID' => $quoteId,
                'Contact' => ['ContactID' => $contactId],
                'Date' => $date,
                'Status' => 'SENT',
            ]],
        ]);
        if ($sentResp['status'] >= 400) {
            throw new RuntimeException(xeroApiErrorMessage($sentResp['body'], 'Could not mark Xero quote as sent.'));
        }
    }

    if (in_array($status, ['ACCEPTED', 'INVOICED'], true)) {
        return;
    }

    $resp = xeroApiRequest('POST', 'Quotes', [
        'Quotes' => [[
            'QuoteID' => $quoteId,
            'Contact' => ['ContactID' => $contactId],
            'Date' => $date,
            'Status' => 'ACCEPTED',
        ]],
    ]);

    if ($resp['status'] >= 400) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not mark Xero quote as accepted.'));
    }
}

/**
 * Map Xero quote line items into invoice line items (faithful quote → invoice conversion).
 * Uses Quantity + UnitAmount + TaxType so Xero recalculates the same totals as the quote.
 * Omits ItemCode so catalogue defaults cannot change price/tax.
 *
 * @param array<int, array<string, mixed>> $quoteLines
 * @return list<array<string, mixed>>
 */
function xeroInvoiceLineItemsFromQuoteLines(array $quoteLines): array
{
    $lines = [];
    foreach ($quoteLines as $quoteLine) {
        if (!is_array($quoteLine)) {
            continue;
        }

        $description = trim((string)($quoteLine['Description'] ?? ''));
        $quantity = (float)($quoteLine['Quantity'] ?? 0);
        $unitAmount = isset($quoteLine['UnitAmount']) && is_numeric($quoteLine['UnitAmount'])
            ? round((float)$quoteLine['UnitAmount'], 4)
            : null;
        $lineAmount = isset($quoteLine['LineAmount']) && is_numeric($quoteLine['LineAmount'])
            ? round((float)$quoteLine['LineAmount'], 4)
            : null;

        if ($description === '' && $unitAmount === null && $lineAmount === null) {
            continue;
        }

        if ($quantity <= 0) {
            $quantity = 1;
        }

        // Prefer UnitAmount; fall back to LineAmount / Quantity when UnitAmount is missing.
        if ($unitAmount === null && $lineAmount !== null) {
            $unitAmount = round($lineAmount / $quantity, 4);
        }
        if ($unitAmount === null) {
            continue;
        }

        $line = [
            'Description' => $description !== '' ? $description : 'Training',
            'Quantity' => $quantity,
            'UnitAmount' => $unitAmount,
            'AccountCode' => !empty($quoteLine['AccountCode'])
                ? (string)$quoteLine['AccountCode']
                : xeroSalesAccountCode(),
            'TaxType' => !empty($quoteLine['TaxType'])
                ? (string)$quoteLine['TaxType']
                : 'OUTPUT2',
        ];

        // Copy only one discount field so Xero cannot double-apply.
        $discountRate = isset($quoteLine['DiscountRate']) && is_numeric($quoteLine['DiscountRate'])
            ? (float)$quoteLine['DiscountRate']
            : 0.0;
        $discountAmount = isset($quoteLine['DiscountAmount']) && is_numeric($quoteLine['DiscountAmount'])
            ? (float)$quoteLine['DiscountAmount']
            : 0.0;
        if ($discountRate > 0) {
            $line['DiscountRate'] = $discountRate;
        } elseif ($discountAmount > 0) {
            $line['DiscountAmount'] = $discountAmount;
        }

        $lines[] = $line;
    }

    if ($lines === []) {
        throw new RuntimeException('Xero quote has no line items to invoice.');
    }

    return $lines;
}

/**
 * True when quote and invoice money fields match within 1p.
 */
function xeroMoneyMatches(float $a, float $b): bool
{
    return abs(round($a, 2) - round($b, 2)) < 0.015;
}

/**
 * Delete a draft invoice (used when totals do not match the source quote).
 */
function xeroDeleteDraftInvoice(string $invoiceId): void
{
    $invoiceId = trim($invoiceId);
    if ($invoiceId === '') {
        return;
    }

    $resp = xeroApiRequest('DELETE', 'Invoices/' . rawurlencode($invoiceId));
    if ($resp['status'] >= 400) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not delete mismatched Xero draft invoice.'));
    }
}

/**
 * Mark an accepted quote as INVOICED after a draft invoice has been created from it.
 */
function xeroMarkQuoteInvoiced(string $quoteId): void
{
    $quoteId = trim($quoteId);
    if ($quoteId === '') {
        return;
    }

    $quote = xeroGetQuote($quoteId);
    $status = strtoupper(trim((string)($quote['Status'] ?? '')));
    if ($status === 'INVOICED') {
        return;
    }

    if ($status !== 'ACCEPTED') {
        xeroMarkQuoteAccepted($quoteId);
        $quote = xeroGetQuote($quoteId);
        $status = strtoupper(trim((string)($quote['Status'] ?? '')));
    }

    if ($status === 'INVOICED') {
        return;
    }

    $contactId = trim((string)($quote['Contact']['ContactID'] ?? ''));
    $date = trim((string)($quote['DateString'] ?? ''));
    if ($date === '') {
        $date = gmdate('Y-m-d');
    }

    $resp = xeroApiRequest('POST', 'Quotes', [
        'Quotes' => [[
            'QuoteID' => $quoteId,
            'Contact' => ['ContactID' => $contactId],
            'Date' => $date,
            'Status' => 'INVOICED',
        ]],
    ]);

    if ($resp['status'] >= 400) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not mark Xero quote as invoiced.'));
    }
}

/**
 * Create a DRAFT sales invoice by converting an existing Xero quote.
 * Copies quote line amounts, verifies totals match, then marks the quote INVOICED.
 * Does not email or authorise the invoice.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array{InvoiceID:string,InvoiceNumber:string,Status:string,Total:float,TotalTax:float,SubTotal:float,QuoteID:string,QuoteNumber:string,QuoteStatus:string}
 */
function xeroCreateDraftInvoiceFromQuote(string $quoteId, array $bookingDetails = []): array
{
    if (!xeroEnabled()) {
        throw new RuntimeException('Xero is disabled.');
    }

    $quote = xeroGetQuote($quoteId);
    $contactId = trim((string)($quote['Contact']['ContactID'] ?? ''));
    if ($contactId === '') {
        throw new RuntimeException('Xero quote is missing a contact.');
    }

    $quoteNumber = trim((string)($quote['QuoteNumber'] ?? ''));
    $quoteStatus = strtoupper(trim((string)($quote['Status'] ?? '')));
    if (!in_array($quoteStatus, ['ACCEPTED', 'INVOICED'], true)) {
        try {
            xeroMarkQuoteAccepted($quoteId);
        } catch (Throwable $e) {
            // Quote may already be accepted/invoiced; continue with invoice creation.
        }
        // Reload so line items / totals are current after the status change.
        $quote = xeroGetQuote($quoteId);
        $quoteStatus = strtoupper(trim((string)($quote['Status'] ?? '')));
        $contactId = trim((string)($quote['Contact']['ContactID'] ?? $contactId));
        $quoteNumber = trim((string)($quote['QuoteNumber'] ?? $quoteNumber));
    }

    $quoteSubTotal = round((float)($quote['SubTotal'] ?? 0), 2);
    $quoteTax = round((float)($quote['TotalTax'] ?? 0), 2);
    $quoteTotal = round((float)($quote['Total'] ?? 0), 2);
    if ($quoteTotal <= 0) {
        throw new RuntimeException('Xero quote total is missing or zero; cannot create a matching invoice.');
    }

    // Tax invoices require a contact postal address — update from booking details first.
    $address = xeroAddressPartsFromBookingDetails($bookingDetails);
    if (!xeroAddressHasContent($address)) {
        throw new RuntimeException(
            'Contact address is required for tax invoice compliance. Add an invoice address on the booking form before creating the invoice.'
        );
    }
    xeroEnsureContactAddress(
        $contactId,
        $address,
        trim((string)($bookingDetails['invoiceName'] ?? $bookingDetails['bookerName'] ?? '')),
        trim((string)($bookingDetails['invoiceEmail'] ?? $bookingDetails['email'] ?? '')),
        trim((string)($bookingDetails['invoicePhone'] ?? $bookingDetails['phone'] ?? ''))
    );

    $lineItems = xeroInvoiceLineItemsFromQuoteLines(
        is_array($quote['LineItems'] ?? null) ? $quote['LineItems'] : []
    );

    $referenceParts = [];
    $po = trim((string)($bookingDetails['purchaseOrderNumber'] ?? ''));
    if ($po !== '') {
        $referenceParts[] = 'PO ' . $po;
    }
    if ($quoteNumber !== '') {
        $referenceParts[] = 'Quote ' . $quoteNumber;
    }
    $reference = implode(' · ', $referenceParts);

    $invoicePayload = [
        'Type' => 'ACCREC',
        'Contact' => ['ContactID' => $contactId],
        'Date' => gmdate('Y-m-d'),
        'DueDate' => gmdate('Y-m-d', strtotime('+30 days')),
        'LineAmountTypes' => xeroNormalizeLineAmountTypes((string)($quote['LineAmountTypes'] ?? 'Exclusive')),
        'LineItems' => $lineItems,
        'Status' => 'DRAFT',
        'CurrencyCode' => (string)($quote['CurrencyCode'] ?? 'GBP'),
    ];

    if ($reference !== '') {
        $invoicePayload['Reference'] = $reference;
    }

    $brandingThemeId = trim((string)($quote['BrandingThemeID'] ?? ''));
    if ($brandingThemeId === '') {
        $brandingThemeId = xeroBrandThemeId();
    }
    if ($brandingThemeId !== '') {
        $invoicePayload['BrandingThemeID'] = $brandingThemeId;
    }

    $resp = xeroApiRequest('POST', 'Invoices', [
        'Invoices' => [$invoicePayload],
    ]);

    if ($resp['status'] >= 400 || empty($resp['body']['Invoices'][0]['InvoiceID'])) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not create Xero draft invoice from quote.'));
    }

    $invoice = $resp['body']['Invoices'][0];
    $invoiceId = (string)$invoice['InvoiceID'];
    $invoiceSubTotal = round((float)($invoice['SubTotal'] ?? 0), 2);
    $invoiceTax = round((float)($invoice['TotalTax'] ?? 0), 2);
    $invoiceTotal = round((float)($invoice['Total'] ?? 0), 2);

    if (
        !xeroMoneyMatches($invoiceTotal, $quoteTotal)
        || !xeroMoneyMatches($invoiceSubTotal, $quoteSubTotal)
        || !xeroMoneyMatches($invoiceTax, $quoteTax)
    ) {
        try {
            xeroDeleteDraftInvoice($invoiceId);
        } catch (Throwable $deleteError) {
            throw new RuntimeException(
                'Draft invoice total (£'
                . number_format($invoiceTotal, 2)
                . ') does not match quote total (£'
                . number_format($quoteTotal, 2)
                . '), and the mismatched draft could not be deleted: '
                . $deleteError->getMessage()
            );
        }

        throw new RuntimeException(
            'Draft invoice total (£'
            . number_format($invoiceTotal, 2)
            . ') does not match quote '
            . ($quoteNumber !== '' ? $quoteNumber . ' ' : '')
            . 'total (£'
            . number_format($quoteTotal, 2)
            . '). The mismatched draft invoice was removed.'
        );
    }

    // Mirror Xero UI conversion: quote moves to Invoiced once the invoice exists.
    if ($quoteStatus !== 'INVOICED') {
        try {
            xeroMarkQuoteInvoiced($quoteId);
            $quoteStatus = 'INVOICED';
        } catch (Throwable $e) {
            // Invoice is already correct; leave quote status as-is if Xero rejects the update.
            $quoteStatus = strtoupper(trim((string)($quote['Status'] ?? $quoteStatus)));
        }
    }

    return [
        'InvoiceID' => $invoiceId,
        'InvoiceNumber' => (string)($invoice['InvoiceNumber'] ?? ''),
        'Status' => (string)($invoice['Status'] ?? 'DRAFT'),
        'Total' => $invoiceTotal,
        'TotalTax' => $invoiceTax,
        'SubTotal' => $invoiceSubTotal,
        'QuoteID' => $quoteId,
        'QuoteNumber' => $quoteNumber,
        'QuoteStatus' => $quoteStatus,
        'QuoteTotal' => $quoteTotal,
        'QuoteTotalTax' => $quoteTax,
        'QuoteSubTotal' => $quoteSubTotal,
    ];
}

/**
 * After booking/terms acceptance: create a DRAFT Xero invoice from the stored quote (not sent).
 *
 * @param array<string, mixed> $bookingDetails
 * @return array{InvoiceID:string,InvoiceNumber:string,Status:string}|null
 */
function xeroMaybeCreateDraftInvoiceAfterQuoteAccepted(int $enquiryId, array $bookingDetails = []): ?array
{
    require_once __DIR__ . '/enquiry_logger.php';

    if (!xeroEnabled()) {
        return null;
    }

    if (enquiryLoggerXeroInvoiceAlreadyCreated($enquiryId)) {
        return null;
    }

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT xero_quote_id, xero_quote_number, xero_contact_id, name, email, form_data_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $quoteId = trim((string)($row['xero_quote_id'] ?? ''));
    if ($quoteId === '') {
        enquiryLoggerEvent(
            $enquiryId,
            'xero_invoice_skipped',
            'Quote accepted, but no Xero quote is linked to this enquiry so no invoice was created.'
        );

        return null;
    }

    // Prefer booking invoice address; fall back to enquiry form address.
    $address = xeroAddressPartsFromBookingDetails($bookingDetails);
    if (!xeroAddressHasContent($address)) {
        $formData = [];
        $rawForm = trim((string)($row['form_data_json'] ?? ''));
        if ($rawForm !== '') {
            $decoded = json_decode($rawForm, true);
            if (is_array($decoded)) {
                $formData = $decoded;
            }
        }
        require_once __DIR__ . '/monday_helpers.php';
        $address = xeroNormalizeAddressParts(mondayAddressFromPost($formData));
    }
    if (xeroAddressHasContent($address)) {
        $bookingDetails = array_merge($bookingDetails, $address);
        if (trim((string)($bookingDetails['invoiceAddress'] ?? '')) === '') {
            $bookingDetails['invoiceAddress'] = trim(implode("\n", array_filter([
                $address['addressLine1'] ?? '',
                $address['addressLine2'] ?? '',
                $address['addressTown'] ?? '',
                $address['addressPostcode'] ?? '',
            ])));
        }
    }

    try {
        $invoice = xeroCreateDraftInvoiceFromQuote($quoteId, $bookingDetails);
        enquiryLoggerMarkXeroInvoiceCreated(
            $enquiryId,
            $invoice['InvoiceID'],
            $invoice['InvoiceNumber']
        );
        enquiryLoggerEvent(
            $enquiryId,
            'xero_invoice_created',
            'Draft invoice created in Xero from the accepted quote (totals matched; not sent).',
            [
                'channel' => 'xero',
                'xero_quote_id' => $invoice['QuoteID'],
                'xero_quote_number' => $invoice['QuoteNumber'],
                'xero_quote_status' => $invoice['QuoteStatus'] ?? null,
                'xero_invoice_id' => $invoice['InvoiceID'],
                'xero_invoice_number' => $invoice['InvoiceNumber'],
                'xero_invoice_status' => $invoice['Status'],
                'quote_subtotal' => $invoice['QuoteSubTotal'] ?? null,
                'quote_vat' => $invoice['QuoteTotalTax'] ?? null,
                'quote_total' => $invoice['QuoteTotal'] ?? null,
                'invoice_subtotal' => $invoice['SubTotal'],
                'invoice_vat' => $invoice['TotalTax'],
                'invoice_total' => $invoice['Total'],
                'totals_matched' => true,
                'sent' => false,
            ]
        );

        return $invoice;
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'xero_invoice_failed',
            'Could not create a draft Xero invoice from the accepted quote.',
            [
                'channel' => 'xero',
                'xero_quote_id' => $quoteId,
                'xero_quote_number' => trim((string)($row['xero_quote_number'] ?? '')),
                'error' => $e->getMessage(),
            ]
        );

        throw $e;
    }
}

/**
 * @return array<string, mixed>
 */
function xeroGetInvoice(string $invoiceId): array
{
    $invoiceId = trim($invoiceId);
    if ($invoiceId === '') {
        throw new RuntimeException('Xero invoice ID is missing.');
    }

    $resp = xeroApiRequest('GET', 'Invoices/' . rawurlencode($invoiceId));
    if ($resp['status'] >= 400 || empty($resp['body']['Invoices'][0])) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not load Xero invoice.'));
    }

    return $resp['body']['Invoices'][0];
}

/**
 * Xero marks an invoice as sent when SentToContact is true (email / mark as sent in Xero).
 *
 * @param array<string, mixed> $invoice
 */
function xeroInvoiceIsSent(array $invoice): bool
{
    $sent = $invoice['SentToContact'] ?? false;
    if ($sent === true || $sent === 1 || $sent === '1' || strtolower((string)$sent) === 'true') {
        return true;
    }

    // Paid invoices that were emailed still count as sent even if the flag is absent.
    $status = strtoupper(trim((string)($invoice['Status'] ?? '')));

    return $status === 'PAID' && !empty($invoice['FullyPaidOnDate']);
}

/**
 * When a linked Xero invoice has been sent, move Monday → Quote Won
 * and create/update the Client Booking Form (Courses Ongoing) record.
 *
 * @return array{
 *   processed:bool,
 *   sent:bool,
 *   already_sent:bool,
 *   invoice_status:?string,
 *   monday_quote_won:?array,
 *   monday_courses_ongoing:?array
 * }|null
 */
function xeroMaybeProcessInvoiceSent(int $enquiryId): ?array
{
    require_once __DIR__ . '/enquiry_logger.php';
    require_once __DIR__ . '/monday_helpers.php';

    if (!xeroEnabled()) {
        return null;
    }

    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_id', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_sent_at', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT xero_invoice_id, xero_invoice_number, xero_invoice_sent_at, booking_details_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $invoiceId = trim((string)($row['xero_invoice_id'] ?? ''));
    if ($invoiceId === '') {
        return null;
    }

    if (trim((string)($row['xero_invoice_sent_at'] ?? '')) !== '') {
        return [
            'processed' => false,
            'sent' => true,
            'already_sent' => true,
            'invoice_status' => null,
            'monday_quote_won' => null,
            'monday_courses_ongoing' => null,
        ];
    }

    $invoice = xeroGetInvoice($invoiceId);
    $invoiceStatus = strtoupper(trim((string)($invoice['Status'] ?? '')));
    $invoiceNumber = trim((string)($invoice['InvoiceNumber'] ?? $row['xero_invoice_number'] ?? ''));

    if (!xeroInvoiceIsSent($invoice)) {
        return [
            'processed' => false,
            'sent' => false,
            'already_sent' => false,
            'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : null,
            'monday_quote_won' => null,
            'monday_courses_ongoing' => null,
        ];
    }

    enquiryLoggerMarkXeroInvoiceSent($enquiryId, $invoiceNumber);
    enquiryLoggerEvent(
        $enquiryId,
        'xero_invoice_sent',
        'Xero invoice marked as sent — progressing Monday to Quote Won and Client Booking Form.',
        [
            'channel' => 'xero',
            'xero_invoice_id' => $invoiceId,
            'xero_invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
            'xero_invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : null,
            'sent_to_contact' => true,
        ]
    );

    $bookingDetails = [];
    $rawBooking = trim((string)($row['booking_details_json'] ?? ''));
    if ($rawBooking !== '') {
        $decoded = json_decode($rawBooking, true);
        if (is_array($decoded)) {
            $bookingDetails = $decoded;
        }
    }

    $mondayQuoteWon = null;
    $mondayCoursesOngoing = null;

    try {
        $mondayQuoteWon = mondayMoveEnquiryToQuoteWonAfterInvoiceSent($enquiryId);
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_failed',
            'Xero invoice was sent, but the Monday item could not be moved to Quote Won.',
            [
                'error' => $e->getMessage(),
                'xero_invoice_id' => $invoiceId,
            ]
        );
    }

    try {
        $mondayCoursesOngoing = mondaySyncCoursesOngoingBookingItem($enquiryId, $bookingDetails);
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_courses_ongoing_failed',
            'Xero invoice was sent, but the Client Booking Form (Courses Ongoing) record could not be created.',
            [
                'error' => $e->getMessage(),
                'xero_invoice_id' => $invoiceId,
            ]
        );
    }

    try {
        require_once __DIR__ . '/forge_webhook.php';
        forgeMaybeMarkInvoiceSent($enquiryId, $bookingDetails);
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'forge_booking_sync_failed',
            'Xero invoice was sent, but Forge status could not be updated to Invoice Sent.',
            [
                'error' => $e->getMessage(),
                'xero_invoice_id' => $invoiceId,
            ]
        );
    }

    return [
        'processed' => true,
        'sent' => true,
        'already_sent' => false,
        'invoice_status' => $invoiceStatus !== '' ? $invoiceStatus : null,
        'monday_quote_won' => $mondayQuoteWon,
        'monday_courses_ongoing' => $mondayCoursesOngoing,
    ];
}

/**
 * Poll Xero for draft invoices that have since been sent, and run Monday progression.
 *
 * @return array{checked:int,processed:int,already_sent:int,still_draft:int,failed:int}
 */
function xeroSyncSentInvoices(?int $onlyEnquiryId = null): array
{
    require_once __DIR__ . '/enquiry_logger.php';

    $stats = [
        'checked' => 0,
        'processed' => 0,
        'already_sent' => 0,
        'still_draft' => 0,
        'failed' => 0,
    ];

    if (!xeroEnabled()) {
        return $stats;
    }

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_invoice_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_invoice_sent_at', 'TEXT');

    if ($onlyEnquiryId !== null) {
        $stmt = $pdo->prepare(
            'SELECT id FROM enquiries
             WHERE id = :id
               AND xero_invoice_id IS NOT NULL
               AND TRIM(xero_invoice_id) != \'\''
        );
        $stmt->execute([':id' => $onlyEnquiryId]);
    } else {
        $stmt = $pdo->query(
            'SELECT id FROM enquiries
             WHERE xero_invoice_id IS NOT NULL
               AND TRIM(xero_invoice_id) != \'\'
               AND (xero_invoice_sent_at IS NULL OR TRIM(xero_invoice_sent_at) = \'\')
             ORDER BY id ASC
             LIMIT 100'
        );
    }

    $ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    foreach ($ids as $id) {
        $enquiryId = (int)$id;
        if ($enquiryId < 1) {
            continue;
        }
        $stats['checked']++;
        try {
            $result = xeroMaybeProcessInvoiceSent($enquiryId);
            if ($result === null) {
                continue;
            }
            if (!empty($result['already_sent'])) {
                $stats['already_sent']++;
            } elseif (!empty($result['processed'])) {
                $stats['processed']++;
            } elseif (empty($result['sent'])) {
                $stats['still_draft']++;
            }
        } catch (Throwable $e) {
            $stats['failed']++;
            enquiryLoggerEvent(
                $enquiryId,
                'xero_invoice_sent_check_failed',
                'Could not check whether the Xero invoice has been sent.',
                ['error' => $e->getMessage()]
            );
        }
    }

    return $stats;
}
