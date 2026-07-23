<?php
declare(strict_types=1);

/**
 * Shared “Inform, follow or engage” content from email.html — used in emails and thank-you pages.
 *
 * @return list<array{label:string,url:string,icon:string}>
 */
function saferHandlingSocialLinks(): array
{
    $iconBase = 'https://creative-assets.mailinblue.com/editor/social-icons/squared_colored';

    return [
        [
            'label' => 'Facebook',
            'url' => 'https://www.facebook.com/share/18pchs1JbB/?mibextid=wwXIfr',
            'icon' => $iconBase.'/facebook_32px.png',
        ],
        [
            'label' => 'YouTube',
            'url' => 'https://youtube.com/@saferhandling2024?si=vsTs-iS6JF79zTr0',
            'icon' => $iconBase.'/youtube_32px.png',
        ],
        [
            'label' => 'LinkedIn',
            'url' => 'https://www.linkedin.com/in/saferhandling?utm_source=share_via&utm_content=profile&utm_medium=member_ios',
            'icon' => $iconBase.'/linkedin_32px.png',
        ],
        [
            'label' => 'TikTok',
            'url' => 'https://www.tiktok.com/@saferhandling?_r=1&_t=ZN-96FMExus2fm',
            'icon' => $iconBase.'/tiktok_32px.png',
        ],
        [
            'label' => 'Instagram',
            'url' => 'https://www.threads.com/@saferhandling',
            'icon' => $iconBase.'/instagram_32px.png',
        ],
    ];
}

function saferHandlingWhatsAppMessageUrl(): string
{
    return 'https://wa.me/message/HIBUZXAHTQDDA1';
}

function saferHandlingSignatureImageUrl(): string
{
    return 'https://img.mailinblue.com/8246699/images/content_library/original/6a0308d29d7025c9e500ca90.png';
}

function saferHandlingDirectorPhotoUrl(): string
{
    return 'https://img.mailinblue.com/8246699/images/content_library/original/6a0305839d7025c9e500c93f.jpeg';
}

function saferHandlingInformFollowEngageWebCss(): string
{
    return <<<'CSS'
    .inform-engage {
      margin-top: 28px;
      padding: 22px 18px 8px;
      border-top: 1px solid #d8e8f8;
      text-align: center;
    }
    .inform-engage h3 {
      margin: 0 0 12px;
      font-size: 1.25rem;
      font-weight: 700;
      color: #0255a4;
    }
    .inform-engage p {
      margin: 0 auto 14px;
      max-width: 34rem;
      color: #2e5d84;
      font-size: 0.95rem;
      line-height: 1.5;
    }
    .inform-engage-socials,
    .inform-engage-whatsapp {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: center;
      gap: 12px;
      margin: 0 0 16px;
    }
    .inform-engage-socials a,
    .inform-engage-whatsapp a {
      display: inline-flex;
      width: 36px;
      height: 36px;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(2, 85, 164, 0.12);
      transition: transform 0.15s ease;
    }
    .inform-engage-socials a:hover,
    .inform-engage-whatsapp a:hover {
      transform: translateY(-2px);
    }
    .inform-engage-socials img,
    .inform-engage-whatsapp img {
      width: 36px;
      height: 36px;
      display: block;
    }
    .inform-engage-signoff {
      margin: 8px 0 0;
      color: #16324a;
      font-weight: 600;
    }
    .inform-engage-signature,
    .inform-engage-photo {
      display: block;
      margin: 12px auto 0;
      max-width: 220px;
      width: 100%;
      height: auto;
    }
    .inform-engage-name {
      margin: 12px 0 0;
      font-size: 1.05rem;
      font-weight: 700;
      color: #1f2d3d;
    }
    .inform-engage-role {
      margin: 4px 0 0;
      font-size: 0.95rem;
      color: #1f2d3d;
    }
CSS;
}

function saferHandlingInformFollowEngageWebHtml(): string
{
    $socialHtml = '';
    foreach (saferHandlingSocialLinks() as $link) {
        $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8');
        $socialHtml .= '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" aria-label="'.$label.'">'
            .'<img src="'.$icon.'" alt="'.$label.'" width="36" height="36" loading="lazy" />'
            .'</a>';
    }

    $whatsapp = htmlspecialchars(saferHandlingWhatsAppMessageUrl(), ENT_QUOTES, 'UTF-8');
    $whatsappIcon = htmlspecialchars(
        'https://creative-assets.mailinblue.com/editor/social-icons/squared_colored/whatsapp_32px.png',
        ENT_QUOTES,
        'UTF-8'
    );
    $signature = htmlspecialchars(saferHandlingSignatureImageUrl(), ENT_QUOTES, 'UTF-8');
    $photo = htmlspecialchars(saferHandlingDirectorPhotoUrl(), ENT_QUOTES, 'UTF-8');

    return <<<HTML
      <div class="inform-engage">
        <h3>Inform, follow or engage</h3>
        <p>I publish short videos and posts around the legalities of managing behaviours of concern and the vital health and safety, human rights and safeguarding frameworks which surround it so why not follow me on my socials below:</p>
        <div class="inform-engage-socials">
          {$socialHtml}
        </div>
        <p>If your request is an urgent one please contact us at our business WhatsApp</p>
        <div class="inform-engage-whatsapp">
          <a href="{$whatsapp}" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">
            <img src="{$whatsappIcon}" alt="WhatsApp" width="36" height="36" loading="lazy" />
          </a>
        </div>
        <p class="inform-engage-signoff">Kind Regards</p>
        <img class="inform-engage-signature" src="{$signature}" alt="Doug Melia signature" width="220" height="auto" loading="lazy" />
        <img class="inform-engage-photo" src="{$photo}" alt="Doug Melia" width="220" height="auto" loading="lazy" />
        <p class="inform-engage-name">Doug Melia</p>
        <p class="inform-engage-role">Director &amp; expert witness</p>
      </div>
HTML;
}

/**
 * Email-safe “Inform, follow or engage” block (table rows) matching email.html.
 */
function saferHandlingInformFollowEngageEmailHtml(): string
{
    $socialCells = '';
    $links = saferHandlingSocialLinks();
    $lastIndex = count($links) - 1;
    foreach ($links as $index => $link) {
        $url = htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8');
        $icon = htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8');
        $spacer = $index < $lastIndex
            ? '<td width="8" style="font-size:0;line-height:1px;">&nbsp;</td>'
            : '';
        $socialCells .= <<<HTML
                                                                                             <td width="32" style="font-weight:normal;">
                                                                                                <table cellspacing="0" cellpadding="0" border="0" role="presentation" width="32" style="width:32px;">
                                                                                                   <tr>
                                                                                                      <td style="font-size:0;line-height:0;padding:5px 0;">
                                                                                                        <a href="{$url}" target="_blank" style="color:#666;text-decoration:none;">
                                                                                                          <img src="{$icon}" width="32" height="32" border="0" alt="" style="display:block;width:32px;height:32px;" />
                                                                                                        </a>
                                                                                                      </td>
                                                                                                   </tr>
                                                                                                </table>
                                                                                             </td>
{$spacer}
HTML;
    }

    $whatsapp = htmlspecialchars(saferHandlingWhatsAppMessageUrl(), ENT_QUOTES, 'UTF-8');
    $whatsappIcon = htmlspecialchars(
        'https://creative-assets.mailinblue.com/editor/social-icons/squared_colored/whatsapp_32px.png',
        ENT_QUOTES,
        'UTF-8'
    );
    $signature = htmlspecialchars(saferHandlingSignatureImageUrl(), ENT_QUOTES, 'UTF-8');
    $photo = htmlspecialchars(saferHandlingDirectorPhotoUrl(), ENT_QUOTES, 'UTF-8');

    return <<<HTML
          <tr>
            <td align="center" style="padding:24px 15px 8px;font-family:Arial,Helvetica,sans-serif;text-align:center;">
              <h2 style="margin:0;font-size:24px;font-weight:700;color:#0255a4;line-height:1.3;">Inform, follow or engage</h2>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:12px 20px 16px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#414141;text-align:center;">
              <p style="margin:0;">I publish short videos and posts around the legalities of managing behaviours of concern and the vital health and safety, human rights and safeguarding frameworks which surround it so why not follow me on my socials below:</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:4px 20px 12px;">
              <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                <tr>
{$socialCells}
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:12px 20px 10px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#414141;text-align:center;">
              <p style="margin:0;">If your request is an urgent one please contact us at our business WhatsApp</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:4px 20px 16px;">
              <a href="{$whatsapp}" target="_blank" style="color:#666;text-decoration:none;">
                <img src="{$whatsappIcon}" width="32" height="32" border="0" alt="WhatsApp" style="display:inline-block;width:32px;height:32px;" />
              </a>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:8px 20px 4px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:#414141;text-align:center;">
              <p style="margin:0;">Kind Regards</p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:12px 20px 0;font-size:0;line-height:0;">
              <img src="{$signature}" width="285" border="0" alt="" style="display:block;width:285px;max-width:100%;height:auto;" />
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:12px 20px 0;font-size:0;line-height:0;">
              <img src="{$photo}" width="285" border="0" alt="Doug Melia" style="display:block;width:285px;max-width:100%;height:auto;" />
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:12px 20px 24px;font-family:Arial,Helvetica,sans-serif;text-align:center;">
              <p style="margin:0 0 4px;font-size:20px;font-weight:400;color:#1f2d3d;">Doug Melia</p>
              <p style="margin:0;font-size:20px;font-weight:400;color:#1f2d3d;">Director &amp; expert witness</p>
            </td>
          </tr>
HTML;
}

function saferHandlingEmailLogoUrl(): string
{
    $url = trim((string) (getenv('BREVO_LOGO_URL') ?: ($GLOBALS['brevoLogoUrl'] ?? '')));
    if ($url !== '') {
        return $url;
    }

    return 'https://img.mailinblue.com/8246699/images/content_library/original/6a02cfcf9d7025c9e500ab4b.jpg';
}

/**
 * Wrap email body rows in the email.html-style shell (logo + content + Inform section).
 *
 * @param string $bodyRowsHtml One or more <tr>...</tr> blocks for the main message.
 */
function saferHandlingEmailTemplate(string $title, string $bodyRowsHtml): string
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $logoSrc = htmlspecialchars(saferHandlingEmailLogoUrl(), ENT_QUOTES, 'UTF-8');
    $engage = saferHandlingInformFollowEngageEmailHtml();

    return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$safeTitle}</title>
</head>
<body bgcolor="#ffffff" text="#414141" link="#666666" style="margin:0;padding:0;background-color:#ffffff;color:#414141;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;width:100%;">
    <tr>
      <td align="center" style="padding:20px 10px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:600px;max-width:600px;background-color:#ffffff;">
          <tr>
            <td align="center" style="padding:10px 15px 20px;font-size:0;line-height:0;">
              <img src="{$logoSrc}" alt="Safer Handling" width="200" border="0" style="display:block;width:200px;max-width:200px;height:auto;" />
            </td>
          </tr>
{$bodyRowsHtml}
{$engage}
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
