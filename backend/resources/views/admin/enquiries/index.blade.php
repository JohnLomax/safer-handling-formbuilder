<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Enquiries" description="Search, filter, and open customer forms. Built for large enquiry volumes." />
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel !p-0">
            @include('admin.partials.alerts')

            <div class="space-y-4 border-b border-sh-border/80 px-6 py-5">
                <form method="GET" action="{{ route('admin.enquiries.index') }}" class="admin-search-bar">
                    <div class="min-w-0 flex-1">
                        <label for="enquiry-search" class="sr-only">Search enquiries</label>
                        <input
                            id="enquiry-search"
                            type="search"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Search by name, email, ID, course, sector, Monday ID…"
                            class="admin-search-input"
                            autocomplete="off"
                        />
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label for="per_page" class="sr-only">Rows per page</label>
                        <select
                            id="per_page"
                            name="per_page"
                            class="rounded-[10px] border border-[#b7d3ee] bg-white px-3 py-2 text-sm text-sh-text shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
                        >
                            @foreach ([25, 50, 100] as $size)
                                <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} / page</option>
                            @endforeach
                        </select>

                        @if ($statusFilter !== 'all')
                            <input type="hidden" name="status" value="{{ $statusFilter }}" />
                        @endif

                        <button type="submit" class="btn-brand whitespace-nowrap">Search</button>

                        @if ($search !== '' || $statusFilter !== 'all' || $perPage !== 25)
                            <a href="{{ route('admin.enquiries.index') }}" class="btn-brand-outline whitespace-nowrap">Clear</a>
                        @endif
                    </div>
                </form>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex flex-wrap gap-2">
                        @foreach ([
                            'all' => 'All',
                            'in_progress' => 'In progress',
                            'contacted' => 'Contacted',
                            'quote_sent' => 'Quote Sent',
                            'quote_accepted' => 'Quote Accepted',
                            'submitted' => 'Submitted',
                            'failed' => 'Failed',
                        ] as $value => $label)
                            <a
                                href="{{ route('admin.enquiries.index', array_filter(['q' => $search ?: null, 'status' => $value === 'all' ? null : $value, 'per_page' => $perPage !== 25 ? $perPage : null])) }}"
                                @class([
                                    'admin-filter-chip',
                                    'admin-filter-chip-active' => $statusFilter === $value,
                                    'admin-filter-chip-idle' => $statusFilter !== $value,
                                ])
                            >
                                {{ $label }}
                                <span class="ml-1 opacity-70">{{ number_format($statusCounts[$value] ?? 0) }}</span>
                            </a>
                        @endforeach
                    </div>

                    <p class="text-sm text-sh-mid">
                        Showing
                        <span class="font-semibold text-sh-text">{{ number_format($enquiries->firstItem() ?? 0) }}–{{ number_format($enquiries->lastItem() ?? 0) }}</span>
                        of
                        <span class="font-semibold text-sh-text">{{ number_format($enquiries->total()) }}</span>
                        @if ($search !== '')
                            for “{{ $search }}”
                        @endif
                    </p>
                </div>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th class="w-[28%]">Customer</th>
                            <th class="w-[12%]">Type</th>
                            <th class="w-[12%]">Status</th>
                            <th class="w-[14%]">Started</th>
                            <th class="w-[14%]">Submitted</th>
                            <th class="w-[8%] text-center">Events</th>
                            <th class="w-[12%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($enquiries as $enquiry)
                            <tr>
                                <td>
                                    <div class="flex min-w-0 items-start gap-2.5">
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-medium text-sh-text" title="{{ $enquiry->name }}">
                                                {{ $enquiry->name }}
                                            </div>
                                            <div class="truncate text-xs text-sh-mid" title="{{ $enquiry->email }}">
                                                {{ $enquiry->email }}
                                            </div>
                                            <div class="mt-0.5 text-[11px] text-sh-mid/80">#{{ $enquiry->id }}</div>
                                        </div>
                                        @if ($enquiry->isMondaySynced())
                                            <x-monday-badge compact class="mt-0.5" />
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap text-sh-mid">{{ $enquiry->enquiryTypeLabel() }}</td>
                                <td>
                                    @if ($enquiry->status === 'submitted')
                                        <span class="status-pill status-pill-submitted">Submitted</span>
                                    @elseif ($enquiry->status === 'quote_sent')
                                        <span class="status-pill status-pill-success">Quote Sent</span>
                                    @elseif ($enquiry->status === 'quote_accepted')
                                        <span class="status-pill status-pill-success">Quote Accepted</span>
                                    @elseif ($enquiry->status === 'contacted')
                                        <span class="status-pill status-pill-success">Contacted</span>
                                    @elseif ($enquiry->status === 'failed')
                                        <span class="status-pill status-pill-muted">Failed</span>
                                    @else
                                        <span class="status-pill status-pill-progress">{{ $enquiry->statusLabel() }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sh-mid">
                                    <div>{{ $enquiry->created_at?->format('d M Y') }}</div>
                                    <div class="text-xs">{{ $enquiry->created_at?->format('H:i') }}</div>
                                </td>
                                <td class="whitespace-nowrap text-sh-mid">
                                    @if ($enquiry->submitted_at)
                                        <div>{{ $enquiry->submitted_at->format('d M Y') }}</div>
                                        <div class="text-xs">{{ $enquiry->submitted_at->format('H:i') }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-center text-sh-mid">{{ number_format($enquiry->events_count) }}</td>
                                <td>
                                    <div class="admin-table-actions">
                                        <x-admin.table-action :href="route('admin.enquiries.show', $enquiry)" icon="chevron">
                                            View
                                        </x-admin.table-action>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-sm text-sh-mid">
                                    @if ($search !== '' || $statusFilter !== 'all')
                                        No enquiries match your search.
                                        <a href="{{ route('admin.enquiries.index') }}" class="link-brand ml-1">Clear filters</a>
                                    @else
                                        No enquiries yet. They will appear here when someone uses the form.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($enquiries->hasPages() || $enquiries->total() > 0)
                <div class="flex flex-col gap-3 border-t border-sh-border/80 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-sh-mid">
                        Page {{ $enquiries->currentPage() }} of {{ max(1, $enquiries->lastPage()) }}
                    </p>
                    <div>
                        {{ $enquiries->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
