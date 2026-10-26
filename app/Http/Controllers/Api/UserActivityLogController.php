<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;

class UserActivityLogController extends Controller
{
    /**
     * GET /api/activity-logs — List user activity logs (Admin)
     */
    public function index(Request $request)
    {
        $query = UserActivityLog::with('user:id,name,email,role');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('module', 'like', "%{$search}%");
            });
        }

        $perPage = $request->input('per_page', 50);
        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        $logs->getCollection()->transform(function ($log) {
            return [
                'id'          => $log->id,
                'user'        => $log->user ? [
                    'id'    => $log->user->id,
                    'name'  => $log->user->name,
                    'email' => $log->user->email,
                    'role'  => $log->user->role,
                ] : null,
                'action'      => $log->action,
                'module'      => $log->module,
                'target_type' => $log->target_type ? class_basename($log->target_type) : null,
                'target_id'   => $log->target_id,
                'old_values'  => $log->old_values,
                'new_values'  => $log->new_values,
                'ip_address'  => $log->ip_address,
                'user_agent'  => $log->user_agent,
                'created_at'  => $log->created_at ? $log->created_at->format('M d, Y h:i A') : null,
            ];
        });

        return response()->json($logs);
    }

    /**
     * GET /api/activity-logs/stats — Activity summary stats
     */
    public function stats(Request $request)
    {
        $query = UserActivityLog::query();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $totalActions   = (clone $query)->count();
        $totalLogins    = (clone $query)->where('action', 'login')->count();
        $totalCreates   = (clone $query)->where('action', 'create')->count();
        $totalUpdates   = (clone $query)->where('action', 'update')->count();
        $totalDeletes   = (clone $query)->where('action', 'delete')->count();

        $byModule = (clone $query)
            ->selectRaw('module, COUNT(*) as count')
            ->groupBy('module')
            ->get();

        $byUser = (clone $query)
            ->selectRaw('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'user_id'   => $row->user_id,
                    'user_name' => $row->user ? $row->user->name : 'Unknown',
                    'count'     => $row->count,
                ];
            });

        return response()->json([
            'data' => [
                'total_actions'  => $totalActions,
                'total_logins'   => $totalLogins,
                'total_creates'  => $totalCreates,
                'total_updates'  => $totalUpdates,
                'total_deletes'  => $totalDeletes,
                'by_module'      => $byModule,
                'top_users'      => $byUser,
            ],
        ]);
    }
}

