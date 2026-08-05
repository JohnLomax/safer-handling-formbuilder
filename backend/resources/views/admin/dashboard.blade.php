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

        <div
            id="enquiry-chart-panel"
            class="brand-panel !p-0 overflow-hidden"
            data-chart-endpoint="{{ $chartEndpoint }}"
            data-chart-preset="{{ $chartPreset }}"
        >
            <div class="border-b border-sh-border/80 bg-gradient-to-r from-[#f3f9ff] via-white to-[#f7fbff] px-6 py-5">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-lg font-semibold text-brand-header">Enquiry activity</h3>
                        <p id="enquiry-chart-summary" class="mt-1 text-sm text-sh-mid">
                            {{ $enquiryChart['days'] }} day{{ $enquiryChart['days'] === 1 ? '' : 's' }}
                            <span class="text-sh-mid/80">· {{ $enquiryChart['rangeLabel'] }}</span>
                        </p>
                    </div>

                    <form id="enquiry-chart-form" class="flex w-full flex-col gap-3 lg:w-auto">
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Chart range presets">
                            @foreach ([
                                '7' => '7 days',
                                '30' => '30 days',
                                '90' => '90 days',
                            ] as $value => $label)
                                <button
                                    type="button"
                                    data-chart-preset="{{ $value }}"
                                    @class([
                                        'admin-filter-chip chart-preset-btn',
                                        'admin-filter-chip-active' => $chartPreset === $value,
                                        'admin-filter-chip-idle' => $chartPreset !== $value,
                                    ])
                                >{{ $label }}</button>
                            @endforeach
                            <span
                                id="chart-custom-chip"
                                @class([
                                    'admin-filter-chip pointer-events-none',
                                    'admin-filter-chip-active' => $chartPreset === 'custom',
                                    'admin-filter-chip-idle' => $chartPreset !== 'custom',
                                ])
                            >Custom</span>
                        </div>

                        <div class="flex flex-wrap items-end gap-2">
                            <div>
                                <label for="chart-from" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-sh-mid">From</label>
                                <input
                                    id="chart-from"
                                    type="date"
                                    name="from"
                                    value="{{ $chartFrom }}"
                                    max="{{ now()->toDateString() }}"
                                    class="rounded-[10px] border border-[#b7d3ee] bg-white px-3 py-2 text-sm text-sh-text shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
                                >
                            </div>
                            <div>
                                <label for="chart-to" class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-sh-mid">To</label>
                                <input
                                    id="chart-to"
                                    type="date"
                                    name="to"
                                    value="{{ $chartTo }}"
                                    max="{{ now()->toDateString() }}"
                                    class="rounded-[10px] border border-[#b7d3ee] bg-white px-3 py-2 text-sm text-sh-text shadow-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
                                >
                            </div>
                            <button type="submit" id="chart-apply-btn" class="btn-brand">Apply</button>
                        </div>
                        <p id="enquiry-chart-status" class="min-h-[1.25rem] text-xs text-sh-mid" aria-live="polite"></p>
                    </form>
                </div>
            </div>

            <div class="relative bg-[radial-gradient(circle_at_top_right,rgba(0,138,252,0.08),transparent_42%),linear-gradient(180deg,#ffffff_0%,#f5faff_100%)] px-4 py-5 sm:px-7 sm:py-7">
                <div class="rounded-[16px] border border-[#d7e8f7] bg-white/80 p-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.8)] sm:p-6">
                    <div class="relative h-[20rem] w-full sm:h-[24rem]">
                        <canvas id="enquiry-trend-chart" aria-label="Enquiry trend chart"></canvas>
                        <div
                            id="enquiry-chart-loading"
                            class="pointer-events-none absolute inset-0 hidden items-center justify-center rounded-[12px] bg-white/55 backdrop-blur-[1px]"
                        >
                            <span class="rounded-full border border-[#d5e7f8] bg-white px-3 py-1.5 text-xs font-semibold text-brand-header shadow-sm">
                                Updating chart…
                            </span>
                        </div>
                    </div>
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

            const panel = document.getElementById('enquiry-chart-panel');
            const trendEl = document.getElementById('enquiry-trend-chart');
            const form = document.getElementById('enquiry-chart-form');
            const fromInput = document.getElementById('chart-from');
            const toInput = document.getElementById('chart-to');
            const summaryEl = document.getElementById('enquiry-chart-summary');
            const statusEl = document.getElementById('enquiry-chart-status');
            const loadingEl = document.getElementById('enquiry-chart-loading');
            const customChip = document.getElementById('chart-custom-chip');
            const applyBtn = document.getElementById('chart-apply-btn');
            if (!panel || !trendEl || !form || !fromInput || !toInput) return;

            const endpoint = panel.dataset.chartEndpoint;
            let chartData = @json($enquiryChart);
            let activePreset = panel.dataset.chartPreset || '30';
            let fetchController = null;

            const ctx = trendEl.getContext('2d');
            const chartHeight = trendEl.parentElement?.clientHeight || 384;
            const barFill = ctx.createLinearGradient(0, 0, 0, chartHeight);
            barFill.addColorStop(0, '#008afc');
            barFill.addColorStop(1, '#0255a4');
            const weekendFill = ctx.createLinearGradient(0, 0, 0, chartHeight);
            weekendFill.addColorStop(0, '#7cbcf0');
            weekendFill.addColorStop(1, '#4a93c9');

            const hoverLine = {
                id: 'enquiryHoverLine',
                afterDatasetsDraw(chart) {
                    const active = chart.getActiveElements();
                    if (!active.length) return;
                    const { ctx: c, chartArea } = chart;
                    const x = active[0].element.x;
                    c.save();
                    c.beginPath();
                    c.setLineDash([4, 4]);
                    c.moveTo(x, chartArea.top);
                    c.lineTo(x, chartArea.bottom);
                    c.lineWidth = 1;
                    c.strokeStyle = 'rgba(2, 85, 164, 0.35)';
                    c.stroke();
                    c.restore();
                },
            };

            const chart = new Chart(ctx, {
                type: 'bar',
                plugins: [hoverLine],
                data: {
                    labels: chartData.labels,
                    datasets: [{
                        label: 'Enquiries',
                        data: chartData.counts,
                        backgroundColor: (item) => (
                            chartData.weekends?.[item.dataIndex] ? weekendFill : barFill
                        ),
                        hoverBackgroundColor: '#0478d8',
                        borderRadius: {
                            topLeft: 8,
                            topRight: 8,
                            bottomLeft: 2,
                            bottomRight: 2,
                        },
                        borderSkipped: false,
                        maxBarThickness: chartData.days > 90 ? 10 : (chartData.days > 45 ? 14 : 22),
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 450,
                        easing: 'easeOutQuart',
                    },
                    interaction: { mode: 'index', intersect: false },
                    layout: {
                        padding: { top: 16, right: 6, bottom: 2, left: 2 },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f3d66',
                            titleColor: '#ffffff',
                            bodyColor: '#dbeafe',
                            titleFont: { size: 13, weight: '600' },
                            bodyFont: { size: 13, weight: '500' },
                            padding: 12,
                            cornerRadius: 10,
                            displayColors: false,
                            caretSize: 6,
                            callbacks: {
                                title: (items) => {
                                    const index = items[0]?.dataIndex ?? 0;
                                    return chartData.tooltipLabels?.[index] || items[0]?.label || '';
                                },
                                label: (item) => {
                                    const n = item.parsed.y || 0;
                                    return `${n} new enquir${n === 1 ? 'y' : 'ies'}`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: {
                                color: '#6b8aa8',
                                maxRotation: chartData.days > 90 ? 45 : 0,
                                minRotation: 0,
                                autoSkip: false,
                                font: { size: chartData.days > 90 ? 10 : 11, weight: '500' },
                                padding: 8,
                                callback(value, index) {
                                    const label = chartData.labels[index] ?? '';
                                    const step = chartData.labelStep || 3;
                                    if (index === 0 || index === chartData.labels.length - 1 || index % step === 0) {
                                        return label;
                                    }
                                    return '';
                                },
                            },
                            border: { display: false },
                        },
                        y: {
                            beginAtZero: true,
                            grace: '12%',
                            suggestedMax: Math.max(4, ...(chartData.counts || [0])),
                            ticks: {
                                color: '#6b8aa8',
                                precision: 0,
                                stepSize: 1,
                                font: { size: 12, weight: '500' },
                                padding: 12,
                            },
                            grid: {
                                color: 'rgba(185, 212, 239, 0.45)',
                                drawTicks: false,
                            },
                            border: { display: false },
                        },
                    },
                },
            });

            function setLoading(isLoading) {
                if (loadingEl) {
                    loadingEl.classList.toggle('hidden', !isLoading);
                    loadingEl.classList.toggle('flex', isLoading);
                }
                form.querySelectorAll('button, input').forEach((el) => {
                    el.disabled = isLoading;
                });
            }

            function setStatus(message, isError = false) {
                if (!statusEl) return;
                statusEl.textContent = message || '';
                statusEl.classList.toggle('text-[#b91c1c]', isError);
                statusEl.classList.toggle('text-sh-mid', !isError);
            }

            function updateSummary(data) {
                if (!summaryEl) return;
                const days = data.days || 0;
                summaryEl.innerHTML = `${days} day${days === 1 ? '' : 's'} <span class="text-sh-mid/80">· ${data.rangeLabel || ''}</span>`;
            }

            function updatePresetUi(preset) {
                activePreset = preset;
                panel.dataset.chartPreset = preset;
                form.querySelectorAll('.chart-preset-btn').forEach((btn) => {
                    const isActive = btn.dataset.chartPreset === preset;
                    btn.classList.toggle('admin-filter-chip-active', isActive);
                    btn.classList.toggle('admin-filter-chip-idle', !isActive);
                });
                if (customChip) {
                    const isCustom = preset === 'custom';
                    customChip.classList.toggle('admin-filter-chip-active', isCustom);
                    customChip.classList.toggle('admin-filter-chip-idle', !isCustom);
                }
            }

            function syncUrl(params) {
                const url = new URL(window.location.href);
                ['range', 'from', 'to'].forEach((key) => url.searchParams.delete(key));
                Object.entries(params).forEach(([key, value]) => {
                    if (value) url.searchParams.set(key, value);
                });
                window.history.replaceState({}, '', url.toString());
            }

            function applyChartData(next) {
                chartData = next;
                chart.data.labels = next.labels || [];
                chart.data.datasets[0].data = next.counts || [];
                chart.data.datasets[0].maxBarThickness = next.days > 90 ? 10 : (next.days > 45 ? 14 : 22);
                chart.options.scales.x.ticks.maxRotation = next.days > 90 ? 45 : 0;
                chart.options.scales.x.ticks.font.size = next.days > 90 ? 10 : 11;
                chart.options.scales.y.suggestedMax = Math.max(4, ...(next.counts || [0]));
                chart.update('active');
                updateSummary(next);
            }

            async function loadChart({ range, from, to }) {
                if (!endpoint) return;

                if (fetchController) {
                    fetchController.abort();
                }
                fetchController = new AbortController();

                const params = new URLSearchParams();
                if (range) params.set('range', range);
                if (from) params.set('from', from);
                if (to) params.set('to', to);

                setLoading(true);
                setStatus('Loading…');

                try {
                    const response = await fetch(`${endpoint}?${params.toString()}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        signal: fetchController.signal,
                        credentials: 'same-origin',
                    });
                    if (!response.ok) {
                        throw new Error('Could not load chart data.');
                    }
                    const payload = await response.json();
                    applyChartData(payload.chart || {});
                    if (payload.from) fromInput.value = payload.from;
                    if (payload.to) toInput.value = payload.to;
                    updatePresetUi(payload.preset || range || 'custom');
                    syncUrl(
                        payload.preset && payload.preset !== 'custom'
                            ? { range: payload.preset }
                            : { range: 'custom', from: payload.from, to: payload.to }
                    );
                    setStatus('');
                } catch (error) {
                    if (error?.name === 'AbortError') return;
                    setStatus(error?.message || 'Could not update the chart.', true);
                } finally {
                    setLoading(false);
                    if (applyBtn) applyBtn.disabled = false;
                }
            }

            form.querySelectorAll('.chart-preset-btn').forEach((btn) => {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    const range = btn.dataset.chartPreset;
                    loadChart({ range });
                });
            });

            form.addEventListener('submit', (event) => {
                event.preventDefault();
                loadChart({
                    range: 'custom',
                    from: fromInput.value,
                    to: toInput.value,
                });
            });
        })();
    </script>
</x-app-layout>
