@php
    $booking = is_array($enquiry->booking_details_json) ? $enquiry->booking_details_json : [];
    $formId = $formId ?? 'booking-edit-form';
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ route('admin.enquiries.booking.update', $enquiry) }}"
    enctype="multipart/form-data"
    class="space-y-4"
>
    @csrf

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="bookerName" value="Booker name" />
            <x-text-input id="bookerName" name="bookerName" type="text" class="mt-1 block w-full" :value="old('bookerName', $booking['bookerName'] ?? $enquiry->name)" required />
        </div>
        <div>
            <x-input-label for="organisation" value="Organisation" />
            <x-text-input id="organisation" name="organisation" type="text" class="mt-1 block w-full" :value="old('organisation', $booking['organisation'] ?? $enquiry->organisation_company)" />
        </div>
        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $booking['email'] ?? $enquiry->email)" required />
        </div>
        <div>
            <x-input-label for="phone" value="Phone" />
            <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $booking['phone'] ?? '')" required />
        </div>
    </div>

    <div>
        <x-input-label for="venueAddress" value="Training venue address" />
        <textarea id="venueAddress" name="venueAddress" rows="3" class="mt-1 block w-full rounded-[10px] border border-[#b7d3ee] px-3 py-2 text-sm shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30" required>{{ old('venueAddress', $booking['venueAddress'] ?? '') }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="studentNames" value="Student names (one per line)" />
            <textarea id="studentNames" name="studentNames" rows="5" class="mt-1 block w-full rounded-[10px] border border-[#b7d3ee] px-3 py-2 text-sm shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">{{ old('studentNames', $booking['studentNames'] ?? '') }}</textarea>
        </div>
        <div>
            <x-input-label for="studentEmails" value="Student emails (one per line)" />
            <textarea id="studentEmails" name="studentEmails" rows="5" class="mt-1 block w-full rounded-[10px] border border-[#b7d3ee] px-3 py-2 text-sm shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">{{ old('studentEmails', $booking['studentEmails'] ?? '') }}</textarea>
        </div>
    </div>

    <div>
        <x-input-label for="studentNamesFile" value="Delegate file upload (optional)" />
        <input id="studentNamesFile" name="studentNamesFile" type="file" class="mt-1 block w-full text-sm" accept=".csv,.xlsx,.xls,.txt,.pdf,.doc,.docx" />
        @if (! empty($booking['studentNamesFile']['originalName']))
            <p class="mt-1 text-xs text-sh-mid">Current file: {{ $booking['studentNamesFile']['originalName'] }}</p>
        @endif
    </div>

    <div>
        <x-input-label for="specialRequests" value="Special requests" />
        <textarea id="specialRequests" name="specialRequests" rows="3" class="mt-1 block w-full rounded-[10px] border border-[#b7d3ee] px-3 py-2 text-sm shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30">{{ old('specialRequests', $booking['specialRequests'] ?? '') }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="invoiceName" value="Invoice name" />
            <x-text-input id="invoiceName" name="invoiceName" type="text" class="mt-1 block w-full" :value="old('invoiceName', $booking['invoiceName'] ?? $enquiry->name)" required />
        </div>
        <div>
            <x-input-label for="invoiceEmail" value="Invoice email" />
            <x-text-input id="invoiceEmail" name="invoiceEmail" type="email" class="mt-1 block w-full" :value="old('invoiceEmail', $booking['invoiceEmail'] ?? $enquiry->email)" required />
        </div>
    </div>

    <div>
        <x-input-label for="invoiceAddress" value="Invoice address" />
        <textarea id="invoiceAddress" name="invoiceAddress" rows="3" class="mt-1 block w-full rounded-[10px] border border-[#b7d3ee] px-3 py-2 text-sm shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30" required>{{ old('invoiceAddress', $booking['invoiceAddress'] ?? '') }}</textarea>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="invoicePhone" value="Invoice phone" />
            <x-text-input id="invoicePhone" name="invoicePhone" type="text" class="mt-1 block w-full" :value="old('invoicePhone', $booking['invoicePhone'] ?? '')" />
        </div>
        <div>
            <x-input-label for="purchaseOrderNumber" value="Purchase order number" />
            <x-text-input id="purchaseOrderNumber" name="purchaseOrderNumber" type="text" class="mt-1 block w-full" :value="old('purchaseOrderNumber', $booking['purchaseOrderNumber'] ?? '')" />
        </div>
    </div>

    <label class="flex items-start gap-3 rounded-[10px] border border-sh-border bg-white/70 p-3 text-sm text-sh-text">
        <input type="checkbox" name="venueRequirements" value="1" class="mt-1" @checked(old('venueRequirements', ! empty($booking['venueRequirementsConfirmed']))) required />
        <span>Venue can meet legal briefing and physical skills requirements.</span>
    </label>

    <label class="flex items-start gap-3 rounded-[10px] border border-sh-border bg-white/70 p-3 text-sm text-sh-text">
        <input type="checkbox" name="termsAccepted" value="1" class="mt-1" @checked(old('termsAccepted', ! empty($booking['termsAccepted']) || $enquiry->terms_accepted_at)) required />
        <span>Safer Handling Terms and Conditions accepted.</span>
    </label>
</form>
