<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/enquiry_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
    ]);
    exit;
}

$enquiryId = enquiryLoggerParseId($_GET['enquiry'] ?? $_GET['enquiryId'] ?? null);
$token = trim((string)($_GET['token'] ?? ''));

if ($enquiryId === null || $token === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Enquiry ID and token are required.',
    ]);
    exit;
}

try {
    $row = enquiryLoggerGetForResume($enquiryId, $token);
    if ($row === null) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'This enquiry link is invalid or has expired.',
        ]);
        exit;
    }

    $formData = [];
    $rawFormData = trim((string)($row['form_data_json'] ?? ''));
    if ($rawFormData !== '') {
        $decoded = json_decode($rawFormData, true);
        if (is_array($decoded)) {
            $formData = $decoded;
        }
    }

    // Prefer dedicated columns when form JSON is missing/stale (common after edits).
    $preferredFromColumns = [
        'preferredDateTime' => trim((string)($row['preferred_date_time'] ?? '')),
        'dateNotSure' => !empty($row['date_not_sure']) ? 'on' : null,
    ];
    if ($preferredFromColumns['preferredDateTime'] !== '') {
        $formData['preferredDateTime'] = $preferredFromColumns['preferredDateTime'];
    }
    if ($preferredFromColumns['dateNotSure'] !== null) {
        $formData['dateNotSure'] = 'on';
        unset($formData['preferredDateTime']);
    } else {
        unset($formData['dateNotSure']);
    }
    $formData = enquiryPostWithNormalisedPreferredDate($formData);

    echo json_encode([
        'success' => true,
        'enquiry' => [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'email' => (string)$row['email'],
            'enquiryType' => (string)$row['enquiry_type'],
            'status' => (string)$row['status'],
            'formData' => $formData,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
