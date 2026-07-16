@props([
    'compact' => false,
    'title' => 'Synced to monday.com',
])

<img
    src="{{ asset('assets/monday-logo-icon.png') }}"
    alt="{{ $title }}"
    title="{{ $title }}"
    {{ $attributes->merge(['class' => 'inline-block h-5 w-5 shrink-0 object-contain']) }}
>
