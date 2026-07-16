<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Safer Handling Feedback Form</title>
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
      max-width: 720px;
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
      overflow-x: clip;
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
    }

    .title-wrap h1 {
      margin: 0;
      font-size: 1.72rem;
      line-height: 1.1;
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
      box-shadow: 0 4px 14px rgba(0, 138, 252, 0.06);
    }

    .section h2 {
      margin: 0 0 6px;
      font-size: 1.25rem;
      color: #0255a4;
    }

    .section-intro {
      margin: 0 0 14px;
      color: var(--text-mid);
      font-size: 0.95rem;
    }

    label {
      display: block;
      font-weight: 700;
      margin: 12px 0 7px;
      color: #20567e;
    }

    input[type="text"],
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

    input[type="text"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--brand-blue);
      background: #fafdff;
      box-shadow: 0 0 0 3px rgba(0, 138, 252, 0.18);
    }

    textarea {
      min-height: 160px;
      resize: vertical;
    }

    .field-error {
      border-color: #dc2626 !important;
      box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.15) !important;
      background: #fff7f7 !important;
    }

    .error-message {
      margin-top: 6px;
      color: #b91c1c;
      font-size: 0.9rem;
      font-weight: 600;
    }

    .actions {
      margin-top: 18px;
    }

    button {
      border: 0;
      border-radius: 10px;
      padding: 11px 17px;
      font-weight: 700;
      font-size: 0.95rem;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
    }

    button:hover {
      transform: translateY(-1px);
    }

    .btn-primary {
      background: #0255a4;
      color: var(--brand-white);
      box-shadow: 0 8px 18px rgba(2, 85, 164, 0.32);
    }

    .btn-primary:hover {
      background: #02478f;
    }

    .btn-primary:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .hidden {
      display: none;
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

    @media (max-width: 720px) {
      .brand-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .logo {
        max-width: 220px;
        width: 100%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="brand-header">
        <img class="logo" src="assets/safer-handling-logo.png" alt="Safer Handling logo" />
        <div class="title-wrap">
          <h1>Feedback form</h1>
          <p>Tell us about any issues you experienced.</p>
        </div>
      </div>

      <form id="feedbackForm" action="submit_feedback.php" method="post" novalidate>
        <div class="section">
          <h2>Share your feedback</h2>
          <p class="section-intro">Let us know what went wrong so we can improve your experience.</p>

          <label for="issueFaced">Issue faced</label>
          <input id="issueFaced" name="issueFaced" type="text" maxlength="255" placeholder="e.g. Problem with the enquiry form" required />
          <p id="issueFacedError" class="error-message hidden">Please tell us which issue you faced.</p>

          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="5000" placeholder="Describe what happened and any steps that led to the issue..." required></textarea>
          <p id="descriptionError" class="error-message hidden">Please provide a description of the issue.</p>

          <div class="actions">
            <button id="submitBtn" type="submit" class="btn-primary">Submit</button>
          </div>
        </div>
      </form>

      <section id="thankYouView" class="thank-you-view hidden">
        <img class="thank-you-logo" src="assets/safer-handling-logo.png" alt="Safer Handling logo" />
        <h2>Thank you</h2>
        <p>Your feedback has been submitted. We appreciate you taking the time to help us improve.</p>
      </section>
    </div>
  </div>

  <script>
    (function () {
      var form = document.getElementById("feedbackForm");
      var thankYouView = document.getElementById("thankYouView");
      var issueFacedInput = document.getElementById("issueFaced");
      var descriptionInput = document.getElementById("description");
      var issueFacedError = document.getElementById("issueFacedError");
      var descriptionError = document.getElementById("descriptionError");
      var submitBtn = document.getElementById("submitBtn");
      var SUBMIT_ENDPOINT = new URL("submit_feedback.php", window.location.href).toString();

      function setFieldError(input, errorEl, hasError, message) {
        input.classList.toggle("field-error", hasError);
        if (hasError) {
          errorEl.textContent = message;
          errorEl.classList.remove("hidden");
        } else {
          errorEl.classList.add("hidden");
        }
      }

      function validateForm() {
        var issueValid = issueFacedInput.value.trim() !== "";
        var descriptionValid = descriptionInput.value.trim() !== "";

        setFieldError(
          issueFacedInput,
          issueFacedError,
          !issueValid,
          "Please tell us which issue you faced."
        );
        setFieldError(
          descriptionInput,
          descriptionError,
          !descriptionValid,
          "Please provide a description of the issue."
        );

        return issueValid && descriptionValid;
      }

      issueFacedInput.addEventListener("input", function () {
        setFieldError(issueFacedInput, issueFacedError, !issueFacedInput.value.trim(), "Please tell us which issue you faced.");
      });

      descriptionInput.addEventListener("input", function () {
        setFieldError(descriptionInput, descriptionError, !descriptionInput.value.trim(), "Please provide a description of the issue.");
      });

      form.addEventListener("submit", function (event) {
        event.preventDefault();

        if (!validateForm()) {
          return;
        }

        if (window.location.protocol === "file:") {
          window.alert("This form must run through a PHP server. From backend/: composer run dev  (or from project root: php -S localhost:8000 router.php)");
          return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";

        fetch(SUBMIT_ENDPOINT, {
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
                  throw new Error(body && body.message ? body.message : "Failed to submit feedback");
                }
                return body;
              });
          })
          .then(function () {
            form.classList.add("hidden");
            thankYouView.classList.remove("hidden");
            thankYouView.scrollIntoView({ behavior: "smooth", block: "start" });
          })
          .catch(function (error) {
            window.alert(error && error.message ? error.message : "We could not submit your feedback. Please try again.");
            console.error(error);
          })
          .finally(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit";
          });
      });
    })();
  </script>
</body>
</html>
