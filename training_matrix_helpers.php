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
        'dateNotSure' => $preferred['dateNotSure'] ? '1' : '0',
        'extraNotes' => trim((string)($post['extraNotes'] ?? '')),
    ];
}
