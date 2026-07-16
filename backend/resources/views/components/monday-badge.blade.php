@props([
    'compact' => false,
    'title' => 'Synced to monday.com',
])

@if ($compact)
    <span
        {{ $attributes->merge(['class' => 'monday-badge-compact', 'title' => $title]) }}
        role="img"
        aria-label="{{ $title }}"
    >
        <span class="monday-dot monday-dot-red"></span>
        <span class="monday-dot monday-dot-yellow"></span>
        <span class="monday-dot monday-dot-green"></span>
        <span class="monday-dot monday-dot-blue"></span>
    </span>
@else
    <img
        src="{{ asset('assets/monday-logo.svg') }}"
        alt="{{ $title }}"
        title="{{ $title }}"
        {{ $attributes->merge(['class' => 'monday-badge-logo']) }}
    >
@endif
