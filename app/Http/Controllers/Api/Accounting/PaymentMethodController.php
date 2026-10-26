<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    /** GET /api/accounting/payment-methods */
    public function index()
    {
        $methods = PaymentMethod::orderBy('name')->get();
        return response()->json(['data' => $methods]);
    }

    /** GET /api/accounting/payment-methods/{id} */
    public function show($id)
    {
        $method = PaymentMethod::findOrFail($id);
        return response()->json(['data' => $method]);
    }

    /** POST /api/accounting/payment-methods */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:100',
            'type'               => 'required|string|in:bank,upi,cash,card,gateway',
            'account_holder_name'=> 'nullable|string|max:150',
            'bank_name'          => 'nullable|string|max:150',
            'account_number'     => 'nullable|string|max:50',
            'ifsc_code'          => 'nullable|string|max:20',
            'upi_id'             => 'nullable|string|max:100',
            'qr_code'            => 'nullable|string',
            'notes'              => 'nullable|string|max:1000',
            'enabled'            => 'nullable|boolean',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['enabled'] = $validated['enabled'] ?? true;

        $method = PaymentMethod::create($validated);

        return response()->json([
            'message' => 'Payment method created successfully',
            'data'    => $method,
        ], 201);
    }

    /** PUT /api/accounting/payment-methods/{id} */
    public function update(Request $request, $id)
    {
        $method = PaymentMethod::findOrFail($id);

        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:100',
            'type'               => 'sometimes|required|string|in:bank,upi,cash,card,gateway',
            'account_holder_name'=> 'nullable|string|max:150',
            'bank_name'          => 'nullable|string|max:150',
            'account_number'     => 'nullable|string|max:50',
            'ifsc_code'          => 'nullable|string|max:20',
            'upi_id'             => 'nullable|string|max:100',
            'qr_code'            => 'nullable|string',
            'notes'              => 'nullable|string|max:1000',
            'enabled'            => 'nullable|boolean',
        ]);

        $method->update($validated);

        return response()->json([
            'message' => 'Payment method updated successfully',
            'data'    => $method,
        ]);
    }

    /** DELETE /api/accounting/payment-methods/{id} */
    public function destroy($id)
    {
        $method = PaymentMethod::findOrFail($id);
        $method->delete();

        return response()->json(['message' => 'Payment method deleted successfully']);
    }
}

