@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-[10px] border-[#b9d4ef] text-[#133a59] shadow-sm focus:border-brand focus:ring-brand']) }}>
