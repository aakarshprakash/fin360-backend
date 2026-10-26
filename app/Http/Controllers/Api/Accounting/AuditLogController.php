<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /** GET /api/accounting/audit-logs */
    public function index(Request $request)
    {
        $query = AuditLog::with('user:id,name');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('table')) {
            $query->where('table_name', $request->table);
        }
        if ($request->filled('userId')) {
            $query->where('user_id', $request->userId);
        }
        if ($request->filled('dateFrom')) {
            $query->whereDate('created_at', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->whereDate('created_at', '<=', $request->dateTo);
        }

        $perPage = $request->get('per_page', 50);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }
}

