<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $actionFilter = (string) $request->query('action', 'all');
        $areaFilter = (string) $request->query('area', 'all');
        $userFilter = $request->query('user');
        $search = trim((string) $request->query('q', ''));

        $query = ActivityLog::query()
            ->with('user')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($actionFilter !== '' && $actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        if ($userFilter !== null && $userFilter !== '' && $userFilter !== 'all') {
            $query->where('user_id', (int) $userFilter);
        }

        if ($areaFilter !== '' && $areaFilter !== 'all') {
            $query->where(function ($builder) use ($areaFilter): void {
                $builder->where('properties->area', $areaFilter);

                if ($areaFilter === 'Training Matrix') {
                    $builder->orWhere('route_name', 'like', '%training-matrix%')
                        ->orWhere('subject_type', 'like', '%TrainingMatrixEntry%');
                } elseif ($areaFilter === 'Enquiry') {
                    $builder->orWhere('route_name', 'like', '%enquiries%')
                        ->orWhere('subject_type', 'like', '%Enquiry%');
                } elseif ($areaFilter === 'Users') {
                    $builder->orWhere('route_name', 'like', '%users%')
                        ->orWhere('subject_type', 'like', '%User%');
                } elseif ($areaFilter === 'Settings') {
                    $builder->orWhere('route_name', 'like', '%settings%');
                } elseif ($areaFilter === 'Feedback') {
                    $builder->orWhere('route_name', 'like', '%feedback%');
                } elseif ($areaFilter === 'Account') {
                    $builder->orWhereIn('action', ['login', 'logout']);
                }
            });
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('description', 'like', '%'.$search.'%')
                    ->orWhere('user_name', 'like', '%'.$search.'%')
                    ->orWhere('user_email', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('route_name', 'like', '%'.$search.'%')
                    ->orWhere('action', 'like', '%'.$search.'%')
                    ->orWhere('properties', 'like', '%'.$search.'%');
            });
        }

        $actionOptions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->mapWithKeys(function (string $action): array {
                $label = (new ActivityLog(['action' => $action]))->actionLabel();

                return [$action => $label];
            });

        return view('admin.activity.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actionOptions' => $actionOptions,
            'areaOptions' => $this->areaOptions(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'actionFilter' => $actionFilter,
            'areaFilter' => $areaFilter,
            'userFilter' => $userFilter ?: 'all',
            'search' => $search,
        ]);
    }

    /**
     * @return Collection<string, string>
     */
    private function areaOptions(): Collection
    {
        return collect([
            'Account' => 'Account',
            'Enquiry' => 'Enquiry',
            'Training Matrix' => 'Training Matrix',
            'Users' => 'Users',
            'Settings' => 'Settings',
            'Feedback' => 'Feedback',
        ]);
    }
}
