<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Vouchers" description="Fixed-amount discounts staff and customers can apply to enquiry quotes.">
            <x-slot name="actions">
                <button type="button" class="btn-brand" x-data @click="$dispatch('open-modal', 'voucher-create-modal')">
                    Add voucher
                </button>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel min-w-0 !p-0">
            @include('admin.partials.alerts')

            <div class="border-b border-sh-border/80 px-6 py-4">
                <p class="text-sm text-sh-mid">{{ $vouchers->total() }} vouchers · fixed £ amounts deducted from quote net (ex VAT).</p>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[14%]">Code</th>
                            <th class="w-[20%]">Name</th>
                            <th class="w-[12%]">Amount</th>
                            <th class="w-[12%]">Assigned quotes</th>
                            <th class="w-[12%]">Status</th>
                            <th class="w-[14%]">Validity</th>
                            <th class="w-[16%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vouchers as $entry)
                            <tr>
                                <td class="font-mono text-sm font-semibold text-sh-text">{{ $entry->code }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $entry->name }}">{{ $entry->name }}</td>
                                <td class="font-semibold text-sh-text">£{{ number_format((float) $entry->amount, 2) }}</td>
                                <td>
                                    <a href="{{ route('admin.vouchers.show', $entry) }}" class="link-brand text-sm font-semibold">
                                        {{ $entry->enquiries_count }}
                                    </a>
                                </td>
                                <td>
                                    @php $status = $entry->statusLabel(); @endphp
                                    @if ($status === 'Active')
                                        <span class="status-pill status-pill-success">{{ $status }}</span>
                                    @else
                                        <span class="status-pill status-pill-muted">{{ $status }}</span>
                                    @endif
                                </td>
                                <td class="text-xs text-sh-mid">
                                    @if ($entry->valid_from || $entry->valid_until)
                                        {{ $entry->valid_from?->format('d M Y') ?? '…' }}
                                        →
                                        {{ $entry->valid_until?->format('d M Y') ?? '…' }}
                                    @else
                                        —
                                    @endif
                                    @if ($entry->max_redemptions)
                                        <div class="mt-0.5">Max {{ $entry->max_redemptions }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="admin-table-actions">
                                        <x-admin.table-action
                                            :href="route('admin.vouchers.show', $entry)"
                                            icon="eye"
                                            title="View"
                                            aria-label="View"
                                        />
                                        <x-admin.table-action
                                            :href="route('admin.vouchers.edit', $entry)"
                                            icon="edit"
                                            title="Edit"
                                            aria-label="Edit"
                                        />
                                        <form action="{{ route('admin.vouchers.destroy', $entry) }}" method="POST" class="inline" onsubmit="return confirm('Delete this voucher? Assigned enquiries keep their discount history but the link is cleared.');">
                                            @csrf
                                            @method('DELETE')
                                            <x-admin.table-action
                                                type="submit"
                                                variant="danger"
                                                icon="delete"
                                                title="Delete"
                                                aria-label="Delete"
                                            />
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-sh-mid">No vouchers yet. Add one to get started.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $vouchers->links() }}
            </div>
        </div>
    </div>

    <x-modal name="voucher-create-modal" maxWidth="3xl" focusable :show="$openCreateModal">
        <div class="admin-modal-header">
            <div>
                <h3 class="text-lg font-semibold text-brand-header">Add voucher</h3>
                <p class="mt-1 text-sm text-sh-mid">Create a fixed-amount discount code.</p>
            </div>
            <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'voucher-create-modal')">&times;</button>
        </div>
        <div class="admin-modal-body">
            @include('admin.vouchers._form', ['mode' => 'create'])
        </div>
        <div class="admin-modal-footer">
            <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'voucher-create-modal')">Cancel</button>
            <button type="submit" form="voucher-create-form" class="btn-brand">Create voucher</button>
        </div>
    </x-modal>

    @if ($editVoucher)
        <x-modal name="voucher-edit-modal" maxWidth="3xl" focusable :show="$openEditModal">
            <div class="admin-modal-header">
                <div>
                    <h3 class="text-lg font-semibold text-brand-header">Edit voucher</h3>
                    <p class="mt-1 text-sm text-sh-mid">{{ $editVoucher->code }}</p>
                </div>
                <button type="button" class="btn-icon" x-on:click="$dispatch('close-modal', 'voucher-edit-modal')">&times;</button>
            </div>
            <div class="admin-modal-body">
                @include('admin.vouchers._form', ['mode' => 'edit', 'voucher' => $editVoucher])
            </div>
            <div class="admin-modal-footer">
                <button type="button" class="btn-brand-outline" x-on:click="$dispatch('close-modal', 'voucher-edit-modal')">Cancel</button>
                <button type="submit" form="voucher-edit-form" class="btn-brand">Save changes</button>
            </div>
        </x-modal>
    @endif
</x-app-layout>
