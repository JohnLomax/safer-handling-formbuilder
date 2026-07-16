@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center rounded-[8px] border border-white/25 bg-white/15 px-3 py-2 text-sm font-medium text-white transition'
            : 'inline-flex items-center rounded-[8px] border border-transparent px-3 py-2 text-sm font-medium text-white/85 transition hover:bg-white/10 hover:text-white';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
