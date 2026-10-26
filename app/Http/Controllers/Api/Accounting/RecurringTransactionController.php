<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\RecurringTransaction;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\ExpenseEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RecurringTransactionController extends Controller
{
    /** GET /api/accounting/recurring */
    public function index()
    {
        $items = RecurringTransaction::orderBy('next_due_date')->get();
        return response()->json(['data' => $items]);
    }

    /** GET /api/accounting/recurring/{id} */
    public function show($id)
    {
        $item = RecurringTransaction::findOrFail($id);
        return response()->json(['data' => $item]);
    }

    /** POST /api/accounting/recurring */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'        => 'required|string|in:income,expense',
            'category'    => 'required|string|max:100',
            'amount'      => 'required|numeric|min:0.01',
            'frequency'   => 'required|string|in:daily,weekly,monthly,quarterly,yearly',
            'start_date'  => 'required|date',
            'end_date'    => 'nullable|date|after:start_date',
            'description' => 'nullable|string|max:1000',
        ]);

        $validated['next_due_date'] = $validated['start_date'];
        $validated['status']        = 'active';
        $validated['user_id']       = Auth::id();

        $item = RecurringTransaction::create($validated);

        return response()->json([
            'message' => 'Recurring transaction created successfully',
            'data'    => $item,
        ], 201);
    }

    /** PUT /api/accounting/recurring/{id} */
    public function update(Request $request, $id)
    {
        $item = RecurringTransaction::findOrFail($id);

        $validated = $request->validate([
            'type'        => 'sometimes|required|string|in:income,expense',
            'category'    => 'sometimes|required|string|max:100',
            'amount'      => 'sometimes|required|numeric|min:0.01',
            'frequency'   => 'sometimes|required|string|in:daily,weekly,monthly,quarterly,yearly',
            'start_date'  => 'sometimes|required|date',
            'end_date'    => 'nullable|date',
            'description' => 'nullable|string|max:1000',
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Recurring transaction updated successfully',
            'data'    => $item,
        ]);
    }

    /** DELETE /api/accounting/recurring/{id} */
    public function destroy($id)
    {
        $item = RecurringTransaction::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Recurring transaction deleted successfully']);
    }

    /** PATCH /api/accounting/recurring/{id}/pause */
    public function pause($id)
    {
        $item = RecurringTransaction::findOrFail($id);
        $item->update(['status' => 'paused']);

        return response()->json([
            'message' => 'Recurring transaction paused',
            'data'    => $item,
        ]);
    }

    /** PATCH /api/accounting/recurring/{id}/resume */
    public function resume($id)
    {
        $item = RecurringTransaction::findOrFail($id);
        $item->update(['status' => 'active']);

        return response()->json([
            'message' => 'Recurring transaction resumed',
            'data'    => $item,
        ]);
    }
}

