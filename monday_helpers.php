<?php
declare(strict_types=1);

require_once __DIR__ . '/training_matrix_helpers.php';

/**
 * Monday `date` column JSON for the current moment (server default timezone).
 *
 * @return array<string, string>
 */
function mondayDateColumnValueNow(): array
{
    $dt = new \DateTimeImmutable('now');

    return ['date' => $dt->format('Y-m-d'), 'time' => $dt->format('H:i:s')];
}

/**
 * Adds "Created Date" when the board has a date column with that title.
 *
 * @param array<int, array<string, mixed>> $columns
 * @param array<string, mixed> $columnValues
 * @return array<string, mixed>
 */
function mondayAppendCreatedDate(array $columns, array $columnValues): array
{
    $out = $columnValues;
    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id !== '' && $title === 'created date' && $type === 'date') {
            $out[$id] = mondayDateColumnValueNow();
            break;
        }
    }

    return $out;
}

/**
 * Normalised address parts from POST (organisation venue / Monday "Address").
 *
 * @param array<string, mixed> $post
 * @return array<string, string>
 */
function mondayAddressFromPost(array $post): array
{
    $org = [
        'addressLine1' => trim((string)($post['addressLine1'] ?? '')),
        'addressLine2' => trim((string)($post['addressLine2'] ?? '')),
        'addressTown' => trim((string)($post['addressTown'] ?? '')),
        'addressPostcode' => trim((string)($post['addressPostcode'] ?? '')),
        'addressLat' => trim((string)($post['addressLat'] ?? '')),
        'addressLng' => trim((string)($post['addressLng'] ?? '')),
    ];
    if (mondayAddressHasContent($org)) {
        return $org;
    }

    return [
        'addressLine1' => trim((string)($post['tmAddressLine1'] ?? '')),
        'addressLine2' => trim((string)($post['tmAddressLine2'] ?? '')),
        'addressTown' => trim((string)($post['tmAddressTown'] ?? '')),
        'addressPostcode' => trim((string)($post['tmAddressPostcode'] ?? '')),
        'addressLat' => trim((string)($post['tmAddressLat'] ?? '')),
        'addressLng' => trim((string)($post['tmAddressLng'] ?? '')),
    ];
}

/**
 * Organisation course matrix rows for Monday.com course resolution.
 *
 * @return array<int, array<string, string>>
 */
function mondayOrganisationCourseMatrix(): array
{
    $rows = [];
    foreach (trainingMatrixAll() as $item) {
        $rows[] = [
            'course' => (string)($item['course'] ?? ''),
            'courseValue' => (string)($item['courseValue'] ?? ''),
            'format' => (string)($item['format'] ?? ''),
            'subOption' => (string)($item['subOption'] ?? ''),
        ];
    }

    return $rows;
}

/**
 * True when value is a course dropdown label (not a hidden Specific Course code).
 */
function mondayIsOrganisationCourseDisplayName(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    foreach (mondayOrganisationCourseMatrix() as $row) {
        if ($value === $row['course']) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve hidden course code from organisation form fields (sector + course + format + style).
 */
function mondayResolveSpecificCourseFromForm(
    string $sector,
    string $orgCourse,
    string $format,
    string $subOption
): string {
    $row = trainingMatrixFindRow($sector, $orgCourse, $format, $subOption);
    if ($row === null) {
        return '';
    }

    return mondayResolveSpecificCourseLabel((string)$row['courseValue']);
}

/**
 * Best Specific Course label for Monday from organisation POST fields.
 */
function mondayResolveOrganisationSpecificCourse(
    string $sector,
    string $orgCourse,
    string $format,
    string $subOption
): string {
    return mondayResolveSpecificCourseFromForm($sector, $orgCourse, $format, $subOption);
}

/**
 * Maps form course codes to active Monday "Specific Course" dropdown labels.
 * Labels must match the board column exactly (see change_column_values docs).
 */
function mondayResolveSpecificCourseLabel(string $value): string
{
    return trim($value);
}

function mondayAddressHasContent(array $address): bool
{
    foreach (['addressLine1', 'addressLine2', 'addressTown', 'addressPostcode'] as $k) {
        if (($address[$k] ?? '') !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Value for Monday column_values keyed by column id (location vs text).
 *
 * @param array<string, string> $address
 * @return mixed|null
 */
function mondayAddressPayloadForColumnType(string $columnType, array $address)
{
    $parts = array_filter([
        $address['addressLine1'] ?? '',
        $address['addressLine2'] ?? '',
        $address['addressTown'] ?? '',
        $address['addressPostcode'] ?? '',
    ], static function ($s) {
        return $s !== '';
    });
    if (count($parts) === 0) {
        return null;
    }

    $singleLine = implode(', ', $parts);
    $multiLine = implode("\n", $parts);
    $type = strtolower(trim($columnType));

    if ($type === 'location') {
        $lat = $address['addressLat'] ?? '';
        $lng = $address['addressLng'] ?? '';
        if ($lat === '' || $lng === '') {
            return null;
        }

        return [
            'address' => $singleLine,
            'lat' => (string)$lat,
            'lng' => (string)$lng,
        ];
    }
    if ($type === 'long_text') {
        return ['text' => $multiLine];
    }

    return $singleLine;
}

/**
 * Sets Monday column titled "Address" when form address fields are present.
 *
 * @param array<int, array<string, mixed>> $columns
 * @param array<string, mixed> $columnValues
 * @param array<string, string> $address
 * @return array<string, mixed>
 */
function mondayAppendAddressColumn(array $columns, array $columnValues, array $address): array
{
    if (!mondayAddressHasContent($address)) {
        return $columnValues;
    }

    $out = $columnValues;
    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id === '' || $title !== 'address') {
            continue;
        }
        $payload = mondayAddressPayloadForColumnType($type, $address);
        if ($payload !== null) {
            $out[$id] = $payload;
        }
        break;
    }

    return $out;
}

/**
 * @return array{status:int,body:array<string,mixed>}
 */
function mondayGraphqlRequest(string $token, string $query, array $variables = [], string $apiUrl = 'https://api.monday.com/v2'): array
{
    $ch = curl_init($apiUrl);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: ' . $token,
            'API-Version: 2024-10',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'query' => $query,
            'variables' => $variables,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
        $decoded = [];
    }

    return ['status' => $status, 'body' => $decoded];
}

function mondayFindGroupIdByName(string $token, int $boardId, string $groupName): ?string
{
    $wanted = strtolower(trim($groupName));
    if ($wanted === '') {
        return null;
    }

    $query = <<<'GQL'
query ($boardId: [ID!]) {
  boards(ids: $boardId) {
    groups {
      id
      title
    }
  }
}
GQL;

    $resp = mondayGraphqlRequest($token, $query, [
        'boardId' => [(string)$boardId],
    ]);

    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        $message = '';
        if (!empty($resp['body']['errors'][0]['message'])) {
            $message = (string)$resp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not load Monday board groups.');
    }

    $groups = $resp['body']['data']['boards'][0]['groups'] ?? [];
    if (!is_array($groups)) {
        return null;
    }

    foreach ($groups as $group) {
        $title = strtolower(trim((string)($group['title'] ?? '')));
        $id = trim((string)($group['id'] ?? ''));
        if ($id !== '' && $title === $wanted) {
            return $id;
        }
    }

    return null;
}

/**
 * Normalise emails for comparison.
 */
function mondayNormaliseEmail(string $email): string
{
    return strtolower(trim($email));
}

/**
 * Extract email text from a Monday email column value/text pair.
 */
function mondayEmailFromColumnValue(?string $text, ?string $value): string
{
    $text = trim((string)$text);
    if ($text !== '' && filter_var($text, FILTER_VALIDATE_EMAIL)) {
        return mondayNormaliseEmail($text);
    }

    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        $candidate = trim((string)($decoded['email'] ?? $decoded['text'] ?? ''));
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return mondayNormaliseEmail($candidate);
        }
    }

    if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
        return mondayNormaliseEmail($value);
    }

    return '';
}

/**
 * Read the email stored on a Monday item.
 */
function mondayItemEmail(string $token, string $itemId, string $emailColumnId): string
{
    $itemId = trim($itemId);
    $emailColumnId = trim($emailColumnId);
    if ($itemId === '' || $emailColumnId === '') {
        return '';
    }

    $query = <<<'GQL'
query ($itemIds: [ID!], $columnIds: [String!]) {
  items(ids: $itemIds) {
    id
    column_values(ids: $columnIds) {
      id
      text
      value
    }
  }
}
GQL;

    $resp = mondayGraphqlRequest($token, $query, [
        'itemIds' => [$itemId],
        'columnIds' => [$emailColumnId],
    ]);
    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        return '';
    }

    $items = $resp['body']['data']['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        return '';
    }

    $columns = $items[0]['column_values'] ?? [];
    if (!is_array($columns) || count($columns) === 0) {
        return '';
    }

    return mondayEmailFromColumnValue(
        isset($columns[0]['text']) ? (string)$columns[0]['text'] : null,
        isset($columns[0]['value']) ? (string)$columns[0]['value'] : null
    );
}

/**
 * Find a Monday item by email and require an exact email-column match.
 * Monday's column search can occasionally return unrelated items.
 */
function mondayFindItemIdByEmailExact(
    string $token,
    int $boardId,
    string $emailColumnId,
    string $email,
    int $limit = 10
): ?string {
    $email = mondayNormaliseEmail($email);
    $emailColumnId = trim($emailColumnId);
    if ($email === '' || $emailColumnId === '' || $boardId <= 0) {
        return null;
    }

    $query = <<<'GQL'
query ($boardId: ID!, $columnId: String!, $columnValues: [String!]!, $limit: Int!, $emailColumnIds: [String!]) {
  items_page_by_column_values(
    board_id: $boardId,
    columns: [{column_id: $columnId, column_values: $columnValues}],
    limit: $limit
  ) {
    items {
      id
      column_values(ids: $emailColumnIds) {
        id
        text
        value
      }
    }
  }
}
GQL;

    $resp = mondayGraphqlRequest($token, $query, [
        'boardId' => (string)$boardId,
        'columnId' => $emailColumnId,
        'columnValues' => [$email],
        'limit' => max(1, $limit),
        'emailColumnIds' => [$emailColumnId],
    ]);
    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        $message = '';
        if (!empty($resp['body']['errors'][0]['message'])) {
            $message = (string)$resp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not search Monday items by email.');
    }

    $items = $resp['body']['data']['items_page_by_column_values']['items'] ?? [];
    if (!is_array($items)) {
        return null;
    }

    // Prefer the newest matching item when the same email appears more than once.
    $bestId = null;
    $bestNumeric = null;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemId = trim((string)($item['id'] ?? ''));
        if ($itemId === '') {
            continue;
        }
        $columns = $item['column_values'] ?? [];
        $itemEmail = '';
        if (is_array($columns) && count($columns) > 0) {
            $itemEmail = mondayEmailFromColumnValue(
                isset($columns[0]['text']) ? (string)$columns[0]['text'] : null,
                isset($columns[0]['value']) ? (string)$columns[0]['value'] : null
            );
        }
        if ($itemEmail !== $email) {
            continue;
        }
        $numeric = ctype_digit($itemId) ? (int)$itemId : null;
        if ($bestId === null) {
            $bestId = $itemId;
            $bestNumeric = $numeric;
            continue;
        }
        if ($numeric !== null && ($bestNumeric === null || $numeric > $bestNumeric)) {
            $bestId = $itemId;
            $bestNumeric = $numeric;
        }
    }

    return $bestId;
}

/**
 * Resolve the Monday item for the current form journey.
 * Prefers enquiries.monday_item_id; falls back to newest email match.
 */
function mondayResolveItemIdForEnquiryJourney(
    string $token,
    int $boardId,
    string $emailColumnId,
    string $email,
    ?int $enquiryId = null
): ?string {
    if ($enquiryId !== null && $enquiryId > 0 && function_exists('enquiryLoggerGetMondayItemId')) {
        $stored = enquiryLoggerGetMondayItemId($enquiryId);
        if ($stored !== null) {
            return $stored;
        }
    }

    return mondayFindItemIdByEmailExact($token, $boardId, $emailColumnId, $email);
}

/**
 * Resolve the Monday item for an enquiry email.
 * Prefers a stored item ID only when its email matches exactly.
 *
 * @return array{itemId:?string,source:string}
 */
function mondayResolveItemIdForEmail(
    string $token,
    int $boardId,
    string $emailColumnId,
    string $email,
    ?string $preferredItemId = null
): array {
    $email = mondayNormaliseEmail($email);
    $preferredItemId = trim((string)$preferredItemId);

    if ($preferredItemId !== '') {
        $preferredEmail = mondayItemEmail($token, $preferredItemId, $emailColumnId);
        if ($preferredEmail !== '' && $preferredEmail === $email) {
            return ['itemId' => $preferredItemId, 'source' => 'stored'];
        }
    }

    $found = mondayFindItemIdByEmailExact($token, $boardId, $emailColumnId, $email);
    if ($found !== null) {
        return ['itemId' => $found, 'source' => 'search'];
    }

    return ['itemId' => null, 'source' => 'none'];
}

/**
 * Create a Monday enquiry item for name/email when none exists yet.
 */
function mondayCreateEnquiryItem(
    string $token,
    int $boardId,
    string $emailColumnId,
    string $name,
    string $email,
    string $enquiryType = 'training',
    ?string $groupId = null,
    array $columns = [],
    array $address = []
): string {
    $name = trim($name);
    $email = trim($email);
    if ($name === '' || $email === '' || $emailColumnId === '') {
        throw new RuntimeException('Name, email, and Monday email column are required to create an item.');
    }

    $notesColumnId = null;
    $notesColumnType = '';
    $contactTypeColumnId = null;
    $contactTypeColumnType = '';
    $heardAboutUsColumnId = null;
    $heardAboutUsColumnType = '';
    $leadSourceColumnId = null;
    $leadSourceColumnType = '';

    foreach ($columns as $column) {
        if (!is_array($column)) {
            continue;
        }
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id === '') {
            continue;
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

    $notesLine = 'I am looking to make an enquiry about: (' . strtoupper(trim($enquiryType)) . ')';
    $notesValue = $notesColumnType === 'long_text' ? ['text' => $notesLine] : $notesLine;

    $columnValues = [
        $emailColumnId => [
            'email' => $email,
            'text' => $email,
        ],
    ];
    if ($notesColumnId !== null) {
        $columnValues[$notesColumnId] = $notesValue;
    }
    if ($contactTypeColumnId !== null) {
        $columnValues[$contactTypeColumnId] = $contactTypeColumnType === 'status'
            ? ['label' => 'New Course Enquiry']
            : ($contactTypeColumnType === 'dropdown' ? ['labels' => ['New Course Enquiry']] : 'New Course Enquiry');
    }
    if ($heardAboutUsColumnId !== null) {
        $columnValues[$heardAboutUsColumnId] = $heardAboutUsColumnType === 'status'
            ? ['label' => 'Form']
            : ($heardAboutUsColumnType === 'dropdown' ? ['labels' => ['Form']] : 'Form');
    }
    if ($leadSourceColumnId !== null) {
        $columnValues[$leadSourceColumnId] = $leadSourceColumnType === 'status'
            ? ['label' => 'Website Form']
            : ($leadSourceColumnType === 'dropdown' ? ['labels' => ['Website Form']] : 'Website Form');
    }

    if ($columns !== []) {
        $columnValues = mondayAppendAddressColumn($columns, $columnValues, $address);
        $columnValues = mondayAppendCreatedDate($columns, $columnValues);
    }

    $createWithGroup = <<<'GQL'
mutation ($boardId: ID!, $groupId: String!, $itemName: String!, $columnValues: JSON!) {
  create_item(
    board_id: $boardId,
    group_id: $groupId,
    item_name: $itemName,
    column_values: $columnValues
  ) { id }
}
GQL;
    $createWithoutGroup = <<<'GQL'
mutation ($boardId: ID!, $itemName: String!, $columnValues: JSON!) {
  create_item(
    board_id: $boardId,
    item_name: $itemName,
    column_values: $columnValues
  ) { id }
}
GQL;

    $encoded = json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($groupId !== null && $groupId !== '') {
        $resp = mondayGraphqlRequest($token, $createWithGroup, [
            'boardId' => (string)$boardId,
            'groupId' => $groupId,
            'itemName' => $name,
            'columnValues' => $encoded,
        ]);
    } else {
        $resp = mondayGraphqlRequest($token, $createWithoutGroup, [
            'boardId' => (string)$boardId,
            'itemName' => $name,
            'columnValues' => $encoded,
        ]);
    }

    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        $message = '';
        if (!empty($resp['body']['errors'][0]['message'])) {
            $message = (string)$resp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not create Monday item.');
    }

    $itemId = trim((string)($resp['body']['data']['create_item']['id'] ?? ''));
    if ($itemId === '') {
        throw new RuntimeException('Monday create_item returned no item id.');
    }

    return $itemId;
}

/**
 * Move a Monday item into a board group by name.
 *
 * @return array{moved:bool,groupId:string,groupName:string}
 */
function mondayMoveItemToGroupByName(string $itemId, string $groupName): array
{
    $itemId = trim($itemId);
    $groupName = trim($groupName);
    if ($itemId === '') {
        throw new RuntimeException('Monday item ID is missing.');
    }
    if ($groupName === '') {
        throw new RuntimeException('Monday group name is missing.');
    }

    $monday = mondayAppConfig();
    $token = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($token === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $groupId = mondayFindGroupIdByName($token, $boardId, $groupName);
    if ($groupId === null) {
        throw new RuntimeException('Monday group "' . $groupName . '" was not found on the board.');
    }

    $mutation = <<<'GQL'
mutation ($itemId: ID!, $groupId: String!) {
  move_item_to_group(item_id: $itemId, group_id: $groupId) {
    id
  }
}
GQL;

    $resp = mondayGraphqlRequest($token, $mutation, [
        'itemId' => $itemId,
        'groupId' => $groupId,
    ]);

    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        $message = '';
        if (!empty($resp['body']['errors'][0]['message'])) {
            $message = (string)$resp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not move Monday item to "' . $groupName . '".');
    }

    return [
        'moved' => true,
        'groupId' => $groupId,
        'groupName' => $groupName,
    ];
}

/**
 * Move a Monday item into the "Being Contacted" group after Edit Enquiry Email is sent.
 *
 * @return array{moved:bool,groupId:string,groupName:string}
 */
function mondayMoveItemToBeingContacted(string $itemId): array
{
    return mondayMoveItemToGroupByName($itemId, 'Being Contacted');
}

/**
 * Move a Monday item into the "Quote Sent" group after a Xero quote is emailed.
 *
 * @return array{moved:bool,groupId:string,groupName:string}
 */
function mondayMoveItemToQuoteSent(string $itemId): array
{
    return mondayMoveItemToGroupByName($itemId, 'Quote Sent');
}

/**
 * Move a Monday item into the "Quote Accepted" group after booking/terms are accepted.
 *
 * @return array{moved:bool,groupId:string,groupName:string}
 */
function mondayMoveItemToQuoteAccepted(string $itemId): array
{
    return mondayMoveItemToGroupByName($itemId, mondayQuoteAcceptedGroupName());
}

function mondayMoveEnquiryToBeingContactedAfterEditEmail(int $enquiryId): void
{
    require_once __DIR__ . '/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT monday_item_id FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $itemId = trim((string)($row['monday_item_id'] ?? ''));
    if ($itemId === '') {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_skipped',
            'Edit Enquiry Email sent, but Monday item could not be moved because no Monday item ID is stored.'
        );

        return;
    }

    try {
        $result = mondayMoveItemToBeingContacted($itemId);
        enquiryLoggerMarkContacted($enquiryId);
        enquiryLoggerEvent(
            $enquiryId,
            'monday_moved_being_contacted',
            'Enquiry moved to Monday group "' . $result['groupName'] . '" and status set to Contacted.',
            [
                'monday_item_id' => $itemId,
                'monday_group_id' => $result['groupId'],
                'monday_group_name' => $result['groupName'],
                'status' => 'contacted',
            ]
        );
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_failed',
            'Could not move enquiry to Monday group "Being Contacted" after Edit Enquiry Email was sent.',
            [
                'monday_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]
        );
    }
}

/**
 * After a Xero quote is emailed: write Monday "Xero Quote ID" and "Quote Sent Date".
 *
 * @return array{
 *   updated:bool,
 *   xeroQuoteColumnId:?string,
 *   xeroQuoteValue:?string,
 *   quoteSentDateColumnId:?string,
 *   quoteSentDate:?array<string,string>
 * }
 */
function mondaySyncXeroQuoteSentFieldsOnItem(string $itemId, string $quoteId, string $quoteNumber = ''): array
{
    $itemId = trim($itemId);
    $quoteId = trim($quoteId);
    $quoteNumber = trim($quoteNumber);
    $quoteValue = $quoteNumber !== '' ? $quoteNumber : $quoteId;
    if ($itemId === '') {
        throw new RuntimeException('Monday item ID is required.');
    }

    $monday = mondayAppConfig();
    $token = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($token === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
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

    $columnsResp = mondayGraphqlRequest($token, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException('Could not read Monday columns for Xero quote sent sync.');
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }
    $columns = is_array($boards[0]['columns'] ?? null) ? $boards[0]['columns'] : [];

    $columnValues = [];
    $xeroQuoteColumnId = null;
    $quoteSentDateColumnId = null;
    $quoteSentDate = mondayDateColumnValueNow();

    if ($quoteValue !== '') {
        foreach (['xero quote id', 'xero quote number'] as $title) {
            [$xeroQuoteColumnId, $xeroQuoteColumnType] = mondayFindColumnByTitles($columns, [$title]);
            if ($xeroQuoteColumnId !== null) {
                $columnValues[$xeroQuoteColumnId] = mondayColumnValueForType($xeroQuoteColumnType, $quoteValue);
                break;
            }
        }
    }

    [$quoteSentDateColumnId, $quoteSentDateColumnType] = mondayFindColumnByTitles($columns, [
        'quote sent date',
    ]);
    if ($quoteSentDateColumnId !== null) {
        if (strtolower($quoteSentDateColumnType) === 'date') {
            $columnValues[$quoteSentDateColumnId] = $quoteSentDate;
        } else {
            $columnValues[$quoteSentDateColumnId] = mondayColumnValueForType(
                $quoteSentDateColumnType,
                $quoteSentDate['date'] . ' ' . $quoteSentDate['time']
            );
        }
    }

    if ($columnValues === []) {
        throw new RuntimeException(
            'Monday columns "Xero Quote ID" / "Quote Sent Date" were not found (or no quote id to write).'
        );
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

    $updateResp = mondayGraphqlRequest($token, $updateQuery, [
        'boardId' => (string)$boardId,
        'itemId' => $itemId,
        'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
        $message = '';
        if (!empty($updateResp['body']['errors'][0]['message'])) {
            $message = (string)$updateResp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not sync Monday quote-sent fields.');
    }

    return [
        'updated' => true,
        'xeroQuoteColumnId' => $xeroQuoteColumnId,
        'xeroQuoteValue' => $quoteValue !== '' ? $quoteValue : null,
        'quoteSentDateColumnId' => $quoteSentDateColumnId,
        'quoteSentDate' => $quoteSentDateColumnId !== null ? $quoteSentDate : null,
    ];
}

/**
 * @deprecated Use mondaySyncXeroQuoteSentFieldsOnItem()
 * @return array{updated:bool,columnId:?string,value:string}
 */
function mondaySetXeroQuoteIdOnItem(string $itemId, string $quoteId, string $quoteNumber = ''): array
{
    $synced = mondaySyncXeroQuoteSentFieldsOnItem($itemId, $quoteId, $quoteNumber);

    return [
        'updated' => $synced['updated'],
        'columnId' => $synced['xeroQuoteColumnId'],
        'value' => (string)($synced['xeroQuoteValue'] ?? ''),
    ];
}

/**
 * Move the enquiry's Monday item to "Quote Sent" after a Xero quote is emailed,
 * and write Xero Quote ID + Quote Sent Date on the Monday item.
 */
function mondayMoveEnquiryToQuoteSentAfterXeroQuote(int $enquiryId): void
{
    require_once __DIR__ . '/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_quote_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_quote_number', 'TEXT');
    $stmt = $pdo->prepare(
        'SELECT monday_item_id, xero_quote_id, xero_quote_number FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $itemId = trim((string)($row['monday_item_id'] ?? ''));
    $quoteId = trim((string)($row['xero_quote_id'] ?? ''));
    $quoteNumber = trim((string)($row['xero_quote_number'] ?? ''));
    if ($itemId === '') {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_skipped',
            'Xero quote emailed, but Monday item could not be moved because no Monday item ID is stored.'
        );

        return;
    }

    try {
        $synced = mondaySyncXeroQuoteSentFieldsOnItem($itemId, $quoteId, $quoteNumber);
        if ($synced['xeroQuoteColumnId'] !== null && $synced['xeroQuoteValue'] !== null) {
            enquiryLoggerEvent(
                $enquiryId,
                'monday_xero_quote_id_set',
                'Xero Quote ID written to Monday enquiry.',
                [
                    'monday_item_id' => $itemId,
                    'monday_column_id' => $synced['xeroQuoteColumnId'],
                    'value' => $synced['xeroQuoteValue'],
                    'xero_quote_id' => $quoteId !== '' ? $quoteId : null,
                    'xero_quote_number' => $quoteNumber !== '' ? $quoteNumber : null,
                ]
            );
        }
        if ($synced['quoteSentDateColumnId'] !== null && is_array($synced['quoteSentDate'])) {
            enquiryLoggerEvent(
                $enquiryId,
                'monday_quote_sent_date_set',
                'Quote Sent Date written to Monday enquiry.',
                [
                    'monday_item_id' => $itemId,
                    'monday_column_id' => $synced['quoteSentDateColumnId'],
                    'date' => $synced['quoteSentDate']['date'] ?? null,
                    'time' => $synced['quoteSentDate']['time'] ?? null,
                ]
            );
        }
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_quote_sent_fields_failed',
            'Could not write Xero Quote ID / Quote Sent Date to Monday enquiry.',
            [
                'monday_item_id' => $itemId,
                'xero_quote_id' => $quoteId !== '' ? $quoteId : null,
                'xero_quote_number' => $quoteNumber !== '' ? $quoteNumber : null,
                'error' => $e->getMessage(),
            ]
        );
    }

    try {
        $result = mondayMoveItemToQuoteSent($itemId);
        enquiryLoggerMarkQuoteSent($enquiryId);
        enquiryLoggerEvent(
            $enquiryId,
            'monday_moved_quote_sent',
            'Enquiry moved to Monday group "' . $result['groupName'] . '" and status set to Quote Sent.',
            [
                'monday_item_id' => $itemId,
                'monday_group_id' => $result['groupId'],
                'monday_group_name' => $result['groupName'],
                'status' => 'quote_sent',
            ]
        );
    } catch (Throwable $e) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_failed',
            'Could not move enquiry to Monday group "Quote Sent" after Xero quote was emailed.',
            [
                'monday_item_id' => $itemId,
                'error' => $e->getMessage(),
            ]
        );
    }
}

function mondayQuoteAcceptedGroupName(): string
{
    $configured = trim((string)(getenv('MONDAY_QUOTE_ACCEPTED_GROUP_NAME') ?: ($GLOBALS['mondayQuoteAcceptedGroupName'] ?? '')));
    if ($configured !== '') {
        return $configured;
    }

    // Legacy setting: completed booking group was used for the post-accept move.
    $legacy = trim((string)(getenv('MONDAY_BOOKING_GROUP_NAME') ?: ($GLOBALS['mondayBookingGroupName'] ?? '')));
    if ($legacy !== '' && strcasecmp($legacy, 'Client Booking Form (Courses Ongoing)') !== 0) {
        return $legacy;
    }

    return 'Quote Accepted';
}

function mondayBookingGroupName(): string
{
    return mondayQuoteAcceptedGroupName();
}

/**
 * Candidate Monday group titles for quote-accepted / completed booking sync, preferred first.
 *
 * @return list<string>
 */
function mondayBookingGroupNameCandidates(): array
{
    $preferred = mondayQuoteAcceptedGroupName();
    $candidates = [
        $preferred,
        'Quote Accepted',
        'Client Booking Form (Courses Ongoing)',
        'Won - Ready for Booking',
        'Courses Ongoing',
        'Client Booking Form',
    ];

    $unique = [];
    foreach ($candidates as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $name;
    }

    return array_values($unique);
}

/**
 * Create a Monday board group by name.
 */
function mondayCreateGroup(string $token, int $boardId, string $groupName): string
{
    $groupName = trim($groupName);
    if ($groupName === '') {
        throw new RuntimeException('Monday group name is required.');
    }

    $mutation = <<<'GQL'
mutation ($boardId: ID!, $groupName: String!) {
  create_group(board_id: $boardId, group_name: $groupName) {
    id
    title
  }
}
GQL;

    $resp = mondayGraphqlRequest($token, $mutation, [
        'boardId' => (string)$boardId,
        'groupName' => $groupName,
    ]);
    if ($resp['status'] >= 400 || !empty($resp['body']['errors'])) {
        $message = '';
        if (!empty($resp['body']['errors'][0]['message'])) {
            $message = (string)$resp['body']['errors'][0]['message'];
        }
        throw new RuntimeException($message !== '' ? $message : 'Could not create Monday group "' . $groupName . '".');
    }

    $id = trim((string)($resp['body']['data']['create_group']['id'] ?? ''));
    if ($id === '') {
        throw new RuntimeException('Monday create_group did not return a group id.');
    }

    return $id;
}

/**
 * Find or create the Monday group used when a quote is accepted.
 *
 * Prefers the configured "Quote Accepted" group and creates it when missing.
 * Falls back to known booking groups only if create fails.
 *
 * @return array{groupId:string,groupName:string,created:bool}
 */
function mondayResolveBookingGroup(string $token, int $boardId): array
{
    $preferred = mondayQuoteAcceptedGroupName();
    $existingPreferred = mondayFindGroupIdByName($token, $boardId, $preferred);
    if ($existingPreferred !== null) {
        return [
            'groupId' => $existingPreferred,
            'groupName' => $preferred,
            'created' => false,
        ];
    }

    try {
        $groupId = mondayCreateGroup($token, $boardId, $preferred);

        return [
            'groupId' => $groupId,
            'groupName' => $preferred,
            'created' => true,
        ];
    } catch (Throwable $createError) {
        foreach (mondayBookingGroupNameCandidates() as $candidate) {
            if (strtolower($candidate) === strtolower($preferred)) {
                continue;
            }
            $groupId = mondayFindGroupIdByName($token, $boardId, $candidate);
            if ($groupId !== null) {
                return [
                    'groupId' => $groupId,
                    'groupName' => $candidate,
                    'created' => false,
                ];
            }
        }

        throw $createError;
    }
}

/**
 * Encode a value for a Monday column type (shared helper for booking sync).
 *
 * @param mixed $value
 * @return mixed
 */
function mondayColumnValueForType(string $columnType, $value)
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
    if ($type === 'checkbox') {
        $checked = $value === true || $value === 1 || $value === '1' || strtolower((string)$value) === 'true' || strtolower((string)$value) === 'yes';

        return ['checked' => $checked];
    }
    if ($type === 'numbers' || $type === 'numeric') {
        return (string)$value;
    }
    if ($type === 'phone') {
        return ['phone' => (string)$value, 'countryShortName' => 'GB'];
    }
    if ($type === 'email') {
        $email = (string)$value;

        return ['email' => $email, 'text' => $email];
    }

    return (string)$value;
}

/**
 * Build a Notes block from booking details for columns that have no dedicated mapping.
 *
 * @param array<string, mixed> $details
 */
function mondayBookingDetailsNotesBlock(array $details): string
{
    $lines = ['--- Booking details ---'];
    $map = [
        'Booker name' => $details['bookerName'] ?? '',
        'Organisation' => $details['organisation'] ?? '',
        'Email' => $details['email'] ?? '',
        'Phone' => $details['phone'] ?? '',
        'Preferred date' => $details['preferredDate'] ?? '',
        'Venue address' => $details['venueAddress'] ?? '',
        'Special requests' => $details['specialRequests'] ?? '',
        'Invoice name' => $details['invoiceName'] ?? '',
        'Invoice email' => $details['invoiceEmail'] ?? '',
        'Invoice address' => $details['invoiceAddress'] ?? '',
        'Invoice phone' => $details['invoicePhone'] ?? '',
        'PO number' => $details['purchaseOrderNumber'] ?? '',
        'Venue requirements confirmed' => !empty($details['venueRequirementsConfirmed']) ? 'Yes' : 'No',
        'Terms accepted' => !empty($details['termsAccepted']) ? 'Yes' : 'No',
    ];

    foreach ($map as $label => $value) {
        $value = trim((string)$value);
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    $delegates = $details['delegates'] ?? [];
    if (is_array($delegates) && count($delegates) > 0) {
        $lines[] = 'Delegates:';
        foreach ($delegates as $delegate) {
            if (!is_array($delegate)) {
                continue;
            }
            $name = trim((string)($delegate['name'] ?? ''));
            $email = trim((string)($delegate['email'] ?? ''));
            if ($name === '' && $email === '') {
                continue;
            }
            $lines[] = '- ' . ($name !== '' ? $name : 'Delegate') . ($email !== '' ? ' <' . $email . '>' : '');
        }
    } elseif (trim((string)($details['studentNames'] ?? '')) !== '') {
        $lines[] = 'Student names:';
        $lines[] = trim((string)$details['studentNames']);
        if (trim((string)($details['studentEmails'] ?? '')) !== '') {
            $lines[] = 'Student emails:';
            $lines[] = trim((string)$details['studentEmails']);
        }
    }

    if (!empty($details['studentNamesFile']) && is_array($details['studentNamesFile'])) {
        $fileName = trim((string)($details['studentNamesFile']['originalName'] ?? ''));
        if ($fileName !== '') {
            $lines[] = 'Delegate file uploaded: ' . $fileName;
        }
    }

    return implode("\n", $lines);
}

/**
 * Resolve a Monday column id/type by matching one of the given lowercase titles.
 *
 * @param array<int, array<string, mixed>> $columns
 * @param list<string> $titles
 * @return array{0: ?string, 1: string}
 */
function mondayFindColumnByTitles(array $columns, array $titles): array
{
    $wanted = [];
    foreach ($titles as $title) {
        $wanted[strtolower(trim($title))] = true;
    }

    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id === '' || !isset($wanted[$title])) {
            continue;
        }

        return [$id, $type];
    }

    return [null, ''];
}

/**
 * Sync submitted booking details to Monday and move the item into
 * "Quote Accepted".
 *
 * @param array<string, mixed> $details
 * @return array{itemId:string,moved:bool,groupName:string}
 */
function mondaySyncBookingDetails(int $enquiryId, array $details): array
{
    require_once __DIR__ . '/enquiry_logger.php';

    $monday = mondayAppConfig();
    $token = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($token === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $email = trim((string)($details['email'] ?? ''));
    $bookerName = trim((string)($details['bookerName'] ?? ''));

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT name, email, monday_item_id FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Enquiry not found for Monday booking sync.');
    }

    if ($email === '') {
        $email = trim((string)($row['email'] ?? ''));
    }
    if ($bookerName === '') {
        $bookerName = trim((string)($row['name'] ?? ''));
    }
    if ($email === '') {
        throw new RuntimeException('Booking email is required for Monday sync.');
    }

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

    $columnsResp = mondayGraphqlRequest($token, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException('Could not read Monday columns for booking sync.');
    }

    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }
    $columns = is_array($boards[0]['columns'] ?? null) ? $boards[0]['columns'] : [];

    $emailColumnId = null;
    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id !== '' && ($type === 'email' || $title === 'email')) {
            $emailColumnId = $id;
            break;
        }
    }
    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on Monday board.');
    }

    $resolved = mondayResolveItemIdForEmail(
        $token,
        $boardId,
        $emailColumnId,
        $email,
        trim((string)($row['monday_item_id'] ?? ''))
    );
    $itemId = $resolved['itemId'] ?? null;

    if ($itemId === null || $itemId === '') {
        $bookingGroup = mondayResolveBookingGroup($token, $boardId);
        $itemId = mondayCreateEnquiryItem(
            $token,
            $boardId,
            $emailColumnId,
            $bookerName !== '' ? $bookerName : $email,
            $email,
            'training',
            $bookingGroup['groupId'],
            $columns,
            []
        );
    }

    enquiryLoggerMarkMondaySynced($enquiryId, $itemId);

    $columnValues = [];
    $mappings = [
        ['titles' => ['phone', 'phone number', 'mobile'], 'value' => $details['phone'] ?? ''],
        ['titles' => ['company/organisation', 'company / organisation', 'company', 'organisation', 'organization', 'company name', 'organisation name'], 'value' => $details['organisation'] ?? ''],
        ['titles' => ['venue address', 'venue', 'training venue', 'training venue address'], 'value' => $details['venueAddress'] ?? ''],
        ['titles' => ['address'], 'value' => $details['venueAddress'] ?? ''],
        ['titles' => ['invoice name'], 'value' => $details['invoiceName'] ?? ''],
        ['titles' => ['invoice email'], 'value' => $details['invoiceEmail'] ?? ''],
        ['titles' => ['invoice address'], 'value' => $details['invoiceAddress'] ?? ''],
        ['titles' => ['invoice phone'], 'value' => $details['invoicePhone'] ?? ''],
        ['titles' => ['po number', 'purchase order', 'purchase order number', 'po'], 'value' => $details['purchaseOrderNumber'] ?? ''],
        ['titles' => ['special requests', 'special request'], 'value' => $details['specialRequests'] ?? ''],
        ['titles' => ['booker name', 'contact name'], 'value' => $details['bookerName'] ?? ''],
    ];

    foreach ($mappings as $mapping) {
        $value = trim((string)($mapping['value'] ?? ''));
        if ($value === '') {
            continue;
        }
        [$columnId, $columnType] = mondayFindColumnByTitles($columns, $mapping['titles']);
        if ($columnId === null || isset($columnValues[$columnId])) {
            continue;
        }
        $columnValues[$columnId] = mondayColumnValueForType($columnType, $value);
    }

    $preferredDate = enquiryPreferredDateOnly((string)($details['preferredDate'] ?? ''));
    if ($preferredDate !== '') {
        [$preferredDateColumnId, $preferredDateColumnType] = mondayFindColumnByTitles($columns, [
            'preferred date',
            'preferred date/time',
            'preferred date & time',
            'preferred datetime',
            'preferred day',
            'preferred day(s)',
        ]);
        if ($preferredDateColumnId !== null && !isset($columnValues[$preferredDateColumnId])) {
            if ($preferredDateColumnType === 'date') {
                $datePayload = mondayDateColumnValueFromDatetimeLocal($preferredDate);
                if ($datePayload !== null) {
                    $columnValues[$preferredDateColumnId] = $datePayload;
                }
            } else {
                $columnValues[$preferredDateColumnId] = mondayColumnValueForType(
                    $preferredDateColumnType,
                    $preferredDate
                );
            }
        }
    }

    [$termsColumnId, $termsColumnType] = mondayFindColumnByTitles($columns, [
        'terms accepted',
        'terms & conditions',
        'terms and conditions',
        't&cs accepted',
    ]);
    if ($termsColumnId !== null && !empty($details['termsAccepted'])) {
        $columnValues[$termsColumnId] = mondayColumnValueForType($termsColumnType, true);
    }

    [$venueReqColumnId, $venueReqColumnType] = mondayFindColumnByTitles($columns, [
        'venue requirements',
        'venue confirmed',
        'venue requirements confirmed',
    ]);
    if ($venueReqColumnId !== null && !empty($details['venueRequirementsConfirmed'])) {
        $columnValues[$venueReqColumnId] = mondayColumnValueForType($venueReqColumnType, true);
    }

    $delegateCount = is_array($details['delegates'] ?? null) ? count($details['delegates']) : 0;
    if ($delegateCount > 0) {
        [$attendeesColumnId, $attendeesColumnType] = mondayFindColumnByTitles($columns, [
            'number of attendees',
            'attendees',
            'delegates',
            'number of delegates',
        ]);
        if ($attendeesColumnId !== null) {
            $columnValues[$attendeesColumnId] = mondayColumnValueForType($attendeesColumnType, (string)$delegateCount);
        }
    }

    [$notesColumnId, $notesColumnType] = mondayFindColumnByTitles($columns, ['notes']);
    if ($notesColumnId !== null) {
        $existingNotes = '';
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
        $notesFetchResp = mondayGraphqlRequest($token, $notesFetchQuery, [
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

        $appendBlock = mondayBookingDetailsNotesBlock($details);
        $notesText = $existingNotes === '' ? $appendBlock : $existingNotes . "\n\n" . $appendBlock;
        $columnValues[$notesColumnId] = mondayColumnValueForType($notesColumnType, $notesText);
    }

    if (count($columnValues) > 0) {
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
        $updateResp = mondayGraphqlRequest($token, $updateQuery, [
            'boardId' => (string)$boardId,
            'itemId' => $itemId,
            'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
            $message = '';
            if (!empty($updateResp['body']['errors'][0]['message'])) {
                $message = (string)$updateResp['body']['errors'][0]['message'];
            }
            throw new RuntimeException($message !== '' ? $message : 'Could not update Monday booking details.');
        }
    }

    $groupName = mondayQuoteAcceptedGroupName();
    $moved = false;
    try {
        $bookingGroup = mondayResolveBookingGroup($token, $boardId);
        $groupName = $bookingGroup['groupName'];
        $moveResult = mondayMoveItemToGroupByName($itemId, $groupName);
        $moved = true;
        $groupName = $moveResult['groupName'];
        enquiryLoggerMarkQuoteAccepted($enquiryId);
        if (!empty($bookingGroup['created'])) {
            enquiryLoggerEvent(
                $enquiryId,
                'monday_booking_group_created',
                'Created Monday group "' . $groupName . '" for accepted quotes.',
                [
                    'monday_group_id' => $bookingGroup['groupId'],
                    'monday_group_name' => $groupName,
                ]
            );
        }
        enquiryLoggerEvent(
            $enquiryId,
            'monday_moved_quote_accepted',
            'Enquiry moved to Monday group "' . $groupName . '" and status set to Quote Accepted.',
            [
                'monday_item_id' => $itemId,
                'monday_group_id' => $moveResult['groupId'] ?? $bookingGroup['groupId'],
                'monday_group_name' => $groupName,
                'status' => 'quote_accepted',
            ]
        );
    } catch (Throwable $moveError) {
        // Still mark accepted locally — booking/terms were submitted even if Monday move failed.
        enquiryLoggerMarkQuoteAccepted($enquiryId);
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_failed',
            'Booking details synced, but the Monday item could not be moved to the Quote Accepted group.',
            [
                'monday_item_id' => $itemId,
                'error' => $moveError->getMessage(),
            ]
        );
    }

    enquiryLoggerEvent(
        $enquiryId,
        'monday_booking_synced',
        $moved
            ? 'Booking details synced to Monday and moved to "' . $groupName . '".'
            : 'Booking details synced to Monday (group move skipped).',
        [
            'monday_item_id' => $itemId,
            'monday_group_name' => $groupName,
            'moved' => $moved,
        ]
    );

    return [
        'itemId' => $itemId,
        'moved' => $moved,
        'groupName' => $groupName,
    ];
}

/**
 * Preferred Monday group after a Xero invoice is sent (Quote Won).
 */
function mondayQuoteWonGroupName(): string
{
    $configured = trim((string)(getenv('MONDAY_QUOTE_WON_GROUP_NAME') ?: ($GLOBALS['mondayQuoteWonGroupName'] ?? '')));
    if ($configured !== '') {
        return $configured;
    }

    return 'Quote Won';
}

/**
 * @return list<string>
 */
function mondayQuoteWonGroupNameCandidates(): array
{
    $candidates = [
        mondayQuoteWonGroupName(),
        'Quote Won',
        'Won - Ready for Booking',
    ];

    $unique = [];
    foreach ($candidates as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $name;
    }

    return array_values($unique);
}

/**
 * @return array{groupId:string,groupName:string,created:bool}
 */
function mondayResolveQuoteWonGroup(string $token, int $boardId): array
{
    foreach (mondayQuoteWonGroupNameCandidates() as $candidate) {
        $groupId = mondayFindGroupIdByName($token, $boardId, $candidate);
        if ($groupId !== null) {
            return [
                'groupId' => $groupId,
                'groupName' => $candidate,
                'created' => false,
            ];
        }
    }

    $preferred = mondayQuoteWonGroupName();
    $groupId = mondayCreateGroup($token, $boardId, $preferred);

    return [
        'groupId' => $groupId,
        'groupName' => $preferred,
        'created' => true,
    ];
}

function mondayCoursesOngoingGroupName(): string
{
    $configured = trim((string)(getenv('MONDAY_COURSES_ONGOING_GROUP_NAME') ?: ($GLOBALS['mondayCoursesOngoingGroupName'] ?? '')));
    if ($configured !== '') {
        return $configured;
    }

    return 'Client Booking Form (Courses Ongoing)';
}

/**
 * @return list<string>
 */
function mondayCoursesOngoingGroupNameCandidates(): array
{
    $candidates = [
        mondayCoursesOngoingGroupName(),
        'Client Booking Form (Courses Ongoing)',
        'Courses Ongoing',
        'Client Booking Form',
    ];

    $unique = [];
    foreach ($candidates as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        $key = strtolower($name);
        if (isset($unique[$key])) {
            continue;
        }
        $unique[$key] = $name;
    }

    return array_values($unique);
}

/**
 * @return array{groupId:string,groupName:string,created:bool}
 */
function mondayResolveCoursesOngoingGroup(string $token, int $boardId): array
{
    foreach (mondayCoursesOngoingGroupNameCandidates() as $candidate) {
        $groupId = mondayFindGroupIdByName($token, $boardId, $candidate);
        if ($groupId !== null) {
            return [
                'groupId' => $groupId,
                'groupName' => $candidate,
                'created' => false,
            ];
        }
    }

    $preferred = mondayCoursesOngoingGroupName();
    $groupId = mondayCreateGroup($token, $boardId, $preferred);

    return [
        'groupId' => $groupId,
        'groupName' => $preferred,
        'created' => true,
    ];
}

/**
 * Move the enquiry pipeline item to Quote Won after the Xero invoice is sent.
 *
 * @return array{moved:bool,groupId:string,groupName:string,itemId:string}
 */
function mondayMoveEnquiryToQuoteWonAfterInvoiceSent(int $enquiryId): array
{
    require_once __DIR__ . '/enquiry_logger.php';

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT monday_item_id FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $itemId = trim((string)($row['monday_item_id'] ?? ''));
    if ($itemId === '') {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_move_skipped',
            'Xero invoice sent, but Monday item could not be moved to Quote Won because no Monday item ID is stored.'
        );

        return [
            'moved' => false,
            'groupId' => '',
            'groupName' => '',
            'itemId' => '',
        ];
    }

    $monday = mondayAppConfig();
    $token = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($token === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $group = mondayResolveQuoteWonGroup($token, $boardId);
    if (!empty($group['created'])) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_quote_won_group_created',
            'Created Monday group "' . $group['groupName'] . '" for Quote Won.',
            [
                'monday_group_id' => $group['groupId'],
                'monday_group_name' => $group['groupName'],
            ]
        );
    }

    $result = mondayMoveItemToGroupByName($itemId, $group['groupName']);
    enquiryLoggerMarkQuoteWon($enquiryId);
    enquiryLoggerEvent(
        $enquiryId,
        'monday_moved_quote_won',
        'Enquiry moved to Monday group "' . $result['groupName'] . '" (Quote Won) after Xero invoice was sent.',
        [
            'monday_item_id' => $itemId,
            'monday_group_id' => $result['groupId'],
            'monday_group_name' => $result['groupName'],
            'status' => 'quote_won',
            'trigger' => 'xero_invoice_sent',
        ]
    );

    return [
        'moved' => true,
        'groupId' => $result['groupId'],
        'groupName' => $result['groupName'],
        'itemId' => $itemId,
    ];
}

/**
 * Build Monday column values from booking details (shared by Quote Accepted + Courses Ongoing).
 *
 * @param array<int, array<string, mixed>> $columns
 * @param array<string, mixed> $details
 * @return array<string, mixed>
 */
function mondayBookingDetailsColumnValues(array $columns, array $details, string $token, string $itemId): array
{
    $columnValues = [];
    $mappings = [
        ['titles' => ['phone', 'phone number', 'mobile'], 'value' => $details['phone'] ?? ''],
        ['titles' => ['company/organisation', 'company / organisation', 'company', 'organisation', 'organization', 'company name', 'organisation name'], 'value' => $details['organisation'] ?? ''],
        ['titles' => ['venue address', 'venue', 'training venue', 'training venue address'], 'value' => $details['venueAddress'] ?? ''],
        ['titles' => ['address'], 'value' => $details['venueAddress'] ?? ''],
        ['titles' => ['invoice name'], 'value' => $details['invoiceName'] ?? ''],
        ['titles' => ['invoice email'], 'value' => $details['invoiceEmail'] ?? ''],
        ['titles' => ['invoice address'], 'value' => $details['invoiceAddress'] ?? ''],
        ['titles' => ['invoice phone'], 'value' => $details['invoicePhone'] ?? ''],
        ['titles' => ['po number', 'purchase order', 'purchase order number', 'po'], 'value' => $details['purchaseOrderNumber'] ?? ''],
        ['titles' => ['special requests', 'special request'], 'value' => $details['specialRequests'] ?? ''],
        ['titles' => ['booker name', 'contact name'], 'value' => $details['bookerName'] ?? ''],
    ];

    foreach ($mappings as $mapping) {
        $value = trim((string)($mapping['value'] ?? ''));
        if ($value === '') {
            continue;
        }
        [$columnId, $columnType] = mondayFindColumnByTitles($columns, $mapping['titles']);
        if ($columnId === null || isset($columnValues[$columnId])) {
            continue;
        }
        $columnValues[$columnId] = mondayColumnValueForType($columnType, $value);
    }

    $preferredDate = enquiryPreferredDateOnly((string)($details['preferredDate'] ?? ''));
    if ($preferredDate !== '') {
        [$preferredDateColumnId, $preferredDateColumnType] = mondayFindColumnByTitles($columns, [
            'preferred date',
            'preferred date/time',
            'preferred date & time',
            'preferred datetime',
            'preferred day',
            'preferred day(s)',
        ]);
        if ($preferredDateColumnId !== null && !isset($columnValues[$preferredDateColumnId])) {
            if ($preferredDateColumnType === 'date') {
                $datePayload = mondayDateColumnValueFromDatetimeLocal($preferredDate);
                if ($datePayload !== null) {
                    $columnValues[$preferredDateColumnId] = $datePayload;
                }
            } else {
                $columnValues[$preferredDateColumnId] = mondayColumnValueForType(
                    $preferredDateColumnType,
                    $preferredDate
                );
            }
        }
    }

    [$termsColumnId, $termsColumnType] = mondayFindColumnByTitles($columns, [
        'terms accepted',
        'terms & conditions',
        'terms and conditions',
        't&cs accepted',
    ]);
    if ($termsColumnId !== null && !empty($details['termsAccepted'])) {
        $columnValues[$termsColumnId] = mondayColumnValueForType($termsColumnType, true);
    }

    [$venueReqColumnId, $venueReqColumnType] = mondayFindColumnByTitles($columns, [
        'venue requirements',
        'venue confirmed',
        'venue requirements confirmed',
    ]);
    if ($venueReqColumnId !== null && !empty($details['venueRequirementsConfirmed'])) {
        $columnValues[$venueReqColumnId] = mondayColumnValueForType($venueReqColumnType, true);
    }

    $delegateCount = is_array($details['delegates'] ?? null) ? count($details['delegates']) : 0;
    if ($delegateCount > 0) {
        [$attendeesColumnId, $attendeesColumnType] = mondayFindColumnByTitles($columns, [
            'number of attendees',
            'attendees',
            'delegates',
            'number of delegates',
        ]);
        if ($attendeesColumnId !== null) {
            $columnValues[$attendeesColumnId] = mondayColumnValueForType($attendeesColumnType, (string)$delegateCount);
        }
    }

    [$notesColumnId, $notesColumnType] = mondayFindColumnByTitles($columns, ['notes']);
    if ($notesColumnId !== null && $itemId !== '') {
        $existingNotes = '';
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
        $notesFetchResp = mondayGraphqlRequest($token, $notesFetchQuery, [
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

        $appendBlock = mondayBookingDetailsNotesBlock($details);
        if ($existingNotes !== '' && str_contains($existingNotes, '--- Booking details ---')) {
            $notesText = $existingNotes;
        } else {
            $notesText = $existingNotes === '' ? $appendBlock : $existingNotes . "\n\n" . $appendBlock;
        }
        $columnValues[$notesColumnId] = mondayColumnValueForType($notesColumnType, $notesText);
    } elseif ($notesColumnId !== null) {
        $columnValues[$notesColumnId] = mondayColumnValueForType($notesColumnType, mondayBookingDetailsNotesBlock($details));
    }

    return $columnValues;
}

/**
 * Create or update a Monday item in Client Booking Form (Courses Ongoing).
 * Uses monday_booking_item_id so the enquiry pipeline item stays in Quote Won.
 *
 * @param array<string, mixed> $details
 * @return array{itemId:string,created:bool,groupName:string}
 */
function mondaySyncCoursesOngoingBookingItem(int $enquiryId, array $details = []): array
{
    require_once __DIR__ . '/enquiry_logger.php';

    $monday = mondayAppConfig();
    $token = $monday['token'];
    $boardIdRaw = $monday['boardId'];
    if ($token === '' || $boardIdRaw === '' || !is_numeric($boardIdRaw)) {
        throw new RuntimeException(mondayConfigMissingMessage());
    }

    $boardId = (int)$boardIdRaw;
    $pdo = enquiryLoggerPdo();
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'monday_booking_item_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'booking_details_json', 'TEXT');

    $stmt = $pdo->prepare(
        'SELECT name, email, monday_booking_item_id, booking_details_json
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Enquiry not found for Courses Ongoing sync.');
    }

    if ($details === []) {
        $raw = trim((string)($row['booking_details_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $details = $decoded;
            }
        }
    }

    $email = trim((string)($details['email'] ?? $row['email'] ?? ''));
    $bookerName = trim((string)($details['bookerName'] ?? $row['name'] ?? ''));
    if ($email === '') {
        throw new RuntimeException('Booking email is required for Courses Ongoing sync.');
    }

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
    $columnsResp = mondayGraphqlRequest($token, $columnsQuery, ['boardId' => [$boardId]]);
    if ($columnsResp['status'] >= 400 || !empty($columnsResp['body']['errors'])) {
        throw new RuntimeException('Could not read Monday columns for Courses Ongoing sync.');
    }
    $boards = $columnsResp['body']['data']['boards'] ?? [];
    if (!is_array($boards) || count($boards) === 0) {
        throw new RuntimeException('Monday board not found.');
    }
    $columns = is_array($boards[0]['columns'] ?? null) ? $boards[0]['columns'] : [];

    $emailColumnId = null;
    foreach ($columns as $column) {
        $title = strtolower(trim((string)($column['title'] ?? '')));
        $type = strtolower(trim((string)($column['type'] ?? '')));
        $id = trim((string)($column['id'] ?? ''));
        if ($id !== '' && ($type === 'email' || $title === 'email')) {
            $emailColumnId = $id;
            break;
        }
    }
    if ($emailColumnId === null) {
        throw new RuntimeException('Email column not found on Monday board.');
    }

    $group = mondayResolveCoursesOngoingGroup($token, $boardId);
    if (!empty($group['created'])) {
        enquiryLoggerEvent(
            $enquiryId,
            'monday_courses_ongoing_group_created',
            'Created Monday group "' . $group['groupName'] . '" for Client Booking Form.',
            [
                'monday_group_id' => $group['groupId'],
                'monday_group_name' => $group['groupName'],
            ]
        );
    }

    $existingBookingItemId = trim((string)($row['monday_booking_item_id'] ?? ''));
    $created = false;
    $itemId = $existingBookingItemId;

    if ($itemId === '') {
        $itemId = mondayCreateEnquiryItem(
            $token,
            $boardId,
            $emailColumnId,
            $bookerName !== '' ? $bookerName : $email,
            $email,
            'training',
            $group['groupId'],
            $columns,
            []
        );
        $created = true;
        enquiryLoggerMarkMondayBookingItemId($enquiryId, $itemId);
    } else {
        // Ensure existing booking item sits in the Courses Ongoing group.
        try {
            mondayMoveItemToGroupByName($itemId, $group['groupName']);
        } catch (Throwable $moveError) {
            // Item may have been deleted — recreate.
            $itemId = mondayCreateEnquiryItem(
                $token,
                $boardId,
                $emailColumnId,
                $bookerName !== '' ? $bookerName : $email,
                $email,
                'training',
                $group['groupId'],
                $columns,
                []
            );
            $created = true;
            enquiryLoggerMarkMondayBookingItemId($enquiryId, $itemId);
            enquiryLoggerEvent(
                $enquiryId,
                'monday_courses_ongoing_recreated',
                'Previous Client Booking Form item was missing; created a new record.',
                [
                    'monday_item_id' => $itemId,
                    'previous_error' => $moveError->getMessage(),
                ]
            );
        }
    }

    $columnValues = mondayBookingDetailsColumnValues($columns, $details, $token, $itemId);
    if ($columnValues !== []) {
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
        $updateResp = mondayGraphqlRequest($token, $updateQuery, [
            'boardId' => (string)$boardId,
            'itemId' => $itemId,
            'columnValues' => json_encode($columnValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        if ($updateResp['status'] >= 400 || !empty($updateResp['body']['errors'])) {
            $message = '';
            if (!empty($updateResp['body']['errors'][0]['message'])) {
                $message = (string)$updateResp['body']['errors'][0]['message'];
            }
            throw new RuntimeException($message !== '' ? $message : 'Could not update Courses Ongoing booking details.');
        }
    }

    enquiryLoggerEvent(
        $enquiryId,
        $created ? 'monday_courses_ongoing_created' : 'monday_courses_ongoing_synced',
        $created
            ? 'Created Client Booking Form (Courses Ongoing) record after Xero invoice was sent.'
            : 'Updated Client Booking Form (Courses Ongoing) record after Xero invoice was sent.',
        [
            'monday_item_id' => $itemId,
            'monday_group_id' => $group['groupId'],
            'monday_group_name' => $group['groupName'],
            'created' => $created,
            'trigger' => 'xero_invoice_sent',
        ]
    );

    return [
        'itemId' => $itemId,
        'created' => $created,
        'groupName' => $group['groupName'],
    ];
}
