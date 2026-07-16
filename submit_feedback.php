<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/feedback_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$issueFaced = trim((string)($_POST['issueFaced'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if ($issueFaced === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please tell us which issue you faced.',
    ]);
    exit;
}

if ($description === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a description of the issue.',
    ]);
    exit;
}

if (strlen($issueFaced) > 255) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Issue faced must be 255 characters or fewer.',
    ]);
    exit;
}

if (strlen($description) > 5000) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Description must be 5000 characters or fewer.',
    ]);
    exit;
}

try {
    $feedbackId = feedbackLoggerCreate($issueFaced, $description);

    echo json_encode([
        'success' => true,
        'message' => 'Thank you. Your feedback has been submitted.',
        'feedbackId' => $feedbackId,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'We could not save your feedback right now. Please try again.',
    ]);
}
