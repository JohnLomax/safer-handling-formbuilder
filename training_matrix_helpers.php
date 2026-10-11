<?php
declare(strict_types=1);

require_once __DIR__ . '/training_matrix_data.php';

const TRAINING_MATRIX_MAX_ATTENDEES = 120;

/**
 * @return list<array<string, mixed>>
 */
function trainingMatrixAll(): array
{
    return trainingMatrix();
}

/**
 * @param array<string, mixed> $item
 */
function trainingMatrixMatchesOrgCourse(array $item, string $orgCourse): bool
{
    $orgCourse = trim($orgCourse);

    return $orgCourse !== ''
        && ($item['course'] === $orgCourse || $item['courseValue'] === $orgCourse);
}

/**
 * True when value matches a Training Matrix courseValue (Monday / Forge code).
 */
function trainingMatrixIsCourseValue(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    foreach (trainingMatrixAll() as $item) {
        if (trim((string)($item['courseValue'] ?? '')) === $value) {
            return true;
        }
    }

    return false;
}

/**
 * True when value matches a customer-facing Course label.
 */
function trainingMatrixIsCourseLabel(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }
    foreach (trainingMatrixAll() as $item) {
        if (trim((string)($item['course'] ?? '')) === $value) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve Monday / Forge course value (not the customer-facing Course label).
 *
 * Prefers mondaySpecificCourse / specificCourse when they are course values,
 * then trainer course select, then the matrix row for the org package.
 *
 * @param array<string, mixed> $enquiry
 * @param array<string, mixed> $formData
 */
function trainingMatrixResolveCourseValue(array $enquiry = [], array $formData = []): string
{
    $acceptCandidate = static function (string $candidate): string {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }
        if (trainingMatrixIsCourseValue($candidate)) {
            return $candidate;
        }
        // Bare Course label must not be sent to Monday Specific Course or Forge course_code.
        if (trainingMatrixIsCourseLabel($candidate)) {
            return '';
        }

        // Trainer / non-matrix codes: keep as-is.
        return $candidate;
    };

    foreach ([
        $formData['mondaySpecificCourse'] ?? '',
        $formData['specificCourse'] ?? '',
    ] as $raw) {
        $resolved = $acceptCandidate((string)$raw);
        if ($resolved !== '') {
            return $resolved;
        }
    }

    $trainerCourse = trim((string)($enquiry['trainer_course_select'] ?? $formData['trainerCourseSelect'] ?? ''));
    if ($trainerCourse !== '') {
        $resolved = $acceptCandidate($trainerCourse);
        if ($resolved !== '') {
            return $resolved;
        }

        return $trainerCourse;
    }

    $sector = trim((string)($formData['sector'] ?? $enquiry['sector'] ?? ''));
    $orgCourse = trim((string)($enquiry['org_course'] ?? $formData['orgCourse'] ?? ''));
    $format = trim((string)($formData['courseFormat'] ?? $enquiry['course_format'] ?? ''));
    if ($format === '') {
        $format = trim((string)($formData['deliveryPreference'] ?? ''));
    }
    $subOption = trim((string)($formData['formatSubOption'] ?? $enquiry['format_sub_option'] ?? ''));
    if ($subOption === '') {
        $subOption = trim((string)($formData['courseStyle'] ?? ''));
    }

    if ($sector !== '' && $orgCourse !== '' && $format !== '' && $subOption !== '') {
        $row = trainingMatrixFindRow($sector, $orgCourse, $format, $subOption);
        if ($row !== null) {
            $courseValue = trim((string)($row['courseValue'] ?? ''));
            if ($courseValue !== '') {
                return $courseValue;
            }
        }
    }

    if ($orgCourse !== '' && trainingMatrixIsCourseValue($orgCourse)) {
        return $orgCourse;
    }

    return '';
}

/**
 * @return array<string, mixed>|null
 */
function trainingMatrixFindRow(string $sector, string $orgCourse, string $format, string $subOption): ?array
{
    $sector = trim($sector);
    $format = trim($format);
    $subOption = trim($subOption);
    if ($sector === '' || $orgCourse === '' || $format === '' || $subOption === '') {
        return null;
    }

    foreach (trainingMatrixAll() as $item) {
        if ($item['sector'] !== $sector) {
            continue;
        }
        if ($item['format'] !== $format || $item['subOption'] !== $subOption) {
            continue;
        }
        if (trainingMatrixMatchesOrgCourse($item, $orgCourse)) {
            return $item;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $row
 * @return array{min: int, max: int}
 */
function trainingMatrixAttendeeBounds(array $row): array
{
    $min = (int)($row['minAttendees'] ?? 1);
    $maxCap = $row['maxCap'] ?? null;
    $max = ($maxCap !== null && is_numeric($maxCap))
        ? (int)$maxCap
        : TRAINING_MATRIX_MAX_ATTENDEES;

    return ['min' => max(1, $min), 'max' => max($min, $max)];
}

function trainingMatrixTrainersRequired(int $attendees): int
{
    if ($attendees < 1) {
        return 1;
    }

    return (int)max(1, (int)ceil($attendees / 20));
}

/**
 * @param array<string, mixed> $row
 */
function trainingMatrixCalculateQuoteValue(array $row, int $attendees): ?float
{
    if ($attendees < 1) {
        return null;
    }

    $pricing = $row['pricing'] ?? null;
    if (!is_array($pricing)) {
        return null;
    }

    $kind = (string)($pricing['kind'] ?? '');

    if ($kind === 'flat' || $kind === 'flatUnlimited') {
        return (float)($pricing['amount'] ?? 0);
    }

    if ($kind === 'perDelegate') {
        return (float)($pricing['rate'] ?? 0) * $attendees;
    }

    if ($kind === 'addonBands') {
        $baseTo12 = (float)($pricing['baseTo12'] ?? 0);
        $per13to20 = (float)($pricing['per13to20'] ?? 0);
        $fixed21Plus = (float)($pricing['fixed21Plus'] ?? 0);
        if ($attendees <= 12) {
            return $baseTo12;
        }
        if ($attendees <= 19) {
            return $baseTo12 + ($attendees - 12) * $per13to20;
        }
        if ($attendees === 20) {
            return $fixed21Plus;
        }

        return $fixed21Plus + ($attendees - 20) * $per13to20;
    }

    if ($kind === 'addonBandsLinear') {
        $baseTo12 = (float)($pricing['baseTo12'] ?? 0);
        $perAfter12 = (float)($pricing['perAfter12'] ?? 0);
        if ($attendees <= 12) {
            return $baseTo12;
        }

        return $baseTo12 + ($attendees - 12) * $perAfter12;
    }

    if ($kind === 'addonBandsPer4621') {
        $baseTo12 = (float)($pricing['baseTo12'] ?? 0);
        $per13to20 = (float)($pricing['per13to20'] ?? 0);
        $per21Plus = (float)($pricing['per21Plus'] ?? 0);
        if ($attendees <= 12) {
            return $baseTo12;
        }
        if ($attendees <= 19) {
            return $baseTo12 + ($attendees - 12) * $per13to20;
        }

        return $per21Plus * $attendees;
    }

    return null;
}

function trainingMatrixFormatQuoteValue(float $value): string
{
    if (abs($value - round($value)) < 0.001) {
        return (string)(int)round($value);
    }

    return number_format($value, 2, '.', '');
}

/**
 * Validate organisation POST fields against the server training matrix and return
 * canonical values for Monday, storage, and email (ignoring client tampering).
 *
 * @param array<string, mixed> $post
 * @return array<string, string>
 */
function trainingMatrixResolveOrganisationSubmission(array $post): array
{
    $sector = trim((string)($post['sector'] ?? ''));
    $orgCourse = trim((string)($post['orgCourse'] ?? ''));
    $courseFormat = trim((string)($post['courseFormat'] ?? ''));
    $formatSubOption = trim((string)($post['formatSubOption'] ?? ''));
    $attendeesRaw = trim((string)($post['matrixAttendees'] ?? ($post['attendees'] ?? '')));

    if ($sector === '' || $orgCourse === '' || $courseFormat === '' || $formatSubOption === '') {
        throw new RuntimeException('Missing organisation course selection.');
    }

    $row = trainingMatrixFindRow($sector, $orgCourse, $courseFormat, $formatSubOption);
    if ($row === null) {
        throw new RuntimeException('Invalid organisation course selection.');
    }

    if ($attendeesRaw === '' || !is_numeric($attendeesRaw)) {
        throw new RuntimeException('Invalid attendee count.');
    }

    $attendees = (int)$attendeesRaw;
    $bounds = trainingMatrixAttendeeBounds($row);
    if ($attendees < $bounds['min'] || $attendees > $bounds['max']) {
        throw new RuntimeException('Attendee count is outside the allowed range for this course.');
    }

    $quoteNumeric = trainingMatrixCalculateQuoteValue($row, $attendees);
    if ($quoteNumeric === null) {
        throw new RuntimeException('Could not calculate quote for this course selection.');
    }

    $organisationCompany = trim((string)($post['organisationCompany'] ?? ''));

    require_once __DIR__ . '/enquiry_logger.php';
    $preferred = enquiryPreferredDateFromPost($post);
    if (! $preferred['enableSecondDate'] && !empty($row['enableSecondDate'])) {
        $preferred['enableSecondDate'] = true;
        $preferred['preferredDate2'] = enquiryPreferredDateOnly((string)($post['preferredDate2'] ?? ($post['preferredDateTime2'] ?? '')));
        if ($preferred['dateNotSure']) {
            $preferred['preferredDate2'] = '';
        }
    }

    return [
        'sector' => (string)$row['sector'],
        'orgCourse' => (string)$row['course'],
        'specificCourse' => (string)$row['courseValue'],
        'deliveryPreference' => (string)$row['format'],
        'courseStyle' => (string)$row['subOption'],
        'attendees' => (string)$attendees,
        'organisationCompany' => $organisationCompany,
        'trainersRequired' => (string)trainingMatrixTrainersRequired($attendees),
        'quoteValue' => trainingMatrixFormatQuoteValue($quoteNumeric),
        'preferredDateTime' => $preferred['preferredDateTime'],
        'preferredDate2' => $preferred['preferredDate2'],
        'enableSecondDate' => $preferred['enableSecondDate'] ? '1' : '0',
        'dateNotSure' => $preferred['dateNotSure'] ? '1' : '0',
        'extraNotes' => trim((string)($post['extraNotes'] ?? '')),
    ];
}

/**
 * Find a training matrix DB row (active or inactive) for Kajabi gating.
 *
 * @param array<string, mixed> $enquiry  Enquiry row or form-like fields
 * @return array{kajabi_enabled:bool,course:string,course_value:string,sector:string,format:string,sub_option:string}|null
 */
function trainingMatrixLookupEntryForEnquiry(array $enquiry): ?array
{
    $formData = [];
    $rawForm = trim((string)($enquiry['form_data_json'] ?? ''));
    if ($rawForm !== '') {
        $decoded = json_decode($rawForm, true);
        if (is_array($decoded)) {
            $formData = $decoded;
        }
    }

    $sector = trim((string)($enquiry['sector'] ?? $formData['sector'] ?? ''));
    $orgCourse = trim((string)($enquiry['org_course'] ?? $formData['orgCourse'] ?? ''));
    $format = trim((string)($enquiry['course_format'] ?? $formData['courseFormat'] ?? ''));
    $subOption = trim((string)($enquiry['format_sub_option'] ?? $formData['formatSubOption'] ?? ''));
    $courseValue = trim((string)(
        $formData['mondaySpecificCourse']
        ?? $formData['specificCourse']
        ?? $enquiry['trainer_course_select']
        ?? $formData['trainerCourseSelect']
        ?? ''
    ));

    if (!function_exists('appDatabasePdo')) {
        require_once __DIR__ . '/database_bridge.php';
    }
    $pdo = appDatabasePdo();
    if ($pdo === null) {
        // Fall back to in-memory active matrix (defaults / cached rows).
        if ($sector !== '' && $orgCourse !== '' && $format !== '' && $subOption !== '') {
            $row = trainingMatrixFindRow($sector, $orgCourse, $format, $subOption);
            if ($row !== null) {
                return [
                    'kajabi_enabled' => array_key_exists('kajabiEnabled', $row)
                        ? (bool)$row['kajabiEnabled']
                        : true,
                    'course' => (string)($row['course'] ?? ''),
                    'course_value' => (string)($row['courseValue'] ?? ''),
                    'sector' => (string)($row['sector'] ?? ''),
                    'format' => (string)($row['format'] ?? ''),
                    'sub_option' => (string)($row['subOption'] ?? ''),
                ];
            }
        }

        return null;
    }

    try {
        if ($sector !== '' && $orgCourse !== '' && $format !== '' && $subOption !== '') {
            $stmt = $pdo->prepare(
                'SELECT sector, course, course_value, format, sub_option, kajabi_enabled
                 FROM training_matrix_entries
                 WHERE sector = :sector
                   AND (course = :course OR course_value = :course_value_match)
                   AND format = :format
                   AND sub_option = :sub_option
                 ORDER BY is_active DESC, id ASC
                 LIMIT 1'
            );
            $stmt->execute([
                ':sector' => $sector,
                ':course' => $orgCourse,
                ':course_value_match' => $orgCourse,
                ':format' => $format,
                ':sub_option' => $subOption,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return [
                    'kajabi_enabled' => array_key_exists('kajabi_enabled', $row)
                        ? (bool)$row['kajabi_enabled']
                        : true,
                    'course' => (string)($row['course'] ?? ''),
                    'course_value' => (string)($row['course_value'] ?? ''),
                    'sector' => (string)($row['sector'] ?? ''),
                    'format' => (string)($row['format'] ?? ''),
                    'sub_option' => (string)($row['sub_option'] ?? ''),
                ];
            }
        }

        if ($courseValue !== '') {
            $stmt = $pdo->prepare(
                'SELECT sector, course, course_value, format, sub_option, kajabi_enabled
                 FROM training_matrix_entries
                 WHERE course_value = :course_value
                 ORDER BY is_active DESC, id ASC
                 LIMIT 1'
            );
            $stmt->execute([':course_value' => $courseValue]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return [
                    'kajabi_enabled' => array_key_exists('kajabi_enabled', $row)
                        ? (bool)$row['kajabi_enabled']
                        : true,
                    'course' => (string)($row['course'] ?? ''),
                    'course_value' => (string)($row['course_value'] ?? ''),
                    'sector' => (string)($row['sector'] ?? ''),
                    'format' => (string)($row['format'] ?? ''),
                    'sub_option' => (string)($row['sub_option'] ?? ''),
                ];
            }
        }
    } catch (Throwable) {
        // Column may not exist yet before migration — treat as enabled.
        return null;
    }

    return null;
}

/**
 * Whether Kajabi auto-invite should run for this enquiry's course combination.
 * Unknown / unmatched combinations default to enabled (legacy behaviour).
 *
 * @param array<string, mixed> $enquiry
 */
function trainingMatrixKajabiEnabledForEnquiry(array $enquiry): bool
{
    $row = trainingMatrixLookupEntryForEnquiry($enquiry);

    if ($row === null) {
        return true;
    }

    return !empty($row['kajabi_enabled']);
}
