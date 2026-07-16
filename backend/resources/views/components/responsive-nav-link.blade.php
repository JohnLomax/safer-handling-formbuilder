@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-[8px] border-l-4 border-brand-green bg-white/15 ps-3 pe-4 py-2 text-start text-base font-medium text-white transition'
            : 'block w-full rounded-[8px] border-l-4 border-transparent ps-3 pe-4 py-2 text-start text-base font-medium text-white/85 transition hover:bg-white/10 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
