<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| App Configuration
|--------------------------------------------------------------------------
| Values here are fallbacks when the Laravel admin database is unavailable.
| Manage live settings in the admin panel: /admin/settings
|
| Optional env overrides (take priority over DB when set):
|   MONDAY_API_TOKEN, MONDAY_BOARD_ID, MONDAY_GROUP_ID, MONDAY_GROUP_NAME
|   IDEAL_POSTCODES_API_KEY
|   BREVO_API_KEY, BREVO_EMAIL_ENABLED, BREVO_RESUME_EMAIL_ENABLED
|   BREVO_LEAD_NOTIFICATION_ENABLED, BREVO_OFFICE_EMAIL
|   BREVO_SENDER_EMAIL, BREVO_SENDER_NAME, BREVO_CONTACT_EMAIL
|   BREVO_LOGO_URL, BREVO_QUOTE_ACCEPT_URL, FORM_BASE_URL
|   XERO_ENABLED, XERO_CLIENT_ID, XERO_CLIENT_SECRET, XERO_REDIRECT_URI
|   XERO_TENANT_ID, XERO_DEFAULT_ITEM_CODE, XERO_VAT_RATE, XERO_BRANDING_THEME_ID
|   APP_DATABASE_PATH
*/

$mondayvariable = '';
$mondayBoardId = '';
$mondayGroupId = '';
$mondayGroupName = 'New Enquiries';

$idealPostcodesApiKey = '';

$brevoApiKey = '';

$brevoEmailEnabled = false;
$brevoSenderEmail = 'training@safer-handling.co.uk';
$brevoSenderName = 'Safer Handling';
$brevoContactEmail = 'training@safer-handling.co.uk';
$brevoOfficeEmail = 'office@safer-handling.co.uk';
$brevoLeadNotificationEnabled = true;
$brevoLogoUrl = 'https://img.mailinblue.com/8246699/images/content_library/original/6a02cfcf9d7025c9e500ab4b.jpg';
$brevoQuoteAcceptUrl = '';

$formBaseUrl = '';
$brevoResumeEmailEnabled = true;

$xeroEnabled = false;
$xeroClientId = '';
$xeroClientSecret = '';
$xeroRedirectUri = '';
$xeroTenantId = '';
$xeroAccessToken = '';
$xeroRefreshToken = '';
$xeroTokenExpiresAt = '';
$xeroDefaultItemCode = '';
$xeroSalesAccountCode = '200';
$xeroVatRate = '20';
$xeroBrandingThemeId = '';

require_once __DIR__ . '/database_bridge.php';
applyAppSettingsToGlobals();

// Keep local variables in sync with DB-backed globals for scripts that read locals.
$mondayvariable = (string)($GLOBALS['mondayvariable'] ?? $mondayvariable);
$mondayBoardId = (string)($GLOBALS['mondayBoardId'] ?? $mondayBoardId);
$mondayGroupId = (string)($GLOBALS['mondayGroupId'] ?? $mondayGroupId);
$mondayGroupName = (string)($GLOBALS['mondayGroupName'] ?? $mondayGroupName);
$idealPostcodesApiKey = (string)($GLOBALS['idealPostcodesApiKey'] ?? $idealPostcodesApiKey);
$brevoApiKey = (string)($GLOBALS['brevoApiKey'] ?? $brevoApiKey);
$brevoEmailEnabled = (bool)($GLOBALS['brevoEmailEnabled'] ?? $brevoEmailEnabled);
$brevoSenderEmail = (string)($GLOBALS['brevoSenderEmail'] ?? $brevoSenderEmail);
$brevoSenderName = (string)($GLOBALS['brevoSenderName'] ?? $brevoSenderName);
$brevoContactEmail = (string)($GLOBALS['brevoContactEmail'] ?? $brevoContactEmail);
$brevoOfficeEmail = (string)($GLOBALS['brevoOfficeEmail'] ?? $brevoOfficeEmail);
$brevoLeadNotificationEnabled = (bool)($GLOBALS['brevoLeadNotificationEnabled'] ?? $brevoLeadNotificationEnabled);
$brevoLogoUrl = (string)($GLOBALS['brevoLogoUrl'] ?? $brevoLogoUrl);
$brevoQuoteAcceptUrl = (string)($GLOBALS['brevoQuoteAcceptUrl'] ?? $brevoQuoteAcceptUrl);
$formBaseUrl = (string)($GLOBALS['formBaseUrl'] ?? $formBaseUrl);
$brevoResumeEmailEnabled = (bool)($GLOBALS['brevoResumeEmailEnabled'] ?? $brevoResumeEmailEnabled);
$xeroEnabled = (bool)($GLOBALS['xeroEnabled'] ?? $xeroEnabled);
$xeroClientId = (string)($GLOBALS['xeroClientId'] ?? $xeroClientId);
$xeroClientSecret = (string)($GLOBALS['xeroClientSecret'] ?? $xeroClientSecret);
$xeroRedirectUri = (string)($GLOBALS['xeroRedirectUri'] ?? $xeroRedirectUri);
$xeroTenantId = (string)($GLOBALS['xeroTenantId'] ?? $xeroTenantId);
$xeroAccessToken = (string)($GLOBALS['xeroAccessToken'] ?? $xeroAccessToken);
$xeroRefreshToken = (string)($GLOBALS['xeroRefreshToken'] ?? $xeroRefreshToken);
$xeroTokenExpiresAt = (string)($GLOBALS['xeroTokenExpiresAt'] ?? $xeroTokenExpiresAt);
$xeroDefaultItemCode = (string)($GLOBALS['xeroDefaultItemCode'] ?? $xeroDefaultItemCode);
$xeroSalesAccountCode = (string)($GLOBALS['xeroSalesAccountCode'] ?? $xeroSalesAccountCode);
$xeroVatRate = (string)($GLOBALS['xeroVatRate'] ?? $xeroVatRate);
$xeroBrandingThemeId = (string)($GLOBALS['xeroBrandingThemeId'] ?? $xeroBrandingThemeId);
