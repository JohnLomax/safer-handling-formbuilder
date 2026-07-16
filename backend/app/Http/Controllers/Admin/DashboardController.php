<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\TrainingMatrixEntry;
use App\Models\User;
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
        ]);
    }
}
