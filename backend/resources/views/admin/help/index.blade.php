<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Help & Support"
            description="How to use the Safer Handling enquiry system — Live/Archive enquiries, quote recipients, Training Matrix, vouchers, Attendees API, and integrations."
        />
    </x-slot>

    <style>
        .help-page {
            --help-gap: 1.25rem;
            --help-pad: 1.25rem;
            --help-radius: 14px;
        }
        .help-layout {
            display: flex;
            flex-direction: column;
            gap: var(--help-gap);
            align-items: stretch;
        }
        .help-sidebar { width: 100%; }
        .help-sidebar-panel {
            padding: var(--help-pad) !important;
        }
        .help-sidebar-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0 var(--help-gap);
        }
        .help-main {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: var(--help-gap);
        }
        .help-section.brand-panel {
            padding: var(--help-pad) !important;
        }
        .help-stack {
            display: flex;
            flex-direction: column;
            gap: var(--help-gap);
        }
        .help-stack > :first-child { margin-top: 0; }
        .help-stack > :last-child { margin-bottom: 0; }
        .help-stack h2,
        .help-stack h3,
        .help-stack p,
        .help-stack ul {
            margin: 0;
        }
        .help-stack ul {
            padding-left: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .help-section-head {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: var(--help-gap);
        }
        .help-section-head > div {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 0;
        }
        .help-nav {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .help-nav a {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            border-radius: 10px;
            padding: 0.55rem 0.7rem;
            font-size: 0.875rem;
            color: #2e5d84;
            text-decoration: none;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .help-nav a:hover {
            background: #eef7ff;
            color: #0255a4;
        }
        .help-nav a.is-section {
            margin-top: 0.5rem;
            font-weight: 700;
            color: #16324a;
        }
        .help-nav a.is-section:first-child {
            margin-top: 0;
        }
        .help-nav .help-nav-dot {
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #9fc8ed;
            flex-shrink: 0;
        }
        .help-hero {
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            border: 1px solid #cfe4f8;
            background:
                radial-gradient(circle at 12% 20%, rgba(0, 138, 252, 0.18), transparent 42%),
                radial-gradient(circle at 88% 10%, rgba(186, 218, 85, 0.28), transparent 36%),
                linear-gradient(135deg, #0255a4 0%, #0478d8 55%, #0b6bb8 100%);
            color: #fff;
            padding: var(--help-pad);
            box-shadow: 0 12px 28px rgba(2, 85, 164, 0.2);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .help-hero img {
            width: 180px;
            max-width: 46%;
            display: block;
            margin: 0;
            background: #fff;
            border-radius: 10px;
            padding: 0.55rem 0.7rem;
        }
        .help-hero h2 {
            margin: 0;
            font-size: 1.45rem;
            line-height: 1.2;
            font-weight: 800;
        }
        .help-hero p {
            margin: 0;
            max-width: 42rem;
            color: rgba(255, 255, 255, 0.92);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        .help-card-grid,
        .help-step-grid {
            display: grid;
            gap: var(--help-gap);
            grid-template-columns: 1fr;
            margin: 0;
        }
        .help-feature-card {
            display: flex;
            gap: 0.85rem;
            align-items: flex-start;
            border: 1px solid #d7e9f8;
            border-radius: var(--help-radius);
            background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
            padding: var(--help-pad);
            height: 100%;
            box-sizing: border-box;
        }
        .help-feature-card .help-icon {
            flex-shrink: 0;
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f4ff;
            color: #0255a4;
        }
        .help-feature-card h4,
        .help-step-card h4 {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: #16324a;
        }
        .help-feature-card p,
        .help-step-card p {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            color: #2e5d84;
            line-height: 1.45;
        }
        .help-step-card {
            position: relative;
            border: 1px solid #d7e9f8;
            border-radius: var(--help-radius);
            background: #fff;
            padding: var(--help-pad);
            padding-left: calc(var(--help-pad) + 0.15rem);
            overflow: hidden;
            height: 100%;
            box-sizing: border-box;
        }
        .help-step-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(180deg, #008afc, #badA55);
        }
        .help-step-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 999px;
            background: #e8f4ff;
            color: #0255a4;
            font-size: 0.78rem;
            font-weight: 800;
            margin: 0 0 0.55rem;
        }
        .help-illus {
            width: 100%;
            border-radius: var(--help-radius);
            border: 1px solid #d7e9f8;
            background: linear-gradient(160deg, #f4faff 0%, #eef7ff 100%);
            padding: var(--help-pad);
            margin: 0;
            box-sizing: border-box;
        }
        .help-illus svg {
            width: 100%;
            height: auto;
            display: block;
        }
        .help-video-wrap {
            margin: 0;
            border-radius: var(--help-radius);
            border: 1px solid #d7e9f8;
            background: #0b1f33;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(2, 85, 164, 0.12);
        }
        .help-video-wrap video {
            display: block;
            width: 100%;
            height: auto;
            max-height: 70vh;
            background: #0b1f33;
        }
        .help-logo-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin: 0;
        }
        .help-logo-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid #d7e9f8;
            border-radius: 999px;
            background: #fff;
            padding: 0.5rem 0.85rem;
            font-size: 0.82rem;
            font-weight: 600;
            color: #16324a;
        }
        .help-logo-chip img {
            width: 1.15rem;
            height: 1.15rem;
            object-fit: contain;
        }
        .help-table-wrap {
            margin: 0;
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #d8e8f8;
        }
        @media (min-width: 640px) {
            .help-card-grid { grid-template-columns: 1fr 1fr; }
            .help-step-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (min-width: 1024px) {
            .help-layout {
                flex-direction: row;
                align-items: flex-start;
                gap: var(--help-gap);
            }
            .help-sidebar {
                width: 250px;
                flex-shrink: 0;
                position: sticky;
                top: var(--help-gap);
            }
            .help-step-grid { grid-template-columns: 1fr 1fr 1fr; }
        }
    </style>

    <div class="admin-shell help-page">
        <div class="help-layout">
            <aside class="help-sidebar">
                <div class="brand-panel help-sidebar-panel">
                    <div class="help-sidebar-title">
                        <span class="inline-flex h-8 w-8 items-center justify-center rounded-[10px] bg-[#e8f4ff] text-brand-header">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a.75.75 0 11-1.061-1.061 3 3 0 112.871 5.026v.345a.75.75 0 01-1.5 0v-.5c0-.72.56-1.164 1.128-1.37a1.5 1.5 0 10-1.438-2.44zM10 15a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Guide</p>
                            <p class="text-sm font-semibold text-sh-text">On this page</p>
                        </div>
                    </div>
                    <nav class="help-nav">
                        <a class="is-section" href="#using-the-system"><span class="help-nav-dot"></span>How to use the system</a>
                        <a href="#whats-new"><span class="help-nav-dot"></span>What's new</a>
                        <a href="#walkthrough"><span class="help-nav-dot"></span>Video walkthrough</a>
                        <a href="#enquiry-journey"><span class="help-nav-dot"></span>Enquiry journey</a>
                        <a href="#form-paths"><span class="help-nav-dot"></span>Form paths (It's for me)</a>
                        <a href="#second-date"><span class="help-nav-dot"></span>Day 1 &amp; Day 2 courses</a>
                        <a href="#enquiries-list"><span class="help-nav-dot"></span>Live &amp; Archive</a>
                        <a href="#quote-recipient"><span class="help-nav-dot"></span>Quote / invoice recipient</a>
                        <a href="#enquiry-page"><span class="help-nav-dot"></span>Working an enquiry</a>
                        <a href="#delete-enquiry"><span class="help-nav-dot"></span>Delete an enquiry</a>
                        <a href="#activity-log"><span class="help-nav-dot"></span>Activity log</a>
                        <a href="#emails"><span class="help-nav-dot"></span>Emails & read status</a>
                        <a href="#booking"><span class="help-nav-dot"></span>Booking & terms</a>
                        <a href="#integrations"><span class="help-nav-dot"></span>Integrations</a>
                        <a href="#attendees-api"><span class="help-nav-dot"></span>Attendees API</a>
                        <a href="#forge-delivery-stages"><span class="help-nav-dot"></span>Forge delivery stages</a>
                        <a class="is-section" href="#vouchers"><span class="help-nav-dot"></span>Vouchers</a>
                        <a class="is-section" href="#training-matrix"><span class="help-nav-dot"></span>Training Matrix</a>
                        <a href="#adding-to-form"><span class="help-nav-dot"></span>Adding to the form / skill matrix</a>
                        <a href="#matrix-fields"><span class="help-nav-dot"></span>Matrix fields</a>
                        <a href="#matrix-pricing"><span class="help-nav-dot"></span>Pricing kinds</a>
                        <a class="is-section" href="#support"><span class="help-nav-dot"></span>Support</a>
                    </nav>
                </div>
            </aside>

            <div class="help-main">
                <section id="using-the-system" class="help-stack scroll-mt-6">
                    <div class="help-hero">
                        <img src="{{ asset('assets/safer-handling-logo.png') }}" alt="Safer Handling" />
                        <h2>How to use the system</h2>
                        <p>
                            Manage enquiries from the public form — live quotes, emails, Accept Quote / venue details,
                            and syncs to Monday, Xero, Forge, and Kajabi.
                        </p>
                    </div>

                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" /></svg>
                            </div>
                            <div>
                                <h4>Enquiries</h4>
                                <p>Live (last 30 days) and Archive tabs, search, status filters, and open each customer journey.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" /></svg>
                            </div>
                            <div>
                                <h4>Training Matrix</h4>
                                <p>Controls organisation and It’s for me course options, live quote pricing, second-date courses, and whether Kajabi auto-invite runs for each combination.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#fff7e8;color:#9a6700;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>
                            </div>
                            <div>
                                <h4>Vouchers</h4>
                                <p>Create fixed £ discount codes. Staff apply them on enquiries; customers can enter a code on the live quote form.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.17 48.17 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" /></svg>
                            </div>
                            <div>
                                <h4>Feedback</h4>
                                <p>Review issues from the public feedback form and mark them resolved.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </div>
                            <div>
                                <h4>Integration settings</h4>
                                <p>Monday, Brevo, Xero, Kajabi, Forge, Enquiry API token &amp; candidate price, postcodes, and email open tracking.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="whats-new" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-header">What's new</h2>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">Recent features added to the enquiry form and admin tools.</p>
                    </div>
                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#eef7ff;color:#0255a4;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
                            </div>
                            <div>
                                <h4>It's for me — book a course</h4>
                                <p>Individuals pick Sector → Course → Format → Course Style → Attendees from the Training Matrix (same live quote as organisations). Switch paths with Change selection / My organisation instead. See <a href="#form-paths" class="link-brand">Form paths</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>
                            </div>
                            <div>
                                <h4>Day 1 &amp; Day 2 (second date)</h4>
                                <p>Tick <strong class="text-sh-text">Enable second date</strong> on a matrix row to collect Day 1 and Day 2 dates (Day 2 address is optional). Syncs to Monday, quote emails, and Forge. See <a href="#second-date" class="link-brand">Day 1 &amp; Day 2</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#fff7e8;color:#9a6700;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" /></svg>
                            </div>
                            <div>
                                <h4>Vouchers</h4>
                                <p>Fixed £ discount codes with optional max redemptions and date windows. The discounted net and voucher code appear on the Xero quote and invoice line description. See <a href="#vouchers" class="link-brand">Vouchers</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            </div>
                            <div>
                                <h4>Max capacity description</h4>
                                <p>Optional note on a matrix row explaining why attendees cannot exceed the max — shown on the form when someone tries to go over.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#eef7ff;color:#0255a4;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                            </div>
                            <div>
                                <h4>Icon actions in admin lists</h4>
                                <p>Enquiries, Training Matrix, Vouchers, and Users use icon-only actions (eye / pencil / bin) instead of text links, with tooltips on hover.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                            </div>
                            <div>
                                <h4>Bulk delete enquiries</h4>
                                <p>On the Enquiries list, select multiple rows and use Delete selected. Monday items are left unchanged; Forge bookings are cancelled first when present. See <a href="#delete-enquiry" class="link-brand">Delete an enquiry</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#eef7ff;color:#0255a4;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75L22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3l-4.5 16.5" /></svg>
                            </div>
                            <div>
                                <h4>Attendees API</h4>
                                <p>External systems (e.g. Forge) can add attendees to an enquiry, log them on the timeline, and raise an extra Xero invoice at £{{ $additionalAttendeeUnitPrice }} each (ex VAT). Price is editable in Integration settings. See <a href="#attendees-api" class="link-brand">Attendees API</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#eef7ff;color:#0255a4;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                            </div>
                            <div>
                                <h4>Live &amp; Archive</h4>
                                <p>Enquiries from the last 30 days stay on <strong class="text-sh-text">Live</strong>; older ones move to <strong class="text-sh-text">Archive</strong>. Tabs switch without a full page reload. See <a href="#enquiries-list" class="link-brand">Live &amp; Archive</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#fff7e8;color:#9a6700;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                            </div>
                            <div>
                                <h4>Quote / invoice recipient</h4>
                                <p>Optional accounts name/email on the public form. Quote emails go to the form filler and, when set, accounts too; Xero uses accounts for billing. Highlighted on the enquiry page. See <a href="#quote-recipient" class="link-brand">Quote / invoice recipient</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" /></svg>
                            </div>
                            <div>
                                <h4>Edit Your Quote in email</h4>
                                <p>The training quote email includes an <strong class="text-sh-text">Edit Your Quote</strong> button linking back to the customer’s enquiry form, alongside Accept Quote. See <a href="#emails" class="link-brand">Emails</a>.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                            </div>
                            <div>
                                <h4>Edit Enquiry Email off by default</h4>
                                <p>The “Continue your Safer Handling enquiry” email is <strong class="text-sh-text">disabled</strong> by default. Turn it back on under Integration settings if needed. Customers can still edit via the quote email button when a quote has been sent.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="walkthrough" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Video walkthrough</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            Watch this overview of the admin portal — enquiries, quotes, booking, and key settings.
                        </p>
                    </div>
                    <div class="help-video-wrap">
                        <video
                            controls
                            preload="metadata"
                            playsinline
                            controlslist="nodownload"
                            title="Safer Handling admin portal walkthrough"
                        >
                            <source src="{{ asset('assets/help/admin-walkthrough.mp4') }}" type="video/mp4">
                            Your browser does not support the video player.
                        </video>
                    </div>
                </section>

                <section id="enquiry-journey" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Enquiry journey</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">From first form visit through to Quote Won.</p>
                    </div>

                    <div class="help-illus" aria-hidden="true">
                        <svg viewBox="0 0 720 140" xmlns="http://www.w3.org/2000/svg" role="img">
                            <defs>
                                <linearGradient id="helpFlow" x1="0" y1="0" x2="1" y2="0">
                                    <stop offset="0%" stop-color="#008afc"/>
                                    <stop offset="100%" stop-color="#badA55"/>
                                </linearGradient>
                            </defs>
                            <rect x="20" y="62" width="680" height="6" rx="3" fill="url(#helpFlow)" opacity="0.35"/>
                            <g font-family="Segoe UI, Arial, sans-serif" font-size="12" font-weight="700" text-anchor="middle">
                                <circle cx="70" cy="65" r="22" fill="#0255a4"/><text x="70" y="70" fill="#fff">1</text>
                                <text x="70" y="110" fill="#16324a">Start form</text>
                                <circle cx="210" cy="65" r="22" fill="#0478d8"/><text x="210" y="70" fill="#fff">2</text>
                                <text x="210" y="110" fill="#16324a">Live quote</text>
                                <circle cx="350" cy="65" r="22" fill="#008afc"/><text x="350" y="70" fill="#fff">3</text>
                                <text x="350" y="110" fill="#16324a">Submit</text>
                                <circle cx="490" cy="65" r="22" fill="#3f8f2a"/><text x="490" y="70" fill="#fff">4</text>
                                <text x="490" y="110" fill="#16324a">Accept terms</text>
                                <circle cx="630" cy="65" r="22" fill="#2f6d1a"/><text x="630" y="70" fill="#fff">5</text>
                                <text x="630" y="110" fill="#16324a">Quote won</text>
                            </g>
                        </svg>
                    </div>

                    <div class="help-step-grid">
                        <div class="help-step-card">
                            <div class="help-step-num">1</div>
                            <h4>Customer starts the form</h4>
                            <p>Name, email, phone, optional quote/invoice recipient, and enquiry type. The automatic Edit Enquiry Email is off by default.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">2</div>
                            <h4>Training & live quote</h4>
                            <p>Organisation and It’s for me (book a course) paths use the Training Matrix: sector → course → format → style → attendees → date (or Day 1 + Day 2 when Enable second date is on). If only one format or course style is available, the form shows it as a prefilled label instead of a dropdown.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">3</div>
                            <h4>Submit</h4>
                            <p>Enquiry is saved; Monday may update; quote email (with Edit Your Quote + Accept Quote) and office lead notification can send.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">4</div>
                            <h4>Quote accepted</h4>
                            <p>Customer completes Accept Quote / venue details — venue, delegates, invoice info, and terms.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">5</div>
                            <h4>Quote won</h4>
                            <p>After the invoice is emailed, status moves to Quote Won. Monday may progress, and Kajabi invites students when Enable Kajabi is on for that matrix row.</p>
                        </div>
                    </div>

                    <div class="help-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-medium text-sh-text">In progress</td>
                                    <td class="text-sh-mid">Started or resumed; not fully submitted yet.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Submitted / Contacted</td>
                                    <td class="text-sh-mid">Form submitted; team contact or auto quote path underway.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Quote Sent</td>
                                    <td class="text-sh-mid">Quote email / Xero quote has gone to the customer.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Quote Accepted</td>
                                    <td class="text-sh-mid">Booking details completed and terms accepted.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Quote Won</td>
                                    <td class="text-sh-mid">Invoice emailed / won path completed. Shown with a tick in the grid.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="form-paths" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Form paths — It's for me &amp; My organisation</h3>
                            <p class="text-sm text-sh-mid">How customers choose a training path on the public enquiry form.</p>
                        </div>
                    </div>

                    <div class="help-step-grid">
                        <div class="help-step-card">
                            <div class="help-step-num">1</div>
                            <h4>Audience</h4>
                            <p>Customer chooses <strong class="text-sh-text">It's for me</strong> or <strong class="text-sh-text">My organisation</strong>.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">2</div>
                            <h4>It's for me options</h4>
                            <p>
                                <strong class="text-sh-text">Book a training course</strong> — Training Matrix selectors (sector, course, format, style, attendees) plus address and preferred date.<br>
                                <strong class="text-sh-text">Online course</strong> — redirects to the Kajabi store.<br>
                                <strong class="text-sh-text">Become a trainer</strong> — trainer course list and quote / book flow.
                            </p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">3</div>
                            <h4>My organisation</h4>
                            <p>Same matrix selectors as “Book a training course”, plus optional company name. Live quote and voucher field apply the same way.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">4</div>
                            <h4>Change selection</h4>
                            <p>
                                After a path is chosen, the form shows a summary with <strong class="text-sh-text">Change selection</strong> (keeps the previous choice preselected).
                                On the organisation path, <strong class="text-sh-text">It's for me instead</strong> switches back and restores the last personal option.
                                On the It's for me path, <strong class="text-sh-text">My organisation instead</strong> switches to the organisation path.
                            </p>
                        </div>
                    </div>

                    <ul class="list-disc text-sm text-sh-mid">
                        <li>It's for me → book a course does not ask for company/organisation.</li>
                        <li>Monday notes / It's for me column are updated when an individual books via the matrix.</li>
                        <li>Quotes, emails, and Forge sync use the same matrix course value as organisation bookings.</li>
                    </ul>
                </section>

                <section id="second-date" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Day 1 &amp; Day 2 courses</h3>
                            <p class="text-sm text-sh-mid">Two-day matrix courses collect a preferred date and venue for each day (Enable second date).</p>
                        </div>
                        <a href="{{ route('admin.training-matrix.index') }}" class="btn-brand-outline text-xs">Open Training Matrix</a>
                    </div>

                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h4>Turn it on</h4>
                                <p>Edit a Training Matrix row and tick <strong class="text-sh-text">Enable second date</strong>. Only that combination shows Day 1 / Day 2 on the form.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                            </div>
                            <div>
                                <h4>On the form</h4>
                                <p>
                                    Customers enter <strong class="text-sh-text">Day 1</strong> date + address and <strong class="text-sh-text">Day 2</strong> date (Day 2 address is optional; leave blank if the same venue).
                                    Applies to both My organisation and It’s for me → book a course.
                                    “Not sure yet” clears both dates.
                                </p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                            </div>
                            <div>
                                <h4>Emails &amp; admin</h4>
                                <p>Quote confirmation and office lead emails show Day 1 / Day 2. The enquiry page lists both dates and addresses clearly labelled.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" /></svg>
                            </div>
                            <div>
                                <h4>Monday &amp; Forge</h4>
                                <p>
                                    Monday notes include Day 1 / Day 2 (and a Day 2 date column if the board has one).
                                    Forge receives two objects in <code class="rounded bg-sh-surface px-1 text-xs">session_dates</code> (one per day with date and address).
                                </p>
                            </div>
                        </div>
                    </div>

                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Leave <strong class="text-sh-text">Enable second date</strong> unticked for single-day courses — the form keeps the normal one-date / one-address fields.</li>
                        <li>Day 2 address is <strong class="text-sh-text">optional</strong> — leave it blank if Day 2 is at the same venue as Day 1, or enter a different venue when needed.</li>
                        <li>When testing, pick a matrix combination with second date on, confirm both date blocks appear, then check the enquiry admin page and Monday notes after submit.</li>
                    </ul>
                </section>

                <section id="enquiries-list" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Live &amp; Archive</h3>
                            <p class="text-sm text-sh-mid">How enquiries are organised on the Enquiries list.</p>
                        </div>
                        <a href="{{ route('admin.enquiries.index') }}" class="btn-brand-outline text-xs">Open Enquiries</a>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li><strong class="text-sh-text">Live</strong> — enquiries created in the last 30 days.</li>
                        <li><strong class="text-sh-text">Archive</strong> — enquiries older than 30 days (they move here automatically).</li>
                        <li>Switching Live / Archive (and status chips / pagination) updates the table in place — no full page reload.</li>
                        <li>Search, status filters, and rows-per-page still apply within the selected tab.</li>
                        <li>Counts on each tab show how many enquiries sit in Live vs Archive.</li>
                    </ul>
                </section>

                <section id="quote-recipient" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Quote / invoice recipient</h3>
                            <p class="text-sm text-sh-mid">Optional accounts details so quotes can be copied to invoice contacts as well as the person who filled the form.</p>
                        </div>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>On the public form (after phone number), customers can enter an optional <strong class="text-sh-text">Name</strong> and <strong class="text-sh-text">Email</strong> under Quote / invoice recipient.</li>
                        <li>The quote email always goes to the person who filled the form. When an accounts email is set (and different), they are included on the same quote email.</li>
                        <li>Xero quotes and invoices still use the accounts recipient when set.</li>
                        <li>The main enquiry contact email is still used for Edit Enquiry / form resume links.</li>
                        <li>On the admin enquiry page the recipient is highlighted (header chip and a dedicated card under Enquiry details).</li>
                        <li>The Enquiries list shows an <strong class="text-sh-text">Invoice sent to</strong> badge when a custom recipient is set — hover the eye to see name and email.</li>
                    </ul>
                </section>

                <section id="enquiry-page" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Working an enquiry</h3>
                            <p class="text-sm text-sh-mid">Open any enquiry from the Enquiries list to manage the full journey.</p>
                        </div>
                        <a href="{{ route('admin.enquiries.index') }}" class="btn-brand-outline text-xs">Open Enquiries</a>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Use the journey timeline to see successes/failures and retry failed steps — including additional attendees invoices from the API.</li>
                        <li><strong class="text-sh-text">Create new enquiry</strong> opens a blank public form so staff can fill it in on behalf of a customer (opens in a new tab; timeline notes it was started by admin).</li>
                        <li><strong class="text-sh-text">Edit form</strong> opens the customer enquiry form with saved details.</li>
                        <li><strong class="text-sh-text">View / Edit booking</strong> lets staff complete Accept Quote details for the client.</li>
                        <li>Preferred date can be “Not sure yet”; staff can set it later in booking edit.</li>
                        <li>Matrix rows with <strong class="text-sh-text">Enable second date</strong> show Day 1 + Day 2 on the enquiry; Forge receives both as <code class="rounded bg-sh-surface px-1 text-xs">session_dates</code>. See <a href="#second-date" class="link-brand">Day 1 &amp; Day 2</a>.</li>
                        <li>Apply or remove a <strong class="text-sh-text">voucher</strong> on the enquiry page (locked once Quote Won). See <a href="#vouchers" class="link-brand">Vouchers</a>.</li>
                        <li>Check <a href="#quote-recipient" class="link-brand">Quote / invoice recipient</a> when Xero should bill a different person.</li>
                        <li>Once Quote Won, the customer enquiry form is locked from further online edits (including vouchers).</li>
                        <li><strong class="text-sh-text">Delete enquiry</strong> removes the record from this admin system — see <a href="#delete-enquiry" class="link-brand">Delete an enquiry</a>.</li>
                    </ul>
                </section>

                <section id="delete-enquiry" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Delete an enquiry</h3>
                            <p class="text-sm text-sh-mid">Remove a test or unwanted enquiry from the admin system without changing Monday.</p>
                        </div>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>On the enquiry page, use <strong class="text-sh-text">Delete enquiry</strong> in the header.</li>
                        <li>On the Enquiries list, tick one or more rows and use <strong class="text-sh-text">Delete selected</strong>. A confirmation popup asks if you are sure before anything is removed.</li>
                        <li>You will be asked: <em>Are you sure you want to delete this enquiry from the system?</em> (or the selected count for bulk delete).</li>
                        <li><strong class="text-sh-text">Monday is not deleted</strong> — the Monday board item stays as it is.</li>
                        <li>If the enquiry has an active Forge booking, that booking is <strong class="text-sh-text">cancelled in Forge</strong> first. If Forge cancel fails, that enquiry is not deleted.</li>
                        <li>The enquiry, its journey timeline, and related booking uploads are removed from this system.</li>
                        <li>Xero quotes/invoices and Kajabi enrollments are <strong class="text-sh-text">not</strong> deleted remotely.</li>
                        <li>Every confirmed delete is written to the <a href="{{ route('admin.activity.index') }}" class="link-brand">Activity</a> audit log (who deleted which enquiry, and whether Forge was cancelled).</li>
                    </ul>
                </section>

                <section id="activity-log" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Activity log</h3>
                            <p class="text-sm text-sh-mid">Admin audit trail under your profile menu — who signed in and what they changed.</p>
                        </div>
                        <a href="{{ route('admin.activity.index') }}" class="btn-brand-outline text-xs">Open Activity</a>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Shows clear action labels (Created, Updated, Deleted, Cancelled, Synced, and so on) plus the area touched (Enquiry, Training Matrix, Users, Settings).</li>
                        <li>Training Matrix create / edit / delete logs include sector, course, format, course style, Kajabi setting, and which fields changed on update.</li>
                        <li>Enquiry deletes record that Monday was left unchanged and whether a Forge booking was cancelled.</li>
                        <li>Filter by action, area, or user, or search by name, course, enquiry id, or IP.</li>
                    </ul>
                </section>

                <section id="emails" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Emails & read status</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">Customer emails sent through Brevo can show as Sent or Read when open tracking is configured.</p>
                    </div>
                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                            </div>
                            <div>
                                <h4>Edit Enquiry Email</h4>
                                <p>
                                    “Continue your Safer Handling enquiry” — optional link to resume the saved form.
                                    Currently <strong class="text-sh-text">off by default</strong>; enable under Integration settings → Send Edit Enquiry Emails after the full form is submitted.
                                </p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                            <div>
                                <h4>Quote &amp; invoice emails</h4>
                                <p>
                                    Quote confirmation (often with Xero PDF) includes <strong class="text-sh-text">Edit Your Quote</strong> (back to the enquiry form) and <strong class="text-sh-text">Accept Quote and add venue details</strong>.
                                    Invoice emails attach the Xero PDF.
                                </p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-sh-mid">
                        <strong class="text-sh-text">Read</strong> appears after Brevo reports the customer opened the email. In Integration settings use <strong>Register in Brevo</strong>, or manually add the webhook under Brevo → account name → Integrations → Webhooks (Transactional email → Opened).
                    </p>
                </section>

                <section id="booking" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Booking & terms</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            The Accept Quote form collects booker details, preferred date, venue address, delegates
                            (student names/emails — pre-filled with the booker, who can remove themselves),
                            invoice details, venue requirements, and Safer Handling terms.
                        </p>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Within 2 days of a set preferred date, customers cannot change the date online.</li>
                        <li>Staff can always update booking details from the enquiry page.</li>
                        <li>Saving booking syncs to Monday (Quote Accepted), can create a draft Xero invoice, and may push Forge as <code>confirmed_book</code>.</li>
                    </ul>
                </section>

                <section id="integrations" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Integrations</h3>
                            <p class="text-sm text-sh-mid">Configure under Integration settings in your profile menu.</p>
                        </div>
                        <a href="{{ route('admin.settings.edit') }}" class="btn-brand-outline text-xs">Open settings</a>
                    </div>
                    <div class="help-logo-row">
                        <span class="help-logo-chip">
                            <img src="{{ asset('assets/monday-logo.svg') }}" alt="" />
                            monday.com
                        </span>
                        <span class="help-logo-chip">
                            <img src="{{ asset('assets/xero-logo.svg') }}" alt="" onerror="this.style.display='none'" />
                            Xero
                        </span>
                        <span class="help-logo-chip">
                            <img src="{{ asset('assets/kajabi-logo.png') }}" alt="" />
                            Kajabi
                        </span>
                        <span class="help-logo-chip">Brevo email</span>
                        <span class="help-logo-chip">Forge CRM</span>
                    </div>
                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon"><x-monday-badge compact class="!h-5 !w-5" /></div>
                            <div>
                                <h4>monday.com</h4>
                                <p>Creates/updates items and moves groups: Being Contacted, Quote Sent, Quote Accepted, Quote Won, Client Booking Form.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon"><x-xero-badge compact class="!h-5 !w-5" /></div>
                            <div>
                                <h4>Xero</h4>
                                <p>Quotes and draft invoices (including optional quote/invoice recipient); email the invoice from the enquiry page. Additional attendees via API create a separate draft invoice. Applied vouchers appear in the quote/invoice line description.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon"><x-kajabi-badge compact class="!h-5 !w-5" /></div>
                            <div>
                                <h4>Kajabi</h4>
                                <p>Enrolls each Accept Quote student on the Legal Briefing course after Quote Won, when Enable Kajabi is on for that Training Matrix row. If off, the enquiry shows “Kajabi is disabled for this course combination” and no invite is sent.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                            </div>
                            <div>
                                <h4>Brevo</h4>
                                <p>Sends customer and office emails. Webhook secret enables Read status on opens.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;font-weight:800;font-size:0.7rem;">Fg</div>
                            <div>
                                <h4>Forge CRM</h4>
                                <p>Booking snapshots with delivery stages — see Forge delivery stages below.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="attendees-api" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Attendees API</h3>
                            <p class="text-sm text-sh-mid">
                                Let an external system add candidates/attendees to an enquiry. Each call updates the count, writes a timeline entry
                                (for example <strong class="text-sh-text">2 new attendees from Forge</strong>), creates an additional Xero draft invoice
                                at <strong class="text-sh-text">£{{ $additionalAttendeeUnitPrice }}</strong> per candidate (ex VAT — editable in Integration settings), and emails the PDF by default.
                            </p>
                        </div>
                        <a href="{{ route('admin.settings.edit') }}#enquiry_api_token" class="btn-brand-outline text-xs">API settings</a>
                    </div>

                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#eef7ff;color:#0255a4;font-weight:800;font-size:0.65rem;">POST</div>
                            <div>
                                <h4>Endpoint</h4>
                                <p class="font-mono text-xs break-all text-sh-text">{{ $attendeesApiUrl }}</p>
                                <p class="mt-2 text-sm text-sh-mid">Replace <code class="rounded bg-sh-surface px-1 text-xs">{id}</code> with the enquiry ID from the admin enquiry page URL.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:{{ $attendeesApiTokenConfigured ? '#f3f9e8' : '#fff7e8' }};color:{{ $attendeesApiTokenConfigured ? '#3f6d1c' : '#9a6700' }};">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                            </div>
                            <div>
                                <h4>Authentication</h4>
                                <p>
                                    @if ($attendeesApiTokenConfigured)
                                        API token is configured in Integration settings.
                                    @else
                                        Set an <strong class="text-sh-text">Enquiry API</strong> token under Integration settings before calling the endpoint (requests are rejected until set).
                                    @endif
                                </p>
                                <p class="mt-2 text-sm text-sh-mid">Send the token as <code class="rounded bg-sh-surface px-1 text-xs">Authorization: Bearer …</code>, <code class="rounded bg-sh-surface px-1 text-xs">X-Api-Token</code>, or <code class="rounded bg-sh-surface px-1 text-xs">?token=</code>.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-brand-header">Request body (JSON)</h4>
                        <div class="help-table-wrap" style="margin-top:0.75rem;">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Required</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code class="rounded bg-sh-surface px-1 text-xs">count</code></td>
                                        <td>Yes</td>
                                        <td>Number of new attendees to add (integer, 1–500).</td>
                                    </tr>
                                    <tr>
                                        <td><code class="rounded bg-sh-surface px-1 text-xs">source</code></td>
                                        <td>No</td>
                                        <td>System name shown on the timeline (default <code class="rounded bg-sh-surface px-1 text-xs">API</code>). Example: <code class="rounded bg-sh-surface px-1 text-xs">Forge</code>.</td>
                                    </tr>
                                    <tr>
                                        <td><code class="rounded bg-sh-surface px-1 text-xs">email_invoice</code></td>
                                        <td>No</td>
                                        <td>Email the new invoice PDF via Brevo (default <code class="rounded bg-sh-surface px-1 text-xs">true</code>).</td>
                                    </tr>
                                    <tr>
                                        <td><code class="rounded bg-sh-surface px-1 text-xs">Idempotency-Key</code> header</td>
                                        <td>No</td>
                                        <td>Optional unique key per add. Retries with the same key return the first result and do not add attendees or create another invoice.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-brand-header">Example</h4>
                        <pre class="mt-3 overflow-x-auto rounded-[12px] border border-[#d8e8f8] bg-[#0b1f33] p-4 text-xs leading-relaxed text-[#e8f4ff]"><code>curl -X POST "{{ str_replace('{id}', '123', $attendeesApiUrl) }}" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: forge-123-attendees-2" \
  -d '{"count": 2, "source": "Forge", "email_invoice": true}'</code></pre>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-brand-header">What happens</h4>
                        <ul class="mt-2 list-disc space-y-2 pl-5 text-sm text-sh-mid">
                            <li>Quote attendees stay as selected on the form; <strong class="text-sh-text">Total Attendees</strong> increases by <code class="rounded bg-sh-surface px-1 text-xs">count</code>. The enquiry page shows each API add and when it happened.</li>
                            <li>Timeline shows e.g. <strong class="text-sh-text">2 new attendees from Forge</strong>.</li>
                            <li>A separate Xero draft invoice is created (does not replace the main course invoice) charging £{{ $additionalAttendeeUnitPrice }} × attendees (ex VAT + VAT).</li>
                            <li>Unless <code class="rounded bg-sh-surface px-1 text-xs">email_invoice</code> is false, the invoice PDF is emailed to the customer.</li>
                            <li>The enquiry must already have a Xero contact and an invoice/booking address (same requirements as the main invoice).</li>
                            <li>Duplicate protection: same <code class="rounded bg-sh-surface px-1 text-xs">count</code> + <code class="rounded bg-sh-surface px-1 text-xs">source</code> within 90 seconds returns <code class="rounded bg-sh-surface px-1 text-xs">409</code> without adding again. Prefer an <code class="rounded bg-sh-surface px-1 text-xs">Idempotency-Key</code> for retries.</li>
                        </ul>
                    </div>

                    <p class="text-sm text-sh-mid">
                        Unit price is configurable under
                        <a href="{{ route('admin.settings.edit') }}" class="link-brand">Integration settings → Enquiry API — add attendees</a>
                        as <strong class="text-sh-text">Additional candidate / attendee price</strong>
                        (<code class="rounded bg-sh-surface px-1 text-xs">additional_attendee_unit_price</code>
                        (currently £{{ $additionalAttendeeUnitPrice }}).
                        Full request docs are also shown on that settings page.
                    </p>
                </section>

                <section id="forge-delivery-stages" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Forge delivery stages</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            The Forge booking webhook sends <code class="rounded bg-sh-surface px-1 text-xs">booking.booking_status</code>
                            and <code class="rounded bg-sh-surface px-1 text-xs">booking.delivery_stage</code> with the same snake_case value.
                            These are Booking Delivery Stages (not invoice/accounting stages). A booking can stay in
                            <strong class="text-sh-text">confirmed_book</strong> for months while delivery is planned.
                        </p>
                    </div>

                    <div class="help-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Stage</th>
                                    <th>Meaning</th>
                                    <th>When this form sends it</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-medium text-sh-text whitespace-nowrap"><code>provisional_book</code></td>
                                    <td class="text-sh-mid">
                                        Reserve / populate with limited data before the customer or admin has formally
                                        completed Accept Quote. Trainers should treat this as not formally agreed —
                                        often incomplete info, not a confirmed delivery brief.
                                        (In Monday naming you may also think of this as <em>prov_booking_delivery</em>.)
                                    </td>
                                    <td class="text-sh-mid">
                                        When the customer completes the public enquiry form (organisation or trainer
                                        quote flow), <strong>before</strong> Accept Quote / terms. Creates the first
                                        Forge booking snapshot as a reserve.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text whitespace-nowrap"><code>confirmed_book</code></td>
                                    <td class="text-sh-mid">
                                        Formal booking: Accept Quote / venue details and terms completed.
                                        This is the main delivery stage after the old “Accepted” webhook status.
                                        (In Monday naming you may also think of this as <em>booking_delivery</em>.)
                                    </td>
                                    <td class="text-sh-mid">
                                        Customer or staff completes Accept Quote (venue + terms). Also kept when a
                                        Xero invoice is emailed or Kajabi enrolls — those are <em>not</em> separate delivery stages.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text whitespace-nowrap"><code>on_hold</code></td>
                                    <td class="text-sh-mid">
                                        Do not progress until moved to <code>confirmed_book</code> or <code>cancelled</code>.
                                        Important visibility for Admin, Trainers, and Session Planning.
                                    </td>
                                    <td class="text-sh-mid">
                                        Explicit stage update on an enquiry already synced to Forge (not auto from the form today).
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text whitespace-nowrap"><code>to_rearrange</code></td>
                                    <td class="text-sh-mid">
                                        Similar to on hold, but clearly for rearranging dates. Session date/times are
                                        cleared in the webhook payload so it is obvious which sessions need new dates.
                                    </td>
                                    <td class="text-sh-mid">
                                        Explicit stage update; webhook sends empty <code>session_dates</code>.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text whitespace-nowrap"><code>cancelled</code></td>
                                    <td class="text-sh-mid">
                                        Not proceeding. If the booking had reached provisional or confirmed, cancel
                                        anything still active. Session dates are cleared in the payload.
                                    </td>
                                    <td class="text-sh-mid">
                                        Explicit stage update on an enquiry already synced to Forge.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div>
                        <h4 class="text-sm font-semibold text-brand-header">Scenario → webhook status</h4>
                        <ul class="mt-2 list-disc text-sm text-sh-mid">
                            <li><strong class="text-sh-text">Enquiry form completed</strong> (organisation / trainer quote flow, before Accept Quote) → <code>provisional_book</code> (first create)</li>
                            <li><strong class="text-sh-text">Accept Quote / admin booking save</strong> (terms + venue) → <code>confirmed_book</code> (create or edit)</li>
                            <li><strong class="text-sh-text">Invoice emailed / Kajabi enroll</strong> → stay <code>confirmed_book</code> (refresh snapshot when needed)</li>
                            <li><strong class="text-sh-text">Xero invoice emailed to customer</strong> → stays <code>confirmed_book</code> (snapshot may refresh; invoicing is not a delivery stage)</li>
                            <li><strong class="text-sh-text">Kajabi enrollment / quote won</strong> → stays <code>confirmed_book</code></li>
                            <li><strong class="text-sh-text">Admin Resync</strong> (enquiry page Forge card) → re-queues the current snapshot as create/edit</li>
                            <li><strong class="text-sh-text">Admin Cancel in Forge</strong> (only after a successful sync) → <code>cancelled</code></li>
                            <li><strong class="text-sh-text">Post-delivery checks</strong> (close-out checklist in Forge) → phase 2; not driven by this form webhook yet</li>
                        </ul>
                    </div>

                    <p class="text-sm text-sh-mid">
                        Configure the Forge webhook URL and token under
                        <a href="{{ route('admin.settings.edit') }}" class="link-brand">Integration settings</a>.
                        On each enquiry, the Forge card shows sync status and — when Forge is enabled —
                        <strong class="text-sh-text">Sync / Resync</strong> and <strong class="text-sh-text">Cancel in Forge</strong>
                        (cancel only after the first successful sync). Successful pushes appear on the journey timeline.
                    </p>
                    <p class="text-sm text-sh-mid">
                        <strong class="text-sh-text">Course codes:</strong>
                        Forge receives <code class="rounded bg-sh-surface px-1 text-xs">booking.course_code</code> from the
                        Training Matrix <em>Monday / Forge course value</em> field — <strong>not</strong> the customer-facing
                        Course label. The same value is written to Monday’s <em>Specific Course</em> column.
                        That value must match the Monday course option and the course code configured in Forge.
                        See <a href="#matrix-fields" class="link-brand">Matrix fields</a>.
                    </p>
                </section>

                <section id="vouchers" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-header">Vouchers</h2>
                            <p class="text-sm text-sh-mid">Fixed-amount discounts deducted from the quote net (ex VAT) before Monday and Xero.</p>
                        </div>
                        <a href="{{ route('admin.vouchers.index') }}" class="btn-brand-outline text-xs">Open Vouchers</a>
                    </div>

                    <div class="help-step-grid">
                        <div class="help-step-card">
                            <div class="help-step-num">1</div>
                            <h4>Create a voucher</h4>
                            <p>Add a code, name, and fixed £ amount. Optionally set active dates, a redemption limit (max redemptions), and notes. Codes are stored uppercase with spaces removed.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">2</div>
                            <h4>See assigned quotes</h4>
                            <p>Open a voucher (eye icon) to list every enquiry that used it, with original quote net, discount, and final net.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">3</div>
                            <h4>Staff apply on an enquiry</h4>
                            <p>On the enquiry page, use Apply voucher with the code. One voucher per enquiry. Remove restores the original quote net.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">4</div>
                            <h4>Customers on the form</h4>
                            <p>Organisation and trainer quote paths — and It’s for me → book a course — show an optional voucher field under the live quote. Submit stores the discounted net for Monday / Xero.</p>
                        </div>
                    </div>

                    <div class="help-card-grid" style="margin-top:var(--help-gap);">
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#fff7e8;color:#9a6700;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                            <div>
                                <h4>Xero quote &amp; invoice description</h4>
                                <p>
                                    When a voucher is applied, the Xero line description includes the voucher code, discount amount, and original net
                                    (for example <code class="rounded bg-sh-surface px-1 text-xs">Voucher SAVE50 (−50.00; was 450.00)</code>).
                                    Invoices created from that quote copy the same description.
                                </p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#fff7e8;color:#9a6700;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </div>
                            <div>
                                <h4>Max redemptions</h4>
                                <p>
                                    Optional cap on how many enquiries can use the code. Leave blank for unlimited.
                                    When the limit is reached the voucher shows as Fully used and new applications are blocked.
                                </p>
                            </div>
                        </div>
                    </div>

                    <ul class="list-disc text-sm text-sh-mid">
                        <li>The Xero Unit Amount is the <strong class="text-sh-text">discounted</strong> net (ex VAT). The voucher is recorded in the line description rather than as a separate Xero discount field.</li>
                        <li>Discounts never go below £0.00 — a £100 voucher on an £80 quote becomes £0.00.</li>
                        <li>Valid from / until dates restrict when the code can be applied (optional).</li>
                        <li>Applying or removing a voucher regenerates and emails a new Xero quote when one was already sent.</li>
                        <li>Quote Won enquiries cannot have vouchers applied or changed — the field is greyed out with an explanation.</li>
                        <li>Deleting a voucher clears the link on enquiries but keeps the discount history fields on those enquiries.</li>
                        <li>It's for me (book a course) and organisation paths both show the voucher field under the live quote.</li>
                    </ul>
                </section>

                <section id="training-matrix" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-header">Training Matrix</h2>
                            <p class="text-sm text-sh-mid">The skill / course catalogue that powers organisation and It’s for me live quotes on the public form.</p>
                        </div>
                        <a href="{{ route('admin.training-matrix.index') }}" class="btn-brand-outline text-xs">Open Training Matrix</a>
                    </div>

                    <div class="help-illus" aria-hidden="true">
                        <svg viewBox="0 0 720 180" xmlns="http://www.w3.org/2000/svg" role="img">
                            <rect x="24" y="24" width="672" height="132" rx="16" fill="#fff" stroke="#d7e9f8"/>
                            <rect x="40" y="40" width="140" height="28" rx="8" fill="#e8f4ff"/>
                            <rect x="196" y="40" width="160" height="28" rx="8" fill="#e8f4ff"/>
                            <rect x="372" y="40" width="120" height="28" rx="8" fill="#e8f4ff"/>
                            <rect x="508" y="40" width="100" height="28" rx="8" fill="#e8f4ff"/>
                            <rect x="624" y="40" width="56" height="28" rx="8" fill="#f3f9e8"/>
                            <rect x="40" y="84" width="640" height="18" rx="6" fill="#f7fbff"/>
                            <rect x="40" y="112" width="640" height="18" rx="6" fill="#f7fbff"/>
                            <text x="48" y="59" font-family="Segoe UI, Arial, sans-serif" font-size="11" font-weight="700" fill="#0255a4">Sector</text>
                            <text x="204" y="59" font-family="Segoe UI, Arial, sans-serif" font-size="11" font-weight="700" fill="#0255a4">Course</text>
                            <text x="380" y="59" font-family="Segoe UI, Arial, sans-serif" font-size="11" font-weight="700" fill="#0255a4">Format</text>
                            <text x="516" y="59" font-family="Segoe UI, Arial, sans-serif" font-size="11" font-weight="700" fill="#0255a4">Style</text>
                            <text x="632" y="59" font-family="Segoe UI, Arial, sans-serif" font-size="11" font-weight="700" fill="#3f6d1c">£</text>
                            <text x="48" y="97" font-family="Segoe UI, Arial, sans-serif" font-size="10" fill="#2e5d84">Education · Legal Briefing · Face to Face · In-house · Live quote</text>
                            <text x="48" y="125" font-family="Segoe UI, Arial, sans-serif" font-size="10" fill="#2e5d84">Healthcare · Soft Restraint · Virtual · Open course · Live quote</text>
                        </svg>
                    </div>

                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Each row is one selectable combination: sector, course, format, course style, attendee limits, pricing, Kajabi invite, and optional second date.</li>
                        <li>Only <strong class="text-sh-text">Active</strong> rows appear on the public form.</li>
                        <li>Customers only see valid matrix combinations — used by both <strong class="text-sh-text">My organisation</strong> and <strong class="text-sh-text">It's for me → Book a training course</strong>.</li>
                        <li><strong class="text-sh-text">Enable Kajabi</strong> is on by default. When on, Quote Won auto-invites Accept Quote students to the online Legal Briefing course; when off, no invite is sent and the enquiry shows “Kajabi is disabled for this course combination”.</li>
                        <li><strong class="text-sh-text">Enable second date</strong> asks for Day 1 and Day 2 on the form — see <a href="#second-date" class="link-brand">Second date courses</a>.</li>
                        <li>On the public form, if a course has only one format or course style, that value is shown as a fixed label (not a dropdown).</li>
                        <li>Use the (?) help buttons on the matrix form for field-level tips.</li>
                        <li>Lower sort order numbers appear first.</li>
                    </ul>
                </section>

                <section id="adding-to-form" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Adding to the form / skill matrix</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            Watch how to add and manage Training Matrix (skill matrix) rows so new options appear on the public enquiry form.
                        </p>
                    </div>
                    <div class="help-video-wrap">
                        <video
                            controls
                            preload="metadata"
                            playsinline
                            controlslist="nodownload"
                            title="Adding to the form / skill matrix walkthrough"
                        >
                            <source src="{{ asset('assets/help/skill-matrix-walkthrough.mp4') }}" type="video/mp4">
                            Your browser does not support the video player.
                        </video>
                    </div>
                </section>

                <section id="matrix-fields" class="brand-panel help-section help-stack scroll-mt-6">
                    <h3 class="text-base font-semibold text-brand-header">Matrix fields</h3>
                    <div class="help-table-wrap">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>What it does</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-medium text-sh-text">Sector</td>
                                    <td class="text-sh-mid">Groups courses on the form (e.g. Education, Healthcare).</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Course label</td>
                                    <td class="text-sh-mid">Customer-facing course name.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Monday / Forge course value</td>
                                    <td class="text-sh-mid">
                                        Exact course code written to Monday’s <em>Specific Course</em> column and sent to Forge as
                                        <code class="rounded bg-sh-surface px-1 text-xs">booking.course_code</code>
                                        (never the customer-facing Course label).
                                        It must match the Monday course option <strong>and</strong> the Forge course code —
                                        keep the same value in the Training Matrix, Monday, and Forge.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Format / Course style</td>
                                    <td class="text-sh-mid">
                                        Delivery type and sub-option used to pick the correct pricing row.
                                        On the public form, a single available format or style is shown as a prefilled label; multiple options still use a dropdown.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Min / Max / Default attendees</td>
                                    <td class="text-sh-mid">Limits and starting value for the attendee stepper.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Max capacity description</td>
                                    <td class="text-sh-mid">Optional reason for the max cap. Shown on the enquiry / edit enquiry form when someone tries to go above the maximum.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Enable Kajabi</td>
                                    <td class="text-sh-mid">
                                        Defaults to on. When checked, Quote Won automatically invites each Accept Quote student to the Legal Briefing course in Kajabi.
                                        Uncheck to skip Kajabi for this combination — the enquiry admin page shows that Kajabi is disabled and Enroll in Kajabi is not offered.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Enable second date</td>
                                    <td class="text-sh-mid">
                                        When checked, the enquiry form asks for Day 1 and Day 2 dates plus a separate address for each day.
                                        Those dates are stored on the enquiry, written to Monday notes (and a Day 2 date column if present), shown in quote emails, and sent to Forge as two <code class="rounded bg-sh-surface px-1 text-xs">session_dates</code>.
                                    </td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Active</td>
                                    <td class="text-sh-mid">Unchecked rows stay in admin but are hidden from customers.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="matrix-pricing" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Pricing kinds</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">How the live quote is calculated from attendee count (Including Travel but Excluding VAT).</p>
                    </div>
                    <div class="help-card-grid">
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;font-weight:800;font-size:0.8rem;">£</div>
                            <div>
                                <h4>Base + per person after 12</h4>
                                <p>Most common. Base up to 12, then per person for each extra attendee.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;font-weight:800;font-size:0.8rem;">£</div>
                            <div>
                                <h4>Banded pricing</h4>
                                <p>Stepped bands (13–20 / 21+) or banded then per-delegate from 21+.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;font-weight:800;font-size:0.8rem;">£</div>
                            <div>
                                <h4>Flat</h4>
                                <p>One fixed amount regardless of headcount.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;font-weight:800;font-size:0.8rem;">£</div>
                            <div>
                                <h4>Per delegate</h4>
                                <p>Rate × attendees — often used for trainer-style pricing.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-sh-mid">
                        After editing matrix rows, test sector → course → format → style → attendees on the public form and confirm the Live Quote before using it with customers.
                        Check that a single format/style appears as a label, that Enable Kajabi on/off matches whether Quote Won sends the Legal Briefing invite, and that Enable second date shows Day 1 / Day 2 when expected.
                    </p>
                </section>

                <section id="support" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h2 class="text-lg font-semibold text-brand-header">Support</h2>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            If something fails on an enquiry (email, Monday, Xero, Kajabi), open the enquiry timeline first — failed steps usually show an error and a Retry action.
                        </p>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Check <a href="{{ route('admin.settings.edit') }}" class="link-brand">Integration settings</a> for API keys, Form base URL, and Brevo webhook secret.</li>
                        <li>Confirm Monday board/token and Brevo open-tracking webhook if Read status is not updating.</li>
                        <li>For public form issues, also check <a href="{{ route('admin.feedback.index') }}" class="link-brand">Feedback</a>.</li>
                        <li>If you still have issues, contact <a href="mailto:john@d3-digital.com" class="link-brand">john@d3-digital.com</a>.</li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
