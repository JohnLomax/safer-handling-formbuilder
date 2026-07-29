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
|   KAJABI_COURSES_URL
|   KAJABI_CLIENT_ID, KAJABI_CLIENT_SECRET
|   KAJABI_SITE_ID, KAJABI_OFFER_ID, KAJABI_OFFER_TITLE, KAJABI_ENABLED
|   BREVO_API_KEY, BREVO_EMAIL_ENABLED, BREVO_RESUME_EMAIL_ENABLED
|   BREVO_LEAD_NOTIFICATION_ENABLED, BREVO_OFFICE_EMAIL
|   BREVO_SENDER_EMAIL, BREVO_SENDER_NAME, BREVO_CONTACT_EMAIL
|   BREVO_LOGO_URL, BREVO_QUOTE_ACCEPT_URL, FORM_BASE_URL
|   OFFICE_AUTO_REPLY_ENABLED, OFFICE_AUTO_REPLY_HOURS
|   OFFICE_IMAP_HOST, OFFICE_IMAP_PORT, OFFICE_IMAP_ENCRYPTION
|   OFFICE_IMAP_USERNAME, OFFICE_IMAP_PASSWORD
|   OFFICE_IMAP_INBOX_FOLDER, OFFICE_IMAP_SENT_FOLDER
|   XERO_ENABLED, XERO_CLIENT_ID, XERO_CLIENT_SECRET, XERO_REDIRECT_URI
|   XERO_TENANT_ID, XERO_DEFAULT_ITEM_CODE, XERO_VAT_RATE, XERO_BRANDING_THEME_ID
|   XERO_WEBHOOK_KEY
|   FORGE_WEBHOOK_ENABLED, FORGE_WEBHOOK_URL, FORGE_WEBHOOK_TOKEN
|   APP_DATABASE_PATH
|
| Do NOT set XERO_ACCESS_TOKEN / XERO_REFRESH_TOKEN / XERO_TOKEN_EXPIRES_AT in env.
| Those rotate on every API refresh and must live in the settings table only.
*/

$mondayvariable = '';
$mondayBoardId = '';
$mondayGroupId = '';
$mondayGroupName = 'New Enquiries';

$idealPostcodesApiKey = '';
$kajabiCoursesUrl = 'https://safer-handling.mykajabi.com/store';
$kajabiClientId = '';
$kajabiClientSecret = '';
$kajabiSiteId = '157262';
$kajabiOfferId = '2150036193';
$kajabiOfferTitle = '2026 Legal Briefing on the use of Reasonable Force';
$kajabiEnabled = false;

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
$brevoWebhookSecret = '';

$officeAutoReplyEnabled = false;
$officeAutoReplyHours = '8';
$officeImapHost = 'outlook.office365.com';
$officeImapPort = '993';
$officeImapEncryption = 'ssl';
$officeImapUsername = 'office@safer-handling.co.uk';
$officeImapPassword = '';
$officeImapInboxFolder = 'INBOX';
$officeImapSentFolder = 'Sent Items';

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

$forgeEnabled = false;
$forgeWebhookUrl = 'https://saferhandling.forgecrm.co.uk/safer_production/webhooks/bookings/';
$forgeWebhookToken = '';

require_once __DIR__ . '/database_bridge.php';
applyAppSettingsToGlobals();

// Keep local variables in sync with DB-backed globals for scripts that read locals.
$mondayvariable = (string)($GLOBALS['mondayvariable'] ?? $mondayvariable);
$mondayBoardId = (string)($GLOBALS['mondayBoardId'] ?? $mondayBoardId);
$mondayGroupId = (string)($GLOBALS['mondayGroupId'] ?? $mondayGroupId);
$mondayGroupName = (string)($GLOBALS['mondayGroupName'] ?? $mondayGroupName);
$idealPostcodesApiKey = (string)($GLOBALS['idealPostcodesApiKey'] ?? $idealPostcodesApiKey);
$kajabiCoursesUrl = (string)($GLOBALS['kajabiCoursesUrl'] ?? $kajabiCoursesUrl);
$kajabiClientId = (string)($GLOBALS['kajabiClientId'] ?? $kajabiClientId);
$kajabiClientSecret = (string)($GLOBALS['kajabiClientSecret'] ?? $kajabiClientSecret);
$kajabiSiteId = (string)($GLOBALS['kajabiSiteId'] ?? $kajabiSiteId);
$kajabiOfferId = (string)($GLOBALS['kajabiOfferId'] ?? $kajabiOfferId);
$kajabiOfferTitle = (string)($GLOBALS['kajabiOfferTitle'] ?? $kajabiOfferTitle);
$kajabiEnabled = (bool)($GLOBALS['kajabiEnabled'] ?? $kajabiEnabled);
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
$brevoWebhookSecret = (string)($GLOBALS['brevoWebhookSecret'] ?? $brevoWebhookSecret);
$officeAutoReplyEnabled = (bool)($GLOBALS['officeAutoReplyEnabled'] ?? $officeAutoReplyEnabled);
$officeAutoReplyHours = (string)($GLOBALS['officeAutoReplyHours'] ?? $officeAutoReplyHours);
$officeImapHost = (string)($GLOBALS['officeImapHost'] ?? $officeImapHost);
$officeImapPort = (string)($GLOBALS['officeImapPort'] ?? $officeImapPort);
$officeImapEncryption = (string)($GLOBALS['officeImapEncryption'] ?? $officeImapEncryption);
$officeImapUsername = (string)($GLOBALS['officeImapUsername'] ?? $officeImapUsername);
$officeImapPassword = (string)($GLOBALS['officeImapPassword'] ?? $officeImapPassword);
$officeImapInboxFolder = (string)($GLOBALS['officeImapInboxFolder'] ?? $officeImapInboxFolder);
$officeImapSentFolder = (string)($GLOBALS['officeImapSentFolder'] ?? $officeImapSentFolder);
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
$forgeEnabled = (bool)($GLOBALS['forgeEnabled'] ?? $forgeEnabled);
$forgeWebhookUrl = (string)($GLOBALS['forgeWebhookUrl'] ?? $forgeWebhookUrl);
$forgeWebhookToken = (string)($GLOBALS['forgeWebhookToken'] ?? $forgeWebhookToken);
