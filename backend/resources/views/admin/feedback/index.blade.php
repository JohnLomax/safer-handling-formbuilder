<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Feedback" description="Review issues submitted through the public feedback form.">
            <x-slot name="actions">
                <a href="{{ route('admin.feedback.export', request()->only('status')) }}" class="btn-brand-outline">
                    Export spreadsheet
                </a>
            </x-slot>
        </x-admin.page-header>
    </x-slot>

    <div class="admin-shell">
        <div class="brand-panel !p-0">
            @include('admin.partials.alerts')

            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-sh-border/80 px-6 py-4">
                <p class="text-sm text-sh-mid">
                    {{ $feedbackItems->total() }} submissions
                    @if ($statusFilter === 'open')
                        · {{ $openCount }} open
                    @elseif ($statusFilter === 'resolved')
                        · {{ $resolvedCount }} resolved
                    @else
                        · {{ $openCount }} open · {{ $resolvedCount }} resolved
                    @endif
                </p>

                <div class="inline-flex flex-wrap gap-2">
                    <a href="{{ route('admin.feedback.index') }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $statusFilter === 'all',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $statusFilter !== 'all',
                    ])>All</a>
                    <a href="{{ route('admin.feedback.index', ['status' => 'open']) }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $statusFilter === 'open',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $statusFilter !== 'open',
                    ])>Open</a>
                    <a href="{{ route('admin.feedback.index', ['status' => 'resolved']) }}" @class([
                        'rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                        'border-brand bg-[#eef6ff] text-brand-header' => $statusFilter === 'resolved',
                        'border-sh-border bg-white text-sh-mid hover:border-brand hover:text-brand' => $statusFilter !== 'resolved',
                    ])>Resolved</a>
                </div>
            </div>

            <div class="admin-table-wrap !rounded-none !border-0">
                <table class="admin-table admin-table-fixed">
                    <thead>
                        <tr>
                            <th class="w-[14%]">Submitted</th>
                            <th class="w-[22%]">Issue faced</th>
                            <th class="w-[38%]">Description</th>
                            <th class="w-[12%]">Status</th>
                            <th class="w-[14%]">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($feedbackItems as $feedback)
                            <tr>
                                <td class="text-sh-mid">{{ $feedback->created_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="admin-table-cell-truncate font-medium text-sh-text" title="{{ $feedback->issue_faced }}">{{ $feedback->issue_faced }}</td>
                                <td class="admin-table-cell-truncate text-sh-mid" title="{{ $feedback->description }}">{{ $feedback->description }}</td>
                                <td>
                                    @if ($feedback->isResolved())
                                        <span class="status-pill status-pill-success">Resolved</span>
                                    @else
                                        <span class="status-pill status-pill-progress">Open</span>
                                    @endif
                                </td>
                                <td>
                                    @if (! $feedback->isResolved())
                                        <form action="{{ route('admin.feedback.resolve', $feedback) }}{{ request('status') ? '?status=' . urlencode(request('status')) : '' }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn-table-action">Mark resolved</button>
                                        </form>
                                    @else
                                        <span class="text-xs text-sh-mid">{{ $feedback->resolved_at?->format('d M Y H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-sh-mid">
                                    No feedback submissions yet. They will appear here when someone uses the feedback form.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4">
                {{ $feedbackItems->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
