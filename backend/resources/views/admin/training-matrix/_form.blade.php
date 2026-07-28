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

    $pricingKindOptions = [
        'addonBandsLinear' => 'Base + per person after 12 (linear)',
        'addonBands' => 'Banded (base / 13–20 / fixed 21+)',
        'addonBandsPer4621' => 'Banded then per-delegate from 21+',
        'flat' => 'Flat fixed amount',
        'flatUnlimited' => 'Flat fixed amount (unlimited)',
        'perDelegate' => 'Per delegate × attendees',
    ];

    $fieldHelp = [
        'sector' => [
            'title' => 'Sector',
            'body' => "The organisation sector shown on the enquiry form (for example Education, Healthcare, or Corporate).\n\nThis groups courses in the sector dropdown customers choose from.",
        ],
        'course' => [
            'title' => 'Course label',
            'body' => "The course name customers see on the public enquiry form.\n\nUse the clear customer-facing title, not an internal Monday code.",
        ],
        'course_value' => [
            'title' => 'Monday course value',
            'body' => "The exact course value written to monday.com when an enquiry is synced.\n\nThis should match the Monday dropdown/status option text for that course.",
        ],
        'format' => [
            'title' => 'Format',
            'body' => "How the training is delivered, such as Face to Face, Virtual, or Blended.\n\nShown as a format choice after the customer picks a course.",
        ],
        'sub_option' => [
            'title' => 'Course style',
            'body' => "A more specific style or sub-option under the format, such as Open Course, In-House, or a delivery variant.\n\nUsed with Format to calculate the right pricing row.",
        ],
        'min_attendees' => [
            'title' => 'Min attendees',
            'body' => "The lowest number of attendees allowed for this course option on the enquiry form.\n\nThe attendee control will not go below this number.",
        ],
        'max_cap' => [
            'title' => 'Max cap',
            'body' => "Optional upper limit for attendees on this course option.\n\nLeave blank if there is no maximum. When set, the form will not allow a higher headcount.",
        ],
        'default_attendees' => [
            'title' => 'Default attendees',
            'body' => "Optional starting attendee value when this course option is selected.\n\nIf blank, the form uses the minimum attendees value instead.",
        ],
        'pricing_kind' => [
            'title' => 'Pricing kind',
            'body' => 'Choose how the quote is calculated from the attendee count.',
            'items' => [
                [
                    'label' => 'Base + per person after 12 (linear)',
                    'description' => 'Most common for organisation courses. Uses a base price up to 12 people, then adds a per-person amount for each attendee above 12.',
                ],
                [
                    'label' => 'Banded (base / 13–20 / fixed 21+)',
                    'description' => 'Stepped pricing with a fixed price at 20 attendees, then banded add-ons either side of that point.',
                ],
                [
                    'label' => 'Banded then per-delegate from 21+',
                    'description' => 'Uses bands up to 19 attendees, then switches to a different per-person rate from 21 upwards.',
                ],
                [
                    'label' => 'Flat / Flat unlimited',
                    'description' => 'One fixed amount for the course, regardless of how many people attend.',
                ],
                [
                    'label' => 'Per delegate',
                    'description' => 'Rate × attendees. Often used for trainer courses.',
                ],
            ],
        ],
        'sort_order' => [
            'title' => 'Sort order',
            'body' => "Controls the order this row appears in admin and on the enquiry form.\n\nLower numbers appear first.",
        ],
        'base_to_12' => [
            'title' => 'Base to 12',
            'body' => "The base course price for up to 12 attendees.\n\nUsed by the banded and linear pricing kinds.",
        ],
        'per_after_12' => [
            'title' => 'Per after 12',
            'body' => "Extra amount added for each attendee above 12.\n\nUsed by linear pricing: total = Base to 12 + (attendees − 12) × Per after 12.",
        ],
        'per_13_to_20' => [
            'title' => 'Per 13–20',
            'body' => "Per-person add-on used between 13 and 19 attendees for banded pricing kinds.",
        ],
        'fixed_21_plus' => [
            'title' => 'Fixed 21+',
            'body' => "The fixed price used at 20 attendees in the standard banded pricing kind.\n\nAdditional people after 20 then use the Per 13–20 add-on again.",
        ],
        'per_21_plus' => [
            'title' => 'Per 21+',
            'body' => "Per-delegate rate used from 21 attendees upward for the “banded then per-delegate from 21+” pricing kind.\n\nTotal = Per 21+ × attendees.",
        ],
        'flat_amount' => [
            'title' => 'Flat amount',
            'body' => "The single fixed quote amount when Pricing kind is Flat or Flat unlimited.\n\nAttendee count does not change this price.",
        ],
        'per_delegate_rate' => [
            'title' => 'Per delegate',
            'body' => "Price charged for each attendee when Pricing kind is Per delegate.\n\nTotal = Per delegate × number of attendees.",
        ],
        'is_active' => [
            'title' => 'Active on the public enquiry form',
            'body' => "When checked, this row is available for customers to choose on the public enquiry form.\n\nUncheck to hide it without deleting the row.",
        ],
    ];

    $value = function (string $name, mixed $default = '') use ($restoreOld, $isEdit, $entry) {
        if ($restoreOld) {
            return old($name, $default);
        }

        return $isEdit ? ($entry->{$name} ?? $default) : $default;
    };

    $help = function (string $key) use ($fieldHelp): array {
        return $fieldHelp[$key] ?? ['title' => $key, 'body' => ''];
    };
@endphp

<form
    id="{{ $formId }}"
    method="POST"
    action="{{ $isEdit ? route('admin.training-matrix.update', $entry) : route('admin.training-matrix.store') }}"
    class="space-y-5"
    x-data="{ kind: @js($kind) }"
>
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif
    <input type="hidden" name="_form" value="{{ $isEdit ? 'edit' : 'create' }}">

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_sector" value="Sector" />
                <x-field-help-button :title="$help('sector')['title']" :body="$help('sector')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_sector" name="sector" type="text" class="mt-1 block w-full" :value="$value('sector')" required />
        </div>
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_course" value="Course label" />
                <x-field-help-button :title="$help('course')['title']" :body="$help('course')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_course" name="course" type="text" class="mt-1 block w-full" :value="$value('course')" required />
        </div>
    </div>

    <div>
        <div class="flex items-center gap-1.5">
            <x-input-label for="{{ $formId }}_course_value" value="Monday course value" />
            <x-field-help-button :title="$help('course_value')['title']" :body="$help('course_value')['body']" />
        </div>
        <x-text-input id="{{ $formId }}_course_value" name="course_value" type="text" class="mt-1 block w-full" :value="$value('course_value')" required />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_format" value="Format" />
                <x-field-help-button :title="$help('format')['title']" :body="$help('format')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_format" name="format" type="text" class="mt-1 block w-full" :value="$value('format')" required />
        </div>
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_sub_option" value="Course style" />
                <x-field-help-button :title="$help('sub_option')['title']" :body="$help('sub_option')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_sub_option" name="sub_option" type="text" class="mt-1 block w-full" :value="$value('sub_option')" required />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_min_attendees" value="Min attendees" />
                <x-field-help-button :title="$help('min_attendees')['title']" :body="$help('min_attendees')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_min_attendees" name="min_attendees" type="number" min="1" class="mt-1 block w-full" :value="$value('min_attendees', 1)" required />
        </div>
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_max_cap" value="Max cap" />
                <x-field-help-button :title="$help('max_cap')['title']" :body="$help('max_cap')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_max_cap" name="max_cap" type="number" min="1" class="mt-1 block w-full" :value="$value('max_cap')" />
        </div>
        <div>
            <div class="flex items-center gap-1.5">
                <x-input-label for="{{ $formId }}_default_attendees" value="Default" />
                <x-field-help-button :title="$help('default_attendees')['title']" :body="$help('default_attendees')['body']" />
            </div>
            <x-text-input id="{{ $formId }}_default_attendees" name="default_attendees" type="number" min="1" class="mt-1 block w-full" :value="$value('default_attendees')" />
        </div>
    </div>

    <div class="rounded-[12px] border border-sh-border bg-sh-surface/50 p-4">
        <h4 class="text-sm font-semibold text-brand-header">Pricing</h4>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
            <div>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_pricing_kind" value="Pricing kind" />
                    <x-field-help-button
                        :title="$help('pricing_kind')['title']"
                        :body="$help('pricing_kind')['body']"
                        :items="$help('pricing_kind')['items'] ?? []"
                    />
                </div>
                <select
                    id="{{ $formId }}_pricing_kind"
                    name="pricing_kind"
                    x-model="kind"
                    class="mt-1 block w-full rounded-[10px] border-[#b9d4ef] text-[#133a59] shadow-sm focus:border-brand focus:ring-brand"
                >
                    @foreach ($pricingKindOptions as $option => $label)
                        <option value="{{ $option }}" @selected($kind === $option)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_sort_order" value="Sort order" />
                    <x-field-help-button :title="$help('sort_order')['title']" :body="$help('sort_order')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$value('sort_order', 0)" />
            </div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div x-show="['addonBands','addonBandsLinear','addonBandsPer4621'].includes(kind)" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_base_to_12" value="Base to 12" />
                    <x-field-help-button :title="$help('base_to_12')['title']" :body="$help('base_to_12')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_base_to_12" name="base_to_12" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('base_to_12', 0) : ($pricing['baseTo12'] ?? 0)" />
            </div>

            <div x-show="kind === 'addonBandsLinear'" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_per_after_12" value="Per after 12" />
                    <x-field-help-button :title="$help('per_after_12')['title']" :body="$help('per_after_12')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_per_after_12" name="per_after_12" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_after_12', 0) : ($pricing['perAfter12'] ?? 0)" />
            </div>

            <div x-show="['addonBands','addonBandsPer4621'].includes(kind)" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_per_13_to_20" value="Per 13–20" />
                    <x-field-help-button :title="$help('per_13_to_20')['title']" :body="$help('per_13_to_20')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_per_13_to_20" name="per_13_to_20" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_13_to_20', '') : ($pricing['per13to20'] ?? '')" />
            </div>

            <div x-show="kind === 'addonBands'" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_fixed_21_plus" value="Fixed 21+" />
                    <x-field-help-button :title="$help('fixed_21_plus')['title']" :body="$help('fixed_21_plus')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_fixed_21_plus" name="fixed_21_plus" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('fixed_21_plus', '') : ($pricing['fixed21Plus'] ?? '')" />
            </div>

            <div x-show="kind === 'addonBandsPer4621'" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_per_21_plus" value="Per 21+" />
                    <x-field-help-button :title="$help('per_21_plus')['title']" :body="$help('per_21_plus')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_per_21_plus" name="per_21_plus" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_21_plus', '') : ($pricing['per21Plus'] ?? '')" />
            </div>

            <div x-show="['flat','flatUnlimited'].includes(kind)" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_flat_amount" value="Flat amount" />
                    <x-field-help-button :title="$help('flat_amount')['title']" :body="$help('flat_amount')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_flat_amount" name="flat_amount" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('flat_amount', '') : ($pricing['amount'] ?? '')" />
            </div>

            <div x-show="kind === 'perDelegate'" x-cloak>
                <div class="flex items-center gap-1.5">
                    <x-input-label for="{{ $formId }}_per_delegate_rate" value="Per delegate" />
                    <x-field-help-button :title="$help('per_delegate_rate')['title']" :body="$help('per_delegate_rate')['body']" />
                </div>
                <x-text-input id="{{ $formId }}_per_delegate_rate" name="per_delegate_rate" type="number" step="0.01" class="mt-1 block w-full" :value="$restoreOld ? old('per_delegate_rate', '') : ($pricing['rate'] ?? '')" />
            </div>
        </div>
    </div>

    <label class="flex items-center gap-2 rounded-[10px] border border-sh-border bg-white px-3 py-2.5">
        <input id="{{ $formId }}_is_active" name="is_active" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked($restoreOld ? old('is_active', true) : ($isEdit ? $entry->is_active : true))>
        <span class="text-sm text-sh-text">Active on the public enquiry form</span>
        <x-field-help-button :title="$help('is_active')['title']" :body="$help('is_active')['body']" />
    </label>
</form>
