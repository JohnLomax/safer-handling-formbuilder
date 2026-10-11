<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Voucher {{ $voucher->code }}"
            description="£{{ number_format((float) $voucher->amount, 2) }} fixed discount · {{ $voucher->name }}"
        >
            <x-slot name="actions">
                <a href="{{ route('admin.vouchers.index') }}" class="btn-brand-outline text-xs">All vouchers</a>
                <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="btn-brand text-xs">Edit</a>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell space-y-6">
        @include('admin.partials.alerts')

        <div class="brand-panel">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Status</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $voucher->statusLabel() }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Amount</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">£{{ number_format((float) $voucher->amount, 2) }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Assigned quotes</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $enquiries->total() }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sh-mid">Max redemptions</p>
                    <p class="mt-1 text-sm font-semibold text-sh-text">{{ $voucher->max_redemptions ?? 'Unlimited' }}</p>
                </div>
            </div>
            @if ($voucher->notes)
                <p class="mt-4 text-sm text-sh-mid">{{ $voucher->notes }}</p>
            @endif
        </div>

        <div class="brand-panel min-w-0 !p-0">
            <div class="border-b border-sh-border/80 px-6 py-4">
                <h3 class="text-base font-semibold text-brand-header">Assigned quotes / enquiries</h3>
                <p class="mt-1 text-sm text-sh-mid">Enquiries that have this voucher applied, with original and discounted quote nets.</p>
            </div>
            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[10%]">Enquiry</th>
                            <th class="w-[22%]">Customer</th>
                            <th class="w-[14%]">Status</th>
                            <th class="w-[14%]">Original</th>
                            <th class="w-[14%]">Discount</th>
                            <th class="w-[14%]">Final</th>
                            <th class="w-[12%]">Opened</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enquiries as $enquiry)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.enquiries.show', $enquiry) }}" class="link-brand font-semibold">#{{ $enquiry->id }}</a>
                                </td>
                                <td>
                                    <div class="font-medium text-sh-text">{{ $enquiry->name }}</div>
                                    <div class="text-xs text-sh-mid">{{ $enquiry->email }}</div>
                                </td>
                                <td class="text-sm text-sh-mid">{{ $enquiry->statusLabel() }}</td>
                                <td class="text-sm text-sh-text">
                                    @if ($enquiry->quote_net_original !== null)
                                        £{{ number_format((float) $enquiry->quote_net_original, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-sm font-semibold text-sh-text">
                                    @if ($enquiry->voucher_amount !== null)
                                        −£{{ number_format((float) $enquiry->voucher_amount, 2) }}
                                    @else
                                        £{{ number_format((float) $voucher->amount, 2) }}
                                    @endif
                                </td>
                                <td class="text-sm font-semibold text-sh-text">
                                    @if ($enquiry->quote_net_final !== null)
                                        £{{ number_format((float) $enquiry->quote_net_final, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-xs text-sh-mid whitespace-nowrap">{{ $enquiry->created_at?->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-sh-mid">No enquiries have used this voucher yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($enquiries->hasPages())
                <div class="px-6 py-4">{{ $enquiries->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
