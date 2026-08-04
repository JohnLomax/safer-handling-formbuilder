<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $actionFilter = (string) $request->query('action', 'all');
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

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('description', 'like', '%'.$search.'%')
                    ->orWhere('user_name', 'like', '%'.$search.'%')
                    ->orWhere('user_email', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('route_name', 'like', '%'.$search.'%');
            });
        }

        $actions = ActivityLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.activity.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'actions' => $actions,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'actionFilter' => $actionFilter,
            'userFilter' => $userFilter ?: 'all',
            'search' => $search,
        ]);
    }
}
