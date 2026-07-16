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

function xeroAccessToken(): string
{
    return appConfigValue('XERO_ACCESS_TOKEN', 'xeroAccessToken');
}

function xeroRefreshToken(): string
{
    return appConfigValue('XERO_REFRESH_TOKEN', 'xeroRefreshToken');
}

function xeroTokenExpiresAt(): int
{
    $raw = appConfigValue('XERO_TOKEN_EXPIRES_AT', 'xeroTokenExpiresAt');
    return is_numeric($raw) ? (int)$raw : 0;
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
            foreach ($pairs as $key => $value) {
                \App\Models\Setting::setValue($key, $value);
            }

            return;
        } catch (Throwable $e) {
            // Fall through to direct SQLite write for legacy form PHP.
        }
    }

    if (!function_exists('appDatabasePdo') || appDatabasePdo() === null) {
        return;
    }

    $pdo = appDatabasePdo();
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO settings ("key", value, created_at, updated_at)
         VALUES (:key, :value, :created_at, :updated_at)
         ON CONFLICT("key") DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
    );

    foreach ($pairs as $key => $value) {
        $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

/**
 * @return array{access_token:string,refresh_token:string,expires_at:int,tenant_id:string}
 */
function xeroEnsureAccessToken(): array
{
    $accessToken = xeroAccessToken();
    $refreshToken = xeroRefreshToken();
    $expiresAt = xeroTokenExpiresAt();
    $tenantId = xeroTenantId();

    if ($accessToken !== '' && $expiresAt > (time() + 60)) {
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $expiresAt,
            'tenant_id' => $tenantId,
        ];
    }

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
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Xero token refresh failed: ' . $err);
    }
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if ($status >= 400 || !is_array($decoded) || empty($decoded['access_token'])) {
        $message = is_array($decoded) ? trim((string)($decoded['error_description'] ?? $decoded['error'] ?? '')) : '';
        throw new RuntimeException($message !== '' ? $message : 'Xero token refresh failed (HTTP ' . $status . ').');
    }

    $accessToken = (string)$decoded['access_token'];
    $refreshToken = (string)($decoded['refresh_token'] ?? $refreshToken);
    $expiresIn = (int)($decoded['expires_in'] ?? 1800);
    $expiresAt = time() + max(60, $expiresIn);
    xeroPersistTokens($accessToken, $refreshToken, $expiresAt, $tenantId);

    return [
        'access_token' => $accessToken,
        'refresh_token' => $refreshToken,
        'expires_at' => $expiresAt,
        'tenant_id' => $tenantId,
    ];
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
    if (!empty($body['Message']) && is_string($body['Message'])) {
        return trim($body['Message']);
    }

    if (!empty($body['Detail']) && is_string($body['Detail'])) {
        return trim($body['Detail']);
    }

    if (!empty($body['Elements'][0]['ValidationErrors'][0]['Message'])) {
        return trim((string)$body['Elements'][0]['ValidationErrors'][0]['Message']);
    }

    return $fallback;
}

/**
 * @return array{ContactID:string,Name:string}
 */
function xeroFindOrCreateContact(string $name, string $email): array
{
    $name = trim($name);
    $email = trim($email);
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('A valid contact name and email are required for Xero.');
    }

    $where = 'EmailAddress!=null&&EmailAddress=="' . str_replace('"', '', $email) . '"';
    $existing = xeroApiRequest('GET', 'Contacts', null, [
        'where' => $where,
        'page' => 1,
    ]);

    if ($existing['status'] < 400) {
        $contacts = $existing['body']['Contacts'] ?? [];
        if (is_array($contacts) && isset($contacts[0]['ContactID'])) {
            return [
                'ContactID' => (string)$contacts[0]['ContactID'],
                'Name' => (string)($contacts[0]['Name'] ?? $name),
            ];
        }
    }

    $payload = [
        'Contacts' => [[
            'Name' => $name,
            'EmailAddress' => $email,
            'FirstName' => $name,
            'IsCustomer' => true,
        ]],
    ];

    $created = xeroApiRequest('POST', 'Contacts', $payload);
    if ($created['status'] >= 400 || empty($created['body']['Contacts'][0]['ContactID'])) {
        throw new RuntimeException(xeroApiErrorMessage($created['body'], 'Could not create Xero contact.'));
    }

    $contact = $created['body']['Contacts'][0];

    return [
        'ContactID' => (string)$contact['ContactID'],
        'Name' => (string)($contact['Name'] ?? $name),
    ];
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

    // Form quoteValue is the amount shown before "+ VAT" (ex-VAT / net).
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
    $descriptionParts[] = 'Cost (ex. VAT): ' . number_format($net, 2, '.', '');
    $descriptionParts[] = 'Total (inc. VAT): ' . number_format($gross, 2, '.', '');
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
    // Form amounts are ex-VAT. Send Exclusive so Xero shows:
    // Subtotal = net, VAT = 20%, Total = net + VAT.
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
        'Summary' => 'Safer Handling training quote',
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
 * Create/find contact and create quote in Xero (ex-VAT + VAT). Returns PDF bytes for emailing.
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

    $contact = xeroFindOrCreateContact($name, $email);
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

    $resp = xeroApiRequest('POST', 'Quotes', [
        'Quotes' => [[
            'QuoteID' => $quoteId,
            'Status' => 'ACCEPTED',
        ]],
    ]);

    if ($resp['status'] >= 400) {
        throw new RuntimeException(xeroApiErrorMessage($resp['body'], 'Could not mark Xero quote as accepted.'));
    }
}

/**
 * Map Xero quote line items into invoice line items.
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
        $unitAmount = isset($quoteLine['UnitAmount']) ? (float)$quoteLine['UnitAmount'] : null;
        if ($description === '' && ($unitAmount === null || $quantity <= 0)) {
            continue;
        }

        $line = [
            'Description' => $description !== '' ? $description : 'Training',
            'Quantity' => $quantity > 0 ? $quantity : 1,
        ];

        if ($unitAmount !== null) {
            $line['UnitAmount'] = $unitAmount;
        }
        if (isset($quoteLine['LineAmount']) && is_numeric($quoteLine['LineAmount'])) {
            $line['LineAmount'] = (float)$quoteLine['LineAmount'];
        }
        if (!empty($quoteLine['AccountCode'])) {
            $line['AccountCode'] = (string)$quoteLine['AccountCode'];
        } else {
            $line['AccountCode'] = xeroSalesAccountCode();
        }
        if (!empty($quoteLine['TaxType'])) {
            $line['TaxType'] = (string)$quoteLine['TaxType'];
        } else {
            $line['TaxType'] = 'OUTPUT2';
        }
        if (!empty($quoteLine['ItemCode'])) {
            $line['ItemCode'] = (string)$quoteLine['ItemCode'];
        }
        if (isset($quoteLine['DiscountRate']) && is_numeric($quoteLine['DiscountRate'])) {
            $line['DiscountRate'] = (float)$quoteLine['DiscountRate'];
        }
        if (isset($quoteLine['DiscountAmount']) && is_numeric($quoteLine['DiscountAmount'])) {
            $line['DiscountAmount'] = (float)$quoteLine['DiscountAmount'];
        }

        $lines[] = $line;
    }

    if ($lines === []) {
        throw new RuntimeException('Xero quote has no line items to invoice.');
    }

    return $lines;
}

/**
 * Create a DRAFT sales invoice from an existing Xero quote. Does not email or authorise it.
 *
 * @param array<string, mixed> $bookingDetails
 * @return array{InvoiceID:string,InvoiceNumber:string,Status:string,Total:float,TotalTax:float,SubTotal:float,QuoteID:string,QuoteNumber:string}
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
            $quoteStatus = 'ACCEPTED';
        } catch (Throwable $e) {
            // Quote may already be accepted/invoiced; continue with invoice creation.
        }
    }

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
        'LineAmountTypes' => (string)($quote['LineAmountTypes'] ?? 'Exclusive'),
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

    return [
        'InvoiceID' => (string)$invoice['InvoiceID'],
        'InvoiceNumber' => (string)($invoice['InvoiceNumber'] ?? ''),
        'Status' => (string)($invoice['Status'] ?? 'DRAFT'),
        'Total' => (float)($invoice['Total'] ?? 0),
        'TotalTax' => (float)($invoice['TotalTax'] ?? 0),
        'SubTotal' => (float)($invoice['SubTotal'] ?? 0),
        'QuoteID' => $quoteId,
        'QuoteNumber' => $quoteNumber,
        'QuoteStatus' => $quoteStatus,
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
        'SELECT xero_quote_id, xero_quote_number, xero_contact_id, name, email
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
            'Draft invoice created in Xero from the accepted quote (not sent).',
            [
                'channel' => 'xero',
                'xero_quote_id' => $invoice['QuoteID'],
                'xero_quote_number' => $invoice['QuoteNumber'],
                'xero_invoice_id' => $invoice['InvoiceID'],
                'xero_invoice_number' => $invoice['InvoiceNumber'],
                'xero_invoice_status' => $invoice['Status'],
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
