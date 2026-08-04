<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Help & Support"
            description="How to use the Safer Handling enquiry system and manage the Training Matrix."
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
                        <a href="#walkthrough"><span class="help-nav-dot"></span>Video walkthrough</a>
                        <a href="#enquiry-journey"><span class="help-nav-dot"></span>Enquiry journey</a>
                        <a href="#enquiry-page"><span class="help-nav-dot"></span>Working an enquiry</a>
                        <a href="#emails"><span class="help-nav-dot"></span>Emails & read status</a>
                        <a href="#booking"><span class="help-nav-dot"></span>Booking & terms</a>
                        <a href="#integrations"><span class="help-nav-dot"></span>Integrations</a>
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
                                <p>Search, filter, and open each customer journey. Filter by Quote Sent, Accepted, or Won.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon" style="background:#f3f9e8;color:#3f6d1c;">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6A2.25 2.25 0 0115.75 3.75H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 018.25 20.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25a2.25 2.25 0 01-2.25-2.25v-2.25z" /></svg>
                            </div>
                            <div>
                                <h4>Training Matrix</h4>
                                <p>Controls organisation course options and live quote pricing on the public form.</p>
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
                                <p>Monday, Brevo, Xero, Kajabi, Forge, postcodes, and email open tracking — under your profile menu.</p>
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
                            <p>Name, email, and enquiry type. Progress can be resumed via the Edit Enquiry Email.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">2</div>
                            <h4>Training & live quote</h4>
                            <p>Organisation path uses the Training Matrix: sector → course → format → style → attendees → date.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">3</div>
                            <h4>Submit</h4>
                            <p>Enquiry is saved; Monday may update; quote / edit emails and office lead notification can send.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">4</div>
                            <h4>Quote accepted</h4>
                            <p>Customer completes Accept Quote / venue details — venue, delegates, invoice info, and terms.</p>
                        </div>
                        <div class="help-step-card">
                            <div class="help-step-num">5</div>
                            <h4>Quote won</h4>
                            <p>After the invoice is emailed, status moves to Quote Won (Monday / Kajabi may progress too).</p>
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

                <section id="enquiry-page" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h3 class="text-base font-semibold text-brand-header">Working an enquiry</h3>
                            <p class="text-sm text-sh-mid">Open any enquiry from the Enquiries list to manage the full journey.</p>
                        </div>
                        <a href="{{ route('admin.enquiries.index') }}" class="btn-brand-outline text-xs">Open Enquiries</a>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Use the journey timeline to see successes/failures and retry failed steps.</li>
                        <li><strong class="text-sh-text">Edit form</strong> opens the customer enquiry form with saved details.</li>
                        <li><strong class="text-sh-text">View / Edit booking</strong> lets staff complete Accept Quote details for the client.</li>
                        <li>Preferred date can be “Not sure yet”; staff can set it later in booking edit.</li>
                        <li>Once Quote Won, the customer enquiry form is locked from further online edits.</li>
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
                                <p>Link for the customer to continue or edit their saved form.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon">
                                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </div>
                            <div>
                                <h4>Quote & invoice emails</h4>
                                <p>Quote confirmation (often with Xero PDF), Accept Quote CTA, and invoice PDF.</p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-sh-mid">
                        <strong class="text-sh-text">Read</strong> appears after Brevo reports the customer opened the email. Set the Brevo webhook secret in Integration settings and register the webhook URL in Brevo for Opened events.
                    </p>
                </section>

                <section id="booking" class="brand-panel help-section help-stack scroll-mt-6">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Booking & terms</h3>
                        <p class="text-sm text-sh-mid" style="margin-top:0.35rem;">
                            The Accept Quote form collects booker details, preferred date, venue address, delegates, invoice details, venue requirements, and Safer Handling terms.
                        </p>
                    </div>
                    <ul class="list-disc text-sm text-sh-mid">
                        <li>Within 2 days of a set preferred date, customers cannot change the date online.</li>
                        <li>Staff can always update booking details from the enquiry page.</li>
                        <li>Saving booking syncs to Monday (Quote Accepted), can create a draft Xero invoice, and may push Forge.</li>
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
                                <p>Quotes and draft invoices; email the invoice to the customer from the enquiry page.</p>
                            </div>
                        </div>
                        <div class="help-feature-card">
                            <div class="help-icon"><x-kajabi-badge compact class="!h-5 !w-5" /></div>
                            <div>
                                <h4>Kajabi / Forge</h4>
                                <p>Kajabi enrollment after quote won where configured; Forge receives booking snapshots.</p>
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
                    </div>
                </section>

                <section id="training-matrix" class="brand-panel help-section help-stack scroll-mt-6">
                    <div class="help-section-head">
                        <div>
                            <h2 class="text-lg font-semibold text-brand-header">Training Matrix</h2>
                            <p class="text-sm text-sh-mid">The skill / course catalogue that powers organisation live quotes on the public form.</p>
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
                        <li>Each row is one selectable combination: sector, course, format, course style, attendee limits, and pricing.</li>
                        <li>Only <strong class="text-sh-text">Active</strong> rows appear on the public form.</li>
                        <li>Customers only see valid matrix combinations.</li>
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
                                    <td class="font-medium text-sh-text">Monday course value</td>
                                    <td class="text-sh-mid">Exact value written to monday.com — must match the Monday option text.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Format / Course style</td>
                                    <td class="text-sh-mid">Delivery type and sub-option used to pick the correct pricing row.</td>
                                </tr>
                                <tr>
                                    <td class="font-medium text-sh-text">Min / Max / Default attendees</td>
                                    <td class="text-sh-mid">Limits and starting value for the attendee stepper.</td>
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
