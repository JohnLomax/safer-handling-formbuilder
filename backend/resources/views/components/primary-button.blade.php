<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center rounded-[10px] border border-brand bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-dark focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
