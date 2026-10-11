<?php
declare(strict_types=1);

/**
 * Validate a voucher code against a quote net total (POST JSON or form).
 *
 * POST fields: code, quoteValue (net ex VAT), optional enquiryId
 */

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/voucher_helpers.php';

$raw = file_get_contents('php://input');
$json = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
$input = is_array($json) ? $json : $_POST;

$code = trim((string) ($input['code'] ?? $input['voucherCode'] ?? ''));
$quoteRaw = trim((string) ($input['quoteValue'] ?? $input['quote_net'] ?? ''));
$quoteRaw = preg_replace('/[^0-9.\-]/', '', $quoteRaw) ?? '';
$quoteNet = is_numeric($quoteRaw) ? (float) $quoteRaw : 0.0;

$enquiryIdRaw = $input['enquiryId'] ?? $input['enquiry_id'] ?? null;
$excludeEnquiryId = is_numeric($enquiryIdRaw) ? (int) $enquiryIdRaw : null;

if ($excludeEnquiryId !== null) {
    try {
        require_once __DIR__ . '/enquiry_logger.php';
        $pdo = enquiryLoggerPdo();
        $stmt = $pdo->prepare('SELECT status FROM enquiries WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $excludeEnquiryId]);
        $status = strtolower(trim((string) $stmt->fetchColumn()));
        if ($status === 'quote_won') {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'message' => 'Can\'t apply a voucher because this enquiry is Quote Won.',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    } catch (Throwable) {
        // continue with normal validation if status lookup fails
    }
}

$result = voucherApplyToQuoteNet($quoteNet, $code, $excludeEnquiryId);

if (! $result['ok']) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Invalid voucher.',
        'original' => $result['original'],
        'original_formatted' => $result['original_formatted'],
        'final' => $result['final'],
        'final_formatted' => $result['final_formatted'],
        'discount' => 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Voucher applied.',
    'voucher' => $result['voucher'],
    'original' => $result['original'],
    'original_formatted' => $result['original_formatted'],
    'discount' => $result['discount'],
    'discount_formatted' => voucherFormatQuoteValue((float) $result['discount']),
    'final' => $result['final'],
    'final_formatted' => $result['final_formatted'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
