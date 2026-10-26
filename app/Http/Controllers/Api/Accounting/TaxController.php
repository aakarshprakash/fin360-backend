<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Tax;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    /** GET /api/accounting/taxes */
    public function index()
    {
        $taxes = Tax::orderBy('name')->get();
        return response()->json(['data' => $taxes]);
    }

    /** GET /api/accounting/taxes/{id} */
    public function show($id)
    {
        $tax = Tax::findOrFail($id);
        return response()->json(['data' => $tax]);
    }

    /** POST /api/accounting/taxes */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'rate' => 'required|numeric|min:0|max:100',
            'type' => 'required|string|in:CGST,SGST,IGST,VAT,Other',
        ]);

        $validated['active'] = true;
        $tax = Tax::create($validated);

        return response()->json([
            'message' => 'Tax rate created successfully',
            'data'    => $tax,
        ], 201);
    }

    /** PUT /api/accounting/taxes/{id} */
    public function update(Request $request, $id)
    {
        $tax = Tax::findOrFail($id);

        $validated = $request->validate([
            'name'   => 'sometimes|required|string|max:100',
            'rate'   => 'sometimes|required|numeric|min:0|max:100',
            'type'   => 'sometimes|required|string|in:CGST,SGST,IGST,VAT,Other',
            'active' => 'nullable|boolean',
        ]);

        $tax->update($validated);

        return response()->json([
            'message' => 'Tax rate updated successfully',
            'data'    => $tax,
        ]);
    }

    /** DELETE /api/accounting/taxes/{id} */
    public function destroy($id)
    {
        $tax = Tax::findOrFail($id);
        $tax->delete();

        return response()->json(['message' => 'Tax rate deleted successfully']);
    }
}

