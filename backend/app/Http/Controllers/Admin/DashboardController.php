<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\TrainingMatrixEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'userCount' => User::query()->count(),
            'adminCount' => User::query()->where('is_admin', true)->count(),
            'matrixCount' => TrainingMatrixEntry::query()->where('is_active', true)->count(),
            'matrixTotal' => TrainingMatrixEntry::query()->count(),
            'enquiryCount' => Enquiry::query()->count(),
            'pendingEnquiries' => Enquiry::query()->where('status', 'in_progress')->count(),
            'mondaySyncedCount' => Enquiry::query()->whereNotNull('monday_synced_at')->count(),
            'recentEnquiries' => Enquiry::query()->latest()->limit(5)->get(),
            'enquiryChart' => $this->enquiryChartData(30),
        ]);
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     counts: list<int>,
     *     total: int,
     *     peak: int,
     *     average: float,
     *     days: int
     * }
     */
    private function enquiryChartData(int $days): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();
        $driver = DB::connection()->getDriverName();
        $dateExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : 'DATE(created_at)';

        $byDay = Enquiry::query()
            ->where('created_at', '>=', $start)
            ->selectRaw("{$dateExpr} as day, COUNT(*) as total")
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr))
            ->pluck('total', 'day');

        $labels = [];
        $counts = [];

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('j M');
            $counts[] = (int) ($byDay[$key] ?? 0);
        }

        $total = array_sum($counts);
        $peak = $counts === [] ? 0 : max($counts);

        return [
            'labels' => $labels,
            'counts' => $counts,
            'total' => $total,
            'peak' => $peak,
            'average' => $days > 0 ? round($total / $days, 1) : 0,
            'days' => $days,
        ];
    }
}
