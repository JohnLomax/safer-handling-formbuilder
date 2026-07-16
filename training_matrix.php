<?php
declare(strict_types=1);

/**
 * Organisation training matrix for the enquiry form (GET JSON).
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/training_matrix_data.php';

echo json_encode([
    'success' => true,
    'matrix' => trainingMatrix(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
