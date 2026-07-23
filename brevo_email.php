<?php
declare(strict_types=1);

const BREVO_DEFAULT_LOGO_URL = 'https://img.mailinblue.com/8246699/images/content_library/original/6a02cfcf9d7025c9e500ab4b.jpg';

/**
 * @return array{email: string, name: string}
 */
function brevoSenderConfig(): array
{
    $email = trim((string)(getenv('BREVO_SENDER_EMAIL') ?: ($GLOBALS['brevoSenderEmail'] ?? '')));
    $name = trim((string)(getenv('BREVO_SENDER_NAME') ?: ($GLOBALS['brevoSenderName'] ?? 'Safer Handling')));

    if ($email === '') {
        $email = 'training@safer-handling.co.uk';
    }

    return ['email' => $email, 'name' => $name];
}

function brevoApiKey(): string
{
    return trim((string)(getenv('BREVO_API_KEY') ?: ($GLOBALS['brevoApiKey'] ?? '')));
}

function brevoEmailEnabled(): bool
{
    $env = getenv('BREVO_EMAIL_ENABLED');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    return (bool)($GLOBALS['brevoEmailEnabled'] ?? true);
}

function brevoQuoteAcceptUrl(string $email = ''): string
{
    $url = trim((string)(getenv('BREVO_QUOTE_ACCEPT_URL') ?: ($GLOBALS['brevoQuoteAcceptUrl'] ?? '')));
    if ($url === '') {
        return '';
    }
    if ($email !== '' && strpos($url, '{{email}}') !== false) {
        $url = str_replace('{{email}}', rawurlencode($email), $url);
    }

    // Misconfigured overrides that point at the enquiry edit form must not be used.
    if (quoteAcceptUrlLooksLikeEnquiryEdit($url)) {
        return '';
    }

    return $url;
}

/**
 * True when a URL path is the enquiry resume/edit form (not the booking form).
 */
function quoteAcceptUrlLooksLikeEnquiryEdit(string $url): bool
{
    $path = strtolower(rtrim((string)(parse_url($url, PHP_URL_PATH) ?: ''), '/'));
    if ($path === '') {
        return false;
    }

    return $path === '/enquiry' || str_ends_with($path, '/enquiry');
}

/**
 * True when a URL path targets the public booking / venue / terms form.
 */
function quoteAcceptUrlIsBookingForm(string $url): bool
{
    $path = strtolower((string)(parse_url($url, PHP_URL_PATH) ?: ''));

    return $path !== '' && (str_ends_with(rtrim($path, '/'), '/booking') || str_contains($path, '/booking'));
}

/**
 * Resolve the Accept Quote CTA to the booking form for this enquiry.
 * Never returns the enquiry edit/resume URL.
 *
 * @param array<string, mixed> $quoteData
 */
function resolveQuoteAcceptUrl(array &$quoteData): string
{
    $enquiryId = (int)($quoteData['enquiryId'] ?? 0);
    if ($enquiryId > 0) {
        require_once __DIR__ . '/enquiry_logger.php';
        $token = trim((string)($quoteData['resumeToken'] ?? ''));
        if ($token === '') {
            $token = enquiryLoggerEnsureResumeToken($enquiryId);
            $quoteData['resumeToken'] = $token;
        }
        $bookingUrl = buildBookingDetailsUrl($enquiryId, $token);
        if ($bookingUrl !== '') {
            $quoteData['acceptQuoteUrl'] = $bookingUrl;

            return $bookingUrl;
        }
    }

    $current = trim((string)($quoteData['acceptQuoteUrl'] ?? ''));
    if ($current !== '' && quoteAcceptUrlIsBookingForm($current) && !quoteAcceptUrlLooksLikeEnquiryEdit($current)) {
        return $current;
    }

    $configured = brevoQuoteAcceptUrl(trim((string)($quoteData['email'] ?? '')));
    if ($configured !== '' && quoteAcceptUrlIsBookingForm($configured)) {
        $quoteData['acceptQuoteUrl'] = $configured;

        return $configured;
    }

    // Drop enquiry-edit / invalid overrides so the mailto fallback is used instead.
    $quoteData['acceptQuoteUrl'] = '';

    return '';
}

function brevoResumeEmailEnabled(): bool
{
    $env = getenv('BREVO_RESUME_EMAIL_ENABLED');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    if (array_key_exists('brevoResumeEmailEnabled', $GLOBALS)) {
        return (bool)$GLOBALS['brevoResumeEmailEnabled'];
    }

    return brevoEmailEnabled();
}

/**
 * Strip accidental form path suffixes from Form base URL settings
 * (e.g. …/enquiry) so /booking and /enquiry links stay correct.
 */
function brevoNormalizeFormBaseUrl(string $url): string
{
    $normalized = rtrim(trim($url), '/');
    if ($normalized === '') {
        return '';
    }

    $lower = strtolower($normalized);
    foreach (['/enquiry', '/booking', '/feedback', '/admin'] as $suffix) {
        if (str_ends_with($lower, $suffix)) {
            $normalized = substr($normalized, 0, -strlen($suffix));
            $normalized = rtrim($normalized, '/');
            break;
        }
    }

    return $normalized;
}

function brevoFormBaseUrl(): string
{
    // Prefer Admin → Settings form_base_url so Coolify APP_URL / temporary
    // sslip hosts do not rewrite customer booking links to the wrong domain.
    $candidates = [
        trim((string)($GLOBALS['formBaseUrl'] ?? '')),
        trim((string)(getenv('FORM_BASE_URL') ?: '')),
        trim((string)(getenv('APP_URL') ?: '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }
        $normalized = brevoNormalizeFormBaseUrl($candidate);
        if ($normalized !== '' && brevoIsUsablePublicBaseUrl($normalized)) {
            return $normalized;
        }
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '') {
            return brevoNormalizeFormBaseUrl($candidate);
        }
    }

    $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $forwardedProto === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim(dirname($script), '/');

    return $scheme . '://' . $host . ($dir !== '' && $dir !== '.' ? $dir : '');
}

/**
 * Prefer real public hostnames over localhost / Coolify temporary sslip URLs.
 */
function brevoIsUsablePublicBaseUrl(string $url): bool
{
    $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
    if ($host === '') {
        return false;
    }

    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }

    if (str_ends_with($host, '.sslip.io') || str_ends_with($host, '.nip.io')) {
        return false;
    }

    return true;
}

function buildEnquiryResumeUrl(int $enquiryId, string $resumeToken): string
{
    $base = brevoFormBaseUrl();
    if ($base === '') {
        return '';
    }

    $query = http_build_query([
        'enquiry' => $enquiryId,
        'token' => $resumeToken,
    ]);

    return $base . '/enquiry?' . $query;
}

function buildBookingDetailsUrl(int $enquiryId, string $resumeToken): string
{
    $base = brevoFormBaseUrl();
    if ($base === '') {
        return '';
    }

    $query = http_build_query([
        'enquiry' => $enquiryId,
        'token' => $resumeToken,
    ]);

    return $base . '/booking?' . $query;
}

function bookingJoiningInstructionsUrl(): string
{
    $configured = trim((string)(getenv('BOOKING_JOINING_INSTRUCTIONS_URL') ?: ($GLOBALS['bookingJoiningInstructionsUrl'] ?? '')));
    if ($configured !== '') {
        return $configured;
    }

    $local = __DIR__ . '/assets/joining-physical-instructions.pdf';
    if (is_file($local)) {
        $base = brevoFormBaseUrl();
        if ($base !== '') {
            return $base . '/assets/joining-physical-instructions.pdf';
        }
    }

    return '';
}

/**
 * @param array<string, mixed> $data
 */
function buildBookingDetailsEmailHtml(array $data): string
{
    $name = htmlspecialchars((string)($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $bookingUrl = htmlspecialchars((string)($data['bookingUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $contactEmail = htmlspecialchars(brevoContactEmail(), ENT_QUOTES, 'UTF-8');
    $logoSrc = htmlspecialchars(brevoLogoUrl(), ENT_QUOTES, 'UTF-8');
    $whatsappBlock = brevoWhatsAppButtonHtml();
    $joiningUrl = trim((string)($data['joiningInstructionsUrl'] ?? ''));
    $joiningBlock = '';
    if ($joiningUrl !== '') {
        $joiningHref = htmlspecialchars($joiningUrl, ENT_QUOTES, 'UTF-8');
        $joiningBlock = <<<HTML
          <tr>
            <td align="center" style="padding:0 20px 24px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 10px;"><strong>Important — Joining Physical Instructions</strong></p>
              <p style="margin:0 0 14px;">Please download the physical instructions before completing your booking details:</p>
              <a href="{$joiningHref}" target="_blank" style="color:#0255a4; font-weight:700; text-decoration:underline;">Download joining instructions</a>
            </td>
          </tr>
HTML;
    }

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Complete Your Safer Handling Booking Details</title>
</head>
<body bgcolor="#ffffff" style="margin:0; padding:0; background-color:#ffffff; color:#414141;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; width:100%;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff;">
          <tr>
            <td align="center" style="padding:10px 15px 20px;">
              <img src="{$logoSrc}" alt="Safer Handling" width="200" border="0" style="display:block; width:200px; max-width:200px; height:auto;" />
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 10px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">Hello {$name},</p>
              <p style="margin:0 0 12px;">Thank you for accepting your Safer Handling training quote.</p>
              <p style="margin:0;">To finalise your booking, please provide your venue details, delegate names, invoice information, and accept our terms and conditions.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:24px 20px 8px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
              <h1 style="margin:0; font-size:24px; font-weight:700; color:#0255a4; line-height:1.3;">Complete your booking details</h1>
            </td>
          </tr>
{$joiningBlock}
          <tr>
            <td align="center" style="padding:0 20px 32px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="320" style="width:320px; background-color:#0255a4; border-radius:4px;">
                <tr>
                  <td align="center" style="padding:14px 20px; border-radius:4px;">
                    <a href="{$bookingUrl}" target="_blank" style="display:block; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; line-height:1.2;">Accept terms &amp; add venue details</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 16px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">This secure link is linked to your enquiry. Please do not forward it to someone else unless they should complete the booking for you.</p>
              <p style="margin:0;">Questions? <a href="mailto:{$contactEmail}" style="color:#0255a4; font-weight:700; text-decoration:underline;">Contact our team</a>.</p>
            </td>
          </tr>
{$whatsappBlock}
          <tr>
            <td align="center" style="padding:20px; border-top:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#666666; text-align:center;">
              <strong style="color:#0255a4;">Safer Handling</strong><br />
              <a href="https://www.safer-handling.co.uk" style="color:#0255a4; text-decoration:underline;">www.safer-handling.co.uk</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildBookingDetailsEmailText(array $data): string
{
    $lines = [
        'Hello ' . (string)($data['name'] ?? '') . ',',
        '',
        'Thank you for accepting your Safer Handling training quote.',
        'To finalise your booking, please provide your venue details, delegate names, invoice information, and accept our terms and conditions.',
        '',
    ];

    $joiningUrl = trim((string)($data['joiningInstructionsUrl'] ?? ''));
    if ($joiningUrl !== '') {
        $lines[] = 'Joining physical instructions:';
        $lines[] = $joiningUrl;
        $lines[] = '';
    }

    $lines[] = 'Complete your booking details here:';
    $lines[] = (string)($data['bookingUrl'] ?? '');
    $lines[] = '';
    $lines[] = 'Questions? Contact us at ' . brevoContactEmail() . '.';
    $lines[] = 'WhatsApp: ' . brevoWhatsAppUrl();
    $lines[] = '';
    $lines[] = 'Safer Handling';
    $lines[] = 'https://www.safer-handling.co.uk';

    return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $data
 */
function sendBookingDetailsEmailViaBrevo(string $toEmail, string $toName, array $data): void
{
    $apiKey = brevoApiKey();
    if ($apiKey === '') {
        throw new RuntimeException('Brevo API key is not configured.');
    }

    $bookingUrl = trim((string)($data['bookingUrl'] ?? ''));
    if ($bookingUrl === '') {
        throw new RuntimeException('Booking details form URL is not configured.');
    }

    $sender = brevoSenderConfig();
    $payload = [
        'sender' => $sender,
        'to' => [
            [
                'email' => $toEmail,
                'name' => $toName,
            ],
        ],
        'replyTo' => [
            'email' => brevoContactEmail(),
            'name' => $sender['name'],
        ],
        'subject' => 'Complete your Safer Handling booking details',
        'htmlContent' => buildBookingDetailsEmailHtml($data),
        'textContent' => buildBookingDetailsEmailText($data),
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        throw new RuntimeException('Unable to initialise Brevo request.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Brevo API request failed: ' . $err);
    }
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if ($status >= 400) {
        $message = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
        if ($message === '') {
            $message = 'Brevo API returned HTTP ' . $status . '.';
        }
        throw new RuntimeException($message);
    }
}

/**
 * Send the booking details / terms acceptance email after a Xero quote is sent.
 *
 * Disabled: the Xero quote email “Accept Quote and add venue details” button
 * already opens the same booking / venue details form, so this second email is redundant.
 */
function maybeSendBookingDetailsEmail(int $enquiryId, string $name, string $email, bool $force = false): bool
{
    return false;
}

/**
 * @param array<string, mixed> $data
 */
function buildResumeEnquiryEmailHtml(array $data): string
{
    $name = htmlspecialchars((string)($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $resumeUrl = htmlspecialchars((string)($data['resumeUrl'] ?? ''), ENT_QUOTES, 'UTF-8');
    $contactEmail = htmlspecialchars(brevoContactEmail(), ENT_QUOTES, 'UTF-8');
    $logoSrc = htmlspecialchars(brevoLogoUrl(), ENT_QUOTES, 'UTF-8');
    $whatsappBlock = brevoWhatsAppButtonHtml();

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Continue Your Safer Handling Enquiry</title>
</head>
<body bgcolor="#ffffff" style="margin:0; padding:0; background-color:#ffffff; color:#414141;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; width:100%;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff;">
          <tr>
            <td align="center" style="padding:10px 15px 20px;">
              <img src="{$logoSrc}" alt="Safer Handling" width="200" border="0" style="display:block; width:200px; max-width:200px; height:auto;" />
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 10px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">Hello {$name},</p>
              <p style="margin:0 0 12px;">Thank you for starting your enquiry with <strong style="color:#0255a4;">Safer Handling</strong>.</p>
              <p style="margin:0;">We saved your progress using the email address <strong>{$email}</strong>. Use the button below any time to return and complete your form.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:24px 20px 8px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
              <h1 style="margin:0; font-size:24px; font-weight:700; color:#0255a4; line-height:1.3;">Continue your enquiry</h1>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 32px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="300" style="width:300px; background-color:#0255a4; border-radius:4px;">
                <tr>
                  <td align="center" style="padding:14px 20px; border-radius:4px;">
                    <a href="{$resumeUrl}" target="_blank" style="display:block; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; line-height:1.2;">Return to my form</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 16px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">If you did not start this enquiry, you can ignore this email.</p>
              <p style="margin:0;">Questions? <a href="mailto:{$contactEmail}" style="color:#0255a4; font-weight:700; text-decoration:underline;">Contact our team</a>.</p>
            </td>
          </tr>
{$whatsappBlock}
          <tr>
            <td align="center" style="padding:20px; border-top:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#666666; text-align:center;">
              <strong style="color:#0255a4;">Safer Handling</strong><br />
              <a href="https://www.safer-handling.co.uk" style="color:#0255a4; text-decoration:underline;">www.safer-handling.co.uk</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildResumeEnquiryEmailText(array $data): string
{
    return implode("\n", [
        'Hello ' . (string)($data['name'] ?? '') . ',',
        '',
        'Thank you for starting your enquiry with Safer Handling.',
        'We saved your progress using the email address ' . (string)($data['email'] ?? '') . '.',
        '',
        'Continue your enquiry here:',
        (string)($data['resumeUrl'] ?? ''),
        '',
        'If you did not start this enquiry, you can ignore this email.',
        '',
        'Questions? Contact us at ' . brevoContactEmail() . '.',
        'WhatsApp: ' . brevoWhatsAppUrl(),
        '',
        'Safer Handling',
        'https://www.safer-handling.co.uk',
    ]);
}

/**
 * @param array<string, mixed> $data
 */
function sendResumeEnquiryEmailViaBrevo(string $toEmail, string $toName, array $data): void
{
    $apiKey = brevoApiKey();
    if ($apiKey === '') {
        throw new RuntimeException('Brevo API key is not configured.');
    }

    $resumeUrl = trim((string)($data['resumeUrl'] ?? ''));
    if ($resumeUrl === '') {
        throw new RuntimeException('Resume form URL is not configured.');
    }

    $sender = brevoSenderConfig();
    $payload = [
        'sender' => $sender,
        'to' => [
            [
                'email' => $toEmail,
                'name' => $toName,
            ],
        ],
        'replyTo' => [
            'email' => brevoContactEmail(),
            'name' => $sender['name'],
        ],
        'subject' => 'Continue your Safer Handling enquiry',
        'htmlContent' => buildResumeEnquiryEmailHtml($data),
        'textContent' => buildResumeEnquiryEmailText($data),
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL for Brevo.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Brevo API request failed: ' . $err);
    }
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if ($status >= 400) {
        $message = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
        if ($message === '') {
            $message = 'Brevo API returned HTTP ' . $status . '.';
        }
        throw new RuntimeException($message);
    }
}

function maybeSendResumeEnquiryEmail(
    int $enquiryId,
    string $name,
    string $email,
    string $enquiryType,
    bool $force = false
): bool {
    if (!brevoResumeEmailEnabled() || brevoApiKey() === '') {
        return false;
    }

    if (!$force && enquiryLoggerResumeEmailAlreadySent($enquiryId)) {
        return false;
    }

    $token = enquiryLoggerEnsureResumeToken($enquiryId);
    $resumeUrl = buildEnquiryResumeUrl($enquiryId, $token);
    if ($resumeUrl === '') {
        throw new RuntimeException('Form base URL is not configured.');
    }

    sendResumeEnquiryEmailViaBrevo($email, $name, [
        'name' => $name,
        'email' => $email,
        'enquiryType' => $enquiryType,
        'resumeUrl' => $resumeUrl,
    ]);

    enquiryLoggerMarkResumeEmailSent($enquiryId);
    enquiryLoggerEvent(
        $enquiryId,
        'resume_email_sent',
        $force
            ? 'Edit Enquiry Email resent so the customer can return to their saved form.'
            : 'Edit Enquiry Email sent so the customer can return to their saved form.',
        ['resent' => $force]
    );

    require_once __DIR__ . '/monday_helpers.php';
    mondayMoveEnquiryToBeingContactedAfterEditEmail($enquiryId);

    return true;
}

function brevoContactEmail(): string
{
    $email = trim((string)(getenv('BREVO_CONTACT_EMAIL') ?: ($GLOBALS['brevoContactEmail'] ?? '')));
    if ($email !== '') {
        return $email;
    }

    return brevoSenderConfig()['email'];
}

function brevoWhatsAppNumber(): string
{
    $configured = trim((string)(getenv('BREVO_WHATSAPP_NUMBER') ?: ($GLOBALS['brevoWhatsAppNumber'] ?? '')));
    if ($configured !== '') {
        return preg_replace('/\D+/', '', $configured) ?: '447872500272';
    }

    return '447872500272';
}

function brevoWhatsAppUrl(): string
{
    return 'https://wa.me/' . brevoWhatsAppNumber();
}

/**
 * Green WhatsApp CTA block for customer emails.
 */
function brevoWhatsAppButtonHtml(): string
{
    $href = htmlspecialchars(brevoWhatsAppUrl(), ENT_QUOTES, 'UTF-8');

    return <<<HTML
          <tr>
            <td align="center" style="padding:0 20px 28px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="300" style="width:300px; background-color:#25D366; border-radius:4px;">
                <tr>
                  <td align="center" style="padding:14px 20px; border-radius:4px;">
                    <a href="{$href}" target="_blank" style="display:block; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; line-height:1.2;">Message us on WhatsApp</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
HTML;
}

function brevoLogoUrl(): string
{
    $url = trim((string)(getenv('BREVO_LOGO_URL') ?: ($GLOBALS['brevoLogoUrl'] ?? '')));

    return $url !== '' ? $url : BREVO_DEFAULT_LOGO_URL;
}

function formatQuoteCurrency(string $value): string
{
    $value = trim($value);
    if ($value === '' || !is_numeric($value)) {
        return '';
    }

    return '£' . number_format((float)$value, 2);
}

function formatPreferredTrainingDate(string $preferredDateTime, bool $dateNotSure): string
{
    if ($dateNotSure) {
        return '';
    }

    $preferredDateTime = trim($preferredDateTime);
    if ($preferredDateTime === '') {
        return '';
    }

    // Already a human-readable label (e.g. from a previous format pass).
    if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $preferredDateTime)) {
        return $preferredDateTime;
    }

    // Date-only: ignore any time/timezone suffix so emails never shift by a day.
    $dateOnly = substr(str_replace(' ', 'T', $preferredDateTime), 0, 10);
    $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateOnly, new \DateTimeZone('Europe/London'));
    if ($dt === false || $dt->format('Y-m-d') !== $dateOnly) {
        return $preferredDateTime;
    }

    return $dt->format('l j F Y');
}

function quoteEmailHasPreferredDate(array $data): bool
{
    $preferredDate = trim((string)($data['preferredDate'] ?? ''));

    return $preferredDate !== '';
}

/**
 * @param array<string, mixed> $data
 */
function buildQuoteSummaryRows(array $data): string
{
    $rows = [];
    $items = [
        'Course' => (string)($data['course'] ?? ''),
        'Sector' => (string)($data['sector'] ?? ''),
        'Delivery' => trim((string)($data['format'] ?? '') . ((string)($data['courseStyle'] ?? '') !== '' ? ' (' . (string)$data['courseStyle'] . ')' : '')),
        'Delegates' => (string)($data['attendees'] ?? ''),
    ];

    if (quoteEmailHasPreferredDate($data)) {
        $items['Preferred date'] = (string)$data['preferredDate'];
    }

    foreach ($items as $label => $value) {
        if ($value !== '') {
            $rows[] = quoteEmailSummaryRow($label, $value, false);
        }
    }

    $quoteDisplay = trim((string)($data['quoteDisplay'] ?? ''));
    if ($quoteDisplay !== '') {
        $rows[] = quoteEmailSummaryRow('Quote total (Including Travel but Excluding VAT)', $quoteDisplay, true);
    }

    if ($rows === []) {
        return quoteEmailSummaryRow('Status', 'Your personalised quote is being prepared.', false);
    }

    return implode('', $rows);
}

function quoteEmailSummaryRow(string $label, string $value, bool $highlight): string
{
    $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $valueEsc = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $valueStyle = $highlight
        ? 'color:#0255a4; font-size:20px; font-weight:700;'
        : 'color:#414141; font-size:15px; font-weight:600;';
    $rowBg = $highlight ? 'background-color:#f4f9ff;' : 'background-color:#ffffff;';

    return <<<HTML
<tr>
  <td width="38%" style="{$rowBg} padding:12px 16px; border-bottom:1px solid #e8eef5; color:#2e5d84; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.4; vertical-align:top;">
    {$labelEsc}
  </td>
  <td width="62%" style="{$rowBg} padding:12px 16px; border-bottom:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; line-height:1.4; vertical-align:top;">
    <span style="{$valueStyle}">{$valueEsc}</span>
  </td>
</tr>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildTrainingDateBlock(array $data): string
{
    if (quoteEmailHasPreferredDate($data)) {
        $date = htmlspecialchars((string)$data['preferredDate'], ENT_QUOTES, 'UTF-8');

        return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f9ff; border:1px solid #d8e8f8; border-radius:4px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#414141; text-align:center;">
      <p style="margin:0 0 6px; color:#0255a4; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Your preferred training date</p>
      <p style="margin:0; font-size:17px; font-weight:700; color:#16324a;">{$date}</p>
    </td>
  </tr>
</table>
HTML;
    }

    return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#fffdf5; border:1px solid #e8dfc0; border-radius:4px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#414141; text-align:center;">
      <p style="margin:0 0 6px; color:#0255a4; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Preferred training date</p>
      <p style="margin:0; font-size:16px; font-weight:600; color:#5a4a20;">Not yet selected</p>
      <p style="margin:10px 0 0; font-size:14px; color:#666666;">A member of our team will contact you to arrange a suitable date.</p>
    </td>
  </tr>
</table>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildDelegatesBlock(array $data): string
{
    $attendees = trim((string)($data['attendees'] ?? ''));
    $attendeesEsc = $attendees !== ''
        ? htmlspecialchars($attendees, ENT_QUOTES, 'UTF-8')
        : '—';
    $delegateLabel = $attendees === '1' ? 'delegate' : 'delegates';
    $confirmLine = $attendees !== ''
        ? 'Please confirm this number is correct. If you need to adjust it, reply to this email or contact us.'
        : 'Please confirm how many delegates will attend. Reply to this email or contact us with the final number.';

    return <<<HTML
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f4f9ff; border:1px solid #d8e8f8; border-radius:4px;">
  <tr>
    <td style="padding:16px 18px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.5; color:#414141; text-align:center;">
      <p style="margin:0 0 6px; color:#0255a4; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.4px;">Number of delegates</p>
      <p style="margin:0; font-size:28px; font-weight:700; color:#0255a4; line-height:1.2;">{$attendeesEsc}</p>
      <p style="margin:4px 0 0; font-size:14px; color:#2e5d84;">{$delegateLabel} on your quote</p>
      <p style="margin:12px 0 0; font-size:14px; color:#666666;">{$confirmLine}</p>
    </td>
  </tr>
</table>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildQuoteEmailHtml(array $data): string
{
    $name = htmlspecialchars((string)($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $contactEmail = htmlspecialchars(brevoContactEmail(), ENT_QUOTES, 'UTF-8');
    $logoSrc = htmlspecialchars(brevoLogoUrl(), ENT_QUOTES, 'UTF-8');
    $quoteRows = buildQuoteSummaryRows($data);
    $trainingDateBlock = buildTrainingDateBlock($data);
    $delegatesBlock = buildDelegatesBlock($data);
    $whatsappBlock = brevoWhatsAppButtonHtml();

    $acceptUrl = resolveQuoteAcceptUrl($data);
    if ($acceptUrl === '') {
        $acceptUrl = 'mailto:' . brevoContactEmail() . '?subject=' . rawurlencode('Accept Quote - Safer Handling Training');
    }
    $acceptHref = htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Safer Handling Training Quote</title>
</head>
<body bgcolor="#ffffff" style="margin:0; padding:0; background-color:#ffffff; color:#414141;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; width:100%;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff;">

          <!-- Logo -->
          <tr>
            <td align="center" style="padding:10px 15px 20px;">
              <img src="{$logoSrc}" alt="Safer Handling" width="200" border="0" style="display:block; width:200px; max-width:200px; height:auto;" />
            </td>
          </tr>

          <!-- Greeting -->
          <tr>
            <td align="center" style="padding:0 20px 10px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">Hello {$name} 👋</p>
              <p style="margin:0 0 12px;">Thank you for requesting a quote for our <strong style="color:#0255a4;">Safer Handling Training</strong>.</p>
              <p style="margin:0;">Please find your quote referenced below for your review.</p>
            </td>
          </tr>

          <!-- Heading -->
          <tr>
            <td align="center" style="padding:24px 20px 8px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
              <h1 style="margin:0; font-size:26px; font-weight:700; color:#0255a4; line-height:1.3;">Your Training Quote</h1>
            </td>
          </tr>

          <!-- Intro -->
          <tr>
            <td align="center" style="padding:0 20px 20px; font-family:Arial,Helvetica,sans-serif; font-size:16px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0;">If you are happy to proceed, click <strong>Accept Quote and add venue details</strong> to open the accept quote form, accept terms, and add your venue details.</p>
            </td>
          </tr>

          <!-- Accept Quote button -->
          <tr>
            <td align="center" style="padding:0 20px 32px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="300" style="width:300px; background-color:#0255a4; border-radius:4px;">
                <tr>
                  <td align="center" style="padding:14px 20px; border-radius:4px;">
                    <a href="{$acceptHref}" target="_blank" style="display:block; font-family:Arial,Helvetica,sans-serif; font-size:16px; font-weight:700; color:#ffffff; text-decoration:none; line-height:1.2;">Accept Quote and add venue details</a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Quote summary -->
          <tr>
            <td style="padding:0 20px 28px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #d8e8f8; border-radius:4px; overflow:hidden;">
                <tr>
                  <td colspan="2" style="background-color:#0255a4; padding:12px 16px; font-family:Arial,Helvetica,sans-serif; font-size:15px; font-weight:700; color:#ffffff;">
                    Your Quote Summary
                  </td>
                </tr>
                {$quoteRows}
              </table>
            </td>
          </tr>

          <!-- Training date -->
          <tr>
            <td style="padding:0 20px 8px; font-family:Arial,Helvetica,sans-serif;">
              <h2 style="margin:0 0 10px; font-size:20px; font-weight:700; color:#0255a4; text-align:center;">Training Date</h2>
              <p style="margin:0 0 14px; font-size:15px; line-height:1.5; color:#666666; text-align:center;">If you have not already selected a preferred training date, please choose one below:</p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 20px 28px;">
              {$trainingDateBlock}
            </td>
          </tr>

          <!-- Delegates -->
          <tr>
            <td style="padding:0 20px 8px; font-family:Arial,Helvetica,sans-serif;">
              <h2 style="margin:0 0 10px; font-size:20px; font-weight:700; color:#0255a4; text-align:center;">Number of Delegates</h2>
              <p style="margin:0 0 14px; font-size:15px; line-height:1.5; color:#666666; text-align:center;">Please confirm the number of delegates who will be attending:</p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 20px 28px;">
              {$delegatesBlock}
            </td>
          </tr>

          <!-- Closing -->
          <tr>
            <td align="center" style="padding:0 20px 16px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0 0 12px;">If you have any questions regarding the quote or the training, please don't hesitate to <a href="mailto:{$contactEmail}" style="color:#0255a4; font-weight:700; text-decoration:underline;">contact us</a>.</p>
              <p style="margin:0;">Thank you for considering us for your training requirements. We look forward to working with you.</p>
            </td>
          </tr>
{$whatsappBlock}
          <!-- Footer -->
          <tr>
            <td align="center" style="padding:20px; border-top:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#666666; text-align:center;">
              <strong style="color:#0255a4;">Safer Handling</strong><br />
              <a href="https://www.safer-handling.co.uk" style="color:#0255a4; text-decoration:underline;">www.safer-handling.co.uk</a>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildQuoteEmailText(array $data): string
{
    $acceptUrl = resolveQuoteAcceptUrl($data);

    $lines = [
        'Hello ' . (string)($data['name'] ?? '') . ',',
        '',
        'Thank you for requesting a quote for our Safer Handling Training.',
        'Please find your quote referenced below for your review.',
        '',
        'If you are happy to proceed, open the booking form to accept your quote and add venue details:',
        $acceptUrl !== '' ? $acceptUrl : ('Email ' . brevoContactEmail() . ' to accept your quote.'),
        '',
        '--- Your Quote Summary ---',
    ];

    foreach (
        [
            'Course' => (string)($data['course'] ?? ''),
            'Sector' => (string)($data['sector'] ?? ''),
            'Delivery' => trim((string)($data['format'] ?? '') . ((string)($data['courseStyle'] ?? '') !== '' ? ' (' . (string)$data['courseStyle'] . ')' : '')),
            'Delegates' => (string)($data['attendees'] ?? ''),
            'Preferred date' => quoteEmailHasPreferredDate($data) ? (string)$data['preferredDate'] : '',
            'Quote total (Including Travel but Excluding VAT)' => (string)($data['quoteDisplay'] ?? ''),
        ] as $label => $value
    ) {
        if ($value !== '') {
            $lines[] = $label . ': ' . $value;
        }
    }

    $attendees = trim((string)($data['attendees'] ?? ''));
    $dateLine = quoteEmailHasPreferredDate($data)
        ? (string)$data['preferredDate']
        : 'Not yet selected — our team will contact you to arrange a date.';

    $lines = array_merge($lines, [
        '',
        'Training Date',
        $dateLine,
        '',
        'Number of Delegates',
        $attendees !== '' ? $attendees . ' delegate(s) on your quote' : 'Please confirm delegate numbers with us.',
        '',
        'If you have any questions regarding the quote or the training, please contact us at ' . brevoContactEmail() . '.',
        'WhatsApp: ' . brevoWhatsAppUrl(),
        '',
        'Thank you for considering us for your training requirements. We look forward to working with you.',
        '',
        'Safer Handling',
        'https://www.safer-handling.co.uk',
    ]);

    return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $quoteData
 * @param array{content?:string,filename?:string}|null $pdfAttachment Raw PDF bytes + filename
 */
function sendQuoteEmailViaBrevo(string $toEmail, string $toName, array $quoteData, ?array $pdfAttachment = null): void
{
    $apiKey = brevoApiKey();
    if ($apiKey === '') {
        throw new RuntimeException('Brevo API key is not configured.');
    }

    $sender = brevoSenderConfig();
    $payload = [
        'sender' => $sender,
        'to' => [
            [
                'email' => $toEmail,
                'name' => $toName,
            ],
        ],
        'replyTo' => [
            'email' => brevoContactEmail(),
            'name' => $sender['name'],
        ],
        'subject' => 'Your Safer Handling Training Quote',
        'htmlContent' => buildQuoteEmailHtml($quoteData),
        'textContent' => buildQuoteEmailText($quoteData),
    ];

    $pdfContent = is_array($pdfAttachment) ? (string)($pdfAttachment['content'] ?? '') : '';
    if ($pdfContent !== '') {
        $filename = trim((string)($pdfAttachment['filename'] ?? 'Safer-Handling-Quote.pdf'));
        if ($filename === '') {
            $filename = 'Safer-Handling-Quote.pdf';
        }
        $payload['attachment'] = [[
            'content' => base64_encode($pdfContent),
            'name' => $filename,
        ]];
    }

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL for Brevo.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Brevo API request failed: ' . $err);
    }
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if ($status >= 400) {
        $message = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
        if ($message === '') {
            $message = 'Brevo API returned HTTP ' . $status . '.';
        }
        throw new RuntimeException($message);
    }
}

function brevoOfficeEmail(): string
{
    $email = appConfigValue('BREVO_OFFICE_EMAIL', 'brevoOfficeEmail', 'office@safer-handling.co.uk');
    if ($email !== '') {
        return $email;
    }

    return 'office@safer-handling.co.uk';
}

function brevoLeadNotificationEnabled(): bool
{
    $env = getenv('BREVO_LEAD_NOTIFICATION_ENABLED');
    if ($env !== false && $env !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    if (array_key_exists('brevoLeadNotificationEnabled', $GLOBALS)) {
        return (bool)$GLOBALS['brevoLeadNotificationEnabled'];
    }

    return true;
}

/**
 * @param array<string, mixed> $data
 */
function buildNewLeadEmailHtml(array $data): string
{
    $name = htmlspecialchars((string)($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars((string)($data['email'] ?? ''), ENT_QUOTES, 'UTF-8');
    $enquiryType = htmlspecialchars((string)($data['enquiryType'] ?? ''), ENT_QUOTES, 'UTF-8');
    $flowLabel = htmlspecialchars((string)($data['flowLabel'] ?? ''), ENT_QUOTES, 'UTF-8');
    $enquiryId = htmlspecialchars((string)($data['enquiryId'] ?? ''), ENT_QUOTES, 'UTF-8');
    $course = htmlspecialchars((string)($data['course'] ?? ''), ENT_QUOTES, 'UTF-8');
    $sector = htmlspecialchars((string)($data['sector'] ?? ''), ENT_QUOTES, 'UTF-8');
    $format = htmlspecialchars((string)($data['format'] ?? ''), ENT_QUOTES, 'UTF-8');
    $courseStyle = htmlspecialchars((string)($data['courseStyle'] ?? ''), ENT_QUOTES, 'UTF-8');
    $attendees = htmlspecialchars((string)($data['attendees'] ?? ''), ENT_QUOTES, 'UTF-8');
    $preferredDate = htmlspecialchars((string)($data['preferredDate'] ?? ''), ENT_QUOTES, 'UTF-8');
    $quoteDisplay = htmlspecialchars((string)($data['quoteDisplay'] ?? ''), ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars((string)($data['address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $extraNotes = nl2br(htmlspecialchars((string)($data['extraNotes'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $logoSrc = htmlspecialchars(brevoLogoUrl(), ENT_QUOTES, 'UTF-8');

    $rows = '';
    $fields = [
        'Enquiry ID' => $enquiryId,
        'Name' => $name,
        'Email' => $email,
        'Enquiry type' => $enquiryType,
        'Lead type' => $flowLabel,
        'Course' => $course,
        'Sector' => $sector,
        'Delivery' => trim($format . ($courseStyle !== '' ? ' (' . $courseStyle . ')' : '')),
        'Delegates' => $attendees,
        'Preferred date' => $preferredDate,
        'Quote (Including Travel but Excluding VAT)' => $quoteDisplay,
        'Address' => $address,
    ];

    foreach ($fields as $label => $value) {
        if ($value === '') {
            continue;
        }
        $rows .= '<tr>'
            . '<td style="padding:8px 12px; border-bottom:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#0255a4; font-weight:700; width:160px; vertical-align:top;">'
            . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            . '</td>'
            . '<td style="padding:8px 12px; border-bottom:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#414141; vertical-align:top;">'
            . $value
            . '</td>'
            . '</tr>';
    }

    if ($extraNotes !== '') {
        $rows .= '<tr>'
            . '<td style="padding:8px 12px; border-bottom:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#0255a4; font-weight:700; width:160px; vertical-align:top;">Notes</td>'
            . '<td style="padding:8px 12px; border-bottom:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:14px; color:#414141; vertical-align:top;">'
            . $extraNotes
            . '</td>'
            . '</tr>';
    }

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>New Safer Handling Lead</title>
</head>
<body bgcolor="#ffffff" style="margin:0; padding:0; background-color:#ffffff; color:#414141;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff; width:100%;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px; max-width:600px; background-color:#ffffff;">
          <tr>
            <td align="center" style="padding:10px 15px 20px;">
              <img src="{$logoSrc}" alt="Safer Handling" width="200" border="0" style="display:block; width:200px; max-width:200px; height:auto;" />
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 8px; font-family:Arial,Helvetica,sans-serif; text-align:center;">
              <h1 style="margin:0; font-size:24px; font-weight:700; color:#0255a4; line-height:1.3;">New lead submitted</h1>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 20px; font-family:Arial,Helvetica,sans-serif; font-size:15px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0;">A new enquiry has been submitted through the Safer Handling form.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:0 20px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border:1px solid #d8e8f8; border-radius:8px; overflow:hidden;">
                {$rows}
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:0 20px 24px; font-family:Arial,Helvetica,sans-serif; font-size:14px; line-height:1.6; color:#414141; text-align:center;">
              <p style="margin:0;">Reply to this email to contact the lead directly.</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:20px; border-top:1px solid #e8eef5; font-family:Arial,Helvetica,sans-serif; font-size:13px; line-height:1.5; color:#666666; text-align:center;">
              <strong style="color:#0255a4;">Safer Handling</strong><br />
              <a href="https://www.safer-handling.co.uk" style="color:#0255a4; text-decoration:underline;">www.safer-handling.co.uk</a>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

/**
 * @param array<string, mixed> $data
 */
function buildNewLeadEmailText(array $data): string
{
    $lines = [
        'New lead submitted',
        '',
        'A new enquiry has been submitted through the Safer Handling form.',
        '',
    ];

    $fields = [
        'Enquiry ID' => (string)($data['enquiryId'] ?? ''),
        'Name' => (string)($data['name'] ?? ''),
        'Email' => (string)($data['email'] ?? ''),
        'Enquiry type' => (string)($data['enquiryType'] ?? ''),
        'Lead type' => (string)($data['flowLabel'] ?? ''),
        'Course' => (string)($data['course'] ?? ''),
        'Sector' => (string)($data['sector'] ?? ''),
        'Delivery' => trim((string)($data['format'] ?? '') . ((string)($data['courseStyle'] ?? '') !== '' ? ' (' . (string)$data['courseStyle'] . ')' : '')),
        'Delegates' => (string)($data['attendees'] ?? ''),
        'Preferred date' => (string)($data['preferredDate'] ?? ''),
        'Quote (Including Travel but Excluding VAT)' => (string)($data['quoteDisplay'] ?? ''),
        'Address' => (string)($data['address'] ?? ''),
        'Notes' => (string)($data['extraNotes'] ?? ''),
    ];

    foreach ($fields as $label => $value) {
        if (trim($value) === '') {
            continue;
        }
        $lines[] = $label . ': ' . $value;
    }

    $lines[] = '';
    $lines[] = 'Reply to this email to contact the lead directly.';
    $lines[] = '';
    $lines[] = 'Safer Handling';
    $lines[] = 'https://www.safer-handling.co.uk';

    return implode("\n", $lines);
}

/**
 * @param array<string, mixed> $leadData
 */
function sendNewLeadNotificationViaBrevo(array $leadData): void
{
    $apiKey = brevoApiKey();
    if ($apiKey === '') {
        throw new RuntimeException('Brevo API key is not configured.');
    }

    $officeEmail = brevoOfficeEmail();
    if ($officeEmail === '') {
        throw new RuntimeException('Office notification email is not configured.');
    }

    $customerEmail = trim((string)($leadData['email'] ?? ''));
    $customerName = trim((string)($leadData['name'] ?? ''));
    $flowLabel = trim((string)($leadData['flowLabel'] ?? 'Enquiry'));
    $subjectName = $customerName !== '' ? $customerName : $customerEmail;
    $subject = 'New lead: ' . ($subjectName !== '' ? $subjectName : $flowLabel);

    $sender = brevoSenderConfig();
    $payload = [
        'sender' => $sender,
        'to' => [
            [
                'email' => $officeEmail,
                'name' => 'Safer Handling Office',
            ],
        ],
        'replyTo' => [
            'email' => $customerEmail !== '' ? $customerEmail : brevoContactEmail(),
            'name' => $customerName !== '' ? $customerName : $sender['name'],
        ],
        'subject' => $subject,
        'htmlContent' => buildNewLeadEmailHtml($leadData),
        'textContent' => buildNewLeadEmailText($leadData),
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL for Brevo.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'accept: application/json',
            'api-key: ' . $apiKey,
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $raw = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Brevo API request failed: ' . $err);
    }
    curl_close($ch);

    $decoded = json_decode($raw, true);
    if ($status >= 400) {
        $message = is_array($decoded) ? trim((string)($decoded['message'] ?? '')) : '';
        if ($message === '') {
            $message = 'Brevo API returned HTTP ' . $status . '.';
        }
        throw new RuntimeException($message);
    }
}

/**
 * @param array<string, mixed> $quoteData
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function buildNewLeadEmailData(
    string $name,
    string $email,
    string $enquiryType,
    ?int $enquiryId,
    bool $isTrainerFlow,
    array $quoteData,
    array $post
): array {
    $addressParts = mondayAddressFromPost($post);
    $address = trim(implode(', ', array_filter([
        $addressParts['addressLine1'] ?? '',
        $addressParts['addressLine2'] ?? '',
        $addressParts['addressTown'] ?? '',
        $addressParts['addressPostcode'] ?? '',
    ], static fn ($part): bool => trim((string)$part) !== '')));

    return [
        'enquiryId' => $enquiryId !== null ? (string)$enquiryId : '',
        'name' => $name,
        'email' => $email,
        'enquiryType' => $enquiryType,
        'flowLabel' => $isTrainerFlow ? 'Become a trainer' : 'Organisation training',
        'course' => (string)($quoteData['course'] ?? ''),
        'sector' => (string)($quoteData['sector'] ?? ''),
        'format' => (string)($quoteData['format'] ?? ''),
        'courseStyle' => (string)($quoteData['courseStyle'] ?? ''),
        'attendees' => (string)($quoteData['attendees'] ?? ''),
        'preferredDate' => (string)($quoteData['preferredDate'] ?? ''),
        'quoteDisplay' => (string)($quoteData['quoteDisplay'] ?? ''),
        'address' => $address,
        'extraNotes' => trim((string)($post['extraNotes'] ?? '')),
    ];
}

/**
 * @param array<string, string> $resolved
 */
function buildQuoteEmailDataFromResolvedOrganisation(array $resolved, string $name, string $email): array
{
    require_once __DIR__ . '/monday_helpers.php';

    return array_merge([
        'name' => $name,
        'course' => $resolved['orgCourse'] ?? '',
        'format' => $resolved['deliveryPreference'] ?? '',
        'courseStyle' => $resolved['courseStyle'] ?? '',
        'sector' => $resolved['sector'] ?? '',
        'attendees' => $resolved['attendees'] ?? '',
        'preferredDate' => formatPreferredTrainingDate(
            $resolved['preferredDateTime'] ?? '',
            ($resolved['dateNotSure'] ?? '0') === '1'
        ),
        'quoteValue' => (string)($resolved['quoteValue'] ?? ''),
        'quoteDisplay' => formatQuoteCurrency($resolved['quoteValue'] ?? ''),
        'email' => $email,
        'acceptQuoteUrl' => '',
        'xeroItemCode' => (string)($resolved['orgCourse'] ?? ''),
    ], mondayAddressFromPost($resolved));
}

/**
 * @param array<string, mixed> $post
 */
function buildQuoteEmailDataFromSubmission(array $post, string $name, string $email): array
{
    $audienceType = trim((string)($post['audienceType'] ?? ''));
    $personalGoal = trim((string)($post['personalGoal'] ?? ''));
    $isTrainerFlow = $audienceType === 'me' && $personalGoal === 'becomeTrainer';

    if ($isTrainerFlow) {
        $course = trim((string)($post['trainerCourseSelect'] ?? ''));
        $attendees = trim((string)($post['trainerAttendees'] ?? ''));
        $format = 'Face to face';
        $courseStyle = 'Full-day';
        $sector = '';
        $preferredDateTime = '';
        $dateNotSure = false;
    } else {
        $course = trim((string)($post['orgCourse'] ?? ''));
        $format = trim((string)($post['courseFormat'] ?? ''));
        $courseStyle = trim((string)($post['formatSubOption'] ?? ''));
        $sector = trim((string)($post['sector'] ?? ''));
        $attendees = trim((string)($post['attendees'] ?? ($post['matrixAttendees'] ?? '')));

        // Prefer preferredDate / preferredDateTime via the shared normaliser so a
        // stale dateNotSure flag or missing hidden field cannot shift the email date.
        require_once __DIR__ . '/enquiry_logger.php';
        $preferred = enquiryPreferredDateFromPost($post);
        $preferredDateTime = $preferred['preferredDateTime'];
        $dateNotSure = $preferred['dateNotSure'];
    }

    $quoteValue = trim((string)($post['quoteValue'] ?? ''));

    require_once __DIR__ . '/monday_helpers.php';

    return array_merge([
        'name' => $name,
        'course' => $course,
        'format' => $format,
        'courseStyle' => $courseStyle,
        'sector' => $sector,
        'attendees' => $attendees,
        'preferredDate' => formatPreferredTrainingDate($preferredDateTime, $dateNotSure),
        'quoteValue' => $quoteValue,
        'quoteDisplay' => formatQuoteCurrency($quoteValue),
        'email' => $email,
        'acceptQuoteUrl' => '',
        'xeroItemCode' => $course,
    ], mondayAddressFromPost($post));
}

/**
 * Send quote via Xero (create quote + PDF) emailed through Brevo when Xero is enabled,
 * otherwise send the Brevo HTML quote email only.
 *
 * @param array<string, mixed> $quoteData
 * @return array{channel:string,xero?:array<string,mixed>}
 */
function sendQuoteToClient(string $toEmail, string $toName, array $quoteData): array
{
    require_once __DIR__ . '/xero.php';

    $quoteData['email'] = $toEmail;
    $quoteData['name'] = $quoteData['name'] ?? $toName;
    // Always resolve Accept Quote → /booking before Xero/Brevo send.
    resolveQuoteAcceptUrl($quoteData);
    if (
        (int)($quoteData['enquiryId'] ?? 0) > 0
        && !quoteAcceptUrlIsBookingForm((string)($quoteData['acceptQuoteUrl'] ?? ''))
    ) {
        throw new RuntimeException(
            'Could not build the Accept Quote booking form link. Set Form base URL in Admin → Settings.'
        );
    }

    if (xeroEnabled()) {
        $result = xeroSendQuoteToClient($toName, $toEmail, $quoteData);
        $quoteNumber = (string)($result['quote']['QuoteNumber'] ?? '');
        if ($quoteNumber !== '') {
            $quoteData['xeroQuoteNumber'] = $quoteNumber;
        }
        sendQuoteEmailViaBrevo($toEmail, $toName, $quoteData, $result['pdf'] ?? null);

        return [
            'channel' => 'xero',
            'xero' => [
                'contact' => $result['contact'],
                'quote' => $result['quote'],
            ],
        ];
    }

    sendQuoteEmailViaBrevo($toEmail, $toName, $quoteData);

    return ['channel' => 'brevo'];
}
