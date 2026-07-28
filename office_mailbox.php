<?php
declare(strict_types=1);

/**
 * Office inbox auto-reply for unreplied inbound mail (email.html via Brevo).
 */

function officeAutoReplyEnabled(): bool
{
    $fromEnv = getenv('OFFICE_AUTO_REPLY_ENABLED');
    if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
        return filter_var($fromEnv, FILTER_VALIDATE_BOOLEAN);
    }

    return (bool) ($GLOBALS['officeAutoReplyEnabled'] ?? false);
}

function officeAutoReplyHours(): int
{
    $raw = appConfigValue('OFFICE_AUTO_REPLY_HOURS', 'officeAutoReplyHours', '8');
    $hours = (int) $raw;

    return $hours > 0 ? $hours : 8;
}

/**
 * @return array{
 *   host:string,
 *   port:int,
 *   encryption:string,
 *   username:string,
 *   password:string,
 *   inbox_folder:string,
 *   sent_folder:string
 * }
 */
function officeImapConfig(): array
{
    $port = (int) appConfigValue('OFFICE_IMAP_PORT', 'officeImapPort', '993');
    if ($port <= 0) {
        $port = 993;
    }

    $encryption = strtolower(trim(appConfigValue('OFFICE_IMAP_ENCRYPTION', 'officeImapEncryption', 'ssl')));
    if (! in_array($encryption, ['ssl', 'tls', 'none'], true)) {
        $encryption = 'ssl';
    }

    return [
        'host' => appConfigValue('OFFICE_IMAP_HOST', 'officeImapHost', 'outlook.office365.com'),
        'port' => $port,
        'encryption' => $encryption,
        'username' => appConfigValue('OFFICE_IMAP_USERNAME', 'officeImapUsername', brevoOfficeEmail()),
        'password' => appConfigValue('OFFICE_IMAP_PASSWORD', 'officeImapPassword', ''),
        'inbox_folder' => appConfigValue('OFFICE_IMAP_INBOX_FOLDER', 'officeImapInboxFolder', 'INBOX'),
        'sent_folder' => appConfigValue('OFFICE_IMAP_SENT_FOLDER', 'officeImapSentFolder', 'Sent Items'),
    ];
}

function officeImapMailboxPath(string $folder): string
{
    $config = officeImapConfig();
    $flags = match ($config['encryption']) {
        'ssl' => '/imap/ssl',
        'tls' => '/imap/tls',
        default => '/imap',
    };

    // novalidate-cert helps some shared hosts; Outlook usually validates fine.
    return sprintf('{%s:%d%s}%s', $config['host'], $config['port'], $flags, $folder);
}

function officeImapOpen(string $folder)
{
    if (! function_exists('imap_open')) {
        throw new RuntimeException('PHP IMAP extension is not installed. Enable ext-imap to use office auto-replies.');
    }

    $config = officeImapConfig();
    if ($config['host'] === '' || $config['username'] === '' || $config['password'] === '') {
        throw new RuntimeException('Office IMAP host, username, and password must be configured.');
    }

    imap_errors();
    imap_alerts();

    $mailbox = officeImapMailboxPath($folder);
    $stream = @imap_open($mailbox, $config['username'], $config['password'], 0, 1);
    if ($stream === false) {
        $errors = imap_errors() ?: [];
        throw new RuntimeException('Unable to connect to office IMAP mailbox: ' . implode('; ', $errors));
    }

    return $stream;
}

function officeImapClose($stream): void
{
    if (is_resource($stream) || (is_object($stream) && get_class($stream) === 'IMAP\Connection')) {
        @imap_close($stream);
    }
}

function officeAutoReplyOwnAddresses(): array
{
    $addresses = array_filter([
        strtolower(trim(brevoOfficeEmail())),
        strtolower(trim(brevoContactEmail())),
        strtolower(trim((string) (brevoSenderConfig()['email'] ?? ''))),
        strtolower(trim(officeImapConfig()['username'])),
    ]);

    return array_values(array_unique($addresses));
}

function officeImapExtractEmail(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/<([^>]+)>/', $raw, $matches) === 1) {
        return strtolower(trim($matches[1]));
    }

    if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        return strtolower($raw);
    }

    return '';
}

function officeImapExtractName(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }

    if (preg_match('/^(.+?)\s*<[^>]+>$/', $raw, $matches) === 1) {
        return trim($matches[1], " \t\"'");
    }

    return '';
}

function officeImapNormalizeMessageId(?string $messageId): string
{
    $messageId = trim((string) $messageId);
    if ($messageId === '') {
        return '';
    }

    return trim($messageId, " \t<>");
}

/**
 * @return list<array{
 *   uid:int,
 *   message_id:string,
 *   from_email:string,
 *   from_name:string,
 *   subject:string,
 *   received_at:string
 * }>
 */
function officeImapFindUnrepliedCandidates(int $hours, int $lookbackDays = 14): array
{
    $inbox = officeImapOpen(officeImapConfig()['inbox_folder']);
    $candidates = [];

    try {
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $lookbackDays) . ' days')
            ->format('d-M-Y');

        $uids = imap_search($inbox, 'SINCE "' . $since . '"', SE_UID);
        if ($uids === false) {
            return [];
        }

        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . max(1, $hours) . ' hours');
        $own = officeAutoReplyOwnAddresses();

        foreach ($uids as $uid) {
            $uid = (int) $uid;
            $overviewList = imap_fetch_overview($inbox, (string) $uid, FT_UID);
            if (! is_array($overviewList) || $overviewList === []) {
                continue;
            }
            $overview = $overviewList[0];

            $messageId = officeImapNormalizeMessageId((string) ($overview->message_id ?? ''));
            if ($messageId === '') {
                continue;
            }

            $fromRaw = (string) ($overview->from ?? '');
            $fromEmail = officeImapExtractEmail($fromRaw);
            if ($fromEmail === '' || in_array($fromEmail, $own, true)) {
                continue;
            }

            // Skip automated / bounce / list mail.
            $headersRaw = (string) imap_fetchheader($inbox, $uid, FT_UID);
            if (preg_match('/^(Auto-Submitted|X-Auto-Response-Suppress|List-Unsubscribe|Precedence):\s*(.+)$/im', $headersRaw) === 1) {
                if (preg_match('/^Auto-Submitted:\s*no\b/im', $headersRaw) !== 1) {
                    continue;
                }
            }
            if (preg_match('/^(mailer-daemon|postmaster|noreply|no-reply)@/i', $fromEmail) === 1) {
                continue;
            }

            $dateRaw = (string) ($overview->date ?? '');
            $received = $dateRaw !== '' ? date_create($dateRaw) : false;
            if ($received === false) {
                continue;
            }
            $receivedAt = DateTimeImmutable::createFromMutable($received)->setTimezone(new DateTimeZone('UTC'));
            if ($receivedAt > $cutoff) {
                continue;
            }

            $candidates[] = [
                'uid' => $uid,
                'message_id' => $messageId,
                'from_email' => $fromEmail,
                'from_name' => officeImapExtractName($fromRaw),
                'subject' => trim((string) ($overview->subject ?? '')),
                'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            ];
        }
    } finally {
        officeImapClose($inbox);
    }

    return $candidates;
}

function officeImapHasHumanReply(string $messageId, string $fromEmail, string $receivedAtUtc): bool
{
    $messageId = officeImapNormalizeMessageId($messageId);
    if ($messageId === '') {
        return false;
    }

    $sent = null;
    $folders = array_values(array_unique(array_filter([
        officeImapConfig()['sent_folder'],
        'Sent Items',
        'Sent',
    ])));

    $lastError = null;
    foreach ($folders as $folder) {
        try {
            $sent = officeImapOpen($folder);
            break;
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    if ($sent === null) {
        throw $lastError ?? new RuntimeException('Unable to open the office Sent folder.');
    }

    try {
        // Prefer In-Reply-To / References match on the original Message-ID.
        $needle = str_replace(['"', '\\'], '', $messageId);
        $uids = imap_search($sent, 'TEXT "' . $needle . '"', SE_UID);
        if (is_array($uids) && $uids !== []) {
            foreach ($uids as $uid) {
                $headersRaw = (string) imap_fetchheader($sent, (int) $uid, FT_UID);
                if (
                    stripos($headersRaw, 'In-Reply-To:') !== false
                    || stripos($headersRaw, 'References:') !== false
                ) {
                    if (stripos($headersRaw, $messageId) !== false) {
                        // Ignore our own Brevo auto-reply marker.
                        if (stripos($headersRaw, 'X-Safer-Handling-Auto-Reply: unreplied-office') !== false) {
                            continue;
                        }

                        return true;
                    }
                }
            }
        }

        // Fallback: any outbound to this address after the inbound arrived.
        $since = date_create($receivedAtUtc . ' UTC');
        if ($since === false) {
            return false;
        }
        $sinceStr = $since->format('d-M-Y');
        $toUids = imap_search($sent, 'TO "' . $fromEmail . '" SINCE "' . $sinceStr . '"', SE_UID);
        if (! is_array($toUids) || $toUids === []) {
            return false;
        }

        foreach ($toUids as $uid) {
            $overviewList = imap_fetch_overview($sent, (string) (int) $uid, FT_UID);
            if (! is_array($overviewList) || $overviewList === []) {
                continue;
            }
            $overview = $overviewList[0];
            $dateRaw = (string) ($overview->date ?? '');
            $sentAt = $dateRaw !== '' ? date_create($dateRaw) : false;
            if ($sentAt === false) {
                continue;
            }
            if ($sentAt->getTimestamp() <= $since->getTimestamp()) {
                continue;
            }

            $headersRaw = (string) imap_fetchheader($sent, (int) $uid, FT_UID);
            if (stripos($headersRaw, 'X-Safer-Handling-Auto-Reply: unreplied-office') !== false) {
                continue;
            }

            return true;
        }

        return false;
    } finally {
        officeImapClose($sent);
    }
}

/**
 * Process unreplied office inbox messages and send email.html auto-replies.
 *
 * @return array{candidates:int,sent:int,skipped:int,failed:int,errors:list<string>}
 */
function officeProcessUnrepliedAutoReplies(bool $dryRun = false): array
{
    $stats = [
        'candidates' => 0,
        'sent' => 0,
        'skipped' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    if (! officeAutoReplyEnabled()) {
        $stats['errors'][] = 'Office unreplied auto-reply is disabled.';

        return $stats;
    }

    if (brevoApiKey() === '') {
        $stats['errors'][] = 'Brevo API key is not configured.';

        return $stats;
    }

    require_once __DIR__ . '/enquiry_logger.php';

    $hours = officeAutoReplyHours();
    $candidates = officeImapFindUnrepliedCandidates($hours);
    $stats['candidates'] = count($candidates);

    $pdo = enquiryLoggerPdo();
    officeAutoReplyEnsureTable($pdo);

    foreach ($candidates as $candidate) {
        $messageId = $candidate['message_id'];
        $existing = officeAutoReplyFindByMessageId($pdo, $messageId);
        if ($existing !== null && trim((string) ($existing['auto_reply_sent_at'] ?? '')) !== '') {
            $stats['skipped']++;
            continue;
        }
        if ($existing !== null && trim((string) ($existing['skipped_reason'] ?? '')) === 'already_replied') {
            $stats['skipped']++;
            continue;
        }

        try {
            if (officeImapHasHumanReply($messageId, $candidate['from_email'], $candidate['received_at'])) {
                officeAutoReplyUpsert($pdo, $candidate, null, 'already_replied');
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $stats['sent']++;
                continue;
            }

            sendOfficeUnrepliedAutoReplyViaBrevo(
                $candidate['from_email'],
                $candidate['from_name'] !== '' ? $candidate['from_name'] : $candidate['from_email'],
                $messageId,
                $candidate['subject']
            );

            officeAutoReplyUpsert($pdo, $candidate, enquiryLoggerNow(), null);
            $stats['sent']++;
        } catch (Throwable $e) {
            $stats['failed']++;
            $stats['errors'][] = $candidate['from_email'] . ': ' . $e->getMessage();
            try {
                officeAutoReplyUpsert($pdo, $candidate, null, 'error: ' . substr($e->getMessage(), 0, 240));
            } catch (Throwable) {
                // ignore persistence failure after send failure
            }
        }
    }

    return $stats;
}

function officeAutoReplyEnsureTable(PDO $pdo): void
{
    $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS office_inbound_auto_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                message_id TEXT NOT NULL UNIQUE,
                from_email TEXT NOT NULL,
                from_name TEXT NULL,
                subject TEXT NULL,
                received_at TEXT NULL,
                auto_reply_sent_at TEXT NULL,
                skipped_reason TEXT NULL,
                created_at TEXT NULL,
                updated_at TEXT NULL
            )'
        );

        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS office_inbound_auto_replies (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) NOT NULL UNIQUE,
            from_email VARCHAR(255) NOT NULL,
            from_name VARCHAR(255) NULL,
            subject VARCHAR(500) NULL,
            received_at DATETIME NULL,
            auto_reply_sent_at DATETIME NULL,
            skipped_reason VARCHAR(255) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )'
    );
}

function officeAutoReplyFindByMessageId(PDO $pdo, string $messageId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM office_inbound_auto_replies WHERE message_id = :message_id LIMIT 1');
    $stmt->execute([':message_id' => $messageId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * @param array{message_id:string,from_email:string,from_name:string,subject:string,received_at:string} $candidate
 */
function officeAutoReplyUpsert(PDO $pdo, array $candidate, ?string $sentAt, ?string $skippedReason): void
{
    $now = enquiryLoggerNow();
    $existing = officeAutoReplyFindByMessageId($pdo, $candidate['message_id']);

    if ($existing === null) {
        $stmt = $pdo->prepare(
            'INSERT INTO office_inbound_auto_replies
                (message_id, from_email, from_name, subject, received_at, auto_reply_sent_at, skipped_reason, created_at, updated_at)
             VALUES
                (:message_id, :from_email, :from_name, :subject, :received_at, :auto_reply_sent_at, :skipped_reason, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':message_id' => $candidate['message_id'],
            ':from_email' => $candidate['from_email'],
            ':from_name' => $candidate['from_name'],
            ':subject' => $candidate['subject'],
            ':received_at' => $candidate['received_at'],
            ':auto_reply_sent_at' => $sentAt,
            ':skipped_reason' => $skippedReason,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE office_inbound_auto_replies
         SET from_email = :from_email,
             from_name = :from_name,
             subject = :subject,
             received_at = :received_at,
             auto_reply_sent_at = COALESCE(:auto_reply_sent_at, auto_reply_sent_at),
             skipped_reason = :skipped_reason,
             updated_at = :updated_at
         WHERE message_id = :message_id'
    );
    $stmt->execute([
        ':message_id' => $candidate['message_id'],
        ':from_email' => $candidate['from_email'],
        ':from_name' => $candidate['from_name'],
        ':subject' => $candidate['subject'],
        ':received_at' => $candidate['received_at'],
        ':auto_reply_sent_at' => $sentAt,
        ':skipped_reason' => $skippedReason,
        ':updated_at' => $now,
    ]);
}
