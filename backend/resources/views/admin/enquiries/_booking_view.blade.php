@php
    $booking = is_array($enquiry->booking_details_json) ? $enquiry->booking_details_json : [];
    $mode = $mode ?? 'edit';
    $isView = $mode === 'view';
@endphp

<div class="space-y-4 text-sm">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Booker name</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['bookerName'] ?? $enquiry->name }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Organisation</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['organisation'] ?? ($enquiry->organisation_company ?: '—') }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Email</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['email'] ?? $enquiry->email }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Phone</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['phone'] ?? '—' }}</p>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Preferred date</p>
        <p class="mt-1 font-medium text-sh-text">
            @php
                $bookingPreferred = trim((string) ($booking['preferredDate'] ?? ''));
            @endphp
            @if ($bookingPreferred !== '')
                {{ $bookingPreferred }}
            @elseif ($enquiry->preferredDateTimeLabel() !== '')
                {{ $enquiry->preferredDateTimeLabel() }}
            @else
                —
            @endif
        </p>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Venue address</p>
        <p class="mt-1 whitespace-pre-wrap font-medium text-sh-text">{{ $booking['venueAddress'] ?? '—' }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Student names</p>
            <p class="mt-1 whitespace-pre-wrap font-medium text-sh-text">{{ $booking['studentNames'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Student emails</p>
            <p class="mt-1 whitespace-pre-wrap font-medium text-sh-text">{{ $booking['studentEmails'] ?? '—' }}</p>
        </div>
    </div>

    @if (! empty($booking['delegates']) && is_array($booking['delegates']))
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Delegates</p>
            <ul class="mt-2 space-y-1">
                @foreach ($booking['delegates'] as $delegate)
                    <li class="font-medium text-sh-text">
                        {{ $delegate['name'] ?? 'Delegate' }}
                        @if (! empty($delegate['email']))
                            <span class="text-sh-mid">— {{ $delegate['email'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! empty($booking['studentNamesFile']['originalName']))
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Uploaded file</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['studentNamesFile']['originalName'] }}</p>
        </div>
    @endif

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Special requests</p>
        <p class="mt-1 whitespace-pre-wrap font-medium text-sh-text">{{ $booking['specialRequests'] ?? '—' }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Invoice name</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['invoiceName'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Invoice email</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['invoiceEmail'] ?? '—' }}</p>
        </div>
    </div>

    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Invoice address</p>
        <p class="mt-1 whitespace-pre-wrap font-medium text-sh-text">{{ $booking['invoiceAddress'] ?? '—' }}</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Invoice phone</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['invoicePhone'] ?? '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">PO number</p>
            <p class="mt-1 font-medium text-sh-text">{{ $booking['purchaseOrderNumber'] ?? '—' }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Venue requirements</p>
            <p class="mt-1 font-medium text-sh-text">{{ ! empty($booking['venueRequirementsConfirmed']) ? 'Confirmed' : '—' }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Terms accepted</p>
            <p class="mt-1 font-medium text-sh-text">{{ $enquiry->terms_accepted_at?->format('d M Y H:i') ?? (! empty($booking['termsAccepted']) ? 'Yes' : '—') }}</p>
        </div>
    </div>
</div>
