<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Safer Handling Enquiry Form</title>
  <link rel="icon" type="image/png" href="assets/safer-handling-logo.png" />
  <style>
    :root {
      --brand-blue: #008afc;
      --brand-blue-dark: #0478d8;
      --brand-green: rgb(186 218 85);
      --brand-white: #ffffff;
      --text-dark: #16324a;
      --text-mid: #2e5d84;
      --border-soft: #d8e8f8;
      --surface-soft: #f7fbff;
    }

    * {
      box-sizing: border-box;
    }

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

    .container {
      max-width: 940px;
      margin: 42px auto;
      padding: 0 18px 44px;
    }

    .card {
      background: var(--brand-white);
      border: 1px solid #cfe4f8;
      border-radius: 18px;
      box-shadow:
        0 18px 45px rgba(0, 138, 252, 0.13),
        0 2px 10px rgba(11, 71, 117, 0.07);
      overflow: hidden;
    }

    .brand-header {
      background: #0255a4;
      padding: 24px;
      color: var(--brand-white);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 22px;
      position: relative;
    }

    .brand-header::after {
      content: "";
      position: absolute;
      inset: auto -40px -50px auto;
      width: 160px;
      height: 160px;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.28) 0%, rgba(255, 255, 255, 0) 70%);
      pointer-events: none;
    }

    .logo {
      width: 220px;
      max-width: 42%;
      display: block;
      background: transparent;
      border: 0;
      border-radius: 0;
      padding: 0;
      box-shadow: none;
    }

    .title-wrap h1 {
      margin: 0;
      font-size: 1.72rem;
      line-height: 1.1;
      letter-spacing: 0.2px;
    }

    .title-wrap p {
      margin: 8px 0 0;
      opacity: 0.98;
      font-size: 1rem;
    }

    form {
      padding: 24px;
      background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
    }

    .section {
      border: 1px solid var(--border-soft);
      border-radius: 14px;
      background: linear-gradient(180deg, #ffffff 0%, #fcfeff 100%);
      padding: 20px;
      margin-bottom: 22px;
      box-shadow: 0 4px 14px rgba(0, 138, 252, 0.06);
    }

    .section h2 {
      margin-top: 0;
      margin-bottom: 6px;
      font-size: 1.25rem;
      color: #0255a4;
    }

    .section-intro {
      margin: 0 0 14px;
      color: var(--text-mid);
      font-size: 0.95rem;
    }

    .sub-section {
      border: 1px solid #dcebf8;
      border-radius: 12px;
      padding: 16px;
      margin: 14px 0;
      background: #fff;
      min-width: 0;
    }

    .sub-section h3 {
      margin: 0 0 10px;
      font-size: 1.02rem;
      color: #20618f;
    }

    label {
      display: block;
      font-weight: 700;
      margin: 12px 0 7px;
      color: #20567e;
    }

    input[type="text"],
    input[type="email"],
    input[type="number"],
    textarea {
      width: 100%;
      padding: 11px 12px;
      border: 1px solid #b9d4ef;
      border-radius: 10px;
      font-size: 0.96rem;
      background: #fff;
      color: #133a59;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    input[type="datetime-local"],
    select {
      width: 100%;
      padding: 11px 40px 11px 12px;
      border: 1px solid #b9d4ef;
      border-radius: 10px;
      font-size: 0.96rem;
      background: #fff;
      color: #133a59;
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    input[type="text"]:focus,
    input[type="email"]:focus,
    input[type="number"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--brand-blue);
      background: #fafdff;
      box-shadow: 0 0 0 3px rgba(0, 138, 252, 0.18);
    }

    input[type="datetime-local"]:focus,
    select:focus {
      outline: none;
      border-color: var(--brand-blue);
      background: #fafdff;
      box-shadow: 0 0 0 3px rgba(0, 138, 252, 0.18);
    }

    .datetime-block {
      margin-top: 14px;
      max-width: 430px;
      padding: 12px 14px;
      border: 1px solid #d5e7f8;
      border-radius: 14px;
      background: linear-gradient(160deg, #fafdff 0%, #f2f9ff 100%);
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85);
    }

    .datetime-block label {
      margin-top: 0;
      font-size: 0.95rem;
      font-weight: 700;
      color: #1f577f;
      letter-spacing: 0.15px;
    }

    .datetime-input {
      max-width: 100%;
      font-weight: 700;
      letter-spacing: 0.2px;
      background: linear-gradient(180deg, #ffffff 0%, #f7fcff 100%);
      box-shadow: inset 0 1px 2px rgba(12, 84, 130, 0.08);
      border-radius: 11px;
    }

    .datetime-input::-webkit-calendar-picker-indicator {
      cursor: pointer;
      filter: saturate(1.2);
    }

    .datetime-hint {
      margin: 8px 0 0;
      font-size: 0.84rem;
      color: #3f6f95;
      font-weight: 600;
    }

    .datetime-choice {
      margin-top: 10px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 10px;
      border: 1px dashed #c5dbef;
      border-radius: 10px;
      background: rgba(255, 255, 255, 0.7);
      font-weight: 600;
      font-size: 0.9rem;
      color: #2f618a;
      cursor: pointer;
    }

    .datetime-choice input {
      margin: 0;
      inline-size: 16px;
      block-size: 16px;
      accent-color: var(--brand-blue);
      cursor: pointer;
    }

    .trainer-company-option {
      margin-top: 12px;
    }

    .matrix-attendees-input {
      flex: 1 1 0;
      min-width: 0;
      width: auto;
      max-width: 100%;
      text-align: center;
      font-weight: 700;
      color: #0f4a78;
      background: #ffffff;
      border: 1px solid #b7d3ee;
      border-radius: 10px;
      padding: 8px 6px;
      box-shadow: inset 0 1px 2px rgba(16, 75, 120, 0.08);
    }

    .attendees-row {
      display: flex;
      align-items: center;
      gap: 8px;
      width: 100%;
      max-width: 100%;
      min-width: 0;
      box-sizing: border-box;
      background: #f4faff;
      border: 1px solid #cfe2f4;
      border-radius: 12px;
      padding: 8px 10px;
    }

    .stepper-btn {
      flex-shrink: 0;
      width: 40px;
      height: 40px;
      padding: 0;
      border: 1px solid #b7d3ee;
      border-radius: 10px;
      background: #ffffff;
      font-size: 1.35rem;
      font-weight: 700;
      line-height: 1;
      color: #0f4a78;
      cursor: pointer;
      transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .stepper-btn:hover:not(:disabled) {
      background: #e8f4ff;
      border-color: var(--brand-blue);
      color: var(--brand-blue);
    }

    .stepper-btn:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .attendees-input {
      width: 100%;
      max-width: 100%;
      text-align: center;
      font-weight: 700;
      color: #0f4a78;
      background: #ffffff;
      border: 1px solid #b7d3ee;
      border-radius: 10px;
      padding: 8px 10px;
      box-shadow: inset 0 1px 2px rgba(16, 75, 120, 0.08);
    }

    .attendees-input:focus {
      outline: none;
      border-color: var(--brand-blue);
      box-shadow: 0 0 0 3px rgba(0, 138, 252, 0.2);
    }

    .attendees-suffix {
      font-size: 0.9rem;
      font-weight: 700;
      color: #2d5f86;
      letter-spacing: 0.1px;
    }

    .address-lookup-block {
      margin-top: 18px;
      padding-top: 14px;
      border-top: 1px solid #cfe2f4;
    }

    .address-lookup-block h4 {
      margin: 0 0 8px;
      font-size: 1rem;
      color: #0f4a78;
    }

    .postcode-lookup-row {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
    }

    .postcode-lookup-row input[type="text"] {
      flex: 1 1 140px;
      min-width: 120px;
      max-width: 100%;
      padding: 8px 10px;
      border: 1px solid #b7d3ee;
      border-radius: 10px;
      box-sizing: border-box;
    }

    .postcode-lookup-row button {
      flex-shrink: 0;
      padding: 8px 14px;
      border-radius: 10px;
      border: 1px solid var(--brand-blue);
      background: var(--brand-blue);
      color: #fff;
      font-weight: 600;
      cursor: pointer;
    }

    .postcode-lookup-row button:disabled {
      opacity: 0.55;
      cursor: not-allowed;
    }

    .address-fields-grid {
      display: grid;
      gap: 10px;
      margin-top: 10px;
    }

    .address-fields-grid label {
      display: block;
      font-weight: 600;
      margin-bottom: 4px;
      color: #1a4a6e;
    }

    .address-fields-grid input,
    .address-fields-grid select {
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
      padding: 8px 10px;
      border: 1px solid #b7d3ee;
      border-radius: 10px;
    }

    textarea {
      min-height: 90px;
      resize: vertical;
    }

    .choice-group {
      display: grid;
      gap: 6px;
      margin-top: 7px;
    }

    .field-error {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
      background: #fff7f7 !important;
    }

    .choice-group-error {
      border: 1px solid #f4a3a3;
      border-radius: 10px;
      padding: 7px;
      background: #fff8f8;
    }

    .choice {
      display: flex;
      align-items: flex-start;
      gap: 11px;
      padding: 11px 12px;
      margin-bottom: 0;
      border: 1px solid #d6e7f8;
      border-radius: 10px;
      background: var(--surface-soft);
      font-weight: 500;
      transition: transform 0.15s ease, border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
    }

    .choice:hover {
      border-color: #9fc8ed;
      background: #ffffff;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(7, 79, 138, 0.08);
    }

    .choice input {
      margin-top: 2px;
      accent-color: var(--brand-blue);
      transform: scale(1.08);
    }

    .note {
      margin-top: 12px;
      font-size: 0.9rem;
      color: #29597d;
      background: #edf8ff;
      border: 1px dashed #acd0ef;
      border-radius: 10px;
      padding: 11px;
    }

    .date-guidance {
      margin-top: 10px;
      background: rgba(186, 218, 85, 0.22);
      border: 1px solid var(--brand-green);
      border-radius: 10px;
      padding: 11px;
      font-size: 0.93rem;
    }

    .pricing-card {
      margin-top: 12px;
      border: 1px solid #d7e9f8;
      border-radius: 10px;
      background: #f8fcff;
      padding: 12px;
    }

    .pricing-card h4 {
      margin: 0 0 10px;
      color: #195783;
      font-size: 0.98rem;
    }

    .pricing-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 6px;
      font-size: 0.92rem;
      color: #204f73;
    }

    .thank-you-view {
      padding: 34px 24px 38px;
      text-align: center;
    }

    .thank-you-logo {
      width: 260px;
      max-width: 82%;
      background: #fff;
      border: 1px solid #d7e8f7;
      border-radius: 12px;
      padding: 8px;
      box-shadow: 0 8px 24px rgba(0, 138, 252, 0.12);
    }

    .thank-you-view h2 {
      color: #0255a4;
      margin: 18px 0 8px;
      font-size: 1.45rem;
    }

    .thank-you-view p {
      margin: 0 auto;
      max-width: 560px;
      color: #2a5e84;
    }

    .actions {
      display: flex;
      gap: 10px;
      margin-top: 14px;
      flex-wrap: wrap;
    }

    button {
      border: 0;
      border-radius: 10px;
      padding: 11px 17px;
      font-weight: 700;
      font-size: 0.95rem;
      letter-spacing: 0.2px;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    button:hover {
      transform: translateY(-1px);
    }

    button:active {
      transform: translateY(0);
    }

    .btn-primary {
      background: #0255a4;
      color: var(--brand-white);
      box-shadow: 0 8px 18px rgba(2, 85, 164, 0.32);
    }

    .btn-primary:hover {
      background: #02478f;
    }

    .btn-secondary {
      background: linear-gradient(120deg, rgba(186, 218, 85, 1), rgba(173, 209, 70, 1));
      color: #1f3510;
      box-shadow: 0 7px 16px rgba(112, 140, 25, 0.22);
    }

    .hidden {
      display: none;
    }

    @media (max-width: 680px) {
      .brand-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .logo {
        max-width: 75%;
      }

      form {
        padding: 18px;
      }

      .section {
        padding: 16px;
      }

      .stepper-btn {
        width: 36px;
        height: 36px;
        font-size: 1.2rem;
      }

      .attendees-row {
        gap: 6px;
        padding: 8px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="brand-header">
        <img
          class="logo"
          src="assets/safer-handling-logo.png"
          alt="Safer Handling logo"
        />
        <div class="title-wrap">
          <h1>How can we help you?</h1>
          <p>Tell us what you need and we will guide you.</p>
        </div>
      </div>

      <form id="enquiryForm" action="submit_enquiry.php" method="post" novalidate>
        <input type="hidden" id="enquiryId" name="enquiryId" value="" />
        <section class="section" id="section1">
          <h2>Start by filling in the form</h2>
          <p class="section-intro">Use the steps below to choose the course that fits you and build your personalised quote.</p>

          <label for="name">Name</label>
          <input id="name" name="name" type="text" required />

          <label for="email">Email</label>
          <input id="email" name="email" type="email" required />

          <label>I am looking to make an enquiry about</label>
          <div class="choice-group" id="enquiryTypeGroup">
            <label class="choice">
              <input type="radio" name="enquiryType" value="training" required />
              <span>TRAINING</span>
            </label>
            <label class="choice">
              <input type="radio" name="enquiryType" value="equipment" />
              <span>EQUIPMENT AND SALES</span>
            </label>
            <label class="choice">
              <input type="radio" name="enquiryType" value="guidance" />
              <span>GUIDANCE AND CONSULTANCY</span>
            </label>
          </div>

          <div class="actions">
            <button type="button" id="continueBtn" class="btn-primary">Continue</button>
          </div>
        </section>

        <section class="section hidden" id="trainingSection">
          <h2>Training Enquiry</h2>
          <p class="section-intro">Complete this part if your enquiry is about training.</p>

          <label>Is this for you or your organisation?</label>
          <div class="choice-group" id="audienceTypeGroup">
            <label class="choice">
              <input type="radio" name="audienceType" value="me" />
              <span>IT'S FOR ME</span>
            </label>
            <label class="choice">
              <input type="radio" name="audienceType" value="organisation" />
              <span>MY ORGANISATION</span>
            </label>
          </div>

          <div id="forMeBlock" class="sub-section hidden">
            <h3>It's for me</h3>
            <div class="choice-group" id="personalGoalGroup">
              <label class="choice">
                <input type="radio" name="personalGoal" value="onlineCourse" />
                <span>I want to complete an online course</span>
              </label>
              <label class="choice">
                <input type="radio" name="personalGoal" value="becomeTrainer" />
                <span>I want to become a trainer</span>
              </label>
            </div>
            <p id="personalGoalHint" class="note">Choose an option to continue.</p>
          </div>

          <div id="trainerCoursesBlock" class="sub-section hidden">
            <h3>Trainer Courses</h3>
            <p class="section-intro">Select a course slot below.</p>
            <label for="trainerCourseSelect">Courses</label>
            <select id="trainerCourseSelect" name="trainerCourseSelect">
              <option value="">Select a course</option>
              <option value="PBS & Safer Physical Intervention TTT">Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award</option>
              <option value="Soft Restraint Kit Train the Trainer Award">Soft Restraint Kit Train the Trainer Award</option>
            </select>
            <div id="trainerCompanyBookingWrap" class="trainer-company-option hidden">
              <label class="datetime-choice" for="bookingViaCompany">
                <input type="checkbox" id="bookingViaCompany" name="bookingViaCompany" />
                <span>I'm booking through a company</span>
              </label>
            </div>
            <label for="trainerAttendees">Attendees</label>
            <input
              id="trainerAttendees"
              name="trainerAttendees"
              class="attendees-input"
              type="number"
              min="1"
              max="120"
              step="1"
              value=""
              inputmode="numeric"
              placeholder="Enter attendees"
            />
            <p id="trainerAttendeesHint" class="note">Enter attendee count to see a live quote.</p>

            <div class="address-lookup-block" id="trainerAddressBlock">
              <h4>Your address</h4>
              <div class="postcode-lookup-row">
                <input
                  type="text"
                  id="tmAddressPostcodeLookup"
                  autocomplete="postal-code"
                  placeholder="e.g. SW1A 1AA"
                  maxlength="16"
                />
                <button type="button" id="tmAddressLookupBtn">Find addresses</button>
              </div>
              <p id="tmAddressLookupStatus" class="note hidden" role="status" aria-live="polite"></p>
              <label for="tmAddressPick" id="tmAddressPickLabel" class="hidden">Choose address</label>
              <select id="tmAddressPick" class="hidden" aria-hidden="true">
                <option value="">—</option>
              </select>
              <div class="address-fields-grid">
                <div>
                  <label for="tmAddressLine1">Address line 1</label>
                  <input type="text" id="tmAddressLine1" name="tmAddressLine1" maxlength="200" autocomplete="address-line1" />
                </div>
                <div>
                  <label for="tmAddressLine2">Address line 2</label>
                  <input type="text" id="tmAddressLine2" name="tmAddressLine2" maxlength="200" autocomplete="address-line2" />
                </div>
                <div>
                  <label for="tmAddressTown">Town / city</label>
                  <input type="text" id="tmAddressTown" name="tmAddressTown" maxlength="120" autocomplete="address-level2" />
                </div>
                <div>
                  <label for="tmAddressPostcode">Postcode</label>
                  <input type="text" id="tmAddressPostcode" name="tmAddressPostcode" maxlength="16" autocomplete="postal-code" />
                </div>
              </div>
              <input type="hidden" id="tmAddressLat" name="tmAddressLat" value="" />
              <input type="hidden" id="tmAddressLng" name="tmAddressLng" value="" />
            </div>
          </div>

          <div id="organisationBlock" class="sub-section hidden">
            <h3>My organisation</h3>

            <p class="section-intro">Selections below only show valid combinations from your approved training matrix.</p>

            <label for="sector">Sector</label>
            <select id="sector" name="sector">
              <option value="">Select sector</option>
            </select>

            <label for="orgCourse">Course</label>
            <select id="orgCourse" name="orgCourse" disabled>
              <option value="">Select sector first</option>
            </select>
            <input type="hidden" id="mondaySpecificCourse" name="mondaySpecificCourse" value="" />

            <label for="courseFormat">Format</label>
            <select id="courseFormat" name="courseFormat" disabled>
              <option value="">Select course first</option>
            </select>

            <label for="formatSubOption">Course Style</label>
            <select id="formatSubOption" name="formatSubOption" disabled>
              <option value="">Select format first</option>
            </select>

            <label for="matrixAttendees">Attendees</label>
            <div class="attendees-row" id="matrixAttendeesStepper">
              <button type="button" class="stepper-btn" id="matrixAttendeesMinus" aria-label="Decrease attendees" disabled>
                −
              </button>
              <input
                id="matrixAttendees"
                name="matrixAttendees"
                class="matrix-attendees-input"
                type="text"
                value=""
                inputmode="numeric"
                pattern="[0-9]*"
                autocomplete="off"
                disabled
              />
              <button type="button" class="stepper-btn" id="matrixAttendeesPlus" aria-label="Increase attendees" disabled>
                +
              </button>
            </div>

            <label for="organisationCompany">Company/Organisation</label>
            <input
              type="text"
              id="organisationCompany"
              name="organisationCompany"
              maxlength="200"
              autocomplete="organization"
            />

            <div class="address-lookup-block" id="organisationAddressBlock">
              <h4>Your address</h4>
              <div class="postcode-lookup-row">
                <input
                  type="text"
                  id="addressPostcodeLookup"
                  autocomplete="postal-code"
                  placeholder="e.g. SW1A 1AA"
                  maxlength="16"
                />
                <button type="button" id="addressLookupBtn">Find addresses</button>
              </div>
              <p id="addressLookupStatus" class="note hidden" role="status" aria-live="polite"></p>
              <label for="addressPick" id="addressPickLabel" class="hidden">Choose address</label>
              <select id="addressPick" class="hidden" aria-hidden="true">
                <option value="">—</option>
              </select>
              <div class="address-fields-grid">
                <div>
                  <label for="addressLine1">Address line 1</label>
                  <input type="text" id="addressLine1" name="addressLine1" maxlength="200" autocomplete="address-line1" />
                </div>
                <div>
                  <label for="addressLine2">Address line 2</label>
                  <input type="text" id="addressLine2" name="addressLine2" maxlength="200" autocomplete="address-line2" />
                </div>
                <div>
                  <label for="addressTown">Town / city</label>
                  <input type="text" id="addressTown" name="addressTown" maxlength="120" autocomplete="address-level2" />
                </div>
                <div>
                  <label for="addressPostcode">Postcode</label>
                  <input type="text" id="addressPostcode" name="addressPostcode" maxlength="16" autocomplete="postal-code" />
                </div>
              </div>
              <input type="hidden" id="addressLat" name="addressLat" value="" />
              <input type="hidden" id="addressLng" name="addressLng" value="" />
            </div>

            <div class="datetime-block">
              <label for="preferredDateTime">Preferred day(s) or date and start time</label>
              <input
                class="datetime-input"
                id="preferredDateTime"
                name="preferredDateTime"
                type="datetime-local"
              />
              <label class="datetime-choice" for="dateNotSure">
                <input type="checkbox" id="dateNotSure" name="dateNotSure" />
                <span>Not sure on date yet</span>
              </label>
              <p class="datetime-hint">Pick a preferred date and start time for your session.</p>
            </div>

            <div id="dateGuidance" class="date-guidance">
              Select sector, course, format, course style, attendees, and a preferred date and start time to confirm a quote.
            </div>

          </div>

          <div id="finalDetailsBlock" class="hidden">
            <input type="hidden" id="attendees" name="attendees" value="12" />
            <input type="hidden" id="trainersRequiredValue" name="trainersRequired" value="" />
            <input type="hidden" id="quoteValue" name="quoteValue" value="" />

            <div id="pricingCard" class="pricing-card hidden">
              <h4>Live Quote</h4>
              <div class="pricing-grid">
                <div id="trainersRequiredRow"><strong>Number of trainers required:</strong> <span id="trainersRequired">1</span></div>
                <div id="totalWithVatRow"><strong>Total price estimate + VAT inc Travel:</strong> <span id="totalWithVatPrice">-</span></div>
              </div>
            </div>

            <div id="extraNotesBlock">
              <label for="extraNotes">Any additional notes</label>
              <textarea id="extraNotes" name="extraNotes" placeholder="Add any extra details here..."></textarea>
            </div>

            <div class="actions">
              <button id="submitBtn" type="submit" class="btn-primary">Submit Enquiry and Get Quote</button>
            </div>
          </div>
        </section>
      </form>

      <section id="thankYouView" class="thank-you-view hidden">
        <img
          class="thank-you-logo"
          src="assets/safer-handling-logo.png"
          alt="Safer Handling logo"
        />
        <h2>Thank You</h2>
        <p>Your enquiry has been submitted. A member of the team will be in touch shortly.</p>
      </section>
    </div>
  </div>

  <script>
    (function () {
      var SHOP_URL = "https://www.safer-handling.co.uk/shop";
      var RESOURCES_URL = "https://www.safer-handling.co.uk/resources";
      var KAJABI_COURSES_URL = "https://safer-handling.mykajabi.com/store";
      var MONDAY_CONTINUE_ENDPOINT = new URL("monday_continue.php", window.location.href).toString();
      var RESUME_ENQUIRY_ENDPOINT = new URL("resume_enquiry.php", window.location.href).toString();
      var SAVE_ENQUIRY_PROGRESS_ENDPOINT = new URL("save_enquiry_progress.php", window.location.href).toString();
      var MONDAY_ONLINE_COURSE_ENDPOINT = new URL("monday_online_course.php", window.location.href).toString();
      var MONDAY_UPDATE_TRAINER_FIELDS_ENDPOINT = new URL("monday_update_trainer_fields.php", window.location.href).toString();
      var MONDAY_BOOKING_VIA_COMPANY_ENDPOINT = new URL("monday_booking_via_company.php", window.location.href).toString();
      var POSTCODE_LOOKUP_ENDPOINT = new URL("postcode_lookup.php", window.location.href).toString();
      var TRAINING_MATRIX_ENDPOINT = new URL("training_matrix.php", window.location.href).toString();

      var form = document.getElementById("enquiryForm");
      var thankYouView = document.getElementById("thankYouView");
      var nameInput = document.getElementById("name");
      var emailInput = document.getElementById("email");
      var enquiryTypeGroup = document.getElementById("enquiryTypeGroup");
      var continueBtn = document.getElementById("continueBtn");
      var trainingSection = document.getElementById("trainingSection");
      var finalDetailsBlock = document.getElementById("finalDetailsBlock");
      var audienceTypeGroup = document.getElementById("audienceTypeGroup");
      var personalGoalGroup = document.getElementById("personalGoalGroup");
      var personalGoalHint = document.getElementById("personalGoalHint");
      var forMeBlock = document.getElementById("forMeBlock");
      var trainerCoursesBlock = document.getElementById("trainerCoursesBlock");
      var trainerCourseSelect = document.getElementById("trainerCourseSelect");
      var trainerCompanyBookingWrap = document.getElementById("trainerCompanyBookingWrap");
      var bookingViaCompanyInput = document.getElementById("bookingViaCompany");
      var trainerAttendeesInput = document.getElementById("trainerAttendees");
      var organisationBlock = document.getElementById("organisationBlock");
      var dateGuidance = document.getElementById("dateGuidance");
      var attendeesInput = document.getElementById("attendees");
      var extraNotesBlock = document.getElementById("extraNotesBlock");
      var extraNotesInput = document.getElementById("extraNotes");
      var submitBtn = document.getElementById("submitBtn");
      var preferredDateTimeInput = document.getElementById("preferredDateTime");
      var dateNotSureInput = document.getElementById("dateNotSure");
      var sectorSelect = document.getElementById("sector");
      var orgCourseSelect = document.getElementById("orgCourse");
      var courseFormatSelect = document.getElementById("courseFormat");
      var formatSubOptionSelect = document.getElementById("formatSubOption");
      var matrixAttendeesInput = document.getElementById("matrixAttendees");
      var matrixAttendeesMinus = document.getElementById("matrixAttendeesMinus");
      var matrixAttendeesPlus = document.getElementById("matrixAttendeesPlus");
      var organisationCompanyInput = document.getElementById("organisationCompany");
      var trainersRequiredValueInput = document.getElementById("trainersRequiredValue");
      var quoteValueInput = document.getElementById("quoteValue");
      var pricingCard = document.getElementById("pricingCard");
      var totalWithVatRow = document.getElementById("totalWithVatRow");
      var trainersRequiredRow = document.getElementById("trainersRequiredRow");
      var totalWithVatPrice = document.getElementById("totalWithVatPrice");
      var trainersRequired = document.getElementById("trainersRequired");

      var MATRIX_DEFAULT_ATTENDEES = 12;
      var MATRIX_MAX_ATTENDEES = 120;
      var TRAINER_DEFAULT_ATTENDEES = 1;

      function matrixSpinnerDefaultCount(minA, maxA, preferredDefault) {
        var baseDefault = Number.isFinite(preferredDefault) && preferredDefault > 0
          ? preferredDefault
          : MATRIX_DEFAULT_ATTENDEES;
        return Math.min(maxA, Math.max(minA, baseDefault));
      }

      function getPreferredDefaultAttendees(pkg) {
        if (!pkg) {
          return MATRIX_DEFAULT_ATTENDEES;
        }
        var preferred = Number(pkg.defaultAttendees);
        if (Number.isFinite(preferred) && preferred > 0) {
          return preferred;
        }
        return MATRIX_DEFAULT_ATTENDEES;
      }

      var TRAINING_MATRIX = [];
      var trainingMatrixLoaded = false;
      var currentEnquiryId = "";

      function setEnquiryId(id) {
        currentEnquiryId = id ? String(id) : "";
        var enquiryIdInput = document.getElementById("enquiryId");
        if (enquiryIdInput) {
          enquiryIdInput.value = currentEnquiryId;
        }
      }

      function appendEnquiryIdToFormData(fd) {
        if (!fd || !currentEnquiryId) {
          return;
        }
        fd.append("enquiryId", currentEnquiryId);
      }

      function applySavedFormValue(name, value) {
        var fields = form.querySelectorAll('[name="' + name + '"]');
        if (!fields.length) {
          return;
        }

        var first = fields[0];
        if (first.type === "radio") {
          fields.forEach(function (field) {
            field.checked = String(field.value) === String(value);
          });
          return;
        }

        if (first.type === "checkbox") {
          first.checked = value === true || value === "1" || value === 1 || value === "on";
          return;
        }

        first.value = value == null ? "" : String(value);
      }

      function applySavedFormData(data) {
        if (!data || typeof data !== "object") {
          return;
        }

        Object.keys(data).forEach(function (name) {
          if (name === "enquiryId") {
            return;
          }
          applySavedFormValue(name, data[name]);
        });
      }

      function restoreOrganisationSelections(data) {
        if (!trainingMatrixLoaded || !data) {
          return;
        }

        if (data.sector) {
          sectorSelect.value = data.sector;
          updateOrganisationMatrixFields("sector");
        }
        if (data.orgCourse) {
          orgCourseSelect.value = data.orgCourse;
          updateOrganisationMatrixFields("course");
        }
        if (data.courseFormat) {
          courseFormatSelect.value = data.courseFormat;
          updateOrganisationMatrixFields("format");
        }
        if (data.formatSubOption) {
          formatSubOptionSelect.value = data.formatSubOption;
          updateOrganisationMatrixFields("subOption");
        }
        if (data.matrixAttendees) {
          matrixAttendeesInput.value = String(data.matrixAttendees);
        }
      }

      function revealSavedEnquiryProgress(enquiryType) {
        continueBtn.style.display = "none";

        if (enquiryType === "equipment" || enquiryType === "guidance") {
          return;
        }

        if (enquiryType === "training") {
          trainingSection.classList.remove("hidden");
          updateAudienceBlocks();
          updateFinalDetailsVisibility();
          trainingSection.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      }

      function showResumeNotice(message) {
        var section1 = document.getElementById("section1");
        if (!section1) {
          return;
        }

        var notice = document.getElementById("resumeNotice");
        if (!notice) {
          notice = document.createElement("p");
          notice.id = "resumeNotice";
          notice.className = "note";
          notice.style.marginTop = "12px";
          var actions = section1.querySelector(".actions");
          if (actions) {
            section1.insertBefore(notice, actions);
          } else {
            section1.appendChild(notice);
          }
        }

        notice.textContent = message;
      }

      var enquiryProgressSaveTimer = null;

      function scheduleEnquiryProgressSave() {
        if (window.location.protocol === "file:" || !currentEnquiryId) {
          return;
        }

        clearTimeout(enquiryProgressSaveTimer);
        enquiryProgressSaveTimer = setTimeout(function () {
          fetch(SAVE_ENQUIRY_PROGRESS_ENDPOINT, {
            method: "POST",
            body: new FormData(form),
          }).catch(function (error) {
            console.error(error);
          });
        }, 800);
      }

      function tryRestoreEnquiryFromUrl() {
        if (window.location.protocol === "file:") {
          return Promise.resolve(false);
        }

        var params = new URLSearchParams(window.location.search);
        var enquiryId = params.get("enquiry") || params.get("enquiryId");
        var token = params.get("token");
        if (!enquiryId || !token) {
          return Promise.resolve(false);
        }

        var url = RESUME_ENQUIRY_ENDPOINT + "?" + new URLSearchParams({
          enquiry: enquiryId,
          token: token,
        }).toString();

        return fetch(url)
          .then(function (response) {
            return response
              .json()
              .catch(function () {
                return {};
              })
              .then(function (body) {
                if (!response.ok || !body.success || !body.enquiry) {
                  throw new Error(body && body.message ? body.message : "Unable to restore your saved enquiry.");
                }
                return body.enquiry;
              });
          })
          .then(function (enquiry) {
            setEnquiryId(enquiry.id);
            nameInput.value = enquiry.name || "";
            emailInput.value = enquiry.email || "";
            applySavedFormData(enquiry.formData || {});
            restoreOrganisationSelections(enquiry.formData || {});
            revealSavedEnquiryProgress(enquiry.enquiryType || getCheckedValue("enquiryType"));
            // Re-apply after cascading selects rebuild their options / audience blocks.
            applySavedFormData(enquiry.formData || {});
            restoreOrganisationSelections(enquiry.formData || {});
            updateFinalDetailsVisibility();
            if (enquiry.status === "quote_sent") {
              showResumeNotice("We restored this enquiry (quote already sent). You can review or update the details.");
            } else if (enquiry.status === "submitted") {
              showResumeNotice("We restored your submitted enquiry. You can review or update your details.");
            } else {
              showResumeNotice("We restored your saved enquiry. You can continue where you left off.");
            }
            return true;
          })
          .catch(function (error) {
            console.error(error);
            showResumeNotice(error && error.message ? error.message : "We could not restore your saved enquiry.");
            return false;
          });
      }

      var TRAINER_COURSE_MATRIX = [
        {
          course: "Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award",
          courseValue: "PBS & Safer Physical Intervention TTT",
          minAttendees: 1,
          maxCap: null,
          defaultAttendees: 1,
          pricing: { kind: "perDelegate", rate: 1662.5, perAttendee: true }
        },
        {
          course: "Soft Restraint Kit Train the Trainer Award",
          courseValue: "Soft Restraint Kit Train the Trainer Award",
          minAttendees: 1,
          maxCap: 120,
          defaultAttendees: 1,
          pricing: { kind: "perDelegate", rate: 438, perAttendee: true }
        }
      ];

      function getCheckedValue(name) {
        var checked = form.querySelector('input[name="' + name + '"]:checked');
        return checked ? checked.value : "";
      }

      function setInputError(inputElement, hasError) {
        inputElement.classList.toggle("field-error", hasError);
      }

      function setGroupError(groupElement, hasError) {
        groupElement.classList.toggle("choice-group-error", hasError);
      }

      function isOnlineCourseMode() {
        return getCheckedValue("audienceType") === "me" && getCheckedValue("personalGoal") === "onlineCourse";
      }

      function isTrainerMode() {
        return getCheckedValue("audienceType") === "me" && getCheckedValue("personalGoal") === "becomeTrainer";
      }

      var trainerMondayFieldSyncTimer = null;

      function scheduleTrainerMondayFieldSync() {
        if (window.location.protocol === "file:") {
          return;
        }
        if (!isTrainerMode()) {
          return;
        }
        var em = emailInput.value.trim();
        if (!em || !emailInput.checkValidity()) {
          return;
        }
        clearTimeout(trainerMondayFieldSyncTimer);
        trainerMondayFieldSyncTimer = setTimeout(function () {
          var course = trainerCourseSelect.value;
          var att = trainerAttendeesInput.value;
          if (!course && !att) {
            return;
          }
          var fd = new FormData();
          fd.append("email", em);
          if (course) {
            fd.append("trainerCourseSelect", course);
          }
          if (att) {
            fd.append("trainerAttendees", att);
          }
          var tr = trainersRequiredValueInput.value.trim();
          var qv = quoteValueInput.value.trim();
          if (tr) {
            fd.append("trainersRequired", tr);
          }
          if (qv) {
            fd.append("quoteValue", qv);
          }
          appendEnquiryIdToFormData(fd);
          fetch(MONDAY_UPDATE_TRAINER_FIELDS_ENDPOINT, { method: "POST", body: fd }).catch(function () {});
        }, 550);
      }

      function syncMondayBookingViaCompanyFlag() {
        if (window.location.protocol === "file:") {
          return;
        }
        if (!isTrainerMode() || !bookingViaCompanyInput) {
          return;
        }
        var em = emailInput.value.trim();
        if (!em || !emailInput.checkValidity()) {
          return;
        }
        var fd = new FormData();
        fd.append("email", em);
        fd.append("checked", bookingViaCompanyInput.checked ? "1" : "0");
        appendEnquiryIdToFormData(fd);
        fetch(MONDAY_BOOKING_VIA_COMPANY_ENDPOINT, { method: "POST", body: fd }).catch(function (err) {
          console.error(err);
        });
      }

      function syncTrainerCompanyBookingOption() {
        if (!trainerCompanyBookingWrap || !bookingViaCompanyInput) {
          return;
        }
        var show = isTrainerMode() && !!trainerCourseSelect.value;
        trainerCompanyBookingWrap.classList.toggle("hidden", !show);
        if (!show) {
          bookingViaCompanyInput.checked = false;
          syncMondayBookingViaCompanyFlag();
        }
      }

      function isOrganisationStyleTrainingPath() {
        return getCheckedValue("audienceType") === "organisation";
      }

      function showThankYouPage() {
        form.classList.add("hidden");
        thankYouView.classList.remove("hidden");
        thankYouView.scrollIntoView({ behavior: "smooth", block: "start" });
      }

      function orgCourseMatchesItem(item, selectedCourse) {
        return item.course === selectedCourse || item.courseValue === selectedCourse;
      }

      function getSelectedPackage() {
        var selectedCourse = orgCourseSelect.value;
        return (
          TRAINING_MATRIX.find(function (item) {
            return (
              item.sector === sectorSelect.value &&
              orgCourseMatchesItem(item, selectedCourse) &&
              item.format === courseFormatSelect.value &&
              item.subOption === formatSubOptionSelect.value
            );
          }) || null
        );
      }

      function getMatrixGroupRows() {
        var pkg = getSelectedPackage();
        return pkg ? [pkg] : [];
      }

      function formatQuoteTotal(pkg, attendeeCount) {
        if (!pkg || !Number.isFinite(attendeeCount) || attendeeCount < 1) {
          return "";
        }
        var p = pkg.pricing;
        var n = attendeeCount;
        if (p.kind === "flat") {
          return formatGbp(p.amount) + " + VAT inc. travel";
        }
        if (p.kind === "flatUnlimited") {
          return formatGbp(p.amount) + " + VAT inc. travel (unlimited attendees)";
        }
        if (p.kind === "perDelegate") {
          var vatPhrase = p.perAttendee ? "+ VAT per attendee" : "+ VAT inc. travel";
          return formatGbp(p.rate * n) + " " + vatPhrase + " (" + n + " × " + formatGbp(p.rate) + ")";
        }
        if (p.kind === "addonBands") {
          if (n <= 12) {
            return formatGbp(p.baseTo12) + " + VAT inc. travel (up to 12)";
          }
          if (n <= 19) {
            var t20 = p.baseTo12 + (n - 12) * p.per13to20;
            return formatGbp(t20) + " + VAT inc. travel (" + formatGbp(p.baseTo12) + " + " + (n - 12) + " × " + formatGbp(p.per13to20) + ")";
          }
          if (n === 20) {
            return formatGbp(p.fixed21Plus) + " + VAT inc. travel (20 attendee offer)";
          }
          var tAfter20 = p.fixed21Plus + (n - 20) * p.per13to20;
          return formatGbp(tAfter20) + " + VAT inc. travel (" + formatGbp(p.fixed21Plus) + " + " + (n - 20) + " × " + formatGbp(p.per13to20) + ", after 20)";
        }
        if (p.kind === "addonBandsLinear") {
          if (n <= 12) {
            return formatGbp(p.baseTo12) + " + VAT inc. travel (up to 12)";
          }
          var tLin = p.baseTo12 + (n - 12) * p.perAfter12;
          return formatGbp(tLin) + " + VAT inc. travel (" + formatGbp(p.baseTo12) + " + " + (n - 12) + " × " + formatGbp(p.perAfter12) + ")";
        }
        if (p.kind === "addonBandsPer4621") {
          if (n <= 12) {
            return formatGbp(p.baseTo12) + " + VAT inc. travel (up to 12)";
          }
          if (n <= 19) {
            var tMid = p.baseTo12 + (n - 12) * p.per13to20;
            return formatGbp(tMid) + " + VAT inc. travel (" + formatGbp(p.baseTo12) + " + " + (n - 12) + " × " + formatGbp(p.per13to20) + ", 13–19)";
          }
          return formatGbp(p.per21Plus * n) + " + VAT inc. travel (" + n + " × " + formatGbp(p.per21Plus) + ", 20+)";
        }
        return "";
      }

      function getTrainerPackage() {
        var selected = trainerCourseSelect.value;
        return (
          TRAINER_COURSE_MATRIX.find(function (item) {
            return item.courseValue === selected || item.course === selected;
          }) || null
        );
      }

      function getCurrentAttendeeCount() {
        if (isOrganisationStyleTrainingPath()) {
          var matrixValue = Number(matrixAttendeesInput.value);
          if (Number.isFinite(matrixValue) && matrixValue > 0) {
            return matrixValue;
          }
        }
        if (isTrainerMode()) {
          var trainerValue = Number(trainerAttendeesInput.value);
          if (Number.isFinite(trainerValue) && trainerValue > 0) {
            return trainerValue;
          }
        }
        return Number(attendeesInput.value) || 0;
      }

      function getOrganisationAttendeeCount() {
        var matrixValue = Number(matrixAttendeesInput.value);
        if (Number.isFinite(matrixValue) && matrixValue > 0) {
          return matrixValue;
        }
        return Number(attendeesInput.value) || 0;
      }

      function getSelectedTrainingRow() {
        if (isOrganisationStyleTrainingPath()) {
          return getSelectedPackage();
        }
        if (isTrainerMode()) {
          return getTrainerPackage();
        }
        return null;
      }

      function syncMatrixStepperButtons() {
        var dis = matrixAttendeesInput.disabled;
        matrixAttendeesMinus.disabled = dis;
        matrixAttendeesPlus.disabled = dis;
      }

      function clampMatrixAttendeesField() {
        if (matrixAttendeesInput.disabled || !matrixAttendeesInput.value) {
          return;
        }
        var minValue = Number(matrixAttendeesInput.min) || 1;
        var maxValue = Number(matrixAttendeesInput.max) || MATRIX_MAX_ATTENDEES;
        var cur = Number(matrixAttendeesInput.value);
        if (!Number.isFinite(cur)) {
          return;
        }
        var bounded = Math.min(maxValue, Math.max(minValue, cur));
        if (bounded !== cur) {
          matrixAttendeesInput.value = String(bounded);
        }
      }

      function commitMatrixAttendeesChange() {
        if (matrixAttendeesInput.disabled) {
          return;
        }
        var selectedPkg = getSelectedPackage();
        var digitsOnly = matrixAttendeesInput.value.replace(/\D/g, "");
        var minValue = Number(matrixAttendeesInput.min) || 1;
        var maxValue = Number(matrixAttendeesInput.max) || MATRIX_MAX_ATTENDEES;
        var nextValue = digitsOnly
          ? Number(digitsOnly)
          : matrixSpinnerDefaultCount(minValue, maxValue, getPreferredDefaultAttendees(selectedPkg));
        if (!Number.isFinite(nextValue)) {
          nextValue = matrixSpinnerDefaultCount(minValue, maxValue, getPreferredDefaultAttendees(selectedPkg));
        }
        var boundedValue = Math.min(maxValue, Math.max(minValue, nextValue));
        matrixAttendeesInput.value = String(boundedValue);
        setInputError(matrixAttendeesInput, !matrixAttendeesInput.value);
        syncFinalAttendeesFromMatrix();
        renderPricing(getSelectedTrainingRow());
        updateFinalDetailsVisibility();
      }

      function stepMatrixAttendees(delta) {
        if (matrixAttendeesInput.disabled) {
          return;
        }
        var selectedPkg = getSelectedPackage();
        clampMatrixAttendeesField();
        var minValue = Number(matrixAttendeesInput.min) || 1;
        var maxValue = Number(matrixAttendeesInput.max) || MATRIX_MAX_ATTENDEES;
        var cur = Number(matrixAttendeesInput.value);
        if (!Number.isFinite(cur)) {
          cur = matrixSpinnerDefaultCount(minValue, maxValue, getPreferredDefaultAttendees(selectedPkg));
        }
        var next = Math.min(maxValue, Math.max(minValue, cur + delta));
        matrixAttendeesInput.value = String(next);
        setInputError(matrixAttendeesInput, !matrixAttendeesInput.value);
        syncFinalAttendeesFromMatrix();
        renderPricing(getSelectedTrainingRow());
        updateFinalDetailsVisibility();
      }

      function applyMatrixSpinnerConstraints(rows) {
        if (!rows.length) {
          matrixAttendeesInput.disabled = true;
          matrixAttendeesInput.value = "";
          matrixAttendeesInput.min = "1";
          matrixAttendeesInput.max = String(MATRIX_MAX_ATTENDEES);
          syncMatrixStepperButtons();
          return;
        }

        var pkg = rows[0];
        matrixAttendeesInput.disabled = false;
        var minA = Number(pkg.minAttendees) || 1;
        var maxA = pkg.maxCap != null && Number.isFinite(pkg.maxCap) ? pkg.maxCap : MATRIX_MAX_ATTENDEES;
        matrixAttendeesInput.min = String(minA);
        matrixAttendeesInput.max = String(maxA);
        if (!matrixAttendeesInput.value) {
          matrixAttendeesInput.value = String(matrixSpinnerDefaultCount(minA, maxA, getPreferredDefaultAttendees(pkg)));
        }
        clampMatrixAttendeesField();
        syncMatrixStepperButtons();
      }

      function syncFinalAttendeesFromMatrix() {
        var matrixValue = Number(matrixAttendeesInput.value);
        if (Number.isFinite(matrixValue) && matrixValue > 0) {
          attendeesInput.value = String(matrixValue);
        }
      }

      function syncFinalAttendeesFromTrainer() {
        var trainerValue = Number(trainerAttendeesInput.value);
        if (Number.isFinite(trainerValue) && trainerValue > 0) {
          attendeesInput.value = String(trainerValue);
        }
      }

      function formatGbp(value) {
        return "£" + value.toFixed(2);
      }

      function getMinimumAttendeesForRow(row) {
        if (!row) {
          return 1;
        }
        var m = Number(row.minAttendees);
        return Number.isFinite(m) && m > 0 ? m : 1;
      }

      function applyAttendeesConstraintsFromRow(row) {
        var minValue = getMinimumAttendeesForRow(row);
        var maxValue = row && row.maxCap != null && Number.isFinite(row.maxCap) ? row.maxCap : MATRIX_MAX_ATTENDEES;
        attendeesInput.min = String(minValue);
        attendeesInput.max = String(maxValue);

        var current = Number(attendeesInput.value);
        if (!Number.isFinite(current) || current < minValue) {
          attendeesInput.value = String(minValue);
          return;
        }
        if (current > maxValue) {
          attendeesInput.value = String(maxValue);
        }
      }

      function getTrainersRequired(attendeesCount) {
        if (!Number.isFinite(attendeesCount) || attendeesCount < 1) {
          return 1;
        }
        return Math.ceil(attendeesCount / 20);
      }

      function extractLeadingCurrencyValue(text) {
        var match = (text || "").match(/£([0-9]+(?:\.[0-9]{2})?)/);
        return match ? match[1] : "";
      }

      function renderPricing(row) {
        if (!row) {
          pricingCard.classList.add("hidden");
          totalWithVatPrice.textContent = "-";
          trainersRequired.textContent = "1";
          trainersRequiredValueInput.value = "1";
          quoteValueInput.value = "";
          attendeesInput.min = "1";
          attendeesInput.max = String(MATRIX_MAX_ATTENDEES);
          return;
        }

        pricingCard.classList.remove("hidden");
        applyAttendeesConstraintsFromRow(row);
        if (isOrganisationStyleTrainingPath()) {
          syncFinalAttendeesFromMatrix();
        }
        if (isTrainerMode()) {
          syncFinalAttendeesFromTrainer();
        }
        var attendeeCount = getCurrentAttendeeCount();
        if (!Number.isFinite(attendeeCount) || attendeeCount < 1) {
          attendeeCount = Number(attendeesInput.value) || 0;
        }
        var displayPrice = formatQuoteTotal(row, attendeeCount);
        if (!displayPrice) {
          totalWithVatPrice.textContent = "-";
          totalWithVatRow.classList.add("hidden");
          quoteValueInput.value = "";
        } else {
          totalWithVatPrice.textContent = displayPrice;
          totalWithVatRow.classList.remove("hidden");
          quoteValueInput.value = extractLeadingCurrencyValue(displayPrice);
        }

        var trainerCount = getTrainersRequired(attendeeCount);
        trainersRequired.textContent = String(trainerCount);
        trainersRequiredValueInput.value = String(trainerCount);
        trainersRequiredRow.classList.remove("hidden");
        if (isTrainerMode()) {
          scheduleTrainerMondayFieldSync();
        }
      }

      function updateFinalDetailsVisibility() {
        var audienceType = getCheckedValue("audienceType");
        var personalGoal = getCheckedValue("personalGoal");
        var orgEndStepSelected = preferredDateTimeInput.value.trim() || dateNotSureInput.checked;
        var shouldShowForMe = audienceType === "me" && personalGoal === "onlineCourse";
        var hasTrainerAttendees = !!trainerAttendeesInput.value && Number(trainerAttendeesInput.value) > 0;
        var shouldShowForTrainer = audienceType === "me" && personalGoal === "becomeTrainer" && !!trainerCourseSelect.value && hasTrainerAttendees;
        var shouldShowForOrg = audienceType === "organisation" && !!orgEndStepSelected;
        var shouldShow = !trainingSection.classList.contains("hidden") && (shouldShowForMe || shouldShowForOrg || shouldShowForTrainer);
        var isOnlineCourse = isOnlineCourseMode();
        var isTrainer = isTrainerMode();
        var bookingViaCompany = !!(bookingViaCompanyInput && bookingViaCompanyInput.checked);

        finalDetailsBlock.classList.toggle("hidden", !shouldShow);
        extraNotesBlock.classList.toggle("hidden", isOnlineCourse);
        submitBtn.textContent = isOnlineCourse
          ? "View Online Courses"
          : isTrainer
            ? (bookingViaCompany ? "Send Quote" : "Book Now")
            : "Submit Enquiry and Get Quote";
        attendeesInput.required = false;
      }

      function hideTrainingEnquiry() {
        trainingSection.classList.add("hidden");
        finalDetailsBlock.classList.add("hidden");
        extraNotesBlock.classList.remove("hidden");
        submitBtn.textContent = "Submit Enquiry and Get Quote";
        attendeesInput.required = false;
        attendeesInput.min = "1";
        attendeesInput.max = String(MATRIX_MAX_ATTENDEES);
        trainerAttendeesInput.required = false;
        trainerAttendeesInput.value = "";
        trainerAttendeesInput.min = "1";
        trainerAttendeesInput.max = String(MATRIX_MAX_ATTENDEES);
        matrixAttendeesInput.required = false;
        matrixAttendeesInput.disabled = true;
        matrixAttendeesInput.value = "";
        matrixAttendeesInput.min = "1";
        matrixAttendeesInput.max = String(MATRIX_MAX_ATTENDEES);
        organisationCompanyInput.required = false;
        organisationCompanyInput.value = "";
        dateNotSureInput.checked = false;
        preferredDateTimeInput.disabled = false;
        syncMatrixStepperButtons();
        setGroupError(audienceTypeGroup, false);
        setGroupError(personalGoalGroup, false);
        setInputError(sectorSelect, false);
        setInputError(orgCourseSelect, false);
        setInputError(courseFormatSelect, false);
        setInputError(formatSubOptionSelect, false);
        setInputError(matrixAttendeesInput, false);
        setInputError(organisationCompanyInput, false);
        setInputError(trainerAttendeesInput, false);
        setInputError(preferredDateTimeInput, false);
        setInputError(trainerCourseSelect, false);
        if (bookingViaCompanyInput) {
          bookingViaCompanyInput.checked = false;
        }
        if (trainerCompanyBookingWrap) {
          trainerCompanyBookingWrap.classList.add("hidden");
        }
        renderPricing(null);
      }

      function validateTrainingFields() {
        var audienceType = getCheckedValue("audienceType");
        var personalGoal = getCheckedValue("personalGoal");
        var isForMe = audienceType === "me";
        var isOrganisation = audienceType === "organisation";
        var isTrainer = isTrainerMode();
        var hasError = false;

        setGroupError(audienceTypeGroup, !audienceType);
        if (!audienceType) {
          hasError = true;
        }

        if (isForMe) {
          setGroupError(personalGoalGroup, !personalGoal);
          if (!personalGoal) {
            hasError = true;
          }

          if (personalGoal === "becomeTrainer") {
            var missingTrainerCourse = !trainerCourseSelect.value;
            var trainerAttendeeCount = Number(trainerAttendeesInput.value);
            var trainerPkg = getTrainerPackage();
            var trainerMin = getMinimumAttendeesForRow(trainerPkg);
            var trainerMax = trainerPkg && trainerPkg.maxCap != null && Number.isFinite(trainerPkg.maxCap) ? trainerPkg.maxCap : MATRIX_MAX_ATTENDEES;
            var invalidTrainerAttendees = !trainerAttendeesInput.value || !Number.isInteger(trainerAttendeeCount) || trainerAttendeeCount < trainerMin || trainerAttendeeCount > trainerMax;
            setInputError(trainerCourseSelect, missingTrainerCourse);
            setInputError(trainerAttendeesInput, invalidTrainerAttendees);
            if (missingTrainerCourse) {
              hasError = true;
            }
            if (invalidTrainerAttendees) {
              hasError = true;
            }
          } else {
            setInputError(trainerCourseSelect, false);
            setInputError(trainerAttendeesInput, false);
          }

          if (!personalGoal || personalGoal !== "becomeTrainer") {
            setInputError(sectorSelect, false);
            setInputError(orgCourseSelect, false);
            setInputError(courseFormatSelect, false);
            setInputError(formatSubOptionSelect, false);
            setInputError(matrixAttendeesInput, false);
            setInputError(organisationCompanyInput, false);
            setInputError(preferredDateTimeInput, false);
          }
        }

        if (isOrganisation) {
          var missingSector = !sectorSelect.value;
          var missingCourse = !orgCourseSelect.value;
          var missingFormat = !courseFormatSelect.value;
          var missingSubOption = !formatSubOptionSelect.value;
          var matrixCount = Number(matrixAttendeesInput.value);
          var missingMatrixAttendees = !matrixAttendeesInput.value || !Number.isInteger(matrixCount) || matrixCount < 1;
          var missingOrganisationCompany = !organisationCompanyInput.value.trim();
          var missingPreferredDate = !preferredDateTimeInput.value.trim() && !dateNotSureInput.checked;

          if (isOrganisation) {
            setGroupError(personalGoalGroup, false);
          }
          setInputError(sectorSelect, missingSector);
          setInputError(orgCourseSelect, missingCourse);
          setInputError(courseFormatSelect, missingFormat);
          setInputError(formatSubOptionSelect, missingSubOption);
          setInputError(matrixAttendeesInput, missingMatrixAttendees);
          setInputError(organisationCompanyInput, missingOrganisationCompany);
          setInputError(preferredDateTimeInput, missingPreferredDate);

          if (missingSector || missingCourse || missingFormat || missingSubOption || missingMatrixAttendees || missingOrganisationCompany || missingPreferredDate) {
            hasError = true;
          }
        }

        if (!isForMe && !isOrganisation) {
          setGroupError(personalGoalGroup, false);
          setInputError(sectorSelect, false);
          setInputError(orgCourseSelect, false);
          setInputError(courseFormatSelect, false);
          setInputError(formatSubOptionSelect, false);
          setInputError(matrixAttendeesInput, false);
          setInputError(organisationCompanyInput, false);
          setInputError(preferredDateTimeInput, false);
          setInputError(trainerCourseSelect, false);
        }

        if (!finalDetailsBlock.classList.contains("hidden") && !isOnlineCourseMode()) {
          var needsOrgPackage = isOrganisation;
          var orgPackageReady = !needsOrgPackage || (!!sectorSelect.value && !!orgCourseSelect.value && !!courseFormatSelect.value && !!formatSubOptionSelect.value && !!matrixAttendeesInput.value);
          if (orgPackageReady) {
            var attendeesCount = Number(attendeesInput.value);
            var minAttendees = getMinimumAttendeesForRow(getSelectedTrainingRow());
            var attendeesInvalid = !Number.isInteger(attendeesCount) || attendeesCount < minAttendees;
            if (isOrganisationStyleTrainingPath()) {
              setInputError(matrixAttendeesInput, attendeesInvalid);
              setInputError(attendeesInput, false);
            } else {
              setInputError(attendeesInput, attendeesInvalid);
            }
            if (attendeesInvalid) {
              hasError = true;
            }
          } else {
            setInputError(attendeesInput, false);
          }
        } else {
          setInputError(attendeesInput, false);
        }

        return !hasError;
      }

      function updateAudienceBlocks() {
        var audienceType = getCheckedValue("audienceType");
        var personalGoal = getCheckedValue("personalGoal");
        var orgStylePath = isOrganisationStyleTrainingPath();
        var trainerPath = isTrainerMode();
        forMeBlock.classList.toggle("hidden", audienceType !== "me");
        trainerCoursesBlock.classList.toggle("hidden", !trainerPath);
        organisationBlock.classList.toggle("hidden", !orgStylePath);
        personalGoalHint.classList.toggle("hidden", !(audienceType === "me" && !personalGoal));

        var personalGoalInputs = form.querySelectorAll('input[name="personalGoal"]');
        Array.prototype.forEach.call(personalGoalInputs, function (input) {
          input.required = audienceType === "me";
        });

        sectorSelect.required = orgStylePath;
        orgCourseSelect.required = orgStylePath;
        courseFormatSelect.required = orgStylePath;
        formatSubOptionSelect.required = orgStylePath;
        matrixAttendeesInput.required = orgStylePath && !matrixAttendeesInput.disabled;
        organisationCompanyInput.required = orgStylePath;
        preferredDateTimeInput.required = orgStylePath && !dateNotSureInput.checked;
        trainerCourseSelect.required = trainerPath;
        trainerAttendeesInput.required = trainerPath;
        if (!trainerPath) {
          trainerAttendeesInput.value = "";
          trainerAttendeesInput.min = "1";
          trainerAttendeesInput.max = String(MATRIX_MAX_ATTENDEES);
        } else {
          var trainerPkg = getTrainerPackage();
          var trainerMin = getMinimumAttendeesForRow(trainerPkg);
          var trainerMax = trainerPkg && trainerPkg.maxCap != null && Number.isFinite(trainerPkg.maxCap) ? trainerPkg.maxCap : 200;
          trainerAttendeesInput.min = String(trainerMin);
          trainerAttendeesInput.max = String(trainerMax);
          if (!trainerAttendeesInput.value) {
            var fallbackDefault = trainerPkg && Number.isFinite(trainerPkg.defaultAttendees)
              ? Number(trainerPkg.defaultAttendees)
              : TRAINER_DEFAULT_ATTENDEES;
            trainerAttendeesInput.value = String(Math.min(trainerMax, Math.max(trainerMin, fallbackDefault)));
          }
        }

        if (audienceType !== "me") {
          setGroupError(personalGoalGroup, false);
        }

        if (!orgStylePath) {
          dateNotSureInput.checked = false;
          preferredDateTimeInput.disabled = false;
          organisationCompanyInput.value = "";
          setInputError(sectorSelect, false);
          setInputError(orgCourseSelect, false);
          setInputError(courseFormatSelect, false);
          setInputError(formatSubOptionSelect, false);
          setInputError(matrixAttendeesInput, false);
          setInputError(organisationCompanyInput, false);
          setInputError(preferredDateTimeInput, false);
        }
        if (!trainerPath) {
          setInputError(trainerCourseSelect, false);
          setInputError(trainerAttendeesInput, false);
        }
        if (orgStylePath) {
          renderPricing(getSelectedTrainingRow());
        } else if (trainerPath && trainerCourseSelect.value && trainerAttendeesInput.value) {
          renderPricing(getSelectedTrainingRow());
        } else {
          renderPricing(null);
        }

        syncTrainerCompanyBookingOption();
        refreshOrganisationDateGuidance();
        updateFinalDetailsVisibility();
      }

      function getUniqueValues(items, key) {
        var values = items.map(function (item) {
          return item[key];
        });
        var unique = values.filter(function (value, index) {
          return values.indexOf(value) === index;
        });
        if (key === "course") {
          var courseOrder = [
            "Positive Behaviour Support",
            "Safer Physical Intervention Training",
            "Combined Positive Behaviour Support & Safer physical intervention strategies",
            "Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award"
          ];
          unique.sort(function (a, b) {
            var aIdx = courseOrder.indexOf(a);
            var bIdx = courseOrder.indexOf(b);
            var aRank = aIdx === -1 ? Number.MAX_SAFE_INTEGER : aIdx;
            var bRank = bIdx === -1 ? Number.MAX_SAFE_INTEGER : bIdx;
            if (aRank !== bRank) {
              return aRank - bRank;
            }
            return 0;
          });
        }
        return unique;
      }

      function repopulateSelect(selectElement, values, placeholderText, keepEnabled) {
        selectElement.innerHTML = "";
        var placeholder = document.createElement("option");
        placeholder.value = "";
        placeholder.textContent = placeholderText;
        selectElement.appendChild(placeholder);

        values.forEach(function (entry) {
          var option = document.createElement("option");
          if (entry && typeof entry === "object" && entry.label != null) {
            option.value = entry.value;
            option.textContent = entry.label;
          } else {
            option.value = entry;
            option.textContent = entry;
          }
          selectElement.appendChild(option);
        });

        selectElement.disabled = !keepEnabled;
      }

      function getCourseSelectOptions(matrixItems) {
        var courseOrder = [
          "Positive Behaviour Support",
          "Safer Physical Intervention Training",
          "Combined Positive Behaviour Support & Safer physical intervention strategies",
          "Positive Behaviour Support & Safer Physical Intervention Train the Trainer Award"
        ];
        var courseMap = {};
        matrixItems.forEach(function (item) {
          if (!courseMap[item.course]) {
            courseMap[item.course] = {};
          }
          courseMap[item.course][item.courseValue] = true;
        });
        var options = Object.keys(courseMap).map(function (course) {
          var hiddenValues = Object.keys(courseMap[course]);
          return {
            label: course,
            value: hiddenValues.length === 1 ? hiddenValues[0] : course
          };
        });
        options.sort(function (a, b) {
          var aIdx = courseOrder.indexOf(a.label);
          var bIdx = courseOrder.indexOf(b.label);
          var aRank = aIdx === -1 ? Number.MAX_SAFE_INTEGER : aIdx;
          var bRank = bIdx === -1 ? Number.MAX_SAFE_INTEGER : bIdx;
          if (aRank !== bRank) {
            return aRank - bRank;
          }
          return 0;
        });
        return options;
      }

      function resolveOrganisationMondayCourseValue() {
        var pkg = getSelectedPackage();
        return pkg && pkg.courseValue ? pkg.courseValue : "";
      }

      function populateTrainingMatrixSectors() {
        var sectors = getUniqueValues(TRAINING_MATRIX, "sector");
        repopulateSelect(sectorSelect, sectors, "Select sector", sectors.length > 0);
      }

      function loadTrainingMatrix() {
        if (window.location.protocol === "file:") {
          return Promise.resolve();
        }

        sectorSelect.disabled = true;
        return fetch(TRAINING_MATRIX_ENDPOINT)
          .then(function (response) {
            return response
              .json()
              .catch(function () {
                return {};
              })
              .then(function (body) {
                if (!response.ok || !body.success || !Array.isArray(body.matrix)) {
                  throw new Error(body && body.message ? body.message : "Could not load training courses.");
                }
                return body.matrix;
              });
          })
          .then(function (matrix) {
            TRAINING_MATRIX = matrix;
            trainingMatrixLoaded = true;
            populateTrainingMatrixSectors();
            sectorSelect.disabled = false;
          })
          .catch(function (error) {
            console.error(error);
            sectorSelect.disabled = true;
          });
      }

      function syncMondaySpecificCourse() {
        var hidden = document.getElementById("mondaySpecificCourse");
        if (!hidden) {
          return;
        }
        hidden.value = resolveOrganisationMondayCourseValue();
      }

      function refreshOrganisationDateGuidance() {
        if (!dateGuidance || !preferredDateTimeInput || !dateNotSureInput) {
          return;
        }
        var showPreferredDate = isOrganisationStyleTrainingPath() && !dateNotSureInput.checked;
        if (showPreferredDate) {
          dateGuidance.textContent =
            "Select sector, course, format, course style, attendees, and a preferred date and start time to confirm a quote.";
        } else {
          dateGuidance.textContent =
            "Select sector, course, format, course style, and attendees to confirm a quote.";
        }
      }

      function updateOrganisationMatrixFields(source) {
        if (!trainingMatrixLoaded) {
          return;
        }

        var selectedSector = sectorSelect.value;
        var selectedCourse = orgCourseSelect.value;
        var selectedFormat = courseFormatSelect.value;
        var selectedSubOption = formatSubOptionSelect.value;

        if (source === "sector") {
          var courses = getCourseSelectOptions(
            TRAINING_MATRIX.filter(function (item) {
              return item.sector === selectedSector;
            })
          );
          repopulateSelect(orgCourseSelect, courses, selectedSector ? "Select course" : "Select sector first", selectedSector && courses.length > 0);
          repopulateSelect(courseFormatSelect, [], "Select course first", false);
          repopulateSelect(formatSubOptionSelect, [], "Select format first", false);
        }

        if (source === "course") {
          var formats = getUniqueValues(
            TRAINING_MATRIX.filter(function (item) {
              return item.sector === selectedSector && orgCourseMatchesItem(item, selectedCourse);
            }),
            "format"
          );
          repopulateSelect(courseFormatSelect, formats, selectedCourse ? "Select format" : "Select course first", selectedCourse && formats.length > 0);
          repopulateSelect(formatSubOptionSelect, [], "Select format first", false);
        }

        if (source === "format") {
          var subOptions = getUniqueValues(
            TRAINING_MATRIX.filter(function (item) {
              return (
                item.sector === selectedSector &&
                orgCourseMatchesItem(item, selectedCourse) &&
                item.format === selectedFormat
              );
            }),
            "subOption"
          );
          repopulateSelect(formatSubOptionSelect, subOptions, selectedFormat ? "Select Course Style" : "Select format first", selectedFormat && subOptions.length > 0);
        }

        selectedSector = sectorSelect.value;
        selectedCourse = orgCourseSelect.value;
        selectedFormat = courseFormatSelect.value;
        selectedSubOption = formatSubOptionSelect.value;

        if (source !== "subOption") {
          matrixAttendeesInput.disabled = true;
          matrixAttendeesInput.value = "";
          matrixAttendeesInput.min = "1";
          matrixAttendeesInput.max = String(MATRIX_MAX_ATTENDEES);
        }

        if (selectedSector && selectedCourse && selectedFormat && selectedSubOption) {
          applyMatrixSpinnerConstraints(getMatrixGroupRows());
          syncFinalAttendeesFromMatrix();
        } else {
          applyMatrixSpinnerConstraints([]);
        }

        refreshOrganisationDateGuidance();
        if (selectedSector && selectedCourse && selectedFormat && selectedSubOption && matrixAttendeesInput.value) {
          renderPricing(getSelectedTrainingRow());
        } else {
          renderPricing(null);
        }

        syncMondaySpecificCourse();

        if (isOrganisationStyleTrainingPath()) {
          matrixAttendeesInput.required = !matrixAttendeesInput.disabled;
        }

        updateFinalDetailsVisibility();
      }

      function appendAddressFieldsToFormData(fd) {
        if (!fd) {
          return;
        }
        var groups = [
          ["addressLine1", "addressLine2", "addressTown", "addressPostcode", "addressLat", "addressLng"],
          ["tmAddressLine1", "tmAddressLine2", "tmAddressTown", "tmAddressPostcode", "tmAddressLat", "tmAddressLng"],
        ];
        groups.forEach(function (ids) {
          ids.forEach(function (id) {
            var el = document.getElementById(id);
            if (el && el.name) {
              fd.append(el.name, el.value || "");
            }
          });
        });
      }

      function initAddressLookup(cfg) {
        var pcInput = document.getElementById(cfg.postcodeInputId);
        var btn = document.getElementById(cfg.lookupBtnId);
        var statusEl = document.getElementById(cfg.statusId);
        var pick = document.getElementById(cfg.pickId);
        var pickLabel = document.getElementById(cfg.pickLabelId);
        var rawRows = [];

        function setStatus(msg) {
          if (statusEl) {
            var text = msg || "";
            statusEl.textContent = text;
            statusEl.classList.toggle("hidden", !text.trim());
          }
        }

        function applyRow(row) {
          if (!row) {
            return;
          }
          var l1 = document.getElementById(cfg.line1Id);
          var l2 = document.getElementById(cfg.line2Id);
          var town = document.getElementById(cfg.townId);
          var pc = document.getElementById(cfg.postcodeFieldId);
          var lat = document.getElementById(cfg.latId);
          var lng = document.getElementById(cfg.lngId);
          if (l1) l1.value = row.line_1 || "";
          if (l2) l2.value = row.line_2 || "";
          if (town) town.value = row.post_town || "";
          if (pc) pc.value = row.postcode || "";
          if (lat) lat.value = row.latitude != null && row.latitude !== "" ? String(row.latitude) : "";
          if (lng) lng.value = row.longitude != null && row.longitude !== "" ? String(row.longitude) : "";
        }

        function resetPick() {
          rawRows = [];
          if (!pick) {
            return;
          }
          pick.innerHTML = '<option value="">—</option>';
          pick.classList.add("hidden");
          pick.setAttribute("aria-hidden", "true");
          if (pickLabel) {
            pickLabel.classList.add("hidden");
          }
        }

        if (btn && pcInput) {
          btn.addEventListener("click", function () {
            var rawPc = (pcInput.value || "").trim();
            if (!rawPc) {
              setStatus("Enter a UK postcode first.");
              return;
            }
            btn.disabled = true;
            setStatus("Looking up…");
            resetPick();
            fetch(POSTCODE_LOOKUP_ENDPOINT + "?postcode=" + encodeURIComponent(rawPc))
              .then(function (response) {
                return response.json().then(function (body) {
                  return { ok: response.ok, body: body };
                });
              })
              .then(function (pack) {
                var body = pack.body || {};
                if (!pack.ok || !body.success) {
                  throw new Error(body.message || "Postcode lookup failed.");
                }
                var rows = body.result;
                if (!Array.isArray(rows) || rows.length === 0) {
                  throw new Error("No addresses found for that postcode.");
                }
                rawRows = rows;
                if (rows.length === 1) {
                  applyRow(rows[0]);
                  setStatus("Address filled — you can edit the fields below.");
                  return;
                }
                if (!pick) {
                  applyRow(rows[0]);
                  setStatus("Address filled — you can edit the fields below.");
                  return;
                }
                pick.innerHTML = '<option value="">Select an address</option>';
                rows.forEach(function (row, idx) {
                  var opt = document.createElement("option");
                  opt.value = String(idx);
                  var bits = [row.line_1, row.post_town, row.postcode].filter(Boolean);
                  opt.textContent = bits.join(", ");
                  pick.appendChild(opt);
                });
                pick.classList.remove("hidden");
                pick.removeAttribute("aria-hidden");
                if (pickLabel) {
                  pickLabel.classList.remove("hidden");
                }
                setStatus(rows.length + " address" + (rows.length === 1 ? "" : "es") + " found — choose from the list.");
              })
              .catch(function (err) {
                setStatus(err && err.message ? err.message : "Lookup failed.");
              })
              .finally(function () {
                btn.disabled = false;
              });
          });
        }

        if (pick) {
          pick.addEventListener("change", function () {
            var idx = parseInt(pick.value, 10);
            if (!Number.isInteger(idx) || idx < 0 || idx >= rawRows.length) {
              return;
            }
            applyRow(rawRows[idx]);
            setStatus("Address filled — you can edit the fields below.");
          });
        }
      }

      initAddressLookup({
        postcodeInputId: "addressPostcodeLookup",
        lookupBtnId: "addressLookupBtn",
        statusId: "addressLookupStatus",
        pickId: "addressPick",
        pickLabelId: "addressPickLabel",
        line1Id: "addressLine1",
        line2Id: "addressLine2",
        townId: "addressTown",
        postcodeFieldId: "addressPostcode",
        latId: "addressLat",
        lngId: "addressLng",
      });

      initAddressLookup({
        postcodeInputId: "tmAddressPostcodeLookup",
        lookupBtnId: "tmAddressLookupBtn",
        statusId: "tmAddressLookupStatus",
        pickId: "tmAddressPick",
        pickLabelId: "tmAddressPickLabel",
        line1Id: "tmAddressLine1",
        line2Id: "tmAddressLine2",
        townId: "tmAddressTown",
        postcodeFieldId: "tmAddressPostcode",
        latId: "tmAddressLat",
        lngId: "tmAddressLng",
      });

      continueBtn.addEventListener("click", function () {
        var name = nameInput.value.trim();
        var email = emailInput.value.trim();
        var enquiryType = getCheckedValue("enquiryType");

        setInputError(nameInput, !name);
        setInputError(emailInput, !email);
        setGroupError(enquiryTypeGroup, !enquiryType);

        if (!name || !email || !enquiryType) {
          return;
        }

        if (window.location.protocol === "file:") {
          window.alert("This form must run through a PHP server. From backend/: composer run dev  (or from project root: php -S localhost:8000 router.php)");
          return;
        }

        continueBtn.disabled = true;
        continueBtn.textContent = "Checking...";

        var continuePayload = new FormData();
        continuePayload.append("name", name);
        continuePayload.append("email", email);
        continuePayload.append("enquiryType", enquiryType);
        appendAddressFieldsToFormData(continuePayload);
        appendEnquiryIdToFormData(continuePayload);

        fetch(MONDAY_CONTINUE_ENDPOINT, {
          method: "POST",
          body: continuePayload,
        })
          .then(function (response) {
            return response
              .json()
              .catch(function () {
                return {};
              })
              .then(function (body) {
                if (!response.ok) {
                  throw new Error(body && body.message ? body.message : "Unable to process Monday check");
                }
                return body;
              });
          })
          .then(function (result) {
            if (!result || !result.success) {
              throw new Error(result && result.message ? result.message : "Monday API failed");
            }

            if (result.enquiryId) {
              setEnquiryId(result.enquiryId);
            }

            continueBtn.style.display = "none";

            if (enquiryType === "equipment") {
              hideTrainingEnquiry();
              window.location.href = SHOP_URL;
              return;
            }

            if (enquiryType === "guidance") {
              hideTrainingEnquiry();
              window.location.href = RESOURCES_URL;
              return;
            }

            trainingSection.classList.remove("hidden");
            updateFinalDetailsVisibility();
            trainingSection.scrollIntoView({ behavior: "smooth", block: "start" });
          })
          .catch(function (error) {
            window.alert(error && error.message ? error.message : "We could not check your details right now. Please try again.");
            console.error(error);
          })
          .finally(function () {
            continueBtn.disabled = false;
            continueBtn.textContent = "Continue";
          });
      });

      form.addEventListener("change", function (event) {
        var name = event.target.name;

        scheduleEnquiryProgressSave();

        if (name === "enquiryType") {
          setGroupError(enquiryTypeGroup, false);
          if (event.target.value !== "training") {
            hideTrainingEnquiry();
          }
        }

        if (name === "audienceType") {
          setGroupError(audienceTypeGroup, false);
          updateAudienceBlocks();
        }

        if (name === "personalGoal") {
          setGroupError(personalGoalGroup, false);
          updateAudienceBlocks();
        }
        if (name === "trainerCourseSelect") {
          setInputError(trainerCourseSelect, !trainerCourseSelect.value);
          var trainerPkg = getTrainerPackage();
          if (trainerPkg) {
            trainerAttendeesInput.min = String(getMinimumAttendeesForRow(trainerPkg));
            trainerAttendeesInput.max = trainerPkg.maxCap != null && Number.isFinite(trainerPkg.maxCap) ? String(trainerPkg.maxCap) : String(MATRIX_MAX_ATTENDEES);
            if (!trainerAttendeesInput.value) {
              trainerAttendeesInput.value = String(Number(trainerPkg.defaultAttendees) || TRAINER_DEFAULT_ATTENDEES);
            }
            syncFinalAttendeesFromTrainer();
            renderPricing(getSelectedTrainingRow());
          } else {
            renderPricing(null);
          }
          syncTrainerCompanyBookingOption();
          updateFinalDetailsVisibility();
          scheduleTrainerMondayFieldSync();
        }

        if (name === "bookingViaCompany") {
          updateFinalDetailsVisibility();
          syncMondayBookingViaCompanyFlag();
        }

        if (name === "sector") {
          setInputError(sectorSelect, false);
          updateOrganisationMatrixFields("sector");
        }

        if (name === "orgCourse") {
          setInputError(orgCourseSelect, false);
          updateOrganisationMatrixFields("course");
        }

        if (name === "courseFormat") {
          setInputError(courseFormatSelect, false);
          updateOrganisationMatrixFields("format");
        }

        if (name === "formatSubOption") {
          setInputError(formatSubOptionSelect, false);
          updateOrganisationMatrixFields("subOption");
        }
      });

      nameInput.addEventListener("input", function () {
        setInputError(nameInput, !nameInput.value.trim());
        scheduleEnquiryProgressSave();
      });

      emailInput.addEventListener("input", function () {
        setInputError(emailInput, !emailInput.value.trim());
        scheduleEnquiryProgressSave();
      });

      matrixAttendeesInput.addEventListener("keydown", function (event) {
        if (["e", "E", "+", "-", "."].indexOf(event.key) > -1) {
          event.preventDefault();
        }
      });

      matrixAttendeesInput.addEventListener("input", function () {
        commitMatrixAttendeesChange();
      });

      matrixAttendeesMinus.addEventListener("click", function () {
        stepMatrixAttendees(-1);
      });

      matrixAttendeesPlus.addEventListener("click", function () {
        stepMatrixAttendees(1);
      });

      trainerAttendeesInput.addEventListener("input", function () {
        var digits = trainerAttendeesInput.value.replace(/\D/g, "");
        if (!digits) {
          trainerAttendeesInput.value = "";
          setInputError(trainerAttendeesInput, true);
          renderPricing(null);
          updateFinalDetailsVisibility();
          return;
        }
        var pkg = getTrainerPackage();
        var minValue = getMinimumAttendeesForRow(pkg);
        var maxValue = pkg && pkg.maxCap != null && Number.isFinite(pkg.maxCap) ? pkg.maxCap : MATRIX_MAX_ATTENDEES;
        var nextValue = Number(digits);
        if (!Number.isFinite(nextValue)) {
          nextValue = minValue;
        }
        var bounded = Math.min(maxValue, Math.max(minValue, nextValue));
        trainerAttendeesInput.value = String(bounded);
        setInputError(trainerAttendeesInput, false);
        syncFinalAttendeesFromTrainer();
        renderPricing(getSelectedTrainingRow());
        updateFinalDetailsVisibility();
        scheduleTrainerMondayFieldSync();
      });

      preferredDateTimeInput.addEventListener("input", function () {
        setInputError(preferredDateTimeInput, !preferredDateTimeInput.value.trim());
        updateFinalDetailsVisibility();
        renderPricing(getSelectedTrainingRow());
      });

      organisationCompanyInput.addEventListener("input", function () {
        setInputError(organisationCompanyInput, !organisationCompanyInput.value.trim());
      });

      dateNotSureInput.addEventListener("change", function () {
        if (dateNotSureInput.checked) {
          preferredDateTimeInput.value = "";
          preferredDateTimeInput.disabled = true;
          setInputError(preferredDateTimeInput, false);
        } else {
          preferredDateTimeInput.disabled = false;
        }
        preferredDateTimeInput.required = isOrganisationStyleTrainingPath() && !dateNotSureInput.checked;
        refreshOrganisationDateGuidance();
        updateFinalDetailsVisibility();
        renderPricing(getSelectedTrainingRow());
      });

      form.addEventListener("submit", function (event) {
        event.preventDefault();
        var attendeesValue = Number(attendeesInput.value);
        var audienceType = getCheckedValue("audienceType");
        var personalGoal = getCheckedValue("personalGoal");
        var requiresAttendees = !isOnlineCourseMode() && !finalDetailsBlock.classList.contains("hidden");
        var minAttendeesForSubmit = requiresAttendees ? getMinimumAttendeesForRow(getSelectedTrainingRow()) : 1;

        var attendeesSubmitInvalid = requiresAttendees && (!Number.isInteger(attendeesValue) || attendeesValue < minAttendeesForSubmit);
        if (isOrganisationStyleTrainingPath()) {
          setInputError(matrixAttendeesInput, attendeesSubmitInvalid);
          setInputError(attendeesInput, false);
        } else {
          setInputError(attendeesInput, attendeesSubmitInvalid);
        }
        if (attendeesSubmitInvalid) {
          return;
        }

        if (!trainingSection.classList.contains("hidden") && !validateTrainingFields()) {
          return;
        }

        syncMondaySpecificCourse();
        if (isOrganisationStyleTrainingPath()) {
          var resolvedMondayCourse = resolveOrganisationMondayCourseValue();
          var mondayCourseHidden = document.getElementById("mondaySpecificCourse");
          if (!resolvedMondayCourse) {
            window.alert("Please reselect course, format, and course style before submitting.");
            return;
          }
          if (mondayCourseHidden) {
            mondayCourseHidden.value = resolvedMondayCourse;
          }
        }

        if (audienceType === "me" && personalGoal === "onlineCourse") {
          submitBtn.disabled = true;
          submitBtn.textContent = "Opening courses...";

          var onlinePayload = new FormData();
          onlinePayload.append("email", emailInput.value.trim());
          if (extraNotesInput && extraNotesInput.value.trim()) {
            onlinePayload.append("extraNotes", extraNotesInput.value.trim());
          }
          appendEnquiryIdToFormData(onlinePayload);

          fetch(MONDAY_ONLINE_COURSE_ENDPOINT, {
            method: "POST",
            body: onlinePayload,
          })
            .then(function (response) {
              return response
                .json()
                .catch(function () {
                  return {};
                })
                .then(function (body) {
                  if (!response.ok || !body.success) {
                    throw new Error(body && body.message ? body.message : "Unable to update Monday enquiry");
                  }
                  return body;
                });
            })
            .then(function () {
              window.location.href = KAJABI_COURSES_URL;
            })
            .catch(function (error) {
              window.alert(error && error.message ? error.message : "Could not update your enquiry before redirect.");
              console.error(error);
              submitBtn.disabled = false;
              submitBtn.textContent = "View Online Courses";
            });
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";

        fetch(form.action, {
          method: "POST",
          body: new FormData(form),
        })
          .then(function (response) {
            return response
              .json()
              .catch(function () {
                return {};
              })
              .then(function (body) {
                if (!response.ok || !body.success) {
                  throw new Error(body && body.message ? body.message : "Failed to submit enquiry");
                }
                return body;
              });
          })
          .then(function () {
            showThankYouPage();
          })
          .catch(function (error) {
            window.alert(error && error.message ? error.message : "We could not submit your enquiry. Please try again.");
            console.error(error);
          })
          .finally(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Enquiry and Get Quote";
          });
      });

      loadTrainingMatrix().then(function () {
        return tryRestoreEnquiryFromUrl();
      });
    })();
  </script>
</body>
</html>
