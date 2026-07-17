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

$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$enquiryType = trim((string)($_POST['enquiryType'] ?? ''));

if ($name === '' || $email === '' || $enquiryType === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Name, email, and enquiry type are required.',
    ]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email address.',
    ]);
    exit;
}

$monday = mondayAppConfig();
$mondayvariable = $monday['token'];
$boardIdRaw = $monday['boardId'];
$groupId = $monday['groupId'];
$groupName = $monday['groupName'];

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
    $enquiryId = null;
    enquiryLoggerSafe(function () use (&$enquiryId): void {
        $enquiryId = enquiryLoggerFindOrCreateInitial($_POST);
    });

    $columnsQuery = <<<'GQL'
query ($boardId: [ID!]) {
  boards(ids: $boardId) {
    id
    groups {
      id
      title
    }
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
        throw new RuntimeException('Could not read Monday board columns.');
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Board not found in Monday.');
    }

    $board = $boards[0];
    $groups = $board['groups'] ?? [];
    $columns = $board['columns'] ?? [];
    $emailColumnId = null;
    $notesColumnId = null;
    $notesColumnType = null;
    $contactTypeColumnId = null;
    $contactTypeColumnType = null;
    $heardAboutUsColumnId = null;
    $heardAboutUsColumnType = null;
    $leadSourceColumnId = null;
    $leadSourceColumnType = null;
    $targetGroupId = $groupId !== '' ? $groupId : null;

    if ($targetGroupId === null && is_array($groups)) {
        foreach ($groups as $group) {
            $title = strtolower(trim((string)($group['title'] ?? '')));
            $id = trim((string)($group['id'] ?? ''));
            if ($id !== '' && $title === strtolower($groupName)) {
                $targetGroupId = $id;
                break;
            }
        }
    }

    if ($targetGroupId === null && is_array($groups) && count($groups) > 0) {
        $fallbackId = trim((string)($groups[0]['id'] ?? ''));
        if ($fallbackId !== '') {
            $targetGroupId = $fallbackId;
        }
    }

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

        if ($contactTypeColumnId === null && $title === 'contact type') {
            $contactTypeColumnId = $id;
            $contactTypeColumnType = $type;
        }

        if ($heardAboutUsColumnId === null && ($title === 'how did you hear about us?' || $title === 'how did you hear about us')) {
            $heardAboutUsColumnId = $id;
            $heardAboutUsColumnType = $type;
        }

        if ($leadSourceColumnId === null && $title === 'lead source') {
            $leadSourceColumnId = $id;
            $leadSourceColumnType = $type;
        }
    }

    if ($emailColumnId === null || $notesColumnId === null) {
        throw new RuntimeException('Missing required columns: Email and/or Notes.');
    }

    $notesLine = 'I am looking to make an enquiry about: (' . strtoupper($enquiryType) . ')';

    $buildColumnValues = static function (string $notesPayload) use (
        $emailColumnId,
        $email,
        $notesColumnId,
        $notesColumnType,
        $contactTypeColumnId,
        $contactTypeColumnType,
        $heardAboutUsColumnId,
        $heardAboutUsColumnType,
        $leadSourceColumnId,
        $leadSourceColumnType
    ): array {
        $notesValue = $notesPayload;
        if ($notesColumnType === 'long_text') {
            $notesValue = ['text' => $notesPayload];
        }

        $columnValues = [
            $emailColumnId => [
                'email' => $email,
                'text' => $email,
            ],
            $notesColumnId => $notesValue,
        ];

        if ($contactTypeColumnId !== null) {
            $contactTypeValue = 'New Course Enquiry';
            if ($contactTypeColumnType === 'status') {
                $contactTypeValue = ['label' => 'New Course Enquiry'];
            } elseif ($contactTypeColumnType === 'dropdown') {
                $contactTypeValue = ['labels' => ['New Course Enquiry']];
            }
            $columnValues[$contactTypeColumnId] = $contactTypeValue;
        }

        if ($heardAboutUsColumnId !== null) {
            $heardAboutUsValue = 'Form';
            if ($heardAboutUsColumnType === 'status') {
                $heardAboutUsValue = ['label' => 'Form'];
            } elseif ($heardAboutUsColumnType === 'dropdown') {
                $heardAboutUsValue = ['labels' => ['Form']];
            }
            $columnValues[$heardAboutUsColumnId] = $heardAboutUsValue;
        }

        if ($leadSourceColumnId !== null) {
            $leadSourceValue = 'Website Form';
            if ($leadSourceColumnType === 'status') {
                $leadSourceValue = ['label' => 'Website Form'];
            } elseif ($leadSourceColumnType === 'dropdown') {
                $leadSourceValue = ['labels' => ['Website Form']];
            }
            $columnValues[$leadSourceColumnId] = $leadSourceValue;
        }

        return $columnValues;
    };

    $columnValues = $buildColumnValues($notesLine);
    $columnValues = mondayAppendAddressColumn($columns, $columnValues, mondayAddressFromPost($_POST));
    $columnValues = mondayAppendCreatedDate($columns, $columnValues);

    $resumeToken = '';
    if ($enquiryId !== null) {
        enquiryLoggerSafe(function () use ($enquiryId, &$resumeToken): void {
            $resumeToken = enquiryLoggerEnsureResumeToken($enquiryId);
        });
    }

    // Edit / same-session continue: update the Monday item already linked to
    // this enquiry. New enquiry (no linked item): always create a new item,
    // even if the email already exists elsewhere on the board.
    $existingMondayItemId = null;
    if ($enquiryId !== null) {
        enquiryLoggerSafe(function () use ($enquiryId, &$existingMondayItemId): void {
            $existingMondayItemId = enquiryLoggerGetMondayItemId($enquiryId);
        });
    }

    if ($existingMondayItemId !== null && $existingMondayItemId !== '') {
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
            'itemId' => $existingMondayItemId,
            'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
            throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update Monday enquiry item.'));
        }

        $nameMutation = <<<'GQL'
mutation ($boardId: ID!, $itemId: ID!, $itemName: String!) {
  change_simple_column_value(
    board_id: $boardId,
    item_id: $itemId,
    column_id: "name",
    value: $itemName
  ) {
    id
  }
}
GQL;
        // Best-effort item rename; column updates above are the source of truth.
        mondayGraphql($mondayvariable, $apiUrl, $nameMutation, [
            'boardId' => (string)$boardId,
            'itemId' => $existingMondayItemId,
            'itemName' => $name,
        ]);

        enquiryLoggerSafe(function () use ($enquiryId, $existingMondayItemId): void {
            if ($enquiryId !== null) {
                enquiryLoggerMarkMondaySynced($enquiryId, $existingMondayItemId);
                enquiryLoggerEvent(
                    $enquiryId,
                    'monday_item_updated',
                    'Existing Monday enquiry item updated for this edit/resume flow.',
                    ['monday_item_id' => $existingMondayItemId]
                );
            }
        });

        echo json_encode([
            'success' => true,
            'exists' => false,
            'created' => false,
            'updated' => true,
            'enquiryId' => $enquiryId,
            'resumeToken' => $resumeToken,
            'resumeEmailSent' => false,
            'message' => 'Existing enquiry updated in Monday.',
        ]);
        exit;
    }

    $createQueryWithGroup = <<<'GQL'
mutation ($boardId: ID!, $groupId: String!, $itemName: String!, $columnValues: JSON!) {
  create_item(
    board_id: $boardId,
    group_id: $groupId,
    item_name: $itemName,
    column_values: $columnValues
  ) {
    id
  }
}
GQL;

    $createQueryWithoutGroup = <<<'GQL'
mutation ($boardId: ID!, $itemName: String!, $columnValues: JSON!) {
  create_item(
    board_id: $boardId,
    item_name: $itemName,
    column_values: $columnValues
  ) {
    id
  }
}
GQL;

    if ($targetGroupId !== null && $targetGroupId !== '') {
        $createResp = mondayGraphql($mondayvariable, $apiUrl, $createQueryWithGroup, [
            'boardId' => (string)$boardId,
            'groupId' => $targetGroupId,
            'itemName' => $name,
            'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    } else {
        $createResp = mondayGraphql($mondayvariable, $apiUrl, $createQueryWithoutGroup, [
            'boardId' => (string)$boardId,
            'itemName' => $name,
            'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    if ($createResp['status'] >= 400 || !empty($createResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($createResp['body'], 'Could not create item in Monday board.'));
    }

    $createdItemId = trim((string)($createResp['body']['data']['create_item']['id'] ?? ''));
    enquiryLoggerSafe(function () use ($enquiryId, $createdItemId): void {
        if ($enquiryId !== null) {
            enquiryLoggerMarkMondaySynced($enquiryId, $createdItemId !== '' ? $createdItemId : null);
            enquiryLoggerEvent(
                $enquiryId,
                'monday_item_created',
                'New enquiry item created in Monday.',
                $createdItemId !== '' ? ['monday_item_id' => $createdItemId] : null
            );
        }
    });

    echo json_encode([
        'success' => true,
        'exists' => false,
        'created' => true,
        'updated' => false,
        'enquiryId' => $enquiryId,
        'resumeToken' => $resumeToken,
        'resumeEmailSent' => false,
        'message' => 'New enquiry created in Monday.',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
