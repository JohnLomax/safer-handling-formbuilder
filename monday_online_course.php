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
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
    ]);
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));
$extraNotes = trim((string)($_POST['extraNotes'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'A valid email is required.',
    ]);
    exit;
}

$monday = mondayAppConfig();
$mondayvariable = $monday['token'];
$boardIdRaw = $monday['boardId'];

if ($mondayvariable === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => mondayConfigMissingMessage(),
    ]);
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

    $payload = json_encode([
        'query' => $query,
        'variables' => $variables,
    ]);

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

    $columnsResp = mondayGraphql($mondayvariable, $apiUrl, $columnsQuery, [
        'boardId' => [$boardId],
    ]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($columnsResp['body'], 'Could not read board columns.'));
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Board not found in Monday.');
    }

    $columns = $boards[0]['columns'] ?? [];
    $emailColumnId = null;
    $notesColumnId = null;
    $notesColumnType = null;
    $deliveryPrefColumnId = null;
    $deliveryPrefColumnType = null;

    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = (string)($column['id'] ?? '');
        if ($id === '') {
            continue;
        }
        if ($emailColumnId === null && ($type === 'email' || $title === 'email')) {
            $emailColumnId = $id;
        }
        if ($notesColumnId === null && $title === 'notes') {
            $notesColumnId = $id;
            $notesColumnType = $type;
        }
        if ($deliveryPrefColumnId === null && $title === 'delivery preference') {
            $deliveryPrefColumnId = $id;
            $deliveryPrefColumnType = $type;
        }
    }

    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on board.');
    }
    if ($deliveryPrefColumnId === null) {
        throw new RuntimeException('Delivery Preference column not found on board.');
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

    $existingNotes = '';
    if ($notesColumnId !== null) {
        $notesQuery = <<<'GQL'
query ($itemIds: [ID!]!, $notesColumnId: [String!]) {
  items (ids: $itemIds) {
    id
    column_values(ids: $notesColumnId) {
      id
      text
    }
  }
}
GQL;
        $notesResp = mondayGraphql($mondayvariable, $apiUrl, $notesQuery, [
            'itemIds' => [(string)$itemId],
            'notesColumnId' => [$notesColumnId],
        ]);
        if ($notesResp['status'] < 400 && empty($notesResp['body']['errors'])) {
            $noteItems = $notesResp['body']['data']['items'] ?? [];
            if (is_array($noteItems) && count($noteItems) > 0) {
                $noteValues = $noteItems[0]['column_values'] ?? [];
                if (is_array($noteValues) && count($noteValues) > 0) {
                    $existingNotes = trim((string)($noteValues[0]['text'] ?? ''));
                }
            }
        }
    }

    $onePersonText = "It's for one person.";
    $appendLines = [$onePersonText];
    if ($extraNotes !== '') {
        $appendLines[] = 'Any additional notes: ' . $extraNotes;
    }
    $appendBlock = implode("\n", $appendLines);
    $newNotesText = $existingNotes === '' ? $appendBlock : $existingNotes . "\n" . $appendBlock;

    $columnValues = [];

    $deliveryPrefValue = 'Online Only';
    if ($deliveryPrefColumnType === 'status') {
        $deliveryPrefValue = ['label' => 'Online Only'];
    } elseif ($deliveryPrefColumnType === 'dropdown') {
        $deliveryPrefValue = ['labels' => ['Online Only']];
    }
    $columnValues[$deliveryPrefColumnId] = $deliveryPrefValue;

    if ($notesColumnId !== null) {
        if ($notesColumnType === 'long_text') {
            $columnValues[$notesColumnId] = ['text' => $newNotesText];
        } else {
            $columnValues[$notesColumnId] = $newNotesText;
        }
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
        throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update online course details.'));
    }

    enquiryLoggerSafe(function () use ($itemId): void {
        enquiryLoggerMondayFieldsUpdated(
            $_POST,
            'Online course enquiry details updated in Monday.',
            $itemId
        );
    });

    echo json_encode([
        'success' => true,
        'message' => 'Monday enquiry updated for online course.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
