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
$checkedRaw = $_POST['checked'] ?? '0';
$checked = false;
if ($checkedRaw === true || $checkedRaw === 1 || $checkedRaw === '1') {
    $checked = true;
} else {
    $s = strtolower(trim((string)$checkedRaw));
    $checked = in_array($s, ['true', 'yes', 'on'], true);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid email is required.']);
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
    $itsForMeColumnId = null;
    $itsForMeColumnType = '';

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
        if ($itsForMeColumnId === null && ($title === "it's for me" || $title === 'its for me' || $title === 'it’s for me')) {
            $itsForMeColumnId = $id;
            $itsForMeColumnType = $type;
        }
    }

    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on Monday board.');
    }
    if ($itsForMeColumnId === null) {
        echo json_encode([
            'success' => true,
            'message' => 'No It\'s for me column on board; nothing to update.',
            'updated' => false,
        ]);
        exit;
    }

    $findQuery = <<<'GQL'
query ($boardId: ID!, $columnId: String!, $columnValues: [String!]!) {
  items_page_by_column_values(
    board_id: $boardId,
    columns: [{column_id: $columnId, column_values: $columnValues}],
    limit: 1
  ) {
    items {
      id
    }
  }
}
GQL;

    $findResp = mondayGraphql($mondayvariable, $apiUrl, $findQuery, [
        'boardId' => (string)$boardId,
        'columnId' => $emailColumnId,
        'columnValues' => [$email],
    ]);
    if ($findResp['status'] >= 400 || !empty($findResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($findResp['body'], 'Could not find Monday item by email.'));
    }

    $items = $findResp['body']['data']['items_page_by_column_values']['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException('No Monday item found for this email.');
    }

    $itemId = trim((string)($items[0]['id'] ?? ''));
    if ($itemId === '') {
        throw new RuntimeException('Could not resolve Monday item id.');
    }

    $columnValues = [];
    if ($itsForMeColumnType === 'checkbox') {
        $columnValues[$itsForMeColumnId] = ['checked' => $checked ? 'true' : 'false'];
    } elseif ($checked) {
        if ($itsForMeColumnType === 'status') {
            $columnValues[$itsForMeColumnId] = ['label' => "It's for me"];
        } elseif ($itsForMeColumnType === 'dropdown') {
            $columnValues[$itsForMeColumnId] = ['labels' => ["It's for me"]];
        } else {
            $columnValues[$itsForMeColumnId] = "It's for me";
        }
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Unchecked state only updates checkbox-type It\'s for me columns.',
            'updated' => false,
        ]);
        exit;
    }

    $columnValues = mondayAppendCreatedDate($columns, $columnValues);

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
        throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update It\'s for me in Monday.'));
    }

    enquiryLoggerSafe(function () use ($itemId): void {
        enquiryLoggerMondayFieldsUpdated(
            $_POST,
            'Booking via company preference updated in Monday.',
            $itemId
        );
    });

    echo json_encode(['success' => true, 'message' => 'Monday updated for booking via company.', 'updated' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
