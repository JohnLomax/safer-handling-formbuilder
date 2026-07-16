<?php
declare(strict_types=1);

/**
 * Server-side postcode lookup proxy (Ideal Postcodes).
 *
 * GET ?postcode=SW1A1AA
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET.']);
    exit;
}

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

/**
 * @return array{status:int,body:?array<string,mixed>,raw:string,error:string}
 */
function postcodeHttpGet(string $url): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['status' => 0, 'body' => null, 'raw' => '', 'error' => 'Could not start request.'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['status' => $status, 'body' => null, 'raw' => '', 'error' => $error];
    }
    curl_close($ch);
    $decoded = json_decode($raw, true);

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : null,
        'raw' => $raw,
        'error' => '',
    ];
}

function normalizeUkPostcode(string $postcode): string
{
    return strtoupper(preg_replace('/\s+/', '', trim($postcode)));
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function normalizeAddressRow(array $row): array
{
    return [
        'postcode' => trim((string)($row['postcode'] ?? '')),
        'post_town' => trim((string)($row['post_town'] ?? '')),
        'line_1' => trim((string)($row['line_1'] ?? '')),
        'line_2' => trim((string)($row['line_2'] ?? '')),
        'line_3' => trim((string)($row['line_3'] ?? '')),
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
    ];
}

/**
 * @return array<int, array<string, mixed>>
 */
function lookupIdealPostcodes(string $postcode, string $apiKey): array
{
    $all = [];
    $page = 0;
    $maxPages = 20;

    while ($page < $maxPages) {
        $url = 'https://api.ideal-postcodes.co.uk/v1/postcodes/' . rawurlencode($postcode)
            . '?api_key=' . rawurlencode($apiKey)
            . '&page=' . $page;
        $resp = postcodeHttpGet($url);
        if ($resp['body'] === null) {
            throw new RuntimeException('Invalid response from Ideal Postcodes.');
        }
        $code = $resp['body']['code'] ?? null;
        if ($resp['status'] === 404 || $code === 4040 || $code === '4040') {
            return [];
        }
        if ($resp['status'] < 200 || $resp['status'] >= 300 || ($code !== 2000 && $code !== '2000')) {
            $message = trim((string)($resp['body']['message'] ?? 'Ideal Postcodes lookup failed.'));
            throw new RuntimeException($message);
        }
        $batch = $resp['body']['result'] ?? [];
        if (!is_array($batch) || count($batch) === 0) {
            break;
        }
        foreach ($batch as $row) {
            if (!is_array($row)) {
                continue;
            }
            $all[] = normalizeAddressRow([
                'postcode' => $row['postcode'] ?? $postcode,
                'post_town' => $row['post_town'] ?? '',
                'line_1' => $row['line_1'] ?? '',
                'line_2' => $row['line_2'] ?? '',
                'line_3' => $row['line_3'] ?? '',
                'latitude' => $row['latitude'] ?? null,
                'longitude' => $row['longitude'] ?? null,
            ]);
        }
        if (count($batch) < 100) {
            break;
        }
        $page++;
    }

    return $all;
}

function idealPostcodesApiKey(): string
{
    return appConfigValue('IDEAL_POSTCODES_API_KEY', 'idealPostcodesApiKey');
}

/**
 * @return array{provider:string,results:array<int, array<string,mixed>>}
 */
function lookupPostcode(string $postcode): array
{
    $idealKey = idealPostcodesApiKey();
    if ($idealKey === '') {
        throw new RuntimeException('Ideal Postcodes is not configured. Set $idealPostcodesApiKey in config.php.');
    }

    return [
        'provider' => 'ideal',
        'results' => lookupIdealPostcodes($postcode, $idealKey),
    ];
}

$postcode = normalizeUkPostcode((string)($_GET['postcode'] ?? ''));
if ($postcode === '' || strlen($postcode) < 5 || strlen($postcode) > 8) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid UK postcode.']);
    exit;
}

try {
    $lookup = lookupPostcode($postcode);
    $results = $lookup['results'];
    if (count($results) === 0) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'No addresses found for that postcode.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'code' => 2000,
        'message' => 'Success',
        'provider' => $lookup['provider'],
        'result' => $results,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
