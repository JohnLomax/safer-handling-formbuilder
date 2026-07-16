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
