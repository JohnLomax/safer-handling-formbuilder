@props([
    'compact' => false,
    'title' => 'Xero',
])

@if ($compact)
    <span
        {{ $attributes->merge(['class' => 'xero-badge-compact', 'title' => $title]) }}
        role="img"
        aria-label="{{ $title }}"
    >
        <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M2.5 2.5L13.5 13.5M13.5 2.5L2.5 13.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
        </svg>
    </span>
@else
    <span
        {{ $attributes->merge(['class' => 'xero-badge', 'title' => $title]) }}
        role="img"
        aria-label="{{ $title }}"
    >
        <svg class="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M2.5 2.5L13.5 13.5M13.5 2.5L2.5 13.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
        </svg>
        <span class="text-[11px] font-bold tracking-wide">Xero</span>
    </span>
@endif
