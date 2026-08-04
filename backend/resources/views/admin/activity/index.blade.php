<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header
            title="Activity log"
            description="Monitor internal user logins (with IP) and changes made in the admin portal."
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
                        placeholder="User, IP, description…"
                        class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm"
                    />
                </div>

                <div class="min-w-[140px]">
                    <label for="activity-action" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-sh-mid">Action</label>
                    <select id="activity-action" name="action" class="w-full rounded-[10px] border border-sh-border px-3 py-2 text-sm">
                        <option value="all" @selected($actionFilter === 'all')>All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected($actionFilter === $action)>{{ ucfirst($action) }}</option>
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
                            <th class="w-[16%]">When</th>
                            <th class="w-[18%]">User</th>
                            <th class="w-[12%]">Action</th>
                            <th class="w-[34%]">Details</th>
                            <th class="w-[20%]">IP / device</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="text-sh-mid whitespace-nowrap">
                                    {{ $log->created_at?->format('d M Y H:i:s') ?? '—' }}
                                </td>
                                <td>
                                    <div class="font-medium text-sh-text">{{ $log->user_name ?: '—' }}</div>
                                    <div class="text-xs text-sh-mid">{{ $log->user_email }}</div>
                                </td>
                                <td>
                                    @php
                                        $pill = match ($log->action) {
                                            'login', 'create' => 'status-pill-success',
                                            'logout', 'delete' => 'status-pill-muted',
                                            default => 'status-pill-progress',
                                        };
                                    @endphp
                                    <span class="status-pill {{ $pill }}">{{ ucfirst($log->action) }}</span>
                                </td>
                                <td>
                                    <div class="text-sh-text">{{ $log->description }}</div>
                                    @if ($log->route_name)
                                        <div class="mt-0.5 text-xs text-sh-mid">{{ $log->route_name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="font-medium text-sh-text">{{ $log->ip_address ?: '—' }}</div>
                                    @if ($log->user_agent)
                                        <div class="mt-0.5 text-xs text-sh-mid admin-table-cell-truncate" title="{{ $log->user_agent }}">
                                            {{ \Illuminate\Support\Str::limit($log->user_agent, 48) }}
                                        </div>
                                    @endif
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
