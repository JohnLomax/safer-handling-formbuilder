@php
    $enquiryQuery = function (array $overrides = []) use ($search, $statusFilter, $tab, $perPage): array {
        return array_filter([
            'q' => array_key_exists('q', $overrides) ? $overrides['q'] : ($search ?: null),
            'status' => array_key_exists('status', $overrides)
                ? $overrides['status']
                : ($statusFilter !== 'all' ? $statusFilter : null),
            'tab' => array_key_exists('tab', $overrides)
                ? $overrides['tab']
                : ($tab !== 'live' ? $tab : null),
            'per_page' => array_key_exists('per_page', $overrides)
                ? $overrides['per_page']
                : ($perPage !== 25 ? $perPage : null),
        ], static fn ($value) => $value !== null && $value !== '');
    };
@endphp

<div
    class="space-y-4 border-b border-sh-border/80 px-6 py-5"
    data-page-ids='@json($enquiries->pluck('id')->map(fn ($id) => (int) $id)->values())'
    data-current-tab="{{ $tab }}"
>
    <div class="inline-flex flex-wrap gap-2 rounded-[12px] border border-sh-border/80 bg-[#f7fbff] p-1">
        @foreach ([
            'live' => 'Live',
            'archive' => 'Archive',
        ] as $value => $label)
            <button
                type="button"
                data-enquiry-tab="{{ $value }}"
                @class([
                    'inline-flex items-center gap-2 rounded-[10px] px-4 py-2 text-sm font-semibold transition',
                    'bg-white text-brand-header shadow-sm' => $tab === $value,
                    'text-sh-mid hover:text-brand-header' => $tab !== $value,
                ])
                @if ($tab === $value) aria-current="page" @endif
            >
                {{ $label }}
                <span
                    data-enquiry-tab-badge
                    @class([
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        'bg-[#e8f4ff] text-brand' => $tab === $value,
                        'bg-white/80 text-sh-mid' => $tab !== $value,
                    ])
                >{{ number_format($tabCounts[$value] ?? 0) }}</span>
            </button>
        @endforeach
    </div>
    <p class="text-xs text-sh-mid" data-enquiry-tab-hint>
        @if ($tab === 'archive')
            Archive shows enquiries older than 30 days.
        @else
            Live shows enquiries from the last 30 days. Older ones move to Archive automatically.
        @endif
    </p>

    <form method="GET" action="{{ route('admin.enquiries.index') }}" class="admin-search-bar">
        @if ($tab === 'archive')
            <input type="hidden" name="tab" value="archive" />
        @endif
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

                        @if ($search !== '' || $statusFilter !== 'all' || $perPage !== 25 || $tab !== 'live')
                <a
                    href="{{ route('admin.enquiries.index', $enquiryQuery(['q' => null, 'status' => null, 'per_page' => null, 'tab' => null])) }}"
                    data-enquiry-clear
                    class="btn-brand-outline whitespace-nowrap"
                >Clear</a>
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
                'quote_won' => 'Quote Won',
                'submitted' => 'Submitted',
                'failed' => 'Failed',
            ] as $value => $label)
                <a
                    href="{{ route('admin.enquiries.index', $enquiryQuery(['status' => $value === 'all' ? null : $value])) }}"
                    data-enquiry-status="{{ $value }}"
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

        <div class="flex flex-wrap items-center gap-3">
            <p class="text-sm text-sh-mid">
                Showing
                <span class="font-semibold text-sh-text">{{ number_format($enquiries->firstItem() ?? 0) }}–{{ number_format($enquiries->lastItem() ?? 0) }}</span>
                of
                <span class="font-semibold text-sh-text">{{ number_format($enquiries->total()) }}</span>
                on
                <span class="font-semibold text-sh-text" data-enquiry-tab-label>{{ $tab === 'archive' ? 'Archive' : 'Live' }}</span>
                @if ($search !== '')
                    for “{{ $search }}”
                @endif
            </p>

            <button
                type="button"
                class="btn-brand-outline whitespace-nowrap text-xs"
                x-show="selectedCount > 0"
                x-cloak
                x-on:click="openBulkDelete()"
            >
                Delete selected (<span x-text="selectedCount"></span>)
            </button>
        </div>
    </div>
</div>

<div class="admin-table-wrap !rounded-none !border-0">
    <table class="admin-table">
        <thead>
            <tr>
                <th class="w-[3rem]">
                    <label class="sr-only" for="select-all-enquiries">Select all on this page</label>
                    <input
                        id="select-all-enquiries"
                        type="checkbox"
                        class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand"
                        x-bind:checked="allSelected"
                        x-on:change="toggleAll($event.target.checked)"
                        @disabled($enquiries->isEmpty())
                    />
                </th>
                <th class="w-[26%]">Customer</th>
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
                        <label class="sr-only" for="enquiry-select-{{ $enquiry->id }}">Select enquiry #{{ $enquiry->id }}</label>
                        <input
                            id="enquiry-select-{{ $enquiry->id }}"
                            type="checkbox"
                            value="{{ $enquiry->id }}"
                            class="rounded border-[#b9d4ef] text-brand shadow-sm focus:ring-brand"
                            x-bind:checked="isSelected({{ (int) $enquiry->id }})"
                            x-on:change="toggleOne({{ (int) $enquiry->id }}, $event.target.checked)"
                        />
                    </td>
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
                            <div class="mt-0.5 flex shrink-0 items-center gap-1">
                                @if ($enquiry->isMondaySynced())
                                    <x-monday-badge compact />
                                @endif
                                @if (in_array($enquiry->status, ['quote_sent', 'quote_accepted', 'quote_won'], true)
                                    || $enquiry->quote_email_sent_at
                                    || filled($enquiry->xero_quote_id))
                                    <x-xero-badge compact />
                                @endif
                                @if ($enquiry->kajabi_enrolled_at || filled($enquiry->kajabi_contact_id))
                                    <x-kajabi-badge compact />
                                @endif
                                @if ($enquiry->hasInvoiceQuoteRecipient())
                                    <span
                                        class="inline-flex items-center rounded-full bg-[#fff8eb] px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-[#9a6700]"
                                        title="Quote/invoice recipient set"
                                    >Invoice to</span>
                                @endif
                            </div>
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
                        @elseif ($enquiry->status === 'quote_won')
                            <span class="status-pill status-pill-success inline-flex items-center gap-1">
                                <svg class="h-3.5 w-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                </svg>
                                Quote Won
                            </span>
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
                            <x-admin.table-action
                                :href="route('admin.enquiries.show', $enquiry)"
                                icon="eye"
                                title="View"
                                aria-label="View"
                            />
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-sm text-sh-mid">
                        @if ($search !== '' || $statusFilter !== 'all')
                            No enquiries match your search on {{ $tab === 'archive' ? 'Archive' : 'Live' }}.
                            <a href="{{ route('admin.enquiries.index', $enquiryQuery(['q' => null, 'status' => null])) }}" data-enquiry-clear class="link-brand ml-1">Clear filters</a>
                        @elseif ($tab === 'archive')
                            No archived enquiries yet. Enquiries older than 30 days appear here.
                        @else
                            No live enquiries in the last 30 days. Older ones are under Archive.
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
