@props([
    'name' => '',
    'email' => '',
])

@php
    $name = trim((string) $name);
    $email = trim((string) $email);
    $label = $name !== '' ? $name : ($email !== '' ? $email : 'Recipient');
@endphp

<span
    {{ $attributes->merge(['class' => 'invoice-sent-badge group relative inline-flex max-w-full items-center gap-1 rounded-full border border-[#f0d9a8] bg-[#fff8eb] py-0.5 pl-2 pr-1 text-[10px] font-semibold uppercase tracking-wide text-[#9a6700]']) }}
>
    <span class="whitespace-nowrap">Invoice sent to</span>
    <button
        type="button"
        class="invoice-sent-badge__eye inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[#9a6700] transition hover:bg-[#f6e6c4] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c9a227]/
        aria-label="Show invoice recipient: {{ $label }}"
    >
        <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    </button>

    <span
        role="tooltip"
        class="pointer-events-none absolute bottom-full right-0 z-30 mb-2 hidden w-max max-w-[16rem] rounded-lg border border-[#d8e8f8] bg-white px-3 py-2 text-left shadow-[0_10px_30px_rgba(15,45,80,0.14)] group-hover:block group-focus-within:block"
    >
        <span class="block text-[10px] font-semibold uppercase tracking-wide text-sh-mid">Invoice / quote recipient</span>
        @if ($name !== '')
            <span class="mt-1 block text-xs font-semibold normal-case tracking-normal text-sh-text">{{ $name }}</span>
        @endif
        @if ($email !== '')
            <span class="mt-0.5 block break-all text-xs font-medium normal-case tracking-normal text-brand-header">{{ $email }}</span>
        @endif
        <span class="absolute -bottom-1 right-3 h-2 w-2 rotate-45 border-b border-r border-[#d8e8f8] bg-white"></span>
    </span>
</span>
