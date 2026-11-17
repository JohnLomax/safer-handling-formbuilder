<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $form->title }} · Safer Handling</title>
    <link rel="icon" type="image/png" href="/assets/safer-handling-logo.png" />
    <style>
        :root {
            --brand-blue: #008afc;
            --brand-blue-dark: #0255a4;
            --brand-green: rgb(186 218 85);
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
        .container { max-width: 640px; margin: 28px auto; padding: 0 16px 40px; }
        .card {
            background: var(--brand-white);
            border: 1px solid #cfe4f8;
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(0, 138, 252, 0.13), 0 2px 10px rgba(11, 71, 117, 0.07);
            overflow: hidden;
        }
        .brand-header {
            background: var(--brand-blue-dark);
            padding: 20px 22px;
            color: var(--brand-white);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .logo { width: 180px; max-width: 48%; display: block; }
        .title-wrap h1 { margin: 0; font-size: 1.35rem; line-height: 1.15; }
        .title-wrap p { margin: 6px 0 0; opacity: 0.95; font-size: 0.95rem; }
        form { padding: 22px; background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%); }
        .section {
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #fcfeff 100%);
            padding: 18px;
            margin-bottom: 18px;
        }
        .section h2 {
            margin: 0 0 6px;
            font-size: 1.05rem;
            color: var(--brand-blue-dark);
        }
        .section .hint { margin: 0 0 14px; font-size: 0.9rem; color: var(--text-mid); }
        label.field {
            display: block;
            margin: 0 0 6px;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .optional { font-weight: 500; color: var(--text-mid); }
        input[type="text"],
        input[type="email"],
        input[type="tel"] {
            width: 100%;
            margin-bottom: 14px;
            padding: 12px 14px;
            border: 1px solid #c5daf0;
            border-radius: 10px;
            font-size: 1rem;
            color: var(--text-dark);
            background: #fff;
        }
        input:focus {
            outline: 2px solid rgba(0, 138, 252, 0.35);
            border-color: var(--brand-blue);
        }
        .grid-2 { display: grid; gap: 0 14px; grid-template-columns: 1fr; }
        @media (min-width: 520px) { .grid-2 { grid-template-columns: 1fr 1fr; } }
        .choice-list { display: grid; gap: 8px; }
        .choice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 10px 12px;
            border: 1px solid var(--border-soft);
            border-radius: 10px;
            background: var(--surface-soft);
            cursor: pointer;
        }
        .choice:hover { border-color: #a9cceb; }
        .choice input { margin-top: 3px; accent-color: var(--brand-blue-dark); width: 18px; height: 18px; flex-shrink: 0; }
        .choice span { font-size: 0.95rem; color: var(--text-dark); }
        .courses-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0 0 16px;
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--brand-blue-dark);
            text-decoration: underline;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 13px 18px;
            border: 0;
            border-radius: 12px;
            background: var(--brand-blue-dark);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-primary:hover { background: #014a8f; }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            margin-top: 10px;
            padding: 12px 18px;
            border: 1px solid #b9d4ef;
            border-radius: 12px;
            background: #fff;
            color: var(--brand-blue-dark);
            font-size: 0.95rem;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-secondary:hover { background: var(--surface-soft); }
        .error-list {
            margin: 0 0 16px;
            padding: 12px 14px;
            border-radius: 10px;
            background: #fff1f1;
            border: 1px solid #f5c2c2;
            color: #8a1f1f;
            font-size: 0.9rem;
        }
        .thank-you {
            padding: 40px 28px 36px;
            text-align: center;
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
        }
        .thank-you-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #eef8d8;
            color: #4d7a14;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .thank-you-icon svg { width: 28px; height: 28px; }
        .thank-you h2 {
            margin: 0 0 10px;
            font-size: 1.55rem;
            line-height: 1.15;
            color: var(--brand-blue-dark);
        }
        .thank-you p {
            margin: 0 auto;
            max-width: 28rem;
            color: var(--text-mid);
            font-size: 1rem;
        }
        .thank-you-actions {
            margin: 24px auto 0;
            max-width: 280px;
        }
        .brand-header.is-thanks .title-wrap { display: none; }
        .brand-header.is-thanks { justify-content: center; }
        .brand-header.is-thanks .logo { max-width: 220px; width: 200px; }
        .credit { margin-top: 18px; text-align: center; font-size: 0.78rem; color: var(--text-mid); }
        .credit a { color: var(--brand-blue-dark); }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="brand-header{{ $submitted ? ' is-thanks' : '' }}">
            <img class="logo" src="/assets/safer-handling-logo.png" alt="Safer Handling" />
            @unless ($submitted)
                <div class="title-wrap">
                    <h1>{{ $form->title }}</h1>
                    @if ($form->intro)
                        <p>{{ $form->intro }}</p>
                    @endif
                </div>
            @endunless
        </div>

        @if ($submitted)
            <div class="thank-you">
                <div class="thank-you-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6L9 17l-5-5" />
                    </svg>
                </div>
                <h2>Thank you</h2>
                <p>We’ve received your details. If you asked us to follow up, a member of the team will be in touch.</p>
                <div class="thank-you-actions">
                    <a class="btn-primary" href="{{ $form->coursesLink() }}" target="_blank" rel="noopener">Browse our courses</a>
                    <a class="btn-secondary" href="https://www.safer-handling.co.uk/" target="_blank" rel="noopener">Visit safer-handling.co.uk</a>
                </div>
            </div>
        @else
            <form method="POST" action="{{ route('custom-forms.submit', $form->slug) }}" novalidate>
                @csrf

                @if ($errors->any())
                    <div class="error-list">
                        <ul style="margin:0; padding-left:18px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="section">
                    <h2>Your details</h2>
                    <p class="hint">Takes under a minute — we’ll only use this to follow up about training.</p>

                    <div class="grid-2">
                        <div>
                            <label class="field" for="first_name">First name</label>
                            <input id="first_name" name="first_name" type="text" maxlength="120" autocomplete="given-name" value="{{ old('first_name') }}" required />
                        </div>
                        <div>
                            <label class="field" for="surname">Surname</label>
                            <input id="surname" name="surname" type="text" maxlength="120" autocomplete="family-name" value="{{ old('surname') }}" required />
                        </div>
                    </div>

                    <label class="field" for="organisation">Name of school / organisation <span class="optional">(optional)</span></label>
                    <input id="organisation" name="organisation" type="text" maxlength="255" autocomplete="organization" value="{{ old('organisation') }}" />

                    <label class="field" for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="255" autocomplete="email" value="{{ old('email') }}" required />

                    <label class="field" for="phone">Phone number <span class="optional">(optional)</span></label>
                    <input id="phone" name="phone" type="tel" maxlength="60" autocomplete="tel" value="{{ old('phone') }}" />

                    <a class="courses-link" href="{{ $form->coursesLink() }}" target="_blank" rel="noopener">
                        Prefer to browse our courses first? Open them here
                    </a>
                </div>

                <div class="section">
                    <h2>What would you like to know more about?</h2>
                    <p class="hint">Tick anything that sounds useful — or choose “Not sure” and we’ll help.</p>
                    <div class="choice-list">
                        @foreach ($interestOptions as $value => $label)
                            <label class="choice">
                                <input
                                    type="checkbox"
                                    name="interests[]"
                                    value="{{ $value }}"
                                    @checked(in_array($value, old('interests', []), true))
                                />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="section">
                    <h2>Would you like us to contact you after the event?</h2>
                    <div class="choice-list">
                        @foreach ($contactOptions as $value => $label)
                            <label class="choice">
                                <input
                                    type="radio"
                                    name="contact_preference"
                                    value="{{ $value }}"
                                    @checked(old('contact_preference') === $value)
                                />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn-primary">Send my details</button>
            </form>
        @endif
    </div>
    <p class="credit">Safer Handling · <a href="https://www.safer-handling.co.uk/" target="_blank" rel="noopener">safer-handling.co.uk</a></p>
</div>
</body>
</html>
