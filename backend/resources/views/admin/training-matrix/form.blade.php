@php
    $pricing = $entry->pricing ?? [];
    $kind = old('pricing_kind', $pricing['kind'] ?? 'addonBandsLinear');
    $isEdit = $entry->exists;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="brand-section-title">
            {{ $isEdit ? 'Edit training matrix row' : 'Add training matrix row' }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="brand-panel">
                @include('admin.partials.alerts')

                <form method="POST" action="{{ $isEdit ? route('admin.training-matrix.update', $entry) : route('admin.training-matrix.store') }}" class="space-y-6">
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="sector" value="Sector" />
                            <x-text-input id="sector" name="sector" type="text" class="mt-1 block w-full" :value="old('sector', $entry->sector)" required />
                        </div>
                        <div>
                            <x-input-label for="course" value="Course label" />
                            <x-text-input id="course" name="course" type="text" class="mt-1 block w-full" :value="old('course', $entry->course)" required />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="course_value" value="Monday course value" />
                        <x-text-input id="course_value" name="course_value" type="text" class="mt-1 block w-full" :value="old('course_value', $entry->course_value)" required />
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="format" value="Format" />
                            <x-text-input id="format" name="format" type="text" class="mt-1 block w-full" :value="old('format', $entry->format)" required />
                        </div>
                        <div>
                            <x-input-label for="sub_option" value="Course style / sub option" />
                            <x-text-input id="sub_option" name="sub_option" type="text" class="mt-1 block w-full" :value="old('sub_option', $entry->sub_option)" required />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <x-input-label for="min_attendees" value="Minimum attendees" />
                            <x-text-input id="min_attendees" name="min_attendees" type="number" min="1" class="mt-1 block w-full" :value="old('min_attendees', $entry->min_attendees ?? 1)" required />
                        </div>
                        <div>
                            <x-input-label for="max_cap" value="Maximum cap (optional)" />
                            <x-text-input id="max_cap" name="max_cap" type="number" min="1" class="mt-1 block w-full" :value="old('max_cap', $entry->max_cap)" />
                        </div>
                        <div>
                            <x-input-label for="default_attendees" value="Default attendees (optional)" />
                            <x-text-input id="default_attendees" name="default_attendees" type="number" min="1" class="mt-1 block w-full" :value="old('default_attendees', $entry->default_attendees)" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="pricing_kind" value="Pricing kind" />
                            <select id="pricing_kind" name="pricing_kind" class="mt-1 block w-full rounded-[10px] border-[#b9d4ef] text-[#133a59] shadow-sm focus:border-brand focus:ring-brand">
                                @foreach (['addonBands', 'addonBandsLinear', 'addonBandsPer4621', 'flat', 'flatUnlimited', 'perDelegate'] as $option)
                                    <option value="{{ $option }}" @selected($kind === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="sort_order" value="Sort order" />
                            <x-text-input id="sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $entry->sort_order ?? 0)" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <x-input-label for="base_to_12" value="Base to 12" />
                            <x-text-input id="base_to_12" name="base_to_12" type="number" step="0.01" class="mt-1 block w-full" :value="old('base_to_12', $pricing['baseTo12'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="per_13_to_20" value="Per 13–20" />
                            <x-text-input id="per_13_to_20" name="per_13_to_20" type="number" step="0.01" class="mt-1 block w-full" :value="old('per_13_to_20', $pricing['per13to20'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="fixed_21_plus" value="Fixed 21+" />
                            <x-text-input id="fixed_21_plus" name="fixed_21_plus" type="number" step="0.01" class="mt-1 block w-full" :value="old('fixed_21_plus', $pricing['fixed21Plus'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="per_after_12" value="Per after 12" />
                            <x-text-input id="per_after_12" name="per_after_12" type="number" step="0.01" class="mt-1 block w-full" :value="old('per_after_12', $pricing['perAfter12'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="per_21_plus" value="Per 21+" />
                            <x-text-input id="per_21_plus" name="per_21_plus" type="number" step="0.01" class="mt-1 block w-full" :value="old('per_21_plus', $pricing['per21Plus'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="flat_amount" value="Flat amount" />
                            <x-text-input id="flat_amount" name="flat_amount" type="number" step="0.01" class="mt-1 block w-full" :value="old('flat_amount', $pricing['amount'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="per_delegate_rate" value="Per delegate rate" />
                            <x-text-input id="per_delegate_rate" name="per_delegate_rate" type="number" step="0.01" class="mt-1 block w-full" :value="old('per_delegate_rate', $pricing['rate'] ?? '')" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="is_active" name="is_active" type="checkbox" value="1" class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand" @checked(old('is_active', $entry->is_active ?? true))>
                        <label for="is_active" class="text-sm text-sh-mid">Active on the public form</label>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-primary-button>{{ $isEdit ? 'Save changes' : 'Create row' }}</x-primary-button>
                        <a href="{{ route('admin.training-matrix.index') }}" class="link-brand no-underline hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
