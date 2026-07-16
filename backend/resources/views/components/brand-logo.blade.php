@props(['class' => ''])

<img
    src="{{ asset('assets/safer-handling-logo.png') }}"
    alt="Safer Handling logo"
    {{ $attributes->merge(['class' => 'block max-w-[220px] w-full bg-transparent ' . $class]) }}
/>
