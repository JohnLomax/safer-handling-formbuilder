@props([
    'title',
    'body' => '',
    'items' => [],
])

<button
    type="button"
    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full border border-[#b9d4ef] bg-white text-[11px] font-bold leading-none text-[#20567e] transition hover:border-brand hover:text-brand"
    aria-label="Help: {{ $title }}"
    title="Help: {{ $title }}"
    x-data
    x-on:click.prevent.stop="$dispatch('open-field-help', { title: @js($title), body: @js($body), items: @js($items) })"
>
    ?
</button>
