<?php
declare(strict_types=1);

require_once __DIR__ . '/enquiry_logger.php';
require_once __DIR__ . '/brevo_email.php';

$enquiryIdParam = enquiryLoggerParseId($_GET['enquiry'] ?? ($_GET['enquiryId'] ?? null));
$token = trim((string)($_GET['token'] ?? ''));
$enquiry = null;
$prefill = [
    'bookerName' => '',
    'organisation' => '',
    'email' => '',
    'phone' => '',
    'venueAddress' => '',
    'invoiceName' => '',
    'invoiceEmail' => '',
    'invoiceAddress' => '',
    'preferredDate' => '',
];
$errorMessage = '';
$alreadySubmitted = false;
$preferredDateLocked = false;
$supportEmail = '';
$joiningUrl = bookingJoiningInstructionsUrl();

if ($enquiryIdParam === null || $token === '') {
    $errorMessage = 'This booking link is missing or incomplete. Please use the link from your email.';
} else {
    $enquiry = enquiryLoggerGetForResume($enquiryIdParam, $token);
    if ($enquiry === null) {
        $errorMessage = 'This booking link is invalid or has expired. Please contact Safer Handling for a new link.';
    } else {
        $bookingMeta = enquiryLoggerGetBookingDetails($enquiryIdParam);
        $alreadySubmitted = is_array($bookingMeta) && trim((string)($bookingMeta['booking_submitted_at'] ?? '')) !== '';

        $formData = json_decode((string)($enquiry['form_data_json'] ?? ''), true);
        if (!is_array($formData)) {
            $formData = [];
        }

        $addressParts = array_filter([
            trim((string)($formData['addressLine1'] ?? '')),
            trim((string)($formData['addressLine2'] ?? '')),
            trim((string)($formData['addressTown'] ?? '')),
            trim((string)($formData['addressPostcode'] ?? '')),
        ]);

        $preferredDate = enquiryPreferredDateOnly(
            (string)($enquiry['preferred_date_time'] ?? ($formData['preferredDate'] ?? ($formData['preferredDateTime'] ?? '')))
        );
        if ($preferredDate === '' && !empty($enquiry['date_not_sure'])) {
            $preferredDate = '';
        }

        $prefill = [
            'bookerName' => trim((string)($enquiry['name'] ?? '')),
            'organisation' => trim((string)($enquiry['organisation_company'] ?? ($formData['organisationCompany'] ?? ''))),
            'email' => trim((string)($enquiry['email'] ?? '')),
            'phone' => trim((string)($formData['phone'] ?? ($formData['phoneNumber'] ?? ''))),
            'venueAddress' => implode("\n", $addressParts),
            'invoiceName' => trim((string)($enquiry['name'] ?? '')),
            'invoiceEmail' => trim((string)($enquiry['email'] ?? '')),
            'invoiceAddress' => implode("\n", $addressParts),
            'preferredDate' => $preferredDate,
        ];

        if ($alreadySubmitted && is_array($bookingMeta['details'] ?? null)) {
            $saved = $bookingMeta['details'];
            foreach (array_keys($prefill) as $key) {
                if (isset($saved[$key]) && trim((string)$saved[$key]) !== '') {
                    $prefill[$key] = (string)$saved[$key];
                }
            }
            $prefill['preferredDate'] = enquiryPreferredDateOnly(
                (string)($saved['preferredDate'] ?? ($prefill['preferredDate'] ?? ''))
            ) ?: $prefill['preferredDate'];
        }

        $preferredDateLocked = enquiryPreferredDateIsLocked($prefill['preferredDate']);
        $supportEmail = function_exists('brevoContactEmail') ? brevoContactEmail() : 'training@safer-handling.co.uk';
        if ($supportEmail === '') {
            $supportEmail = 'training@safer-handling.co.uk';
        }
    }
}

function bookingH(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Safer Handling — Accept Quote and add venue details</title>
  <link rel="icon" type="image/png" href="assets/safer-handling-logo.png" />
  <style>
    :root {
      --brand-blue: #008afc;
      --brand-white: #ffffff;
      --text-dark: #16324a;
      --text-mid: #2e5d84;
      --border-soft: #d8e8f8;
      --surface-soft: #f7fbff;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Segoe UI", Arial, Helvetica, sans-serif;
      background:
        radial-gradient(circle at 12% 8%, rgba(0, 138, 252, 0.14), transparent 33%),
        radial-gradient(circle at 85% 7%, rgba(186, 218, 85, 0.2), transparent 28%),
        linear-gradient(180deg, #f5fbff 0%, #edf7ff 100%);
      color: var(--text-dark);
      line-height: 1.45;
    }
    .container { max-width: 820px; margin: 42px auto; padding: 0 18px 44px; }
    .card {
      background: var(--brand-white);
      border: 1px solid #cfe4f8;
      border-radius: 18px;
      box-shadow: 0 18px 45px rgba(0, 138, 252, 0.13), 0 2px 10px rgba(11, 71, 117, 0.07);
      overflow: visible;
    }
    .brand-header {
      background: #0255a4;
      padding: 24px;
      color: var(--brand-white);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 22px;
      border-radius: 18px 18px 0 0;
      overflow: hidden;
    }
    .logo { width: 220px; max-width: 42%; display: block; }
    .title-wrap h1 { margin: 0; font-size: 1.55rem; line-height: 1.15; }
    .title-wrap p { margin: 8px 0 0; opacity: 0.98; font-size: 0.98rem; }
    form, .message-panel { padding: 24px; }
    .section {
      border: 1px solid var(--border-soft);
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #fcfeff 100%);
      padding: 20px;
      margin-bottom: 22px;
    }
    .section h2 { margin: 0 0 6px; font-size: 1.2rem; color: #0255a4; }
    .section-intro { margin: 0 0 14px; color: var(--text-mid); font-size: 0.95rem; }
    .alert {
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 18px;
      font-weight: 600;
    }
    .alert-info { background: #eef7ff; border: 1px solid #b9d4ef; color: #20567e; }
    .alert-warn { background: #fff8e8; border: 1px solid #f0d48a; color: #8a5b00; }
    .alert-error { background: #fff7f7; border: 1px solid #f1c0c0; color: #b91c1c; }
    .alert-success { background: #f1f9eb; border: 1px solid #c5e3ad; color: #3f6d1c; }
    label { display: block; font-weight: 700; margin: 12px 0 7px; color: #20567e; }
    .hint { display: block; margin-top: 6px; color: var(--text-mid); font-size: 0.88rem; font-weight: 500; }
    input[type="text"],
    input[type="email"],
    input[type="tel"],
    input[type="date"],
    input[type="file"],
    textarea {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #b9d4ef;
      border-radius: 10px;
      font-size: 0.96rem;
      background: #fff;
      color: #133a59;
    }
    input[type="date"]:disabled,
    input[type="date"][readonly] {
      background: #f3f7fb;
      color: #3a5f7d;
      cursor: not-allowed;
    }
    textarea { min-height: 110px; resize: vertical; }
    .field-error {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
      background: #fff7f7 !important;
      scroll-margin-top: 96px;
    }
    .checkbox-row {
      display: flex;
      gap: 12px;
      align-items: flex-start;
      margin-top: 14px;
      padding: 12px;
      border: 1px solid #dcebf8;
      border-radius: 10px;
      background: #fff;
    }
    .checkbox-row input { margin-top: 3px; }
    .checkbox-row label { margin: 0; font-weight: 600; color: #16324a; }
    .terms-box {
      max-height: 220px;
      overflow: auto;
      border: 1px solid #d8e8f8;
      border-radius: 10px;
      padding: 14px;
      background: #f8fbff;
      font-size: 0.9rem;
      color: #2e5d84;
      white-space: pre-wrap;
    }
    .delegate-note {
      background: #fff8e8;
      border: 1px solid #f0d48a;
      border-radius: 10px;
      padding: 12px 14px;
      margin: 12px 0 8px;
      color: #8a5b00;
      font-size: 0.92rem;
    }
    .actions { margin-top: 8px; }
    button {
      border: 0;
      border-radius: 10px;
      padding: 12px 18px;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      background: #0255a4;
      color: #fff;
      box-shadow: 0 8px 18px rgba(2, 85, 164, 0.32);
    }
    button:disabled { opacity: 0.7; cursor: not-allowed; }
    .hidden { display: none; }
    .thank-you-view { padding: 34px 24px 38px; text-align: center; }
    .thank-you-view h2 { color: #0255a4; margin: 18px 0 8px; }
    .thank-you-view > p { margin: 0 auto; max-width: 560px; color: #2a5e84; }
<?= saferHandlingInformFollowEngageWebCss() ?>
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="brand-header">
        <img class="logo" src="assets/safer-handling-logo.png" alt="Safer Handling" />
        <div class="title-wrap">
          <h1>Accept Quote and add venue details</h1>
          <p>Accept terms and confirm your venue &amp; delegate details</p>
        </div>
      </div>

      <?php if ($errorMessage !== ''): ?>
        <div class="message-panel">
          <div class="alert alert-error"><?= bookingH($errorMessage) ?></div>
        </div>
      <?php elseif ($alreadySubmitted): ?>
        <div class="thank-you-view" id="alreadySubmittedView">
          <h2>Booking details already submitted</h2>
          <p>Thank you — we have received your booking details for this enquiry. Our team will be in touch if anything else is needed.</p>
          <?= saferHandlingInformFollowEngageWebHtml() ?>
        </div>
      <?php else: ?>
        <form id="bookingForm" action="submit_booking.php" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="enquiryId" value="<?= (int)$enquiryIdParam ?>" />
          <input type="hidden" name="token" value="<?= bookingH($token) ?>" />

          <?php if ($joiningUrl !== ''): ?>
            <div class="alert alert-info">
              <strong>Important — Joining Physical Instructions</strong><br />
              Please download the physical instructions before completing this form:
              <a href="<?= bookingH($joiningUrl) ?>" target="_blank" rel="noopener">Download joining instructions</a>
            </div>
          <?php endif; ?>

          <div class="section">
            <h2>Booker details</h2>
            <p class="section-intro">Please fill in the details below to complete your booking.</p>

            <label for="bookerName">Booker Name *</label>
            <input type="text" id="bookerName" name="bookerName" required maxlength="200" value="<?= bookingH($prefill['bookerName']) ?>" />

            <label for="organisation">Organisation</label>
            <input type="text" id="organisation" name="organisation" maxlength="200" value="<?= bookingH($prefill['organisation']) ?>" />

            <label for="email">Email Address *</label>
            <input type="email" id="email" name="email" required maxlength="200" value="<?= bookingH($prefill['email']) ?>" />

            <label for="phone">Phone Number *</label>
            <input type="tel" id="phone" name="phone" required maxlength="40" value="<?= bookingH($prefill['phone']) ?>" placeholder="e.g. +44 7700 900123" />
          </div>

          <div class="section">
            <h2>Training details</h2>
            <p class="section-intro">This will be where the training will take place. Please do not input a personal address.</p>

            <label for="preferredDate">Preferred date *</label>
            <?php if ($preferredDateLocked): ?>
              <input
                type="date"
                id="preferredDateDisplay"
                value="<?= bookingH($prefill['preferredDate']) ?>"
                readonly
                disabled
                aria-describedby="preferredDateLockMessage"
              />
              <input type="hidden" id="preferredDate" name="preferredDate" value="<?= bookingH($prefill['preferredDate']) ?>" />
              <div class="alert alert-warn" id="preferredDateLockMessage" style="margin-top:10px;margin-bottom:0;">
                Your preferred date is within 2 days and can no longer be changed online.
                Please contact support<?= $supportEmail !== '' ? ' at <a href="mailto:' . bookingH($supportEmail) . '">' . bookingH($supportEmail) . '</a>' : '' ?> if you need to rearrange.
              </div>
            <?php else: ?>
              <input
                type="date"
                id="preferredDate"
                name="preferredDate"
                required
                value="<?= bookingH($prefill['preferredDate']) ?>"
                min="<?= bookingH((new DateTimeImmutable('today', new DateTimeZone('Europe/London')))->format('Y-m-d')) ?>"
              />
              <span class="hint">Confirm or update your preferred training date.</span>
            <?php endif; ?>

            <label for="venueAddress">Training Venue Address *</label>
            <textarea id="venueAddress" name="venueAddress" required maxlength="1000"><?= bookingH($prefill['venueAddress']) ?></textarea>

            <div class="delegate-note">
              <strong>Important — Please read before entering delegate details</strong><br /><br />
              Enter one name per line in the Names field, and the corresponding email address per line in the Emails field.
              Make sure the order matches exactly — the first name must correspond to the first email, and so on.
              Incorrect ordering will result in training links being sent to the wrong person.
            </div>

            <label for="studentNames">Student Names</label>
            <textarea id="studentNames" name="studentNames" maxlength="5000" placeholder="One name per line"></textarea>
            <span class="hint">For a small number of delegates, list names here one per line.</span>

            <label for="studentEmails">Email Addresses</label>
            <textarea id="studentEmails" name="studentEmails" maxlength="5000" placeholder="One email per line"></textarea>

            <label for="studentNamesFile">Student Names — File Upload</label>
            <input type="file" id="studentNamesFile" name="studentNamesFile" accept=".csv,.xlsx,.xls,.txt,.pdf,.doc,.docx" />
            <span class="hint">For larger groups, upload a spreadsheet or document instead.</span>

            <label for="specialRequests">Special Requests</label>
            <textarea id="specialRequests" name="specialRequests" maxlength="2000" placeholder="Accessibility requirements, dietary needs, or anything else we should know"></textarea>

            <div class="checkbox-row">
              <input type="checkbox" id="venueRequirements" name="venueRequirements" value="1" required />
              <label for="venueRequirements">
                Venue Requirements * — I confirm our venue can meet the following requirements:
                Legal Briefing: a TV or projector with screen, so the trainer can connect their laptop or USB drive for the presentation.
                Physical Skills: a clear space large enough for delegates to practise techniques in groups of three, plus chairs available for the seated exercises.
              </label>
            </div>
          </div>

          <div class="section">
            <h2>Invoice details</h2>

            <label for="invoiceName">Invoice Name *</label>
            <input type="text" id="invoiceName" name="invoiceName" required maxlength="200" value="<?= bookingH($prefill['invoiceName']) ?>" />
            <span class="hint">Name to appear on the invoice — amend if different from above.</span>

            <label for="invoiceEmail">Invoice Email *</label>
            <input type="email" id="invoiceEmail" name="invoiceEmail" required maxlength="200" value="<?= bookingH($prefill['invoiceEmail']) ?>" />
            <span class="hint">Email address invoices should be sent to — amend if different from above.</span>

            <label for="invoiceAddress">Invoice Address *</label>
            <textarea id="invoiceAddress" name="invoiceAddress" required maxlength="1000"><?= bookingH($prefill['invoiceAddress']) ?></textarea>

            <label for="invoicePhone">Invoice Phone</label>
            <input type="tel" id="invoicePhone" name="invoicePhone" maxlength="40" />

            <label for="purchaseOrderNumber">Purchase Order Number</label>
            <input type="text" id="purchaseOrderNumber" name="purchaseOrderNumber" maxlength="100" />
          </div>

          <div class="section">
            <h2>Agreement</h2>
            <p class="section-intro">Payment Terms and Conditions</p>
            <div class="terms-box" tabindex="0"><?= bookingH(<<<'TERMS'
SAFER HANDLING – STANDARD TERMS & CONDITIONS

1. Confirmation of dates.
Once course dates or placements on courses have been booked/confirmed all payments owed will become due unless agreed otherwise.

2. Illness, Injury, Acts of God.
In the event of illness or some other unavoidable impediment to trainer availability, Safer Handling reserves the right to use replacement trainers of a suitable calibre, if available at short notice. In the event that training cannot take place as agreed due to trainer illness or impediment or due to travel problems caused by hazardous road or weather conditions, Safer Handling reserves the right to cancel the training. In this event, a new training day will be agreed. There may also be the option to undertake the course online, or as a hybrid/blended option.

3. In-House Course Fees.
£X + VAT per day (as agreed in course costs) + travelling expenses @ 0.45 pence per mile car travel or reimbursement of rail fare or airfare. Evening accommodation in the form of a budget hotel, evening meal & breakfast to be arranged or charged for if an overnight stay is required prior to a course commencing.

4. Postponement or Cancellation of Confirmed Course Dates – Cancellation Fees.
Should you decide to cancel your booking or should a course be cancelled by the course sponsor/booking agency prior to the course commencing once confirmed the following sliding scale of charges will apply:
28 days prior to course date: 50% fees per day cancelled
14 days prior to course date: 75% fees per day cancelled
7 days – 24 hrs or less: 100% fees per day cancelled

5. Overdue Payments and Formal Debt Recovery
Please note that any overdue payments may unfortunately result in formal legal action being taken to recover the debt owed including our legal right to claim interest on any outstanding amounts owed under The Late Payment of Commercial Debts (Interest) Act 1998 as supplemented and amended by the Commercial Debt Regulations 2002.

6. Copyright and use of training material
Our courses are carefully structured and based on up to date research and best practice. They are tailored to be context and environment specific to meet the needs of delegates and the training objectives of the client organisation.

The copyright of all course materials including Course Notes, Exercise Sheets, Visual Teaching Aids and PowerPoint presentations remains at all times the property of Safer Handling and/or the trainer who produced the material. All teaching and delegate material produced for use within our courses will be supplied by Safer Handling unless otherwise agreed. If course notes are printed by the client organisation, the copyright still remains that of Safer Handling and the author. Supporting course notes and material for delegates is supplied for use as a personal reference and revision aid. They must not be used for commercial training purposes or reproduced for wider distribution within the organisation or externally without the explicit agreement of Safer Handling.

7. Submission of work for marking and/or certification.
If applicable, all course work must be submitted within a reasonable period of time. For attendance courses the pre-course work must be submitted a minimum of two weeks prior to the course commencing.

For online courses all course work must be submitted within three months starting from the date of purchase of the course.
In exceptional circumstances extensions can be made to a maximum of six months for online courses only.

After that any certification becomes null and void.
TERMS
) ?></div>

            <div class="checkbox-row">
              <input type="checkbox" id="termsAccepted" name="termsAccepted" value="1" required />
              <label for="termsAccepted">I have read and agree to the above Safer Handling Terms and Conditions *</label>
            </div>
          </div>

          <div class="actions">
            <button type="submit" id="submitBtn" class="btn-primary">Submit booking details</button>
          </div>
        </form>

        <div class="thank-you-view hidden" id="thankYouView">
          <h2>Thank you</h2>
          <p>Your booking details have been submitted. Our team will use these to finalise your training arrangements.</p>
          <?= saferHandlingInformFollowEngageWebHtml() ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($errorMessage === '' && !$alreadySubmitted): ?>
  <script>
    (function () {
      var form = document.getElementById("bookingForm");
      var submitBtn = document.getElementById("submitBtn");
      var thankYouView = document.getElementById("thankYouView");

      function setError(el, on) {
        if (!el) return;
        if (el.type === "checkbox") {
          var row = el.closest(".checkbox-row");
          if (row) {
            row.classList.toggle("field-error", !!on);
          }
          return;
        }
        el.classList.toggle("field-error", !!on);
      }

      function requiredValue(id) {
        var el = document.getElementById(id);
        return el && String(el.value || "").trim() !== "";
      }

      function isScrollableErrorTarget(el) {
        if (!el || el.closest(".hidden")) {
          return false;
        }
        if (el.tagName === "INPUT" && String(el.type || "").toLowerCase() === "hidden") {
          return false;
        }
        var style = window.getComputedStyle(el);
        return style.display !== "none" && style.visibility !== "hidden";
      }

      function scrollElementIntoView(el) {
        if (!el) {
          return;
        }
        var rect = el.getBoundingClientRect();
        var y = Math.max(0, rect.top + (window.pageYOffset || document.documentElement.scrollTop || 0) - 96);
        try {
          el.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" });
        } catch (e1) {
          try { el.scrollIntoView(true); } catch (e2) {}
        }
        try {
          window.scrollTo({ top: y, left: 0, behavior: "smooth" });
        } catch (e3) {
          window.scrollTo(0, y);
        }
        document.documentElement.scrollTop = y;
        document.body.scrollTop = y;
      }

      function scrollToFirstError() {
        var selectors = ["input.field-error", "select.field-error", "textarea.field-error", ".field-error"];
        var target = null;
        for (var s = 0; s < selectors.length && !target; s++) {
          var candidates = form.querySelectorAll(selectors[s]);
          for (var i = 0; i < candidates.length; i++) {
            if (isScrollableErrorTarget(candidates[i])) {
              target = candidates[i];
              break;
            }
          }
        }
        if (!target) {
          return;
        }

        var focusTarget = /^(INPUT|SELECT|TEXTAREA)$/.test(target.tagName)
          ? target
          : (target.querySelector("input:not([type='hidden']), select, textarea") || target);

        window.requestAnimationFrame(function () {
          scrollElementIntoView(target);
          window.setTimeout(function () {
            scrollElementIntoView(target);
            if (focusTarget && typeof focusTarget.focus === "function" && !focusTarget.disabled) {
              try {
                focusTarget.focus({ preventScroll: false });
              } catch (e) {
                try { focusTarget.focus(); } catch (e2) {}
              }
            }
          }, 50);
        });
      }

      function validate() {
        var ok = true;
        var requiredIds = [
          "bookerName", "email", "phone", "preferredDate", "venueAddress",
          "invoiceName", "invoiceEmail", "invoiceAddress"
        ];
        requiredIds.forEach(function (id) {
          var el = document.getElementById(id);
          // Locked preferred date uses a hidden input — still validate it has a value.
          var missing = !requiredValue(id);
          if (el && el.type !== "hidden") {
            setError(el, missing);
          }
          if (missing) ok = false;
        });

        var venueReq = document.getElementById("venueRequirements");
        var terms = document.getElementById("termsAccepted");
        if (venueReq) {
          setError(venueReq, !venueReq.checked);
          if (!venueReq.checked) ok = false;
        }
        if (terms) {
          setError(terms, !terms.checked);
          if (!terms.checked) ok = false;
        }

        var names = (document.getElementById("studentNames").value || "").trim();
        var emails = (document.getElementById("studentEmails").value || "").trim();
        var fileInput = document.getElementById("studentNamesFile");
        var hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
        if (!names && !emails && !hasFile) {
          setError(document.getElementById("studentNames"), true);
          setError(document.getElementById("studentEmails"), true);
          ok = false;
        } else {
          setError(document.getElementById("studentNames"), false);
          setError(document.getElementById("studentEmails"), false);
        }

        if (names || emails) {
          var nameLines = names ? names.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean) : [];
          var emailLines = emails ? emails.split(/\r?\n/).map(function (l) { return l.trim(); }).filter(Boolean) : [];
          if (nameLines.length !== emailLines.length) {
            window.alert("Student names and email addresses must have the same number of lines, in matching order.");
            setError(document.getElementById("studentNames"), true);
            setError(document.getElementById("studentEmails"), true);
            ok = false;
          }
        }

        return ok;
      }

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!validate()) {
          scrollToFirstError();
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";

        fetch(form.action, {
          method: "POST",
          body: new FormData(form),
        })
          .then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
              if (!response.ok || !body.success) {
                throw new Error(body && body.message ? body.message : "Unable to submit booking details.");
              }
              return body;
            });
          })
          .then(function () {
            form.classList.add("hidden");
            thankYouView.classList.remove("hidden");
          })
          .catch(function (error) {
            window.alert(error && error.message ? error.message : "Could not submit booking details.");
          })
          .finally(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit booking details";
          });
      });
    })();
  </script>
  <?php endif; ?>
</body>
</html>
