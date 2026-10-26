<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FinancialYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FinancialYearController extends Controller
{
    /** GET /api/accounting/financial-years */
    public function index()
    {
        $years = FinancialYear::orderBy('start_date', 'desc')->get();
        return response()->json(['data' => $years]);
    }

    /** GET /api/accounting/financial-years/{id} */
    public function show($id)
    {
        $year = FinancialYear::findOrFail($id);
        return response()->json(['data' => $year]);
    }

    /** POST /api/accounting/financial-years */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'nullable|string|max:100',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        // Check for overlap
        $overlap = FinancialYear::where(function ($q) use ($validated) {
            $q->whereBetween('start_date', [$validated['start_date'], $validated['end_date']])
              ->orWhereBetween('end_date', [$validated['start_date'], $validated['end_date']]);
        })->exists();

        if ($overlap) {
            return response()->json(['message' => 'Financial year dates overlap with an existing year'], 422);
        }

        $validated['status']     = 'open';
        $validated['is_default'] = false;
        $validated['user_id']    = Auth::id();

        $year = FinancialYear::create($validated);

        return response()->json([
            'message' => 'Financial year created successfully',
            'data'    => $year,
        ], 201);
    }

    /** PUT /api/accounting/financial-years/{id} */
    public function update(Request $request, $id)
    {
        $year = FinancialYear::findOrFail($id);

        if ($year->status === 'closed') {
            return response()->json(['message' => 'Cannot update a closed financial year'], 422);
        }

        $validated = $request->validate([
            'name'       => 'nullable|string|max:100',
            'start_date' => 'sometimes|required|date',
            'end_date'   => 'sometimes|required|date',
        ]);

        $year->update($validated);

        return response()->json([
            'message' => 'Financial year updated successfully',
            'data'    => $year,
        ]);
    }

    /** POST /api/accounting/financial-years/{id}/close */
    public function close($id)
    {
        $year = FinancialYear::findOrFail($id);

        if ($year->status === 'closed') {
            return response()->json(['message' => 'Financial year is already closed'], 422);
        }

        $year->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Financial year closed successfully',
            'data'    => $year,
        ]);
    }

    /** PATCH /api/accounting/financial-years/{id}/set-default */
    public function setDefault($id)
    {
        $year = FinancialYear::findOrFail($id);

        if ($year->status === 'closed') {
            return response()->json(['message' => 'Cannot set a closed year as default'], 422);
        }

        // Unset all others
        FinancialYear::where('is_default', true)->update(['is_default' => false]);
        $year->update(['is_default' => true]);

        return response()->json([
            'message' => 'Financial year set as default',
            'data'    => $year,
        ]);
    }

    /** GET /api/accounting/financial-years/{id}/balances */
    public function balances($id)
    {
        $year = FinancialYear::findOrFail($id);

        $accounts = \App\Models\Accounting\Account::where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'opening_balance', 'balance']);

        return response()->json([
            'data' => [
                'financial_year' => $year,
                'accounts'       => $accounts->map(function ($account) {
                    return [
                        'id'              => $account->id,
                        'code'            => $account->code,
                        'name'            => $account->name,
                        'type'            => $account->type,
                        'opening_balance' => round((float) $account->opening_balance, 2),
                        'closing_balance' => round((float) $account->balance, 2),
                    ];
                }),
            ],
        ]);
    }
}

