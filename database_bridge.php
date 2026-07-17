<?php
declare(strict_types=1);

/**
 * Shared database bridge for legacy form PHP and the Laravel admin backend.
 * Supports SQLite (local default) and MySQL (Coolify / production).
 */

/**
 * Read an env value from getenv / $_ENV / $_SERVER (Coolify is inconsistent).
 */
function appEnvValue(string $key, string $default = ''): string
{
    $fromGetenv = getenv($key);
    if ($fromGetenv !== false && trim((string)$fromGetenv) !== '') {
        return trim((string)$fromGetenv);
    }

    if (isset($_ENV[$key]) && trim((string)$_ENV[$key]) !== '') {
        return trim((string)$_ENV[$key]);
    }

    if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
        return trim((string)$_SERVER[$key]);
    }

    return $default;
}

function appDatabaseDriver(): string
{
    $url = appEnvValue('DB_URL');
    if ($url !== '' && preg_match('#^(mysql|mariadb):#i', $url)) {
        return 'mysql';
    }

    $connection = strtolower(appEnvValue('DB_CONNECTION', 'sqlite'));
    if (in_array($connection, ['mysql', 'mariadb'], true)) {
        return 'mysql';
    }

    // Coolify sometimes leaves DB_CONNECTION=sqlite while MySQL host/user are set.
    $host = appEnvValue('DB_HOST');
    $user = appEnvValue('DB_USERNAME');
    $database = appEnvValue('DB_DATABASE');
    if ($host !== '' && $user !== '') {
        $looksLikeSqlitePath = str_contains($database, '/')
            || str_ends_with(strtolower($database), '.sqlite')
            || str_ends_with(strtolower($database), '.sqlite3');
        if (!$looksLikeSqlitePath) {
            return 'mysql';
        }
        if (
            appEnvValue('DB_PASSWORD') !== ''
            || $host === 'mysql'
            || in_array(appEnvValue('DB_PORT'), ['3306', '2248'], true)
        ) {
            return 'mysql';
        }
    }

    return 'sqlite';
}

function appDatabasePath(): string
{
    $configured = appEnvValue('APP_DATABASE_PATH');
    if ($configured !== '') {
        return $configured;
    }

    $database = appEnvValue('DB_DATABASE');
    if ($database !== '' && str_starts_with($database, '/') && !str_contains($database, '://')) {
        return $database;
    }

    return __DIR__ . '/data/app.sqlite';
}

/**
 * @return array{dsn:string,user:?string,pass:?string}
 */
function appDatabaseCredentials(): array
{
    if (appDatabaseDriver() === 'mysql') {
        $url = appEnvValue('DB_URL');
        if ($url !== '') {
            $parts = parse_url($url);
            if (is_array($parts) && isset($parts['host'])) {
                $host = (string)$parts['host'];
                $port = isset($parts['port']) ? (int)$parts['port'] : 3306;
                $db = isset($parts['path']) ? ltrim((string)$parts['path'], '/') : 'default';
                $user = isset($parts['user']) ? rawurldecode((string)$parts['user']) : 'mysql';
                $pass = isset($parts['pass']) ? rawurldecode((string)$parts['pass']) : '';
                if (str_contains($db, '/') || str_ends_with(strtolower($db), '.sqlite')) {
                    $db = 'default';
                }

                return [
                    'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
                    'user' => $user,
                    'pass' => $pass,
                ];
            }
        }

        $host = appEnvValue('DB_HOST', '127.0.0.1');
        $port = (int)(appEnvValue('DB_PORT', '3306') ?: '3306');
        $db = appEnvValue('DB_DATABASE', 'default');
        if ($db === '' || str_contains($db, '/') || str_ends_with(strtolower($db), '.sqlite')) {
            $db = 'default';
        }
        $user = appEnvValue('DB_USERNAME', 'mysql');
        $pass = appEnvValue('DB_PASSWORD');

        return [
            'dsn' => sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db),
            'user' => $user,
            'pass' => $pass,
        ];
    }

    return [
        'dsn' => 'sqlite:' . appDatabasePath(),
        'user' => null,
        'pass' => null,
    ];
}

function appDatabasePdo(): ?PDO
{
    static $pdo = null;
    static $attempted = false;

    if ($attempted) {
        return $pdo;
    }

    $attempted = true;
    $creds = appDatabaseCredentials();

    if (appDatabaseDriver() === 'sqlite') {
        $path = appDatabasePath();
        if (!is_file($path)) {
            return null;
        }
    }

    try {
        $pdo = new PDO($creds['dsn'], $creds['user'], $creds['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 10,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

/**
 * @return array<string, string>
 */
function appSettingsFlushCache(): void
{
    appSettingsAll(true);
}

/**
 * @return array<string, string>
 */
function appSettingsAll(bool $flush = false): array
{
    static $cache = null;

    if ($flush) {
        $cache = null;
    }

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $pdo = appDatabasePdo();
    if ($pdo === null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query('SELECT `key`, value FROM settings');
        if ($stmt === false) {
            return $cache;
        }

        while ($row = $stmt->fetch()) {
            $cache[(string)$row['key']] = $row['value'] !== null ? (string)$row['value'] : '';
        }
    } catch (Throwable $e) {
        $cache = [];
    }

    return $cache;
}

function appSetting(string $key, string $default = ''): string
{
    $settings = appSettingsAll();

    return array_key_exists($key, $settings) ? (string)$settings[$key] : $default;
}

function appSettingBool(string $key, bool $default = false): bool
{
    $value = appSetting($key, $default ? '1' : '0');

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

/**
 * Upsert a settings row using the live PDO driver (MySQL or SQLite).
 */
function appSettingSet(string $key, string $value): void
{
    $pdo = appDatabasePdo();
    if ($pdo === null) {
        return;
    }

    $now = gmdate('Y-m-d H:i:s');
    $driver = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

    if (in_array($driver, ['mysql', 'mariadb'], true)) {
        $stmt = $pdo->prepare(
            'INSERT INTO settings (`key`, value, created_at, updated_at)
             VALUES (:key, :value, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at)'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO settings ("key", value, created_at, updated_at)
             VALUES (:key, :value, :created_at, :updated_at)
             ON CONFLICT("key") DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at'
        );
    }

    $stmt->execute([
        ':key' => $key,
        ':value' => $value,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    appSettingsFlushCache();
}

/**
 * @return list<array<string, mixed>>
 */
function appTrainingMatrixRows(): array
{
    static $cache = null;

    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $pdo = appDatabasePdo();
    if ($pdo === null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query(
            'SELECT sector, course, course_value, format, sub_option, min_attendees, max_cap, default_attendees, pricing
             FROM training_matrix_entries
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        if ($stmt === false) {
            return $cache;
        }

        while ($row = $stmt->fetch()) {
            $pricing = json_decode((string)($row['pricing'] ?? '{}'), true);
            if (!is_array($pricing)) {
                $pricing = [];
            }

            $item = [
                'sector' => (string)$row['sector'],
                'course' => (string)$row['course'],
                'courseValue' => (string)$row['course_value'],
                'format' => (string)$row['format'],
                'subOption' => (string)$row['sub_option'],
                'minAttendees' => (int)$row['min_attendees'],
                'maxCap' => $row['max_cap'] !== null ? (int)$row['max_cap'] : null,
                'pricing' => $pricing,
            ];

            if ($row['default_attendees'] !== null) {
                $item['defaultAttendees'] = (int)$row['default_attendees'];
            }

            $cache[] = $item;
        }
    } catch (Throwable $e) {
        $cache = [];
    }

    return $cache;
}

function applyAppSettingsToGlobals(): void
{
    if (appDatabasePdo() === null) {
        return;
    }

    $GLOBALS['mondayvariable'] = appSetting('monday_api_token', (string)($GLOBALS['mondayvariable'] ?? ''));
    $GLOBALS['mondayBoardId'] = appSetting('monday_board_id', (string)($GLOBALS['mondayBoardId'] ?? ''));
    $GLOBALS['mondayGroupId'] = appSetting('monday_group_id', (string)($GLOBALS['mondayGroupId'] ?? ''));
    $GLOBALS['mondayGroupName'] = appSetting('monday_group_name', (string)($GLOBALS['mondayGroupName'] ?? 'New Enquiries'));
    $GLOBALS['mondayBookingGroupName'] = appSetting('monday_booking_group_name', (string)($GLOBALS['mondayBookingGroupName'] ?? 'Quote Accepted'));
    $GLOBALS['mondayQuoteAcceptedGroupName'] = appSetting('monday_quote_accepted_group_name', (string)($GLOBALS['mondayQuoteAcceptedGroupName'] ?? $GLOBALS['mondayBookingGroupName']));
    $GLOBALS['idealPostcodesApiKey'] = appSetting('ideal_postcodes_api_key', (string)($GLOBALS['idealPostcodesApiKey'] ?? ''));
    $GLOBALS['brevoApiKey'] = appSetting('brevo_api_key', (string)($GLOBALS['brevoApiKey'] ?? ''));
    $GLOBALS['brevoEmailEnabled'] = appSettingBool('brevo_email_enabled', (bool)($GLOBALS['brevoEmailEnabled'] ?? false));
    $GLOBALS['brevoSenderEmail'] = appSetting('brevo_sender_email', (string)($GLOBALS['brevoSenderEmail'] ?? ''));
    $GLOBALS['brevoSenderName'] = appSetting('brevo_sender_name', (string)($GLOBALS['brevoSenderName'] ?? 'Safer Handling'));
    $GLOBALS['brevoContactEmail'] = appSetting('brevo_contact_email', (string)($GLOBALS['brevoContactEmail'] ?? ''));
    $GLOBALS['brevoOfficeEmail'] = appSetting('brevo_office_email', (string)($GLOBALS['brevoOfficeEmail'] ?? 'office@safer-handling.co.uk'));
    $GLOBALS['brevoLeadNotificationEnabled'] = appSettingBool('brevo_lead_notification_enabled', (bool)($GLOBALS['brevoLeadNotificationEnabled'] ?? true));
    $GLOBALS['brevoLogoUrl'] = appSetting('brevo_logo_url', (string)($GLOBALS['brevoLogoUrl'] ?? ''));
    $GLOBALS['brevoQuoteAcceptUrl'] = appSetting('brevo_quote_accept_url', (string)($GLOBALS['brevoQuoteAcceptUrl'] ?? ''));
    $GLOBALS['formBaseUrl'] = appSetting('form_base_url', (string)($GLOBALS['formBaseUrl'] ?? ''));
    $GLOBALS['brevoResumeEmailEnabled'] = appSettingBool('brevo_resume_email_enabled', (bool)($GLOBALS['brevoResumeEmailEnabled'] ?? true));
    $GLOBALS['xeroEnabled'] = appSettingBool('xero_enabled', (bool)($GLOBALS['xeroEnabled'] ?? false));
    $GLOBALS['xeroClientId'] = appSetting('xero_client_id', (string)($GLOBALS['xeroClientId'] ?? ''));
    $GLOBALS['xeroClientSecret'] = appSetting('xero_client_secret', (string)($GLOBALS['xeroClientSecret'] ?? ''));
    $GLOBALS['xeroRedirectUri'] = appSetting('xero_redirect_uri', (string)($GLOBALS['xeroRedirectUri'] ?? ''));
    $GLOBALS['xeroTenantId'] = appSetting('xero_tenant_id', (string)($GLOBALS['xeroTenantId'] ?? ''));
    $GLOBALS['xeroAccessToken'] = appSetting('xero_access_token', (string)($GLOBALS['xeroAccessToken'] ?? ''));
    $GLOBALS['xeroRefreshToken'] = appSetting('xero_refresh_token', (string)($GLOBALS['xeroRefreshToken'] ?? ''));
    $GLOBALS['xeroTokenExpiresAt'] = appSetting('xero_token_expires_at', (string)($GLOBALS['xeroTokenExpiresAt'] ?? ''));
    $GLOBALS['xeroDefaultItemCode'] = appSetting('xero_default_item_code', (string)($GLOBALS['xeroDefaultItemCode'] ?? ''));
    $GLOBALS['xeroSalesAccountCode'] = appSetting('xero_sales_account_code', (string)($GLOBALS['xeroSalesAccountCode'] ?? '200'));
    $GLOBALS['xeroVatRate'] = appSetting('xero_vat_rate', (string)($GLOBALS['xeroVatRate'] ?? '20'));
    $GLOBALS['xeroBrandingThemeId'] = appSetting('xero_branding_theme_id', (string)($GLOBALS['xeroBrandingThemeId'] ?? ''));
}

/**
 * Resolve a config string: non-empty env var wins, then DB-backed $GLOBALS, then default.
 */
function appConfigValue(string $envKey, string $globalKey, string $default = ''): string
{
    $fromEnv = trim((string)(getenv($envKey) ?: ''));
    if ($fromEnv !== '') {
        return $fromEnv;
    }

    $fromGlobal = trim((string)($GLOBALS[$globalKey] ?? ''));
    if ($fromGlobal !== '') {
        return $fromGlobal;
    }

    return $default;
}

/**
 * @return array{token:string,boardId:string,groupId:string,groupName:string}
 */
function mondayAppConfig(): array
{
    $groupName = appConfigValue('MONDAY_GROUP_NAME', 'mondayGroupName', 'New Enquiries');
    if ($groupName === '') {
        $groupName = 'New Enquiries';
    }

    return [
        'token' => appConfigValue('MONDAY_API_TOKEN', 'mondayvariable'),
        'boardId' => appConfigValue('MONDAY_BOARD_ID', 'mondayBoardId'),
        'groupId' => appConfigValue('MONDAY_GROUP_ID', 'mondayGroupId'),
        'groupName' => $groupName,
    ];
}

function mondayConfigMissingMessage(): string
{
    return 'Monday.com is not configured. Set monday_api_token and monday_board_id in Admin → Settings, or set MONDAY_API_TOKEN and MONDAY_BOARD_ID.';
}
