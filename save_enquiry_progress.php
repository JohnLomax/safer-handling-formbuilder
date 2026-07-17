<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/enquiry_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$enquiryId = enquiryLoggerResolveAuthenticatedEnquiryId($_POST);
if ($enquiryId === null) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid enquiry ID and resume token are required.',
    ]);
    exit;
}

try {
    enquiryLoggerUpdateFromPost($enquiryId, $_POST, 'in_progress');

    echo json_encode([
        'success' => true,
        'enquiryId' => $enquiryId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
