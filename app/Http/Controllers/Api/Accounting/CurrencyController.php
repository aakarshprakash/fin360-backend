<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /** GET /api/accounting/currencies */
    public function index()
    {
        $currencies = Currency::orderBy('code')->get();
        return response()->json(['data' => $currencies]);
    }

    /** GET /api/accounting/currencies/{id} */
    public function show($id)
    {
        $currency = Currency::findOrFail($id);
        return response()->json(['data' => $currency]);
    }

    /** POST /api/accounting/currencies */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'          => 'required|string|max:10|unique:currencies,code',
            'name'          => 'required|string|max:100',
            'symbol'        => 'nullable|string|max:10',
            'exchange_rate' => 'nullable|numeric|min:0',
            'is_base'       => 'nullable|boolean',
        ]);

        $validated['active'] = true;

        // If setting as base, unset all others
        if (!empty($validated['is_base'])) {
            Currency::where('is_base', true)->update(['is_base' => false]);
        }

        $currency = Currency::create($validated);

        return response()->json([
            'message' => 'Currency added successfully',
            'data'    => $currency,
        ], 201);
    }

    /** PUT /api/accounting/currencies/{id} */
    public function update(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);

        $validated = $request->validate([
            'code'          => 'sometimes|required|string|max:10|unique:currencies,code,' . $id,
            'name'          => 'sometimes|required|string|max:100',
            'symbol'        => 'nullable|string|max:10',
            'exchange_rate' => 'nullable|numeric|min:0',
            'is_base'       => 'nullable|boolean',
            'active'        => 'nullable|boolean',
        ]);

        if (!empty($validated['is_base'])) {
            Currency::where('is_base', true)->where('id', '!=', $id)->update(['is_base' => false]);
        }

        $currency->update($validated);

        return response()->json([
            'message' => 'Currency updated successfully',
            'data'    => $currency,
        ]);
    }

    /** DELETE /api/accounting/currencies/{id} */
    public function destroy($id)
    {
        $currency = Currency::findOrFail($id);

        if ($currency->is_base) {
            return response()->json(['message' => 'Cannot delete the base currency'], 422);
        }

        $currency->delete();

        return response()->json(['message' => 'Currency deleted successfully']);
    }

    /** POST /api/accounting/currencies/refresh-rates */
    public function refreshRates()
    {
        // Stub — in production, fetch from an exchange rate API
        return response()->json(['message' => 'Exchange rates refreshed (stub)']);
    }
}

