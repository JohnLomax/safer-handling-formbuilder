<x-app-layout>
    <x-slot name="header">
        <x-admin.page-header title="Dashboard" description="Overview of enquiries, users, and form configuration." />
    </x-slot>

    <div class="admin-shell space-y-6">
        @include('admin.partials.alerts')

        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
        </div>

        <div class="brand-panel !p-0 overflow-hidden">
            <div class="flex flex-col gap-5 border-b border-sh-border/80 bg-gradient-to-r from-[#f7fbff] to-white px-6 py-5 sm:flex-row sm:items-end sm:justify-between">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-brand-header">Enquiry activity</h3>
                    <p class="mt-1 text-sm text-sh-mid">New enquiries over the last {{ $enquiryChart['days'] }} days.</p>
                </div>
                <div class="grid grid-cols-3 gap-3 sm:min-w-[22rem]">
                    <div class="rounded-[12px] border border-[#d5e7f8] bg-white px-3 py-2.5 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sh-mid">Total</p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums text-brand-header">{{ $enquiryChart['total'] }}</p>
                    </div>
                    <div class="rounded-[12px] border border-[#d5e7f8] bg-white px-3 py-2.5 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sh-mid">Daily avg</p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums text-brand-header">{{ $enquiryChart['average'] }}</p>
                    </div>
                    <div class="rounded-[12px] border border-[#d5e7f8] bg-white px-3 py-2.5 text-center shadow-sm">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-sh-mid">Peak day</p>
                        <p class="mt-0.5 text-2xl font-semibold tabular-nums text-brand-header">{{ $enquiryChart['peak'] }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-[linear-gradient(180deg,#ffffff_0%,#f7fbff_100%)] px-5 py-6 sm:px-8 sm:py-8">
                <div class="relative h-[22rem] w-full sm:h-[26rem]">
                    <canvas id="enquiry-trend-chart" aria-label="Enquiry trend chart"></canvas>
                </div>
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
                                            <div class="flex shrink-0 items-center gap-1">
                                                @if ($enquiry->isMondaySynced())
                                                    <x-monday-badge compact />
                                                @endif
                                                @if (in_array($enquiry->status, ['quote_sent', 'quote_accepted'], true)
                                                    || $enquiry->quote_email_sent_at
                                                    || filled($enquiry->xero_quote_id))
                                                    <x-xero-badge compact />
                                                @endif
                                            </div>
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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            if (typeof Chart === 'undefined') return;

            const chartData = @json($enquiryChart);

            const trendEl = document.getElementById('enquiry-trend-chart');
            if (trendEl) {
                const ctx = trendEl.getContext('2d');
                const chartHeight = trendEl.parentElement?.clientHeight || 416;
                const gradient = ctx.createLinearGradient(0, 0, 0, chartHeight);
                gradient.addColorStop(0, 'rgba(0, 138, 252, 0.28)');
                gradient.addColorStop(0.45, 'rgba(0, 138, 252, 0.1)');
                gradient.addColorStop(1, 'rgba(0, 138, 252, 0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: chartData.labels,
                        datasets: [{
                            label: 'Enquiries',
                            data: chartData.counts,
                            borderColor: '#008afc',
                            backgroundColor: gradient,
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#008afc',
                            pointBorderWidth: 2,
                            pointHoverBackgroundColor: '#0255a4',
                            pointHoverBorderColor: '#ffffff',
                            pointHoverBorderWidth: 2,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        layout: {
                            padding: { top: 12, right: 8, bottom: 4, left: 4 },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#133a59',
                                titleColor: '#ffffff',
                                bodyColor: '#dbeafe',
                                padding: 12,
                                cornerRadius: 10,
                                displayColors: false,
                                callbacks: {
                                    label: (item) => `${item.parsed.y} enquir${item.parsed.y === 1 ? 'y' : 'ies'}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                offset: true,
                                grid: { display: false },
                                ticks: {
                                    color: '#6b8aa8',
                                    maxRotation: 0,
                                    autoSkip: true,
                                    maxTicksLimit: 10,
                                    font: { size: 12, weight: '500' },
                                    padding: 10,
                                },
                                border: { display: false },
                            },
                            y: {
                                beginAtZero: true,
                                grace: '18%',
                                suggestedMax: Math.max(3, ...(chartData.counts || [0])),
                                ticks: {
                                    color: '#6b8aa8',
                                    precision: 0,
                                    stepSize: 1,
                                    font: { size: 12, weight: '500' },
                                    padding: 10,
                                },
                                grid: {
                                    color: 'rgba(185, 212, 239, 0.55)',
                                    drawTicks: false,
                                },
                                border: { display: false },
                            },
                        },
                    },
                });
            }

        })();
    </script>
</x-app-layout>
