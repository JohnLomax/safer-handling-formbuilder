<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Activity log"
            description="Audit trail of admin sign-ins and changes — enquiries, Training Matrix, users, and settings."
        />
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel !p-0">
            @include('admin.partials.alerts')

            <form method="GET" action="{{ route('admin.activity.index') }}" class="flex flex-wrap items-end gap-3 border-b border-sh-border/80 px-6 py-4">
                <div class="min-w-[180px] flex-1">
                    <label for="activity-q" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sh-mid">Search</label>
                    <input
                        id="activity-q"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="User, enquiry, course, IP…"
                        class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm"
                    />
                </div>

                <div class="min-w-[150px]">
                    <label for="activity-action" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sh-mid">Action</label>
                    <select id="activity-action" name="action" class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm">
                        <option value="all" @selected($actionFilter === 'all')>All actions</option>
                        @foreach ($actionOptions as $actionValue => $actionLabel)
                            <option value="{{ $actionValue }}" @selected($actionFilter === $actionValue)>{{ $actionLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[160px]">
                    <label for="activity-area" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sh-mid">Area</label>
                    <select id="activity-area" name="area" class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm">
                        <option value="all" @selected($areaFilter === 'all')>All areas</option>
                        @foreach ($areaOptions as $areaValue => $areaLabel)
                            <option value="{{ $areaValue }}" @selected($areaFilter === $areaValue)>{{ $areaLabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="min-w-[180px]">
                    <label for="activity-user" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sh-mid">User</label>
                    <select id="activity-user" name="user" class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm">
                        <option value="all" @selected($userFilter === 'all')>All users</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) $userFilter === (string) $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="submit" class="btn-brand">Filter</button>
                    <a href="{{ route('admin.activity.index') }}" class="btn-brand-outline">Reset</a>
                </div>
            </form>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[14%]">When</th>
                            <th class="w-[16%]">User</th>
                            <th class="w-[14%]">Action</th>
                            <th class="w-[38%]">Details</th>
                            <th class="w-[18%]">IP / device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            @php
                                $detailRows = $log->detailRows();
                                $areaLabel = $log->areaLabel();
                                $subjectUrl = $log->subjectUrl();
                            @endphp
                            <tr>
                                <td class="text-sh-mid whitespace-nowrap align-top">
                                    <div class="font-medium text-sh-text">{{ $log->created_at?->format('d M Y') ?? '—' }}</div>
                                    <div class="text-xs">{{ $log->created_at?->format('H:i:s') ?? '' }}</div>
                                </td>
                                <td class="align-top">
                                    <div class="font-medium text-sh-text">{{ $log->user_name ?: '—' }}</div>
                                    <div class="text-xs text-sh-mid">{{ $log->user_email }}</div>
                                </td>
                                <td class="align-top">
                                    <span class="status-pill {{ $log->actionPillClass() }}">{{ $log->actionLabel() }}</span>
                                    @if ($areaLabel)
                                        <div class="mt-1.5 text-[11px] font-semibold uppercase tracking-wide text-sh-mid">{{ $areaLabel }}</div>
                                    @endif
                                </td>
                                <td class="align-top">
                                    <div class="text-sm font-medium text-sh-text">{{ $log->description }}</div>
                                    @if ($subjectUrl)
                                        <div class="mt-1">
                                            <a href="{{ $subjectUrl }}" class="link-brand text-xs">Open related record</a>
                                        </div>
                                    @endif
                                    @if (count($detailRows) > 0)
                                        <div class="mt-2 space-y-1">
                                            @foreach ($detailRows as $row)
                                                <div class="text-xs text-sh-mid">
                                                    <span class="font-semibold text-sh-text/80">{{ $row['label'] }}:</span>
                                                    {{ $row['value'] }}
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="align-top">
                                    <div class="font-medium text-sh-text">{{ $log->ip_address ?: '—' }}</div>
                                    <div class="mt-0.5 text-xs text-sh-mid" title="{{ $log->user_agent }}">
                                        {{ $log->deviceSummary() }}
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-sm text-sh-mid">
                                    No activity recorded yet. Logins and admin changes will appear here.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($logs->hasPages())
                <div class="border-t border-sh-border/80 px-6 py-4">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
