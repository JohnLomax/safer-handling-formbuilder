<?php
declare(strict_types=1);

require_once __DIR__ . '/database_bridge.php';

/**
 * Shared enquiry tracking and journey logging for the form and Laravel admin.
 */

function enquiryLoggerPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = appDatabasePdo();
    if (!$pdo instanceof PDO) {
        throw new RuntimeException('Database connection is unavailable.');
    }

    enquiryLoggerEnsureSchema($pdo);

    return $pdo;
}

function enquiryLoggerEnsureSchema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    // MySQL schema is owned by Laravel migrations — only bootstrap SQLite locally.
    if (appDatabaseDriver() !== 'sqlite') {
        $ready = true;

        return;
    }

    $path = appDatabasePath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS enquiries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            enquiry_type TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT "in_progress",
            audience_type TEXT,
            personal_goal TEXT,
            trainer_course_select TEXT,
            booking_via_company INTEGER DEFAULT 0,
            trainer_attendees INTEGER,
            sector TEXT,
            org_course TEXT,
            course_format TEXT,
            format_sub_option TEXT,
            matrix_attendees INTEGER,
            organisation_company TEXT,
            preferred_date_time TEXT,
            date_not_sure INTEGER DEFAULT 0,
            attendees INTEGER,
            extra_notes TEXT,
            form_data_json TEXT,
            monday_item_id TEXT,
            monday_synced_at TEXT,
            quote_email_sent_at TEXT,
            submitted_at TEXT,
            created_at TEXT,
            updated_at TEXT
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS enquiry_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            enquiry_id INTEGER NOT NULL,
            event_type TEXT NOT NULL,
            message TEXT NOT NULL,
            metadata TEXT,
            created_at TEXT NOT NULL,
            FOREIGN KEY (enquiry_id) REFERENCES enquiries(id) ON DELETE CASCADE
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_enquiries_email ON enquiries(email)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_enquiry_events_enquiry_id ON enquiry_events(enquiry_id)');

    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'resume_token', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'resume_email_sent_at', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'organisation_company', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'booking_details_json', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'booking_email_sent_at', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'booking_submitted_at', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'terms_accepted_at', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_invoice_id', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_invoice_number', 'TEXT');
    enquiryLoggerEnsureColumn($pdo, 'enquiries', 'xero_invoice_created_at', 'TEXT');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_enquiries_resume_token ON enquiries(resume_token)');

    $ready = true;
}

function enquiryLoggerEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    // MySQL schema is owned by Laravel migrations — never run SQLite PRAGMA there.
    if (appDatabaseDriver() !== 'sqlite') {
        return;
    }

    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll() : [];

    foreach ($columns as $info) {
        if (($info['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
}

function enquiryLoggerNow(): string
{
    return gmdate('Y-m-d H:i:s');
}

function enquiryLoggerParseId(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $id = (int)$value;

    return $id > 0 ? $id : null;
}

function enquiryLoggerResolveId(?int $enquiryId, ?string $email = null): ?int
{
    if ($enquiryId !== null && $enquiryId > 0) {
        $pdo = enquiryLoggerPdo();
        $stmt = $pdo->prepare('SELECT id FROM enquiries WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $enquiryId]);
        $row = $stmt->fetch();

        return $row ? (int)$row['id'] : null;
    }

    $email = trim((string)$email);
    if ($email === '') {
        return null;
    }

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT id FROM enquiries
         WHERE email = :email
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();

    return $row ? (int)$row['id'] : null;
}

/**
 * @param array<string, mixed> $post
 */
function enquiryLoggerCreateInitial(array $post): int
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'INSERT INTO enquiries (
            name, email, enquiry_type, status, form_data_json, created_at, updated_at
        ) VALUES (
            :name, :email, :enquiry_type, :status, :form_data_json, :created_at, :updated_at
        )'
    );

    $stmt->execute([
        ':name' => trim((string)($post['name'] ?? '')),
        ':email' => trim((string)($post['email'] ?? '')),
        ':enquiry_type' => trim((string)($post['enquiryType'] ?? '')),
        ':status' => 'in_progress',
        ':form_data_json' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $id = (int)$pdo->lastInsertId();
    enquiryLoggerEnsureResumeToken($id);
    enquiryLoggerEvent($id, 'details_entered', 'Initial enquiry details entered on the form.');

    return $id;
}

function enquiryLoggerPostResumeToken(array $post): string
{
    return trim((string)($post['resumeToken'] ?? $post['token'] ?? ''));
}

/**
 * Authenticated enquiry reuse for Edit Enquiry / same-session continue.
 * Requires enquiryId + matching resume token. Without a valid token a new
 * enquiry flow is always started (even if the email already exists).
 */
function enquiryLoggerResolveAuthenticatedEnquiryId(array $post): ?int
{
    $enquiryId = enquiryLoggerPostId($post);
    $token = enquiryLoggerPostResumeToken($post);
    if ($enquiryId === null || $token === '') {
        return null;
    }

    $row = enquiryLoggerGetForResume($enquiryId, $token);
    if ($row === null) {
        return null;
    }

    if (!enquiryLoggerIsEligibleForSubmitReuse($enquiryId)) {
        return null;
    }

    return $enquiryId;
}

function enquiryLoggerFindOrCreateInitial(array $post): int
{
    $existingId = enquiryLoggerResolveAuthenticatedEnquiryId($post);
    if ($existingId !== null) {
        enquiryLoggerUpdateFromPost($existingId, $post, 'in_progress');
        enquiryLoggerEnsureResumeToken($existingId);

        return $existingId;
    }

    return enquiryLoggerCreateInitial($post);
}

function enquiryLoggerGetMondayItemId(int $enquiryId): ?string
{
    if ($enquiryId <= 0) {
        return null;
    }

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT monday_item_id FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $itemId = trim((string)($row['monday_item_id'] ?? ''));

    return $itemId !== '' ? $itemId : null;
}

function enquiryLoggerEnsureResumeToken(int $enquiryId): string
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT resume_token FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $existing = trim((string)($row['resume_token'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }

    $token = bin2hex(random_bytes(24));
    $stmt = $pdo->prepare(
        'UPDATE enquiries SET resume_token = :resume_token, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':resume_token' => $token,
        ':updated_at' => enquiryLoggerNow(),
    ]);

    return $token;
}

/**
 * @return array<string, mixed>|null
 */
function enquiryLoggerGetForResume(int $enquiryId, string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT * FROM enquiries
         WHERE id = :id
           AND resume_token = :token
           AND status IN (
             :status_in_progress,
             :status_contacted,
             :status_submitted,
             :status_quote_sent,
             :status_quote_accepted,
             :status_failed
           )
         LIMIT 1'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':token' => $token,
        ':status_in_progress' => 'in_progress',
        ':status_contacted' => 'contacted',
        ':status_submitted' => 'submitted',
        ':status_quote_sent' => 'quote_sent',
        ':status_quote_accepted' => 'quote_accepted',
        ':status_failed' => 'failed',
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function enquiryLoggerResumeEmailAlreadySent(int $enquiryId): bool
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT resume_email_sent_at FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();

    return trim((string)($row['resume_email_sent_at'] ?? '')) !== '';
}

function enquiryLoggerQuoteEmailAlreadySent(int $enquiryId): bool
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT quote_email_sent_at FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();

    return trim((string)($row['quote_email_sent_at'] ?? '')) !== '';
}

function enquiryLoggerHasEvent(int $enquiryId, string $eventType): bool
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT 1 FROM enquiry_events WHERE enquiry_id = :enquiry_id AND event_type = :event_type LIMIT 1'
    );
    $stmt->execute([
        ':enquiry_id' => $enquiryId,
        ':event_type' => $eventType,
    ]);

    return (bool)$stmt->fetchColumn();
}

function enquiryLoggerLeadNotificationAlreadySent(int $enquiryId): bool
{
    return enquiryLoggerHasEvent($enquiryId, 'lead_notification_sent');
}

/**
 * Relative journey order — higher means further along.
 */
function enquiryLoggerStatusRank(string $status): int
{
    return match ($status) {
        'in_progress' => 1,
        'failed' => 2,
        'submitted' => 3,
        'contacted' => 4,
        'quote_sent' => 5,
        'quote_accepted' => 6,
        default => 0,
    };
}

/**
 * Whether an explicit enquiryId from the form (including Edit Enquiry resume)
 * can be updated in place. Quote/lead email flags must NOT force a new row —
 * those only gate whether emails are sent again on that same enquiry.
 *
 * New bookings without an enquiryId still create a fresh row, so repeat
 * customers keep receiving emails.
 */
function enquiryLoggerIsEligibleForSubmitReuse(int $enquiryId): bool
{
    if ($enquiryId <= 0) {
        return false;
    }

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT id, status FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }

    // Booking/terms already completed — do not reopen via enquiry form submit.
    if ((string)($row['status'] ?? '') === 'quote_accepted') {
        return false;
    }

    return true;
}

function enquiryLoggerMarkResumeEmailSent(int $enquiryId): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET resume_email_sent_at = :resume_email_sent_at, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':resume_email_sent_at' => $now,
        ':updated_at' => $now,
    ]);
}

/**
 * @param array<string, mixed> $post
 */
/**
 * Normalise preferred date / "not sure" from enquiry form POST.
 * Stored as YYYY-MM-DD (date only). "Not sure" clears the date.
 *
 * @param array<string, mixed> $post
 * @return array{preferredDateTime: string, dateNotSure: bool}
 */
function enquiryPreferredDateFromPost(array $post): array
{
    $rawNotSure = $post['dateNotSure'] ?? null;
    $dateNotSure = $rawNotSure !== null
        && $rawNotSure !== ''
        && $rawNotSure !== '0'
        && $rawNotSure !== 0
        && $rawNotSure !== false
        && strtolower((string)$rawNotSure) !== 'false';

    $preferredDate = trim((string)($post['preferredDate'] ?? ''));
    $pdt = trim((string)($post['preferredDateTime'] ?? ''));
    if ($preferredDate === '' && $pdt !== '') {
        $preferredDate = $pdt;
    }

    $dateOnly = '';
    if ($preferredDate !== '') {
        $preferredDate = str_replace(' ', 'T', $preferredDate);
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', substr($preferredDate, 0, 10))
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $preferredDate)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $preferredDate);
        if ($dt !== false) {
            $dateOnly = $dt->format('Y-m-d');
        }
    }

    if ($dateNotSure) {
        $dateOnly = '';
    }

    return [
        'preferredDateTime' => $dateOnly,
        'dateNotSure' => $dateNotSure,
    ];
}

/**
 * Apply normalised preferred-date fields onto a POST/form-data array for storage.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function enquiryPostWithNormalisedPreferredDate(array $post): array
{
    $preferred = enquiryPreferredDateFromPost($post);
    $out = $post;
    unset($out['preferredTime']);
    if ($preferred['preferredDateTime'] !== '') {
        $out['preferredDateTime'] = $preferred['preferredDateTime'];
        $out['preferredDate'] = $preferred['preferredDateTime'];
    } else {
        unset($out['preferredDateTime'], $out['preferredDate']);
    }
    if ($preferred['dateNotSure']) {
        $out['dateNotSure'] = 'on';
    } else {
        unset($out['dateNotSure']);
    }

    return $out;
}

function enquiryLoggerUpdateFromPost(int $enquiryId, array $post, string $status = 'in_progress'): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();
    $post = enquiryPostWithNormalisedPreferredDate($post);
    $preferred = enquiryPreferredDateFromPost($post);

    $currentStmt = $pdo->prepare('SELECT status FROM enquiries WHERE id = :id LIMIT 1');
    $currentStmt->execute([':id' => $enquiryId]);
    $currentStatus = (string)($currentStmt->fetchColumn() ?: '');
    // Never move an enquiry backwards in the journey (e.g. quote_sent → in_progress).
    if (
        $currentStatus !== ''
        && enquiryLoggerStatusRank($currentStatus) > enquiryLoggerStatusRank($status)
    ) {
        $status = $currentStatus;
    }

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            name = :name,
            email = :email,
            enquiry_type = :enquiry_type,
            status = :status,
            audience_type = :audience_type,
            personal_goal = :personal_goal,
            trainer_course_select = :trainer_course_select,
            booking_via_company = :booking_via_company,
            trainer_attendees = :trainer_attendees,
            sector = :sector,
            org_course = :org_course,
            course_format = :course_format,
            format_sub_option = :format_sub_option,
            matrix_attendees = :matrix_attendees,
            organisation_company = :organisation_company,
            preferred_date_time = :preferred_date_time,
            date_not_sure = :date_not_sure,
            attendees = :attendees,
            extra_notes = :extra_notes,
            form_data_json = :form_data_json,
            updated_at = :updated_at
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $enquiryId,
        ':name' => trim((string)($post['name'] ?? '')),
        ':email' => trim((string)($post['email'] ?? '')),
        ':enquiry_type' => trim((string)($post['enquiryType'] ?? '')),
        ':status' => $status,
        ':audience_type' => trim((string)($post['audienceType'] ?? '')),
        ':personal_goal' => trim((string)($post['personalGoal'] ?? '')),
        ':trainer_course_select' => trim((string)($post['trainerCourseSelect'] ?? '')),
        ':booking_via_company' => isset($post['bookingViaCompany']) ? 1 : 0,
        ':trainer_attendees' => isset($post['trainerAttendees']) && $post['trainerAttendees'] !== '' ? (int)$post['trainerAttendees'] : null,
        ':sector' => trim((string)($post['sector'] ?? '')),
        ':org_course' => trim((string)($post['orgCourse'] ?? '')),
        ':course_format' => trim((string)($post['courseFormat'] ?? '')),
        ':format_sub_option' => trim((string)($post['formatSubOption'] ?? '')),
        ':matrix_attendees' => isset($post['matrixAttendees']) && $post['matrixAttendees'] !== '' ? (int)$post['matrixAttendees'] : null,
        ':organisation_company' => trim((string)($post['organisationCompany'] ?? '')),
        ':preferred_date_time' => $preferred['preferredDateTime'],
        ':date_not_sure' => $preferred['dateNotSure'] ? 1 : 0,
        ':attendees' => isset($post['attendees']) && $post['attendees'] !== '' ? (int)$post['attendees'] : null,
        ':extra_notes' => trim((string)($post['extraNotes'] ?? '')),
        ':form_data_json' => json_encode($post, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':updated_at' => $now,
    ]);
}

function enquiryLoggerMarkSubmitted(int $enquiryId): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            submitted_at = COALESCE(submitted_at, :submitted_at),
            updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':submitted_at' => $now,
        ':updated_at' => $now,
    ]);

    // Keep quote_sent / quote_accepted / contacted when the customer edits later.
    enquiryLoggerSetStatusIfNotPast($enquiryId, 'submitted', ['contacted', 'quote_sent', 'quote_accepted']);
}

function enquiryLoggerMarkContacted(int $enquiryId): void
{
    enquiryLoggerSetStatusIfNotPast($enquiryId, 'contacted', ['quote_sent', 'quote_accepted']);
}

/**
 * Quote Sent — set when Xero has created/sent the quote email to the customer.
 */
function enquiryLoggerMarkQuoteSent(int $enquiryId): void
{
    enquiryLoggerSetStatusIfNotPast($enquiryId, 'quote_sent', ['quote_accepted']);
}

/**
 * Quote Accepted — set when the customer accepts the quote (booking / terms form).
 */
function enquiryLoggerMarkQuoteAccepted(int $enquiryId): void
{
    enquiryLoggerSetStatusIfNotPast($enquiryId, 'quote_accepted', []);
}

/**
 * Advance enquiry status without regressing past a later stage.
 *
 * @param list<string> $doNotOverwrite
 */
function enquiryLoggerSetStatusIfNotPast(int $enquiryId, string $status, array $doNotOverwrite = []): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare('SELECT status FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    $current = (string)($row['status'] ?? '');
    if ($current !== '' && in_array($current, $doNotOverwrite, true)) {
        return;
    }

    $update = $pdo->prepare(
        'UPDATE enquiries SET status = :status, updated_at = :updated_at WHERE id = :id'
    );
    $update->execute([
        ':id' => $enquiryId,
        ':status' => $status,
        ':updated_at' => $now,
    ]);
}

function enquiryLoggerMarkMondaySynced(int $enquiryId, ?string $mondayItemId = null): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            monday_item_id = COALESCE(:monday_item_id, monday_item_id),
            monday_synced_at = COALESCE(monday_synced_at, :monday_synced_at),
            updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':monday_item_id' => $mondayItemId,
        ':monday_synced_at' => $now,
        ':updated_at' => $now,
    ]);
}

function enquiryLoggerMarkQuoteEmailSent(int $enquiryId): void
{
    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET quote_email_sent_at = :quote_email_sent_at, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':quote_email_sent_at' => $now,
        ':updated_at' => $now,
    ]);
}

function enquiryLoggerBookingEmailAlreadySent(int $enquiryId): bool
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT booking_email_sent_at FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();

    return trim((string)($row['booking_email_sent_at'] ?? '')) !== '';
}

function enquiryLoggerMarkBookingEmailSent(int $enquiryId): void
{
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'booking_email_sent_at', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET booking_email_sent_at = :booking_email_sent_at, updated_at = :updated_at WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':booking_email_sent_at' => $now,
        ':updated_at' => $now,
    ]);
}

/**
 * @param array<string, mixed> $details
 */
function enquiryLoggerSaveBookingDetails(int $enquiryId, array $details): void
{
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'booking_details_json', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'booking_submitted_at', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'terms_accepted_at', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();
    $termsAccepted = !empty($details['termsAccepted']);

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            booking_details_json = :booking_details_json,
            booking_submitted_at = :booking_submitted_at,
            terms_accepted_at = :terms_accepted_at,
            updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':booking_details_json' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':booking_submitted_at' => $now,
        ':terms_accepted_at' => $termsAccepted ? $now : null,
        ':updated_at' => $now,
    ]);
}

/**
 * @return array<string, mixed>|null
 */
function enquiryLoggerGetBookingDetails(int $enquiryId): ?array
{
    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare(
        'SELECT booking_details_json, booking_submitted_at, terms_accepted_at, booking_email_sent_at
         FROM enquiries WHERE id = :id LIMIT 1'
    );
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $decoded = json_decode((string)($row['booking_details_json'] ?? ''), true);

    return [
        'details' => is_array($decoded) ? $decoded : null,
        'booking_submitted_at' => $row['booking_submitted_at'] ?? null,
        'terms_accepted_at' => $row['terms_accepted_at'] ?? null,
        'booking_email_sent_at' => $row['booking_email_sent_at'] ?? null,
    ];
}

function enquiryLoggerMarkXeroQuoteSent(
    int $enquiryId,
    string $contactId,
    string $quoteId,
    string $quoteNumber
): void {
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_contact_id', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_quote_id', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_quote_number', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_quote_sent_at', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            xero_contact_id = :xero_contact_id,
            xero_quote_id = :xero_quote_id,
            xero_quote_number = :xero_quote_number,
            xero_quote_sent_at = :xero_quote_sent_at,
            updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':xero_contact_id' => $contactId !== '' ? $contactId : null,
        ':xero_quote_id' => $quoteId !== '' ? $quoteId : null,
        ':xero_quote_number' => $quoteNumber !== '' ? $quoteNumber : null,
        ':xero_quote_sent_at' => $now,
        ':updated_at' => $now,
    ]);

    // Xero quote emailed to the customer = Quote Sent.
    enquiryLoggerMarkQuoteSent($enquiryId);
}

function enquiryLoggerXeroInvoiceAlreadyCreated(int $enquiryId): bool
{
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_id', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $stmt = $pdo->prepare('SELECT xero_invoice_id FROM enquiries WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $enquiryId]);
    $row = $stmt->fetch();

    return trim((string)($row['xero_invoice_id'] ?? '')) !== '';
}

function enquiryLoggerMarkXeroInvoiceCreated(
    int $enquiryId,
    string $invoiceId,
    string $invoiceNumber
): void {
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_id', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_number', 'TEXT');
    enquiryLoggerEnsureColumn(enquiryLoggerPdo(), 'enquiries', 'xero_invoice_created_at', 'TEXT');

    $pdo = enquiryLoggerPdo();
    $now = enquiryLoggerNow();

    $stmt = $pdo->prepare(
        'UPDATE enquiries SET
            xero_invoice_id = :xero_invoice_id,
            xero_invoice_number = :xero_invoice_number,
            xero_invoice_created_at = COALESCE(xero_invoice_created_at, :xero_invoice_created_at),
            updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':id' => $enquiryId,
        ':xero_invoice_id' => $invoiceId !== '' ? $invoiceId : null,
        ':xero_invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
        ':xero_invoice_created_at' => $now,
        ':updated_at' => $now,
    ]);
}

/**
 * @param array<string, mixed>|null $metadata
 */
function enquiryLoggerEvent(int $enquiryId, string $eventType, string $message, ?array $metadata = null): void
{
    $pdo = enquiryLoggerPdo();

    $stmt = $pdo->prepare(
        'INSERT INTO enquiry_events (enquiry_id, event_type, message, metadata, created_at)
         VALUES (:enquiry_id, :event_type, :message, :metadata, :created_at)'
    );

    $stmt->execute([
        ':enquiry_id' => $enquiryId,
        ':event_type' => $eventType,
        ':message' => $message,
        ':metadata' => $metadata !== null
            ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null,
        ':created_at' => enquiryLoggerNow(),
    ]);
}

function enquiryLoggerPostId(array $post): ?int
{
    return enquiryLoggerParseId($post['enquiryId'] ?? null);
}

function enquiryLoggerSafe(callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        // Enquiry logging must never break the public form.
    }
}

/**
 * @param array<string, mixed> $post
 */
function enquiryLoggerMondayFieldsUpdated(array $post, string $message, ?string $mondayItemId = null): void
{
    $enquiryId = enquiryLoggerResolveAuthenticatedEnquiryId($post);
    if ($enquiryId === null) {
        return;
    }

    enquiryLoggerMarkMondaySynced($enquiryId, $mondayItemId);
    enquiryLoggerEvent($enquiryId, 'monday_fields_updated', $message);
}
