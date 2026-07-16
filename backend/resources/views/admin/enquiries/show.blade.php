<x-app-layout>
    <x-slot name="header">
        <div class="admin-page-header">
            <div>
                <a href="{{ route('admin.enquiries.index') }}" class="text-sm text-sh-mid transition hover:text-brand">&larr; All enquiries</a>
                <h2 class="mt-2 brand-section-title">{{ $enquiry->name }}</h2>
                <p class="mt-1 text-sm text-sh-mid">{{ $enquiry->email }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($enquiry->status === 'submitted')
                    <span class="status-pill status-pill-submitted">Submitted</span>
                @elseif ($enquiry->status === 'quote_sent')
                    <span class="status-pill status-pill-success">Quote Sent</span>
                @elseif ($enquiry->status === 'quote_accepted')
                    <span class="status-pill status-pill-success">Quote Accepted</span>
                @elseif ($enquiry->status === 'contacted')
                    <span class="status-pill status-pill-success">Contacted</span>
                @else
                    <span class="status-pill status-pill-progress">{{ $enquiry->statusLabel() }}</span>
                @endif
                <span class="status-pill bg-[#eef6ff] text-[#0f4a78]">{{ $enquiry->enquiryTypeLabel() }}</span>
                @if ($enquiry->isMondaySynced())
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-sh-border bg-white px-2.5 py-1">
                        <x-monday-badge compact />
                        <span class="text-xs font-semibold text-sh-text">Monday</span>
                    </span>
                @endif
                @if (in_array($enquiry->status, ['quote_sent', 'quote_accepted'], true)
                    || $enquiry->quote_email_sent_at
                    || filled($enquiry->xero_quote_id))
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-[#9ad7ea] bg-[#e8f8fc] px-2.5 py-1">
                        <x-xero-badge compact />
                        <span class="text-xs font-semibold text-[#0b6e8a]">Xero</span>
                    </span>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="admin-shell space-y-6">
            @include('admin.partials.alerts')

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Started</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->created_at?->format('d M Y') }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->created_at?->format('H:i') }}</p>
                </div>
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Submitted</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->submitted_at?->format('d M Y') ?? '—' }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->submitted_at?->format('H:i') ?? 'Not yet' }}</p>
                </div>
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid inline-flex items-center gap-1.5">
                        <x-monday-badge compact class="!h-3.5 !w-3.5" />
                        Monday sync
                    </p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->isMondaySynced() ? 'Synced' : 'Pending' }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->monday_synced_at?->format('d M Y H:i') ?? '—' }}</p>
                </div>
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid inline-flex items-center gap-1.5">
                        <x-xero-badge compact class="!h-3.5 !w-3.5" />
                        Xero Quote
                    </p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->quote_email_sent_at ? 'Sent' : 'Not sent' }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->quote_email_sent_at?->format('d M Y H:i') ?? '—' }}</p>
                </div>
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Edit Enquiry Email</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->resume_email_sent_at ? 'Sent' : 'Not sent' }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->resume_email_sent_at?->format('d M Y H:i') ?? '—' }}</p>
                </div>
                <div class="enquiry-stat">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Booking details</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiry->booking_submitted_at ? 'Submitted' : ($enquiry->booking_email_sent_at ? 'Email sent' : 'Pending') }}</p>
                    <p class="text-xs text-sh-mid">{{ $enquiry->booking_submitted_at?->format('d M Y H:i') ?? ($enquiry->booking_email_sent_at?->format('d M Y H:i') ?? '—') }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="brand-panel lg:col-span-3">
                    <h3 class="text-base font-semibold text-brand-header">Enquiry details</h3>

                    <div class="mt-2 divide-y divide-sh-border/70">
                        <div class="enquiry-detail-row">
                            <dt class="enquiry-detail-label">Reference</dt>
                            <dd class="enquiry-detail-value">#{{ $enquiry->id }}</dd>
                        </div>
                        <div class="enquiry-detail-row">
                            <dt class="enquiry-detail-label">Audience</dt>
                            <dd class="enquiry-detail-value">{{ $enquiry->audience_type ? ucfirst($enquiry->audience_type) : '—' }}</dd>
                        </div>
                        @if ($enquiry->personal_goal)
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Personal goal</dt>
                                <dd class="enquiry-detail-value">{{ ucfirst($enquiry->personal_goal) }}</dd>
                            </div>
                        @endif
                        @if ($enquiry->sector)
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Sector</dt>
                                <dd class="enquiry-detail-value">{{ $enquiry->sector }}</dd>
                            </div>
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Course</dt>
                                <dd class="enquiry-detail-value">{{ $enquiry->org_course ?: '—' }}</dd>
                            </div>
                            @if ($enquiry->course_format)
                                <div class="enquiry-detail-row">
                                    <dt class="enquiry-detail-label">Format</dt>
                                    <dd class="enquiry-detail-value">{{ $enquiry->course_format }} · {{ $enquiry->format_sub_option }}</dd>
                                </div>
                            @endif
                            @if ($enquiry->matrix_attendees)
                                <div class="enquiry-detail-row">
                                    <dt class="enquiry-detail-label">Attendees</dt>
                                    <dd class="enquiry-detail-value">{{ $enquiry->matrix_attendees }}</dd>
                                </div>
                            @endif
                            @if ($enquiry->organisation_company)
                                <div class="enquiry-detail-row">
                                    <dt class="enquiry-detail-label">Company/Organisation</dt>
                                    <dd class="enquiry-detail-value">{{ $enquiry->organisation_company }}</dd>
                                </div>
                            @endif
                        @endif
                        @if ($enquiry->trainer_course_select)
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Trainer course</dt>
                                <dd class="enquiry-detail-value">{{ $enquiry->trainer_course_select }}</dd>
                            </div>
                            @if ($enquiry->trainer_attendees)
                                <div class="enquiry-detail-row">
                                    <dt class="enquiry-detail-label">Attendees</dt>
                                    <dd class="enquiry-detail-value">{{ $enquiry->trainer_attendees }}</dd>
                                </div>
                            @endif
                        @endif
                        @if ($enquiry->preferredDateTimeLabel() !== '')
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Preferred date</dt>
                                <dd class="enquiry-detail-value">{{ $enquiry->preferredDateTimeLabel() }}</dd>
                            </div>
                        @endif
                        @if ($enquiry->extra_notes)
                            <div class="enquiry-detail-row">
                                <dt class="enquiry-detail-label">Notes</dt>
                                <dd class="enquiry-detail-value whitespace-pre-wrap">{{ $enquiry->extra_notes }}</dd>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-6 lg:col-span-2">
                    <div class="brand-panel">
                        <h3 class="text-base font-semibold text-brand-header">Integrations</h3>
                        <div class="mt-4 space-y-3">
                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <x-monday-badge />
                                        <span class="text-sm font-semibold text-sh-text">monday.com</span>
                                    </div>
                                    @if ($enquiry->isMondaySynced())
                                        <span class="status-pill status-pill-success">Synced</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Pending</span>
                                    @endif
                                </div>
                                @if ($enquiry->monday_item_id)
                                    <p class="mt-3 text-xs text-sh-mid">Item ID <span class="font-mono font-semibold text-sh-text">{{ $enquiry->monday_item_id }}</span></p>
                                @endif
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <x-xero-badge compact />
                                        <span class="text-sm font-semibold text-sh-text">Xero Quote</span>
                                    </div>
                                    @if ($enquiry->quote_email_sent_at)
                                        <span class="status-pill status-pill-success">Sent</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Not sent</span>
                                    @endif
                                </div>
                                @if ($enquiry->xero_quote_number)
                                    <p class="mt-3 text-xs text-sh-mid">{{ $enquiry->xero_quote_number }}</p>
                                @endif
                                @if ($enquiry->quote_email_sent_at)
                                    <p class="mt-1 text-xs text-sh-mid">{{ $enquiry->quote_email_sent_at->format('d M Y · H:i') }}</p>
                                @endif
                                @if (in_array('quote_email', $retryableActions, true))
                                    <form method="POST" action="{{ route('admin.enquiries.retry.quote-email', $enquiry) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn-brand-outline text-xs">
                                            Send quote
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <x-xero-badge compact />
                                        <span class="text-sm font-semibold text-sh-text">Xero invoice</span>
                                    </div>
                                    @if ($enquiry->xero_invoice_id)
                                        <span class="status-pill status-pill-success">Draft</span>
                                    @elseif ($enquiry->events->contains(fn ($event) => $event->event_type === 'xero_invoice_failed'))
                                        <span class="status-pill status-pill-progress">Failed</span>
                                    @elseif ($enquiry->xero_quote_id && ($enquiry->booking_submitted_at || $enquiry->status === 'quote_accepted'))
                                        <span class="status-pill status-pill-muted">Pending</span>
                                    @else
                                        <span class="status-pill status-pill-muted">—</span>
                                    @endif
                                </div>
                                @if ($enquiry->xero_invoice_number)
                                    <p class="mt-3 text-xs text-sh-mid">{{ $enquiry->xero_invoice_number }} · not sent</p>
                                @elseif ($enquiry->xero_invoice_created_at)
                                    <p class="mt-3 text-xs text-sh-mid">Created {{ $enquiry->xero_invoice_created_at->format('d M Y · H:i') }} · not sent</p>
                                @endif
                                @if (in_array('xero_invoice', $retryableActions, true))
                                    <form method="POST" action="{{ route('admin.enquiries.retry.xero-invoice', $enquiry) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn-brand-outline text-xs">
                                            Create draft invoice
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[#b9d4ef] bg-[#eef6ff] text-brand" title="Email" aria-hidden="true">
                                            <svg class="h-3 w-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                            </svg>
                                        </span>
                                        <span class="text-sm font-semibold text-sh-text">Lead notification</span>
                                    </div>
                                    @php
                                        $leadSent = $enquiry->events->contains(fn ($event) => $event->event_type === 'lead_notification_sent');
                                        $leadFailed = $enquiry->events->contains(fn ($event) => $event->event_type === 'lead_notification_failed');
                                    @endphp
                                    @if ($leadSent)
                                        <span class="status-pill status-pill-success">Sent</span>
                                    @elseif ($leadFailed)
                                        <span class="status-pill status-pill-progress">Failed</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Not sent</span>
                                    @endif
                                </div>
                                @if (in_array('lead_notification', $retryableActions, true))
                                    <form method="POST" action="{{ route('admin.enquiries.retry.lead-notification', $enquiry) }}" class="mt-3">
                                        @csrf
                                        <button type="submit" class="btn-brand-outline text-xs">
                                            Send lead email
                                        </button>
                                    </form>
                                @endif
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-sh-text">Enquiry form</span>
                                    @if ($enquiry->submitted_at)
                                        <span class="status-pill status-pill-success">Submitted</span>
                                    @elseif ($enquiry->resume_email_sent_at)
                                        <span class="status-pill status-pill-progress">In progress</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Started</span>
                                    @endif
                                </div>
                                @if ($enquiry->resume_email_sent_at)
                                    <p class="mt-3 text-xs text-sh-mid">Edit Enquiry Email {{ $enquiry->resume_email_sent_at->format('d M Y · H:i') }}</p>
                                @endif
                                @if ($enquiry->submitted_at)
                                    <p class="mt-1 text-xs text-sh-mid">Form submitted {{ $enquiry->submitted_at->format('d M Y · H:i') }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a
                                        href="{{ $enquiry->formEditUrl() }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn-brand-outline text-xs inline-flex items-center gap-1.5"
                                        title="Open enquiry form to edit"
                                    >
                                        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                        </svg>
                                        Edit form
                                    </a>
                                    @if (in_array('resume_email', $retryableActions, true))
                                        <form method="POST" action="{{ route('admin.enquiries.retry.resume-email', $enquiry) }}">
                                            @csrf
                                            <button type="submit" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" title="Send Edit Enquiry Email">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                    <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                </svg>
                                                Send email
                                            </button>
                                        </form>
                                    @elseif ($enquiry->resume_email_sent_at)
                                        <form method="POST" action="{{ route('admin.enquiries.resend.resume-email', $enquiry) }}">
                                            @csrf
                                            <button type="submit" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" title="Resend Edit Enquiry Email">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                    <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                </svg>
                                                Resend email
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-sh-text">Booking details</span>
                                    @if ($enquiry->booking_submitted_at)
                                        <span class="status-pill status-pill-success">Completed</span>
                                    @elseif ($enquiry->booking_email_sent_at)
                                        <span class="status-pill status-pill-progress">Awaiting client</span>
                                    @else
                                        <span class="status-pill status-pill-muted">Not sent</span>
                                    @endif
                                </div>
                                @if ($enquiry->booking_email_sent_at)
                                    <p class="mt-3 text-xs text-sh-mid">Email {{ $enquiry->booking_email_sent_at->format('d M Y · H:i') }}</p>
                                @endif
                                @if ($enquiry->booking_submitted_at)
                                    <p class="mt-1 text-xs text-sh-mid">Form submitted {{ $enquiry->booking_submitted_at->format('d M Y · H:i') }}</p>
                                @endif

                                <div class="mt-3 flex flex-wrap gap-2">
                                    @if ($enquiry->hasBookingDetails())
                                        <button type="button" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" x-data @click="$dispatch('open-modal', 'booking-view-modal')" title="View booking details">
                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                            </svg>
                                            View
                                        </button>
                                        <button type="button" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" x-data @click="$dispatch('open-modal', 'booking-edit-modal')" title="Edit booking details">
                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                            </svg>
                                            Edit
                                        </button>
                                    @elseif ($enquiry->canStaffProgressBooking())
                                        <button type="button" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" x-data @click="$dispatch('open-modal', 'booking-edit-modal')" title="Complete booking for client">
                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                            </svg>
                                            Complete for client
                                        </button>
                                        <a
                                            href="{{ $enquiry->bookingFormUrl() }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn-brand-outline text-xs"
                                        >
                                            Client link
                                        </a>
                                    @endif

                                    @if (in_array('booking_email', $retryableActions, true))
                                        <form method="POST" action="{{ route('admin.enquiries.retry.booking-email', $enquiry) }}">
                                            @csrf
                                            <button type="submit" class="btn-brand-outline text-xs inline-flex items-center gap-1.5" title="Send accept terms email">
                                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                    <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                </svg>
                                                Send email
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="rounded-[12px] border border-sh-border bg-white/70 p-4">
                                <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Journey events</p>
                                <p class="mt-1 text-2xl font-semibold text-sh-text">{{ $enquiry->events->count() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="brand-panel">
                <div class="flex flex-wrap items-end justify-between gap-3 border-b border-sh-border/70 pb-4">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Journey log</h3>
                        <p class="mt-1 text-sm text-sh-mid">Chronological record of everything that happened with this enquiry.</p>
                    </div>
                </div>

                @if ($enquiry->events->isEmpty())
                    <p class="mt-6 text-sm text-sh-mid">No journey events recorded yet.</p>
                @else
                    @php
                        $resolvedProcesses = $enquiry->events
                            ->filter(fn ($event) => $event->isSuccessful() && $event->processKey() !== null)
                            ->map(fn ($event) => $event->processKey())
                            ->unique()
                            ->values()
                            ->all();
                    @endphp
                    <div class="mt-6">
                        @foreach ($enquiry->events as $event)
                            @php
                                $processKey = $event->processKey();
                                $isResolvedFailure = $event->isFailed()
                                    && $processKey !== null
                                    && in_array($processKey, $resolvedProcesses, true);
                                $showRetry = $event->isRetryable() && ! $isResolvedFailure;
                                $isSuccessHighlight = $event->isManualRetrySuccess() || $isResolvedFailure;
                            @endphp
                            <div class="journey-item">
                                <div class="journey-marker">
                                    @if ($event->isMondayEvent())
                                        <x-monday-badge compact />
                                    @elseif ($event->isXeroEvent())
                                        <x-xero-badge compact />
                                    @elseif ($event->isEmailEvent())
                                        <span @class([
                                            'text-[10px] font-bold leading-none',
                                            'text-[#15803d]' => $isSuccessHighlight,
                                            'text-[#b91c1c]' => $event->isFailed() && ! $isResolvedFailure,
                                            'text-brand' => ! $isSuccessHighlight && ! ($event->isFailed() && ! $isResolvedFailure),
                                        ])>@</span>
                                    @else
                                        <span class="h-2 w-2 rounded-full bg-brand"></span>
                                    @endif
                                </div>

                                <div @class([
                                    'rounded-[12px] border px-4 py-3',
                                    'border-[#86efac] bg-[#f0fdf4]' => $isSuccessHighlight,
                                    'border-[#f3c1c1] bg-[#fef2f2]' => $event->isFailed() && ! $isResolvedFailure,
                                    'border-sh-border/80 bg-white/70' => ! $isSuccessHighlight && ! ($event->isFailed() && ! $isResolvedFailure),
                                ])>
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="inline-flex items-center gap-1.5 text-sm font-semibold text-sh-text">
                                            @if ($event->isXeroEvent())
                                                <x-xero-badge compact class="!h-4 !w-4" />
                                            @elseif ($event->isMondayEvent())
                                                <x-monday-badge compact class="!h-4 !w-4" />
                                            @endif
                                            {{ $event->label() }}
                                        </p>
                                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                                            @php
                                                $isBookingJourneyEvent = in_array($event->event_type, [
                                                    'booking_email_sent',
                                                    'booking_email_failed',
                                                    'booking_details_submitted',
                                                    'monday_booking_synced',
                                                    'monday_booking_sync_failed',
                                                    'quote_email_sent',
                                                ], true);
                                                $isResumeEmailJourneyEvent = in_array($event->event_type, [
                                                    'resume_email_sent',
                                                    'resume_email_failed',
                                                    'form_submitted',
                                                    'details_updated',
                                                ], true);
                                                $isAcceptTermsEmailJourneyEvent = in_array($event->event_type, [
                                                    'booking_email_sent',
                                                    'booking_email_failed',
                                                    'quote_email_sent',
                                                    'booking_details_submitted',
                                                ], true);
                                            @endphp
                                            @if ($isResumeEmailJourneyEvent)
                                                <a
                                                    href="{{ $enquiry->formEditUrl() }}"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1"
                                                    title="Edit enquiry form"
                                                >
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                                    </svg>
                                                    Edit form
                                                </a>
                                                @if (in_array('resume_email', $retryableActions, true))
                                                    <form method="POST" action="{{ route('admin.enquiries.retry.resume-email', $enquiry) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1.5" title="Send Edit Enquiry Email">
                                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                                <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                            </svg>
                                                            Send email
                                                        </button>
                                                    </form>
                                                @elseif ($enquiry->resume_email_sent_at)
                                                    <form method="POST" action="{{ route('admin.enquiries.resend.resume-email', $enquiry) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1.5" title="Resend Edit Enquiry Email">
                                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                                <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                            </svg>
                                                            Resend email
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                            @if ($isBookingJourneyEvent && $enquiry->hasBookingDetails())
                                                <button type="button" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1" x-data @click="$dispatch('open-modal', 'booking-view-modal')" title="View booking">
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" />
                                                        <path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.186A10.004 10.004 0 0110 3c4.257 0 7.893 2.66 9.336 6.41.147.381.146.804 0 1.186A10.004 10.004 0 0110 17c-4.257 0-7.893-2.66-9.336-6.41zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                                <button type="button" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1" x-data @click="$dispatch('open-modal', 'booking-edit-modal')" title="Edit booking">
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                                    </svg>
                                                </button>
                                            @elseif ($isBookingJourneyEvent && $enquiry->canStaffProgressBooking() && ! $enquiry->hasBookingDetails())
                                                <button type="button" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1" x-data @click="$dispatch('open-modal', 'booking-edit-modal')" title="Complete booking for client">
                                                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.501a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z" />
                                                    </svg>
                                                </button>
                                            @endif
                                            @if ($isAcceptTermsEmailJourneyEvent)
                                                @if (in_array('booking_email', $retryableActions, true))
                                                    <form method="POST" action="{{ route('admin.enquiries.retry.booking-email', $enquiry) }}">
                                                        @csrf
                                                        <button type="submit" class="btn-brand-outline px-2.5 py-1 text-xs inline-flex items-center gap-1.5" title="Send accept terms email">
                                                            <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M3 4a2 2 0 00-2 2v1.161l8.441 4.221a1.25 1.25 0 001.118 0L19 7.162V6a2 2 0 00-2-2H3z" />
                                                                <path d="M19 8.839l-7.77 3.885a2.75 2.75 0 01-2.46 0L1 8.839V14a2 2 0 002 2h14a2 2 0 002-2V8.839z" />
                                                            </svg>
                                                            Send email
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                            @if ($showRetry)
                                                <form method="POST" action="{{ route('admin.enquiries.retry.event', [$enquiry, $event]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn-brand-outline px-2.5 py-1 text-xs">
                                                        Retry
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($event->isManualRetrySuccess())
                                                <span class="inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-1 text-xs font-semibold text-[#15803d]">
                                                    Retry succeeded
                                                </span>
                                            @elseif ($isResolvedFailure)
                                                <span class="inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-1 text-xs font-semibold text-[#15803d]">
                                                    Resolved
                                                </span>
                                            @endif
                                            <time class="text-xs tabular-nums text-sh-mid" datetime="{{ $event->created_at?->toIso8601String() }}">
                                                {{ $event->created_at?->format('d M Y · H:i:s') }}
                                            </time>
                                        </div>
                                    </div>
                                    @if ($event->message)
                                        <p class="mt-1.5 text-sm leading-relaxed text-sh-mid">{{ $event->message }}</p>
                                    @endif
                                    @if ($event->metadata)
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            @if (! empty($event->metadata['monday_item_id']))
                                                <span class="inline-flex items-center rounded-full bg-[#eef6ff] px-2.5 py-1 text-xs font-medium text-brand-header">
                                                    Monday item {{ $event->metadata['monday_item_id'] }}
                                                </span>
                                            @endif
                                            @if (! empty($event->metadata['office_email']))
                                                <span class="inline-flex items-center rounded-full bg-[#eef6ff] px-2.5 py-1 text-xs font-medium text-brand-header">
                                                    To {{ $event->metadata['office_email'] }}
                                                </span>
                                            @endif
                                            @if (! empty($event->metadata['xero_invoice_number']))
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e8f8fc] px-2.5 py-1 text-xs font-medium text-[#0b6e8a]">
                                                    <x-xero-badge compact class="!h-4 !w-4 !rounded-full" />
                                                    Invoice {{ $event->metadata['xero_invoice_number'] }}
                                                </span>
                                            @elseif (! empty($event->metadata['xero_quote_number']))
                                                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e8f8fc] px-2.5 py-1 text-xs font-medium text-[#0b6e8a]">
                                                    <x-xero-badge compact class="!h-4 !w-4 !rounded-full" />
                                                    Xero {{ $event->metadata['xero_quote_number'] }}
                                                </span>
                                            @endif
                                            @if (isset($event->metadata['subtotal']) || isset($event->metadata['vat']) || isset($event->metadata['total']))
                                                <span class="inline-flex items-center rounded-full bg-[#ecfdf3] px-2.5 py-1 text-xs font-medium text-[#047857]">
                                                    @if (isset($event->metadata['subtotal']))
                                                        Subtotal £{{ number_format((float) $event->metadata['subtotal'], 2) }}
                                                    @endif
                                                    @if (isset($event->metadata['vat']))
                                                        @if (isset($event->metadata['subtotal'])) · @endif
                                                        VAT £{{ number_format((float) $event->metadata['vat'], 2) }}
                                                    @endif
                                                    @if (isset($event->metadata['total']))
                                                        @if (isset($event->metadata['subtotal']) || isset($event->metadata['vat'])) · @endif
                                                        Total £{{ number_format((float) $event->metadata['total'], 2) }}
                                                    @endif
                                                </span>
                                            @endif
                                            @if (! empty($event->metadata['channel']))
                                                @if ($event->metadata['channel'] === 'xero')
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#e8f8fc] px-2.5 py-1 text-xs font-medium text-[#0b6e8a]">
                                                        <x-xero-badge compact class="!h-4 !w-4 !rounded-full" />
                                                        Via Xero
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-[#eef6ff] px-2.5 py-1 text-xs font-medium text-brand-header">
                                                        Via {{ strtoupper((string) $event->metadata['channel']) }}
                                                    </span>
                                                @endif
                                            @endif
                                            @if (! empty($event->metadata['error']) && ! $isResolvedFailure)
                                                <span class="inline-flex items-center rounded-full bg-[#fef2f2] px-2.5 py-1 text-xs font-medium text-[#b91c1c]">
                                                    {{ $event->metadata['error'] }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
    </div>

    @if ($enquiry->hasBookingDetails())
        <x-modal name="booking-view-modal" maxWidth="3xl" focusable :show="$openBookingViewModal ?? false">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">Booking details</h3>
                    <p class="mt-1 text-sm text-sh-mid">Submitted {{ $enquiry->booking_submitted_at?->format('d M Y · H:i') }}</p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'booking-view-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.enquiries._booking_view', ['enquiry' => $enquiry])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'booking-view-modal')">Close</button>
                <button
                    type="button"
                    class="btn-brand"
                    x-on:click="$dispatch('close-modal', 'booking-view-modal'); $dispatch('open-modal', 'booking-edit-modal')"
                >
                    Edit booking
                </button>
            </div>
        </x-modal>
    @endif

    @if ($enquiry->canStaffProgressBooking())
        <x-modal name="booking-edit-modal" maxWidth="4xl" focusable :show="$openBookingEditModal ?? false">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">
                        {{ $enquiry->hasBookingDetails() ? 'Edit booking details' : 'Complete booking for client' }}
                    </h3>
                    <p class="mt-1 text-sm text-sh-mid">
                        {{ $enquiry->hasBookingDetails()
                            ? 'Update booking details and re-sync to Monday Quote Accepted.'
                            : 'Fill these in if the client has not completed the booking form. Saving also syncs Monday.' }}
                    </p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'booking-edit-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.partials.alerts')
                @include('admin.enquiries._booking_form', ['enquiry' => $enquiry, 'formId' => 'booking-edit-form'])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'booking-edit-modal')">Cancel</button>
                <button type="submit" form="booking-edit-form" class="btn-brand">
                    {{ $enquiry->hasBookingDetails() ? 'Save & sync to Monday' : 'Complete booking & sync' }}
                </button>
            </div>
        </x-modal>
    @endif
</x-app-layout>