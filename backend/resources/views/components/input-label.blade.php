@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-bold text-[#20567e]']) }}>
    {{ $value ?? $slot }}
</label>
