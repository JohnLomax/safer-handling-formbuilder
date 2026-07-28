@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
    '4xl' => 'sm:max-w-4xl',
    '1440' => 'admin-modal-panel-wide',
][$maxWidth];
@endphp

<div
    x-data="{
        show: @js($show),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
        fieldHelpOpen() {
            const el = document.querySelector('[data-field-help-modal]')
            return !!(el && el.getAttribute('data-open') === 'true')
        },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="if (!fieldHelpOpen()) { show = false }"
    x-on:keydown.tab="if (!fieldHelpOpen()) { $event.preventDefault(); $event.shiftKey || nextFocusable().focus() }"
    x-on:keydown.shift.tab="if (!fieldHelpOpen()) { $event.preventDefault(); prevFocusable().focus() }"
    x-show="show"
    class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:px-6"
    style="display: {{ $show ? 'block' : 'none' }};"
>
    <div
        x-show="show"
        class="absolute inset-0 z-0 bg-[#16324a]/45 backdrop-blur-[2px] transition-opacity"
        x-on:click="if (!fieldHelpOpen()) { show = false }"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    <div
        x-show="show"
        class="admin-modal-panel relative z-10 mb-6 mx-auto w-full {{ $maxWidth }}"
        @if ($maxWidth === 'admin-modal-panel-wide')
            style="max-width: 1440px;"
        @endif
        x-on:click.stop
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-3 scale-[0.98]"
    >
        {{ $slot }}
    </div>
</div>
