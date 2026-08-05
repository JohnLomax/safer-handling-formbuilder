<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\TrainingMatrixEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MAX_CHART_DAYS = 366;

    public function index(Request $request): View
    {
        $payload = $this->chartPayload($request);

        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'adminCount' => User::query()->where('is_admin', true)->count(),
            'matrixCount' => TrainingMatrixEntry::query()->where('is_active', true)->count(),
            'matrixTotal' => TrainingMatrixEntry::query()->count(),
            'enquiryCount' => Enquiry::query()->count(),
            'pendingEnquiries' => Enquiry::query()->where('status', 'in_progress')->count(),
            'mondaySyncedCount' => Enquiry::query()->whereNotNull('monday_synced_at')->count(),
            'recentEnquiries' => Enquiry::query()->latest()->limit(5)->get(),
            'enquiryChart' => $payload['chart'],
            'chartFrom' => $payload['from'],
            'chartTo' => $payload['to'],
            'chartPreset' => $payload['preset'],
            'chartEndpoint' => route('admin.dashboard.chart'),
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        return response()->json($this->chartPayload($request));
    }

    /**
     * @return array{chart: array<string, mixed>, from: string, to: string, preset: string}
     */
    private function chartPayload(Request $request): array
    {
        $range = $this->resolveChartRange($request);

        return [
            'chart' => $this->enquiryChartData($range['start'], $range['end']),
            'from' => $range['start']->toDateString(),
            'to' => $range['end']->toDateString(),
            'preset' => $range['preset'],
        ];
    }

    /**
     * @return array{start: Carbon, end: Carbon, preset: string}
     */
    private function resolveChartRange(Request $request): array
    {
        $today = Carbon::today();
        $preset = (string) $request->query('range', '30');

        if (in_array($preset, ['7', '30', '90'], true)) {
            $days = (int) $preset;

            return [
                'start' => $today->copy()->subDays($days - 1)->startOfDay(),
                'end' => $today->copy()->endOfDay(),
                'preset' => $preset,
            ];
        }

        $start = $this->parseDateOrDefault(
            $request->query('from'),
            $today->copy()->subDays(29)->startOfDay()
        )->startOfDay();

        $end = $this->parseDateOrDefault(
            $request->query('to'),
            $today->copy()->endOfDay()
        )->endOfDay();

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($end->greaterThan($today->copy()->endOfDay())) {
            $end = $today->copy()->endOfDay();
        }

        $dayCount = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        if ($dayCount > self::MAX_CHART_DAYS) {
            $start = $end->copy()->subDays(self::MAX_CHART_DAYS - 1)->startOfDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'preset' => 'custom',
        ];
    }

    private function parseDateOrDefault(mixed $value, Carbon $default): Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return $default->copy();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return $default->copy();
        }
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     tooltipLabels: list<string>,
     *     counts: list<int>,
     *     weekends: list<bool>,
     *     total: int,
     *     peak: int,
     *     average: float,
     *     days: int,
     *     rangeLabel: string,
     *     labelStep: int
     * }
     */
    private function enquiryChartData(Carbon $start, Carbon $end): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $days = (int) $start->diffInDays($end->copy()->startOfDay()) + 1;

        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $byDay = Enquiry::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', $end)
            ->selectRaw("{$dateExpr} as day, COUNT(*) as total")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr))
            ->pluck('total', 'day');

        $labels = [];
        $tooltipLabels = [];
        $counts = [];
        $weekends = [];
        $useMonthLabels = $days > 45;

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $useMonthLabels ? $day->format('j M') : $day->format('j');
            $tooltipLabels[] = $day->format('l, j F Y');
            $counts[] = (int) ($byDay[$key] ?? 0);
            $weekends[] = $day->isWeekend();
        }

        $total = array_sum($counts);
        $peak = $counts === [] ? 0 : max($counts);
        $labelStep = match (true) {
            $days <= 14 => 1,
            $days <= 31 => 3,
            $days <= 90 => 7,
            default => 14,
        };

        return [
            'labels' => $labels,
            'tooltipLabels' => $tooltipLabels,
            'counts' => $counts,
            'weekends' => $weekends,
            'total' => $total,
            'peak' => $peak,
            'average' => $days > 0 ? round($total / $days, 1) : 0,
            'days' => $days,
            'rangeLabel' => $start->format('j M Y').' – '.$end->format('j M Y'),
            'labelStep' => $labelStep,
        ];
    }
}
