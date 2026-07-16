@props([
    'compact' => false,
    'title' => 'Xero',
])

<img
    src="{{ asset('assets/xero-logo.svg') }}"
    alt="{{ $title }}"
    title="{{ $title }}"
    {{ $attributes->merge(['class' => 'inline-block h-5 w-5 shrink-0 object-contain']) }}
>
