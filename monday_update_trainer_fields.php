<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/monday_helpers.php';
require_once __DIR__ . '/enquiry_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$trainerCourse = trim((string)($_POST['trainerCourseSelect'] ?? ''));
$trainerAttendees = trim((string)($_POST['trainerAttendees'] ?? ''));
$trainersRequired = trim((string)($_POST['trainersRequired'] ?? ''));
$quoteValue = trim((string)($_POST['quoteValue'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email is required.']);
    exit;
}

if ($trainerCourse === '' && $trainerAttendees === '' && $trainersRequired === '' && $quoteValue === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Provide course, attendees, and/or quote to sync.']);
    exit;
}

$monday = mondayAppConfig();
$mondayvariable = $monday['token'];
$boardIdRaw = $monday['boardId'];

if ($mondayvariable === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => mondayConfigMissingMessage()]);
    exit;
}

$boardId = (int)$boardIdRaw;
$apiUrl = 'https://api.monday.com/v2';

/**
 * @return array{status:int,body:array<string,mixed>}
 */
function mondayGraphql(string $token, string $apiUrl, string $query, array $variables = []): array
{
    $ch = curl_init($apiUrl);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }
    $payload = json_encode(['query' => $query, 'variables' => $variables]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: ' . $token,
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);
    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Monday API request failed: ' . $err);
    }
    curl_close($ch);
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid Monday API response.');
    }
    return ['status' => $status, 'body' => $decoded];
}

/**
 * @param array<string,mixed> $responseBody
 */
function mondayErrorMessage(array $responseBody, string $fallback): string
{
    $errors = $responseBody['errors'] ?? null;
    if (!is_array($errors) || count($errors) === 0) {
        return $fallback;
    }
    $first = $errors[0] ?? null;
    if (!is_array($first)) {
        return $fallback;
    }
    $message = trim((string)($first['message'] ?? ''));
    return $message !== '' ? $message : $fallback;
}

/**
 * @param mixed $value
 * @return mixed
 */
function toMondayColumnValue(string $columnType, $value)
{
    $type = strtolower(trim($columnType));
    if (strpos($type, 'dropdown') !== false) {
        return ['labels' => [(string)$value]];
    }
    if ($type === 'status' || strpos($type, 'status') !== false) {
        return ['label' => (string)$value];
    }
    if ($type === 'long_text') {
        return ['text' => (string)$value];
    }
    if ($type === 'numbers' || $type === 'numeric') {
        return (string)$value;
    }
    return (string)$value;
}

/**
 * Prefer Monday column "Number of Attendees" over a generic "Attendees" title when both exist.
 *
 * @param array<int, array<string, mixed>> $columns
 * @return array{0: ?string, 1: string}
 */
function mondayAttendeesColumnFromBoard(array $columns): array
{
    $idNum = null;
    $typeNum = '';
    $idShort = null;
    $typeShort = '';
    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        if ($title === 'number of attendees' && $idNum === null) {
            $idNum = $id;
            $typeNum = $type;
        }
        if ($title === 'attendees' && $idShort === null) {
            $idShort = $id;
            $typeShort = $type;
        }
    }
    if ($idNum !== null) {
        return [$idNum, $typeNum];
    }
    if ($idShort !== null) {
        return [$idShort, $typeShort];
    }

    return [null, ''];
}

try {
    $columnsQuery = <<<'GQL'
query ($boardId: [ID!]) {
  boards(ids: $boardId) {
    columns {
      id
      title
      type
    }
  }
}
GQL;

    $columnsResp = mondayGraphql($mondayvariable, $apiUrl, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($columnsResp['body'], 'Could not read Monday columns.'));
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }

    $columns = $boards[0]['columns'] ?? [];
    $emailColumnId = null;
    $specificCourseColumnId = null;
    $specificCourseColumnType = '';
    $quoteValueColumnId = null;
    $quoteValueColumnType = '';

    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        if ($emailColumnId === null && ($type === 'email' || $title === 'email')) {
            $emailColumnId = $id;
        }
        if ($specificCourseColumnId === null && $title === 'specific course') {
            $specificCourseColumnId = $id;
            $specificCourseColumnType = $type;
        }
        if ($quoteValueColumnId === null && $title === 'quote value') {
            $quoteValueColumnId = $id;
            $quoteValueColumnType = $type;
        }
    }

    [$attendeesColumnId, $attendeesColumnType] = mondayAttendeesColumnFromBoard($columns);

    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on Monday board.');
    }

    $enquiryId = enquiryLoggerResolveAuthenticatedEnquiryId($_POST);
    $itemId = mondayResolveItemIdForEnquiryJourney(
        $mondayvariable,
        $boardId,
        $emailColumnId,
        $email,
        $enquiryId
    );
    if ($itemId === null || $itemId === '') {
        throw new RuntimeException('No Monday item found for this enquiry journey.');
    }

    $columnValues = [];
    if ($trainerCourse !== '' && $specificCourseColumnId !== null) {
        $columnValues[$specificCourseColumnId] = toMondayColumnValue(
            $specificCourseColumnType,
            mondayResolveSpecificCourseLabel($trainerCourse)
        );
    }
    if ($trainerAttendees !== '' && $attendeesColumnId !== null) {
        $columnValues[$attendeesColumnId] = toMondayColumnValue($attendeesColumnType, $trainerAttendees);
    }
    if ($quoteValue !== '' && $quoteValueColumnId !== null) {
        $columnValues[$quoteValueColumnId] = toMondayColumnValue($quoteValueColumnType, $quoteValue);
    }

    $columnValues = mondayAppendCreatedDate($columns, $columnValues);

    if (count($columnValues) === 0) {
        echo json_encode(['success' => true, 'message' => 'No matching Monday columns to update.', 'updated' => false]);
        exit;
    }

    $updateQuery = <<<'GQL'
mutation ($boardId: ID!, $itemId: ID!, $columnValues: JSON!) {
  change_multiple_column_values(
    board_id: $boardId,
    item_id: $itemId,
    column_values: $columnValues
  ) {
    id
  }
}
GQL;

    $updateResp = mondayGraphql($mondayvariable, $apiUrl, $updateQuery, [
        'boardId' => (string)$boardId,
        'itemId' => $itemId,
        'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update Monday fields.'));
    }

    enquiryLoggerSafe(function () use ($itemId): void {
        enquiryLoggerMondayFieldsUpdated(
            $_POST,
            'Trainer course fields updated in Monday.',
            $itemId
        );
    });

    echo json_encode(['success' => true, 'message' => 'Monday fields updated.', 'updated' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
