@props(['mode' => 'create', 'entry' => null])

@php
    $defaults = [
        'min_attendees' => 1,
        'is_active' => true,
        'sort_order' => 0,
        'pricing' => ['kind' => 'addonBandsLinear', 'baseTo12' => 0, 'perAfter12' => 0],
    ];
    $entry = $entry ?? new \App\Models\TrainingMatrixEntry($defaults);
    $isEdit = $mode === 'edit' && $entry->exists;
    $formId = $isEdit ? 'matrix-edit-form' : 'matrix-create-form';
    $restoreOld = old('_form') === ($isEdit ? 'edit' : 'create');
    $pricing = $entry->pricing ?? $defaults['pricing'];
    $kind = $restoreOld ? old('pricing_kind', 'addonBandsLinear') : ($pricing['kind'] ?? 'addonBandsLinear');

    $value = function (string $name, mixed $default = '') use ($restoreOld, $isEdit, $entry) {
        if ($restoreOld) {
            return old($name, $default);
        }

        return $isEdit ? ($entry->{$name} ?? $default) : $default;
    };
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEdit ? route('admin.training-matrix.update', $entry) : route('admin.training-matrix.store') }}"
    class="space-y-5"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="_form" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_sector" value="Sector" />
            <x-text-input id="{{ $formId }}_sector" name="sector" type="text" class="mt-1 block w-full" :value="$value('sector')" required />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_course" value="Course label" />
            <x-text-input id="{{ $formId }}_course" name="course" type="text" class="mt-1 block w-full" :value="$value('course')" required />
        </div>
    </div>

    <div>
        <x-input-label for="{{ $formId }}_course_value" value="Monday course value" />
        <x-text-input id="{{ $formId }}_course_value" name="course_value" type="text" class="mt-1 block w-full" :value="$value('course_value')" required />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <x-input-label for="{{ $formId }}_format" value="Format" />
            <x-text-input id="{{ $formId }}_format" name="format" type="text" class="mt-1 block w-full" :value="$value('format')" required />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_sub_option" value="Course style" />
            <x-text-input id="{{ $formId }}_sub_option" name="sub_option" type="text" class="mt-1 block w-full" :value="$value('sub_option')" required />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <x-input-label for="{{ $formId }}_min_attendees" value="Min attendees" />
            <x-text-input id="{{ $formId }}_min_attendees" name="min_attendees" type="number" min="1" class="mt-1 block w-full" :value="$value('min_attendees', 1)" required />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_max_cap" value="Max cap" />
            <x-text-input id="{{ $formId }}_max_cap" name="max_cap" type="number" min="1" class="mt-1 block w-full" :value="$value('max_cap')" />
        </div>
        <div>
            <x-input-label for="{{ $formId }}_default_attendees" value="Default" />
            <x-text-input id="{{ $formId }}_default_attendees" name="default_attendees" type="number" min="1" class="mt-1 block w-full" :value="$value('default_attendees')" />
        </div>
    </div>

    <div class="rounded-[12px] border border-sh-border bg-sh-surface/50 p-4">
        <h4 class="text-sm font-semibold text-brand-header">Pricing</h4>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <x-input-label for="{{ $formId }}_pricing_kind" value="Pricing kind" />
                <select id="{{ $formId }}_pricing_kind" name="pricing_kind" class="mt-1 block w-full rounded-[10px] border-[#b9d4ef] text-[#133a59] shadow-sm focus:border-brand focus:ring-brand">
                    @foreach (['addonBands', 'addonBandsLinear', 'addonBandsPer4621', 'flat', 'flatUnlimited', 'perDelegate'] as $option)
                        <option value="{{ $option }}" @selected($kind === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-input-label for="{{ $formId }}_sort_order" value="Sort order" />
                <x-text-input id="{{ $formId }}_sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$value('sort_order', 0)" />
            </div>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-3">
            <div>
                <x-input-label for="{{ $formId }}_base_to_12" value="Base to 12" />
                <x-text-input id="{{ $formId }}_base_to_12" name="base_to_12" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('base_to_12', 0) : ($pricing['baseTo12'] ?? 0)" />
            </div>
            <div>
                <x-input-label for="{{ $formId }}_per_after_12" value="Per after 12" />
                <x-text-input id="{{ $formId }}_per_after_12" name="per_after_12" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_after_12', 0) : ($pricing['perAfter12'] ?? 0)" />
            </div>
            <div>
                <x-input-label for="{{ $formId }}_per_delegate_rate" value="Per delegate" />
                <x-text-input id="{{ $formId }}_per_delegate_rate" name="per_delegate_rate" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_delegate_rate', '') : ($pricing['rate'] ?? '')" />
            </div>
        </div>
    </div>

    <label class="flex items-center gap-2 rounded-[10px] border border-sh-border bg-white px-3 py-2.5">
        <input id="{{ $formId }}_is_active" name="is_active" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($restoreOld ? old('is_active', true) : ($isEdit ? $entry->is_active : true))>
        <span class="text-sm text-sh-text">Active on the public enquiry form</span>
    </label>
</form>
