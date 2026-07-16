<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Dashboard" description="Overview of enquiries, users, and form configuration." />
    </x-slot>

    <div class="admin-shell space-y-6">
        @include('admin.partials.alerts')

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">Enquiries</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $enquiryCount }}</p>
                <p class="mt-1 text-xs text-sh-mid">{{ $pendingEnquiries }} in progress · {{ $mondaySyncedCount }} on Monday</p>
            </div>
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">Users</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $userCount }}</p>
                <p class="mt-1 text-xs text-sh-mid">{{ $adminCount }} admin accounts</p>
            </div>
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">Training matrix</p>
                <p class="mt-2 text-3xl font-semibold text-sh-text">{{ $matrixCount }}</p>
                <p class="mt-1 text-xs text-sh-mid">{{ $matrixTotal }} total rows configured</p>
            </div>
            <div class="admin-stat-card">
                <p class="text-sm font-medium text-sh-mid">Form API</p>
                <p class="mt-2 text-sm font-semibold leading-snug text-sh-text break-all">{{ url('/api/training-matrix') }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-5">
            <div class="brand-panel lg:col-span-3 !p-0">
                <div class="flex items-center justify-between border-b border-sh-border/80 px-6 py-4">
                    <div>
                        <h3 class="text-base font-semibold text-brand-header">Recent enquiries</h3>
                        <p class="mt-1 text-sm text-sh-mid">Latest activity from the public form.</p>
                    </div>
                    <a href="{{ route('admin.enquiries.index') }}" class="link-brand no-underline hover:underline">View all</a>
                </div>

                <div class="admin-table-wrap !rounded-none !border-0">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th> </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentEnquiries as $enquiry)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <div class="font-medium text-sh-text">{{ $enquiry->name }}</div>
                                                <div class="text-xs text-sh-mid">{{ $enquiry->email }}</div>
                                            </div>
                                            @if ($enquiry->isMondaySynced())
                                                <x-monday-badge compact />
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($enquiry->status === 'submitted')
                                            <span class="status-pill status-pill-submitted">Submitted</span>
                                        @elseif ($enquiry->status === 'quote_sent')
                                            <span class="status-pill status-pill-success">Quote Sent</span>
                                        @elseif ($enquiry->status === 'quote_accepted')
                                            <span class="status-pill status-pill-success">Quote Accepted</span>
                                        @elseif ($enquiry->status === 'contacted')
                                            <span class="status-pill status-pill-success">Contacted</span>
                                        @else
                                            <span class="status-pill status-pill-progress">{{ $enquiry->statusLabel() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-sh-mid">{{ $enquiry->created_at?->format('d M Y H:i') }}</td>
                                    <td>
                                        <x-admin.table-action :href="route('admin.enquiries.show', $enquiry)" icon="chevron">
                                            View
                                        </x-admin.table-action>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-sm text-sh-mid">No enquiries yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="brand-panel lg:col-span-2">
                <h3 class="text-base font-semibold text-brand-header">Quick actions</h3>
                <div class="mt-4 grid gap-2">
                    <a href="{{ route('admin.enquiries.index') }}" class="rounded-[12px] border border-sh-border bg-white px-4 py-3 text-sm font-semibold text-sh-text transition hover:border-brand hover:text-brand">View enquiries</a>
                    <a href="{{ route('admin.training-matrix.index') }}" class="rounded-[12px] border border-sh-border bg-white px-4 py-3 text-sm font-semibold text-sh-text transition hover:border-brand hover:text-brand">Manage training matrix</a>
                    <a href="{{ route('admin.settings.edit') }}" class="rounded-[12px] border border-sh-border bg-white px-4 py-3 text-sm font-semibold text-sh-text transition hover:border-brand hover:text-brand">Integration settings</a>
                    <a href="{{ route('admin.users.index', ['modal' => 'create']) }}" class="rounded-[12px] border border-sh-border bg-white px-4 py-3 text-sm font-semibold text-sh-text transition hover:border-brand hover:text-brand">Add admin user</a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
