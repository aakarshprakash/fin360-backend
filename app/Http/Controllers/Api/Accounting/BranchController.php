<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Branch;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\ExpenseEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchController extends Controller
{
    /** GET /api/accounting/branches */
    public function index(Request $request)
    {
        $query = Branch::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $branches = $query->orderBy('name')->get();

        return response()->json(['data' => $branches]);
    }

    /** GET /api/accounting/branches/{id} */
    public function show($id)
    {
        $branch = Branch::findOrFail($id);
        return response()->json(['data' => $branch]);
    }

    /** POST /api/accounting/branches */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:150',
            'code'       => 'required|string|max:20|unique:branches,code',
            'address'    => 'nullable|string|max:500',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:191',
            'gst_number' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
        ]);

        $validated['status']  = 'active';
        $validated['user_id'] = Auth::id();

        // If setting as default, unset all others
        if (!empty($validated['is_default'])) {
            Branch::where('is_default', true)->update(['is_default' => false]);
        }

        $branch = Branch::create($validated);

        return response()->json([
            'message' => 'Branch created successfully',
            'data'    => $branch,
        ], 201);
    }

    /** PUT /api/accounting/branches/{id} */
    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'name'       => 'sometimes|required|string|max:150',
            'code'       => 'sometimes|required|string|max:20|unique:branches,code,' . $id,
            'address'    => 'nullable|string|max:500',
            'phone'      => 'nullable|string|max:30',
            'email'      => 'nullable|email|max:191',
            'gst_number' => 'nullable|string|max:50',
            'is_default' => 'nullable|boolean',
            'status'     => 'nullable|string|in:active,inactive',
        ]);

        if (!empty($validated['is_default'])) {
            Branch::where('is_default', true)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully',
            'data'    => $branch,
        ]);
    }

    /** DELETE /api/accounting/branches/{id} */
    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);

        if ($branch->is_default) {
            return response()->json(['message' => 'Cannot delete the default branch'], 422);
        }

        $branch->delete();

        return response()->json(['message' => 'Branch deleted successfully']);
    }

    /** GET /api/accounting/branches/{id}/reports/{type} */
    public function report(Request $request, $id, $type)
    {
        $branch = Branch::findOrFail($id);

        $dateFrom = $request->get('dateFrom', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo', Carbon::today()->format('Y-m-d'));

        // Branch-specific reports use branch_id filter if columns exist
        // For now, return a stub with branch info
        switch ($type) {
            case 'profit-loss':
                $income  = IncomeEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');
                $expense = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');

                return response()->json([
                    'data' => [
                        'branch'        => $branch,
                        'period'        => ['from' => $dateFrom, 'to' => $dateTo],
                        'total_income'  => round($income, 2),
                        'total_expense' => round($expense, 2),
                        'net_profit'    => round($income - $expense, 2),
                    ],
                ]);

            case 'income-report':
                $income = IncomeEntry::whereBetween('date', [$dateFrom, $dateTo])
                    ->selectRaw('category, sum(amount) as total, count(*) as count')
                    ->groupBy('category')
                    ->orderByDesc('total')
                    ->get();

                return response()->json([
                    'data' => [
                        'branch'      => $branch,
                        'period'      => ['from' => $dateFrom, 'to' => $dateTo],
                        'by_category' => $income,
                    ],
                ]);

            case 'expense-report':
                $expense = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])
                    ->selectRaw('category, sum(amount) as total, count(*) as count')
                    ->groupBy('category')
                    ->orderByDesc('total')
                    ->get();

                return response()->json([
                    'data' => [
                        'branch'      => $branch,
                        'period'      => ['from' => $dateFrom, 'to' => $dateTo],
                        'by_category' => $expense,
                    ],
                ]);

            default:
                return response()->json(['message' => 'Unknown report type: ' . $type], 404);
        }
    }
}

