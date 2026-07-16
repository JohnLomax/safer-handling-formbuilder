<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/enquiry_logger.php';
require_once __DIR__ . '/brevo_email.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$enquiryId = enquiryLoggerPostId($_POST) ?? enquiryLoggerParseId($_POST['enquiryId'] ?? null);
$token = trim((string)($_POST['token'] ?? ''));

if ($enquiryId === null || $token === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'This booking link is invalid.',
    ]);
    exit;
}

$enquiry = enquiryLoggerGetForResume($enquiryId, $token);
if ($enquiry === null) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'This booking link is invalid or has expired.',
    ]);
    exit;
}

$existing = enquiryLoggerGetBookingDetails($enquiryId);
if (is_array($existing) && trim((string)($existing['booking_submitted_at'] ?? '')) !== '') {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'Booking details have already been submitted for this enquiry.',
    ]);
    exit;
}

$bookerName = trim((string)($_POST['bookerName'] ?? ''));
$organisation = trim((string)($_POST['organisation'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$venueAddress = trim((string)($_POST['venueAddress'] ?? ''));
$studentNames = trim((string)($_POST['studentNames'] ?? ''));
$studentEmails = trim((string)($_POST['studentEmails'] ?? ''));
$specialRequests = trim((string)($_POST['specialRequests'] ?? ''));
$invoiceName = trim((string)($_POST['invoiceName'] ?? ''));
$invoiceEmail = trim((string)($_POST['invoiceEmail'] ?? ''));
$invoiceAddress = trim((string)($_POST['invoiceAddress'] ?? ''));
$invoicePhone = trim((string)($_POST['invoicePhone'] ?? ''));
$purchaseOrderNumber = trim((string)($_POST['purchaseOrderNumber'] ?? ''));
$venueRequirements = isset($_POST['venueRequirements']);
$termsAccepted = isset($_POST['termsAccepted']);

$required = [
    'Booker name' => $bookerName,
    'Organisation' => $organisation,
    'Email address' => $email,
    'Phone number' => $phone,
    'Training venue address' => $venueAddress,
    'Invoice name' => $invoiceName,
    'Invoice email' => $invoiceEmail,
    'Invoice address' => $invoiceAddress,
];

foreach ($required as $label => $value) {
    if ($value === '') {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => $label . ' is required.',
        ]);
        exit;
    }
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.',
    ]);
    exit;
}

if (!filter_var($invoiceEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid invoice email address.',
    ]);
    exit;
}

if (!$venueRequirements) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please confirm the venue requirements.',
    ]);
    exit;
}

if (!$termsAccepted) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please accept the Safer Handling Terms and Conditions.',
    ]);
    exit;
}

$nameLines = $studentNames === ''
    ? []
    : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $studentNames) ?: []), static fn ($line) => $line !== ''));
$emailLines = $studentEmails === ''
    ? []
    : array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $studentEmails) ?: []), static fn ($line) => $line !== ''));

$uploadedFileMeta = null;
$hasUpload = isset($_FILES['studentNamesFile'])
    && is_array($_FILES['studentNamesFile'])
    && (int)($_FILES['studentNamesFile']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if (count($nameLines) === 0 && count($emailLines) === 0 && !$hasUpload) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide student names and emails, or upload a delegate list.',
    ]);
    exit;
}

if ((count($nameLines) > 0 || count($emailLines) > 0) && count($nameLines) !== count($emailLines)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Student names and email addresses must have the same number of lines, in matching order.',
    ]);
    exit;
}

foreach ($emailLines as $delegateEmail) {
    if (!filter_var($delegateEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'One or more student email addresses are invalid.',
        ]);
        exit;
    }
}

if ($hasUpload) {
    $file = $_FILES['studentNamesFile'];
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The delegate file could not be uploaded. Please try again.',
        ]);
        exit;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    $originalName = basename((string)($file['name'] ?? 'delegates'));
    $size = (int)($file['size'] ?? 0);
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid uploaded file.',
        ]);
        exit;
    }
    if ($size > 8 * 1024 * 1024) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Delegate file must be 8MB or smaller.',
        ]);
        exit;
    }

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['csv', 'xlsx', 'xls', 'txt', 'pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowed, true)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Unsupported file type. Please upload CSV, Excel, TXT, PDF, or Word.',
        ]);
        exit;
    }

    $uploadDir = __DIR__ . '/data/booking-uploads/' . $enquiryId;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Could not store the uploaded delegate file.',
        ]);
        exit;
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME)) ?: 'delegates';
    $storedName = $safeBase . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $storedName;
    if (!move_uploaded_file($tmpPath, $destPath)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Could not store the uploaded delegate file.',
        ]);
        exit;
    }

    $uploadedFileMeta = [
        'originalName' => $originalName,
        'storedName' => $storedName,
        'relativePath' => 'data/booking-uploads/' . $enquiryId . '/' . $storedName,
        'size' => $size,
        'mime' => (string)($file['type'] ?? ''),
    ];
}

$delegates = [];
for ($i = 0, $count = count($nameLines); $i < $count; $i++) {
    $delegates[] = [
        'name' => $nameLines[$i],
        'email' => $emailLines[$i],
    ];
}

$details = [
    'bookerName' => $bookerName,
    'organisation' => $organisation,
    'email' => $email,
    'phone' => $phone,
    'venueAddress' => $venueAddress,
    'studentNames' => $studentNames,
    'studentEmails' => $studentEmails,
    'delegates' => $delegates,
    'studentNamesFile' => $uploadedFileMeta,
    'specialRequests' => $specialRequests,
    'venueRequirementsConfirmed' => true,
    'invoiceName' => $invoiceName,
    'invoiceEmail' => $invoiceEmail,
    'invoiceAddress' => $invoiceAddress,
    'invoicePhone' => $invoicePhone,
    'purchaseOrderNumber' => $purchaseOrderNumber,
    'termsAccepted' => true,
    'source' => 'booking_form',
];

try {
    enquiryLoggerSaveBookingDetails($enquiryId, $details);
    enquiryLoggerMarkQuoteAccepted($enquiryId);
    enquiryLoggerEvent(
        $enquiryId,
        'booking_details_submitted',
        'Customer submitted booking details, venue confirmation, and accepted terms.',
        [
            'organisation' => $organisation,
            'delegate_count' => count($delegates),
            'has_upload' => $uploadedFileMeta !== null,
            'status' => 'quote_accepted',
        ]
    );

    $mondayWarning = '';
    try {
        require_once __DIR__ . '/monday_helpers.php';
        mondaySyncBookingDetails($enquiryId, $details);
    } catch (Throwable $mondayError) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_booking_sync_failed',
            'Booking details were saved, but Monday sync failed.',
            ['error' => $mondayError->getMessage()]
        );
        $mondayWarning = 'Saved locally, but Monday sync failed: ' . $mondayError->getMessage();
    }

    $xeroWarning = '';
    try {
        require_once __DIR__ . '/xero.php';
        xeroMaybeCreateDraftInvoiceAfterQuoteAccepted($enquiryId, $details);
    } catch (Throwable $xeroError) {
        $xeroWarning = 'Draft Xero invoice could not be created: ' . $xeroError->getMessage();
    }

    $payload = [
        'success' => true,
        'message' => 'Booking details submitted successfully.',
        'enquiryId' => $enquiryId,
    ];
    $warnings = array_values(array_filter([$mondayWarning, $xeroWarning]));
    if ($warnings !== []) {
        $payload['warning'] = implode(' ', $warnings);
    }

    echo json_encode($payload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'We could not save your booking details right now. Please try again.',
    ]);
}
