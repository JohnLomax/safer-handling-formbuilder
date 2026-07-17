<?php
declare(strict_types=1);

header('Content-Type: application/json');

$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
}

require_once __DIR__ . '/monday_helpers.php';
require_once __DIR__ . '/training_matrix_helpers.php';
require_once __DIR__ . '/brevo_email.php';
require_once __DIR__ . '/enquiry_logger.php';

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

/**
 * @param mixed $value
 * @return mixed
 */
function toMondayColumnValue(string $columnType, $value)
{
    $type = strtolower(trim($columnType));
    // Monday sometimes returns compound type ids; match by substring.
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
 * Single-select Monday column (status or dropdown) by human label — never returns a bare string,
 * so dropdown columns never receive raw values like "1".
 *
 * @return array<string, mixed>
 */
function mondaySingleSelectColumnPayload(string $columnType, string $label): array
{
    $type = strtolower(trim($columnType));
    if (strpos($type, 'dropdown') !== false) {
        return ['labels' => [$label]];
    }
    if ($type === 'status' || strpos($type, 'status') !== false) {
        return ['label' => $label];
    }
    // Empty or unknown type: sector on some boards still behaves as dropdown in mutations
    return ['labels' => [$label]];
}

/**
 * Monday "Delivery Preference" label: Format (Course Style).
 */
function mondayDeliveryPreferenceLabel(string $format, string $courseStyle = ''): string
{
    $format = trim($format);
    $courseStyle = trim($courseStyle);
    if ($format === '') {
        return '';
    }
    if ($courseStyle === '') {
        return $format;
    }

    return $format . ' (' . $courseStyle . ')';
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

/**
 * Resolve Monday item for this submission journey only.
 * Uses the item created on Continue for this enquiryId. Never reuses another
 * board item just because the email already exists — creates a new one instead.
 *
 * @param array<int, array<string, mixed>> $columns
 */
function mondayResolveOrCreateItemForSubmission(
    string $token,
    int $boardId,
    string $emailColumnId,
    string $email,
    string $name,
    string $enquiryType,
    array $columns,
    ?int $enquiryId = null
): string {
    require_once __DIR__ . '/enquiry_logger.php';

    if ($enquiryId !== null) {
        $preferredItemId = enquiryLoggerGetMondayItemId($enquiryId);
        if ($preferredItemId !== null) {
            return $preferredItemId;
        }
    }

    $monday = mondayAppConfig();
    $groupId = $monday['groupId'] !== '' ? $monday['groupId'] : null;
    if ($groupId === null) {
        $groupId = mondayFindGroupIdByName($token, $boardId, $monday['groupName'] !== '' ? $monday['groupName'] : 'New Enquiries');
    }

    $itemId = mondayCreateEnquiryItem(
        $token,
        $boardId,
        $emailColumnId,
        $name !== '' ? $name : $email,
        $email,
        $enquiryType !== '' ? $enquiryType : 'training',
        $groupId,
        $columns,
        mondayAddressFromPost($_POST)
    );

    if ($enquiryId !== null) {
        enquiryLoggerSafe(function () use ($enquiryId, $itemId): void {
            enquiryLoggerMarkMondaySynced($enquiryId, $itemId);
            enquiryLoggerEvent(
                $enquiryId,
                'monday_item_created',
                'Monday item created during final submission for this enquiry journey.',
                ['monday_item_id' => $itemId]
            );
        });
    }

    return $itemId;
}

/**
 * @param array<string,string> $trainerData
 */
function updateMondayTrainerSubmission(string $email, array $trainerData): void
{
    $monday = mondayAppConfig();
    $mondayToken = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($mondayToken === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $apiUrl = 'https://api.monday.com/v2';

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

    $columnsResp = mondayGraphql($mondayToken, $apiUrl, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($columnsResp['body'], 'Could not read Monday columns.'));
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }

    $columns = $boards[0]['columns'] ?? [];
    $emailColumnId = null;
    $notesColumnId = null;
    $notesColumnType = '';
    $deliveryPrefColumnId = null;
    $deliveryPrefColumnType = '';
    $specificCourseColumnId = null;
    $specificCourseColumnType = '';
    $attendeesColumnId = null;
    $attendeesColumnType = '';
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
        if ($notesColumnId === null && $title === 'notes') {
            $notesColumnId = $id;
            $notesColumnType = $type;
        }
        if ($deliveryPrefColumnId === null && $title === 'delivery preference') {
            $deliveryPrefColumnId = $id;
            $deliveryPrefColumnType = $type;
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
    $itemId = mondayResolveOrCreateItemForSubmission(
        $mondayToken,
        $boardId,
        $emailColumnId,
        $email,
        trim((string)($_POST['name'] ?? '')),
        trim((string)($_POST['enquiryType'] ?? 'training')),
        is_array($columns) ? $columns : [],
        $enquiryId
    );

    $existingNotes = '';
    if ($notesColumnId !== null) {
        $notesFetchQuery = <<<'GQL'
query ($itemIds: [ID!], $noteIds: [String!]) {
  items(ids: $itemIds) {
    column_values(ids: $noteIds) {
      id
      text
    }
  }
}
GQL;
        $notesFetchResp = mondayGraphql($mondayToken, $apiUrl, $notesFetchQuery, [
            'itemIds' => [$itemId],
            'noteIds' => [$notesColumnId],
        ]);
        if ($notesFetchResp['status'] < 400 && empty($notesFetchResp['body']['errors'])) {
            $cvItems = $notesFetchResp['body']['data']['items'] ?? [];
            if (is_array($cvItems) && count($cvItems) > 0) {
                $cv = $cvItems[0]['column_values'] ?? [];
                if (is_array($cv) && count($cv) > 0) {
                    $existingNotes = trim((string)($cv[0]['text'] ?? ''));
                }
            }
        }
    }

    $baseNotes = preg_replace('/^.*online only.*$/im', '', $existingNotes ?? '');
    $baseNotes = preg_replace('/^.*it\'?s for one person.*$/im', '', (string)$baseNotes);
    $baseNotes = trim((string)$baseNotes);

    $trainerNotes = [
        'I want to become a trainer.',
        'Specific Course: ' . $trainerData['specificCourse'],
    ];
    if ($trainerData['bookedThroughCompany'] === 'Yes') {
        $trainerNotes[] = 'Booked through a company: Yes';
    }
    if (($trainerData['trainersRequired'] ?? '') !== '') {
        $trainerNotes[] = 'Number of trainers required: ' . trim((string)$trainerData['trainersRequired']);
    }
    if (($trainerData['extraNotes'] ?? '') !== '') {
        $trainerNotes[] = 'Any additional notes: ' . trim((string)$trainerData['extraNotes']);
    }

    $notesText = trim($baseNotes . "\n" . implode("\n", $trainerNotes));

    $columnValues = [];
    if ($notesColumnId !== null) {
        $columnValues[$notesColumnId] = toMondayColumnValue($notesColumnType, $notesText);
    }
    if ($deliveryPrefColumnId !== null) {
        $columnValues[$deliveryPrefColumnId] = toMondayColumnValue($deliveryPrefColumnType, 'Face to face (Full-day)');
    }
    if ($specificCourseColumnId !== null) {
        $columnValues[$specificCourseColumnId] = toMondayColumnValue(
            $specificCourseColumnType,
            mondayResolveSpecificCourseLabel($trainerData['specificCourse'])
        );
    }
    // Form "Attendees" → Monday "Number of Attendees" (or "Attendees" if that is the only column).
    if ($attendeesColumnId !== null) {
        $columnValues[$attendeesColumnId] = toMondayColumnValue($attendeesColumnType, $trainerData['attendees']);
    }
    // Form total (hidden quoteValue) → Monday "Quote Value".
    if ($quoteValueColumnId !== null && $trainerData['quoteValue'] !== '') {
        $columnValues[$quoteValueColumnId] = toMondayColumnValue($quoteValueColumnType, $trainerData['quoteValue']);
    }

    $columnValues = mondayAppendAddressColumn($columns, $columnValues, mondayAddressFromPost($_POST));

    $columnValues = mondayAppendCreatedDate($columns, $columnValues);

    if (count($columnValues) === 0) {
        return;
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

    $updateResp = mondayGraphql($mondayToken, $apiUrl, $updateQuery, [
        'boardId' => (string)$boardId,
        'itemId' => $itemId,
        'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update Monday trainer details.'));
    }
}

/**
 * Monday `date` column JSON from a preferred date value (YYYY-MM-DD, optionally with time).
 * Preferred date is date-only — no time is written to Monday.
 *
 * @return array<string, string>|null Null when empty/unparseable (caller may clear the column).
 */
function mondayDateColumnValueFromDatetimeLocal(string $datetimeLocal): ?array
{
    $datetimeLocal = trim($datetimeLocal);
    if ($datetimeLocal === '') {
        return null;
    }
    $datetimeLocal = str_replace(' ', 'T', $datetimeLocal);
    $dt = \DateTimeImmutable::createFromFormat('Y-m-d', substr($datetimeLocal, 0, 10))
        ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $datetimeLocal)
        ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $datetimeLocal);
    if ($dt === false) {
        return null;
    }

    return ['date' => $dt->format('Y-m-d')];
}

/**
 * Remove previous preferred-date note lines before writing a fresh one.
 */
function mondayStripPreferredDateNoteLines(string $notes): string
{
    $notes = trim($notes);
    if ($notes === '') {
        return '';
    }

    $lines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
    $kept = [];
    foreach ($lines as $line) {
        if (preg_match('/^preferred date(\s*\/\s*time)?\s*:/i', trim((string)$line))) {
            continue;
        }
        $kept[] = $line;
    }

    return trim(implode("\n", $kept));
}

/**
 * @param array<string,string> $orgData
 */
function updateMondayOrganisationSubmission(string $email, array $orgData): void
{
    $monday = mondayAppConfig();
    $mondayToken = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($mondayToken === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $apiUrl = 'https://api.monday.com/v2';

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

    $columnsResp = mondayGraphql($mondayToken, $apiUrl, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($columnsResp['body'], 'Could not read Monday columns.'));
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }

    $columns = $boards[0]['columns'] ?? [];
    $emailColumnId = null;
    $sectorColumnId = null;
    $sectorColumnType = '';
    $specificCourseColumnId = null;
    $specificCourseColumnType = '';
    $attendeesColumnId = null;
    $attendeesColumnType = '';
    $deliveryPreferenceColumnId = null;
    $deliveryPreferenceColumnType = '';
    $preferredDateColumnId = null;
    $preferredDateColumnType = '';
    $quoteValueColumnId = null;
    $quoteValueColumnType = '';
    $companyColumnId = null;
    $companyColumnType = '';
    $notesColumnId = null;
    $notesColumnType = '';

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
        if ($sectorColumnId === null && $title === 'sector') {
            $sectorColumnId = $id;
            $sectorColumnType = $type;
        }
        if ($specificCourseColumnId === null && $title === 'specific course') {
            $specificCourseColumnId = $id;
            $specificCourseColumnType = $type;
        }
        if ($deliveryPreferenceColumnId === null && $title === 'delivery preference') {
            $deliveryPreferenceColumnId = $id;
            $deliveryPreferenceColumnType = $type;
        }
        if ($preferredDateColumnId === null && in_array($title, [
            'preferred date',
            'preferred date/time',
            'preferred date & time',
            'preferred datetime',
            'preferred day',
            'preferred day(s)',
        ], true)) {
            $preferredDateColumnId = $id;
            $preferredDateColumnType = $type;
        }
        if ($quoteValueColumnId === null && $title === 'quote value') {
            $quoteValueColumnId = $id;
            $quoteValueColumnType = $type;
        }
        if ($companyColumnId === null && in_array($title, [
            'company/organisation',
            'company / organisation',
            'company/organization',
            'company / organization',
            'company',
            'organisation',
            'organization',
            'company name',
            'organisation name',
            'organization name',
        ], true)) {
            $companyColumnId = $id;
            $companyColumnType = $type;
        }
        if ($notesColumnId === null && $title === 'notes') {
            $notesColumnId = $id;
            $notesColumnType = $type;
        }
    }

    [$attendeesColumnId, $attendeesColumnType] = mondayAttendeesColumnFromBoard($columns);

    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on Monday board.');
    }

    $enquiryId = enquiryLoggerResolveAuthenticatedEnquiryId($_POST);
    $itemId = mondayResolveOrCreateItemForSubmission(
        $mondayToken,
        $boardId,
        $emailColumnId,
        $email,
        trim((string)($_POST['name'] ?? '')),
        trim((string)($_POST['enquiryType'] ?? 'training')),
        is_array($columns) ? $columns : [],
        $enquiryId
    );

    require_once __DIR__ . '/enquiry_logger.php';
    $preferred = enquiryPreferredDateFromPost([
        'preferredDateTime' => $orgData['preferredDateTime'] ?? '',
        'dateNotSure' => (($orgData['dateNotSure'] ?? '') === '1') ? 'on' : null,
    ]);
    $preferredDateTime = $preferred['preferredDateTime'];
    $dateNotSure = $preferred['dateNotSure'];

    $noteLines = [];
    if (($orgData['organisationCompany'] ?? '') !== '') {
        $noteLines[] = 'Company/Organisation: ' . trim((string)$orgData['organisationCompany']);
    }
    if (($orgData['courseStyle'] ?? '') !== '') {
        $noteLines[] = 'Course Style: ' . trim($orgData['courseStyle']);
    }
    if ($dateNotSure) {
        $noteLines[] = 'Preferred date: not sure yet';
    } elseif ($preferredDateTime !== '') {
        require_once __DIR__ . '/brevo_email.php';
        $formattedPreferred = formatPreferredTrainingDate($preferredDateTime, false);
        $noteLines[] = 'Preferred date: ' . ($formattedPreferred !== '' ? $formattedPreferred : $preferredDateTime);
    }
    if (($orgData['trainersRequired'] ?? '') !== '') {
        $noteLines[] = 'Number of trainers required: ' . trim((string)$orgData['trainersRequired']);
    }
    if (($orgData['extraNotes'] ?? '') !== '') {
        $noteLines[] = 'Any additional notes: ' . trim($orgData['extraNotes']);
    }

    $existingNotes = '';
    if ($notesColumnId !== null && count($noteLines) > 0) {
        $notesFetchQuery = <<<'GQL'
query ($itemIds: [ID!], $noteIds: [String!]) {
  items(ids: $itemIds) {
    column_values(ids: $noteIds) {
      id
      text
    }
  }
}
GQL;
        $notesFetchResp = mondayGraphql($mondayToken, $apiUrl, $notesFetchQuery, [
            'itemIds' => [$itemId],
            'noteIds' => [$notesColumnId],
        ]);
        if ($notesFetchResp['status'] < 400 && empty($notesFetchResp['body']['errors'])) {
            $cvItems = $notesFetchResp['body']['data']['items'] ?? [];
            if (is_array($cvItems) && count($cvItems) > 0) {
                $cv = $cvItems[0]['column_values'] ?? [];
                if (is_array($cv) && count($cv) > 0) {
                    $existingNotes = trim((string)($cv[0]['text'] ?? ''));
                }
            }
        }
    }

    $columnValues = [];
    if ($sectorColumnId !== null && ($orgData['sector'] ?? '') !== '') {
        $sectorLabel = trim((string)$orgData['sector']);
        if ($sectorLabel !== '') {
            $columnValues[$sectorColumnId] = mondaySingleSelectColumnPayload($sectorColumnType, $sectorLabel);
        }
    }
    if ($specificCourseColumnId !== null && ($orgData['specificCourse'] ?? '') !== '') {
        $columnValues[$specificCourseColumnId] = toMondayColumnValue(
            $specificCourseColumnType,
            mondayResolveSpecificCourseLabel((string)$orgData['specificCourse'])
        );
    }
    if ($attendeesColumnId !== null && ($orgData['attendees'] ?? '') !== '') {
        $columnValues[$attendeesColumnId] = toMondayColumnValue($attendeesColumnType, $orgData['attendees']);
    }
    if ($companyColumnId !== null && ($orgData['organisationCompany'] ?? '') !== '') {
        $columnValues[$companyColumnId] = toMondayColumnValue(
            $companyColumnType,
            trim((string)$orgData['organisationCompany'])
        );
    }
    if ($deliveryPreferenceColumnId !== null && ($orgData['deliveryPreference'] ?? '') !== '') {
        $deliveryLabel = mondayDeliveryPreferenceLabel(
            (string)$orgData['deliveryPreference'],
            (string)($orgData['courseStyle'] ?? '')
        );
        if ($deliveryLabel !== '') {
            $columnValues[$deliveryPreferenceColumnId] = toMondayColumnValue($deliveryPreferenceColumnType, $deliveryLabel);
        }
    }
    if ($quoteValueColumnId !== null && ($orgData['quoteValue'] ?? '') !== '') {
        $columnValues[$quoteValueColumnId] = toMondayColumnValue($quoteValueColumnType, $orgData['quoteValue']);
    }

    if ($preferredDateColumnId !== null) {
        if ($dateNotSure || $preferredDateTime === '') {
            // Clear stale Preferred Date when the customer is not sure / clears the field.
            $columnValues[$preferredDateColumnId] = null;
        } elseif ($preferredDateColumnType === 'date') {
            $datePayload = mondayDateColumnValueFromDatetimeLocal($preferredDateTime);
            if (is_array($datePayload) && ($datePayload['date'] ?? '') !== '') {
                $columnValues[$preferredDateColumnId] = $datePayload;
            }
        } else {
            $columnValues[$preferredDateColumnId] = toMondayColumnValue($preferredDateColumnType, $preferredDateTime);
        }
    }

    if ($notesColumnId !== null && count($noteLines) > 0) {
        $baseNotes = mondayStripPreferredDateNoteLines($existingNotes);
        $appendBlock = implode("\n", $noteLines);
        $notesText = $baseNotes === '' ? $appendBlock : $baseNotes . "\n" . $appendBlock;
        $columnValues[$notesColumnId] = toMondayColumnValue($notesColumnType, $notesText);
    }

    $columnValues = mondayAppendAddressColumn($columns, $columnValues, mondayAddressFromPost($orgData));

    $columnValues = mondayAppendCreatedDate($columns, $columnValues);

    if (count($columnValues) === 0) {
        return;
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

    $updateResp = mondayGraphql($mondayToken, $apiUrl, $updateQuery, [
        'boardId' => (string)$boardId,
        'itemId' => $itemId,
        'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
        throw new RuntimeException(mondayErrorMessage($updateResp['body'], 'Could not update Monday organisation details.'));
    }
}

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
        'message' => 'Please provide a valid email address.',
    ]);
    exit;
}

$requiredPdoDriver = appDatabaseDriver() === 'mysql' ? 'mysql' : 'sqlite';
if (!class_exists('PDO') || !in_array($requiredPdoDriver, PDO::getAvailableDrivers(), true)) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'Enquiry storage is unavailable: the PHP PDO ' . $requiredPdoDriver . ' driver is not enabled.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * @param array<string, mixed> $post
 */
function persistEnquiryRecord(array $post, string $name, string $email, string $enquiryType): int
{
    // Edit flow: enquiryId + resumeToken must match an existing enquiry.
    // New flow: no valid token → always create a fresh enquiry row.
    $enquiryId = enquiryLoggerResolveAuthenticatedEnquiryId($post);
    $reusingExisting = $enquiryId !== null;
    $alreadyHadQuote = $reusingExisting && enquiryLoggerQuoteEmailAlreadySent($enquiryId);
    $alreadySubmitted = $reusingExisting && enquiryLoggerHasEvent($enquiryId, 'form_submitted');

    if (!$reusingExisting) {
        $enquiryId = enquiryLoggerCreateInitial($post);
    }

    enquiryLoggerUpdateFromPost($enquiryId, $post, 'submitted');
    enquiryLoggerMarkSubmitted($enquiryId);

    if ($alreadySubmitted || $alreadyHadQuote) {
        enquiryLoggerEvent(
            $enquiryId,
            'details_updated',
            'Customer updated their enquiry details via the Edit Enquiry link.',
            [
                'quote_already_sent' => $alreadyHadQuote,
                'source' => 'edit_enquiry_form',
            ]
        );
    } else {
        enquiryLoggerEvent($enquiryId, 'form_submitted', 'Enquiry form submitted by the customer.');
    }
    enquiryLoggerEvent($enquiryId, 'storage_saved', 'Enquiry saved to the admin database.');

    return $enquiryId;
}

try {
    $enquiryId = null;
    $storageWarning = '';
    try {
        $enquiryId = persistEnquiryRecord($_POST, $name, $email, $enquiryType);
    } catch (Throwable $storageError) {
        $resolvedId = enquiryLoggerPostId($_POST) ?? enquiryLoggerResolveId(null, $email);
        enquiryLoggerSafe(function () use ($resolvedId, $storageError): void {
            if ($resolvedId !== null) {
                enquiryLoggerEvent(
                    $resolvedId,
                    'storage_failed',
                    'Local enquiry log could not be saved.',
                    ['error' => $storageError->getMessage()]
                );
            }
        });
        $storageWarning = 'Local enquiry log could not be saved (' . $storageError->getMessage() . '). Your Monday enquiry was still processed if applicable.';
    }

    $audienceType = trim((string)($_POST['audienceType'] ?? ''));
    $personalGoal = trim((string)($_POST['personalGoal'] ?? ''));
    $isTrainerFlow = $audienceType === 'me' && $personalGoal === 'becomeTrainer';

    if ($isTrainerFlow) {
        $trainerCourse = trim((string)($_POST['trainerCourseSelect'] ?? ''));
        $trainerAttendees = trim((string)($_POST['trainerAttendees'] ?? ''));
        $trainersRequired = trim((string)($_POST['trainersRequired'] ?? ''));
        $quoteValue = trim((string)($_POST['quoteValue'] ?? ''));
        if ($trainersRequired === '' && $trainerAttendees !== '' && is_numeric($trainerAttendees)) {
            $trainersRequired = (string)max(1, (int)ceil((int)$trainerAttendees / 20));
        }

        if ($trainerCourse === '' || $trainerAttendees === '' || $trainersRequired === '') {
            throw new RuntimeException('Missing trainer details required for Monday update.');
        }

        updateMondayTrainerSubmission($email, [
            'specificCourse' => $trainerCourse,
            'attendees' => $trainerAttendees,
            'trainersRequired' => $trainersRequired,
            'quoteValue' => $quoteValue,
            'bookedThroughCompany' => isset($_POST['bookingViaCompany']) ? 'Yes' : 'No',
            'extraNotes' => trim((string)($_POST['extraNotes'] ?? '')),
        ]);
        if ($enquiryId !== null) {
            enquiryLoggerSafe(function () use ($enquiryId): void {
                enquiryLoggerMarkMondaySynced($enquiryId);
                enquiryLoggerEvent($enquiryId, 'monday_submission_updated', 'Trainer enquiry details synced to Monday.');
            });
        }
    }

    $isOrganisationTrainingFlow = $enquiryType === 'training' && $audienceType === 'organisation';
    $resolvedOrganisation = null;
    if ($isOrganisationTrainingFlow) {
        $resolvedOrganisation = trainingMatrixResolveOrganisationSubmission($_POST);
        updateMondayOrganisationSubmission($email, array_merge(
            $resolvedOrganisation,
            mondayAddressFromPost($_POST)
        ));
        if ($enquiryId !== null) {
            enquiryLoggerSafe(function () use ($enquiryId): void {
                enquiryLoggerMarkMondaySynced($enquiryId);
                enquiryLoggerEvent($enquiryId, 'monday_submission_updated', 'Organisation training details synced to Monday.');
            });
        }
    }

    $emailWarning = '';
    $quoteEmailData = null;
    require_once __DIR__ . '/xero.php';

    $isQuoteFlow = $isTrainerFlow || $isOrganisationTrainingFlow;
    $quoteAlreadySent = $enquiryId !== null && enquiryLoggerQuoteEmailAlreadySent($enquiryId);
    $shouldSendQuoteEmail = (brevoEmailEnabled() || xeroEnabled())
        && $isQuoteFlow
        && !$quoteAlreadySent;

    // Edit Enquiry Email is for returning to the enquiry form. Skip it when a
    // quote email is going out — that email’s Accept Quote button is the CTA
    // and must open /booking, not /enquiry.
    if ($enquiryId !== null && !$shouldSendQuoteEmail) {
        enquiryLoggerSafe(function () use ($enquiryId, $name, $email, $enquiryType): void {
            try {
                maybeSendResumeEnquiryEmail($enquiryId, $name, $email, $enquiryType);
            } catch (Throwable $emailError) {
                enquiryLoggerEvent(
                    $enquiryId,
                    'resume_email_failed',
                    'Edit Enquiry Email could not be sent.',
                    ['error' => $emailError->getMessage()]
                );
            }
        });
    }
    if ($isQuoteFlow && !$shouldSendQuoteEmail && $enquiryId !== null) {
        enquiryLoggerSafe(function () use ($enquiryId, $quoteAlreadySent): void {
            if ($quoteAlreadySent) {
                enquiryLoggerEvent(
                    $enquiryId,
                    'quote_email_skipped',
                    'Enquiry details updated without creating a new quote — a quote was already sent for this enquiry.',
                    ['quote_already_sent' => true]
                );

                return;
            }

            if (!brevoEmailEnabled() && !xeroEnabled()) {
                enquiryLoggerEvent(
                    $enquiryId,
                    'quote_email_skipped',
                    'Quote email was not sent because Xero and Brevo quote sending are both disabled.',
                    ['xero_enabled' => false, 'brevo_email_enabled' => false]
                );
            }
        });
    }
    if ($shouldSendQuoteEmail) {
        try {
            $quoteEmailData = $resolvedOrganisation !== null
                ? buildQuoteEmailDataFromResolvedOrganisation(
                    array_merge($resolvedOrganisation, mondayAddressFromPost($_POST)),
                    $name,
                    $email
                )
                : buildQuoteEmailDataFromSubmission($_POST, $name, $email);
            if ($enquiryId !== null) {
                // Accept Quote must open /booking (venue + terms), never /enquiry edit.
                $quoteEmailData['enquiryId'] = $enquiryId;
                $quoteEmailData['resumeToken'] = enquiryLoggerEnsureResumeToken($enquiryId);
                $quoteEmailData['email'] = $email;
            }
            $quoteSendResult = sendQuoteToClient($email, $name, $quoteEmailData);
            if ($enquiryId !== null) {
                enquiryLoggerSafe(function () use ($enquiryId, $quoteSendResult, $name, $email): void {
                    enquiryLoggerMarkQuoteEmailSent($enquiryId);
                    if (($quoteSendResult['channel'] ?? '') === 'xero') {
                        $quote = $quoteSendResult['xero']['quote'] ?? [];
                        $contact = $quoteSendResult['xero']['contact'] ?? [];
                        enquiryLoggerMarkXeroQuoteSent(
                            $enquiryId,
                            (string)($contact['ContactID'] ?? ''),
                            (string)($quote['QuoteID'] ?? ''),
                            (string)($quote['QuoteNumber'] ?? '')
                        );
                        enquiryLoggerEvent(
                            $enquiryId,
                            'quote_email_sent',
                            'Xero quote created and emailed to the customer via Brevo (PDF attached).',
                            [
                                'channel' => 'xero',
                                'xero_quote_id' => $quote['QuoteID'] ?? null,
                                'xero_quote_number' => $quote['QuoteNumber'] ?? null,
                                'xero_contact_id' => $contact['ContactID'] ?? null,
                                'subtotal' => $quote['SubTotal'] ?? null,
                                'vat' => $quote['TotalTax'] ?? null,
                                'total' => $quote['Total'] ?? null,
                                'status' => 'quote_sent',
                            ]
                        );
                        require_once __DIR__ . '/monday_helpers.php';
                        mondayMoveEnquiryToQuoteSentAfterXeroQuote($enquiryId);

                        try {
                            maybeSendBookingDetailsEmail($enquiryId, $name, $email);
                        } catch (Throwable $bookingEmailError) {
                            enquiryLoggerEvent(
                                $enquiryId,
                                'booking_email_failed',
                                'Booking details / terms acceptance email could not be sent.',
                                ['error' => $bookingEmailError->getMessage()]
                            );
                        }
                    } else {
                        // Auto Brevo quote email = Contacted.
                        enquiryLoggerMarkContacted($enquiryId);
                        enquiryLoggerEvent(
                            $enquiryId,
                            'quote_email_sent',
                            'Automatic quote confirmation email sent via Brevo.',
                            ['channel' => 'brevo', 'status' => 'contacted']
                        );
                    }
                });
            }
        } catch (Throwable $emailError) {
            if ($enquiryId !== null) {
                enquiryLoggerSafe(function () use ($enquiryId, $emailError): void {
                    enquiryLoggerEvent(
                        $enquiryId,
                        'quote_email_failed',
                        'Automatic quote email could not be sent.',
                        ['error' => $emailError->getMessage()]
                    );
                });
            }
            $emailWarning = 'Your enquiry was saved, but we could not send the quote email (' . $emailError->getMessage() . '). Our team will still be in touch.';
        }
    }

    $shouldSendLeadNotification = brevoLeadNotificationEnabled()
        && ($isTrainerFlow || $isOrganisationTrainingFlow)
        && ($enquiryId === null || !enquiryLoggerLeadNotificationAlreadySent($enquiryId));
    if ($shouldSendLeadNotification) {
        try {
            if ($quoteEmailData === null) {
                $quoteEmailData = $resolvedOrganisation !== null
                    ? buildQuoteEmailDataFromResolvedOrganisation($resolvedOrganisation, $name, $email)
                    : buildQuoteEmailDataFromSubmission($_POST, $name, $email);
            }

            $leadEmailData = buildNewLeadEmailData(
                $name,
                $email,
                $enquiryType,
                $enquiryId,
                $isTrainerFlow,
                $quoteEmailData,
                $_POST
            );
            sendNewLeadNotificationViaBrevo($leadEmailData);
            if ($enquiryId !== null) {
                $officeEmail = brevoOfficeEmail();
                enquiryLoggerSafe(function () use ($enquiryId, $officeEmail): void {
                    enquiryLoggerEvent(
                        $enquiryId,
                        'lead_notification_sent',
                        'New lead notification email sent to ' . $officeEmail . ' via Brevo.',
                        ['office_email' => $officeEmail]
                    );
                });
            }
        } catch (Throwable $leadEmailError) {
            if ($enquiryId !== null) {
                $officeEmail = brevoOfficeEmail();
                enquiryLoggerSafe(function () use ($enquiryId, $leadEmailError, $officeEmail): void {
                    enquiryLoggerEvent(
                        $enquiryId,
                        'lead_notification_failed',
                        'New lead notification email to ' . $officeEmail . ' could not be sent.',
                        [
                            'office_email' => $officeEmail,
                            'error' => $leadEmailError->getMessage(),
                        ]
                    );
                });
            }
        }
    }

    $successPayload = [
        'success' => true,
        'message' => 'Enquiry submitted successfully.',
        'enquiryId' => $enquiryId,
    ];
    if ($storageWarning !== '') {
        $successPayload['storageWarning'] = $storageWarning;
    }
    if ($emailWarning !== '') {
        $successPayload['emailWarning'] = $emailWarning;
    }
    echo json_encode($successPayload);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
