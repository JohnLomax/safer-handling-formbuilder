@php
    $mode = $mode ?? 'create';
    $voucher = $voucher ?? null;
    $isEdit = $mode === 'edit' && $voucher !== null;
    $formId = $isEdit ? 'voucher-edit-form' : 'voucher-create-form';
    $restoreOld = old('_form') === ($isEdit ? 'edit' : 'create');
    $value = function (string $key, $default = '') use ($restoreOld, $isEdit, $voucher) {
        if ($restoreOld) {
            return old($key, $default);
        }

        return $isEdit ? ($voucher->{$key} ?? $default) : $default;
    };
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEdit ? route('admin.vouchers.update', $voucher) : route('admin.vouchers.store') }}"
    class="space-y-4"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="_form" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_code" value="Code" />
            <x-text-input id="{{ $formId }}_code" name="code" type="text" class="mt-1 block w-full font-mono uppercase" :value="$value('code')" required maxlength="64" placeholder="e.g. SAVE100" />
            <p class="mt-1 text-xs text-sh-mid">Customers enter this on the enquiry form. Spaces are removed; stored uppercase.</p>
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_name" value="Name" />
            <x-text-input id="{{ $formId }}_name" name="name" type="text" class="mt-1 block w-full" :value="$value('name')" required maxlength="255" placeholder="Spring promo" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_amount" value="Fixed amount (£)" />
            <x-text-input id="{{ $formId }}_amount" name="amount" type="number" step="0.01" min="0.01" class="mt-1 block w-full" :value="$value('amount')" required />
            <p class="mt-1 text-xs text-sh-mid">Deducted from the quote net total (ex VAT).</p>
            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_max_redemptions" value="Max redemptions (optional)" />
            <x-text-input id="{{ $formId }}_max_redemptions" name="max_redemptions" type="number" min="1" class="mt-1 block w-full" :value="$value('max_redemptions')" />
            <x-input-error :messages="$errors->get('max_redemptions')" class="mt-2" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_valid_from" value="Valid from (optional)" />
            <x-text-input id="{{ $formId }}_valid_from" name="valid_from" type="datetime-local" class="mt-1 block w-full" :value="$restoreOld ? old('valid_from') : ($isEdit && $voucher->valid_from ? $voucher->valid_from->format('Y-m-d\\TH:i') : '')" />
            <x-input-error :messages="$errors->get('valid_from')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_valid_until" value="Valid until (optional)" />
            <x-text-input id="{{ $formId }}_valid_until" name="valid_until" type="datetime-local" class="mt-1 block w-full" :value="$restoreOld ? old('valid_until') : ($isEdit && $voucher->valid_until ? $voucher->valid_until->format('Y-m-d\\TH:i') : '')" />
            <x-input-error :messages="$errors->get('valid_until')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="{{ $formId }}_notes" value="Notes (optional)" />
        <textarea id="{{ $formId }}_notes" name="notes" rows="3" class="mt-1 block w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm">{{ $value('notes') }}</textarea>
        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
    </div>

    <label class="flex items-center gap-2 rounded-[10px] border border-sh-border bg-white px-3 py-2.5">
        <input type="hidden" name="is_active" value="0">
        <input id="{{ $formId }}_is_active" name="is_active" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($restoreOld ? (string) old('is_active', '1') === '1' : ($isEdit ? $voucher->is_active : true))>
        <span class="text-sm text-sh-text">Active — available for customers and staff to apply</span>
    </label>
</form>
