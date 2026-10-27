<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountingCustomerController extends Controller
{
    /** GET /api/accounting/customers */
    public function index(Request $request)
    {
        $query = AccountingCustomer::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }
        if ($request->filled('currency')) {
            $query->where('currency', $request->currency);
        }

        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $customers->items(),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'per_page'     => $customers->perPage(),
                'total'        => $customers->total(),
            ],
        ]);
    }

    /** GET /api/accounting/customers/{id} */
    public function show($id)
    {
        $customer = AccountingCustomer::with('invoices')->findOrFail($id);
        return response()->json(['data' => $customer]);
    }

    /** POST /api/accounting/customers */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:150',
            'email'        => 'nullable|email|max:191',
            'phone'        => 'nullable|string|max:30',
            'gst_number'   => 'nullable|string|max:50',
            'pan'          => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:150',
            'address'      => 'nullable|string|max:500',
            'city'         => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'country'      => 'nullable|string|max:100',
            'postal_code'  => 'nullable|string|max:20',
            'currency'     => 'nullable|string|max:10',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $validated['user_id'] = Auth::id();
        $customer = AccountingCustomer::create($validated);

        return response()->json([
            'message' => 'Customer created successfully',
            'data'    => $customer,
        ], 201);
    }

    /** PUT /api/accounting/customers/{id} */
    public function update(Request $request, $id)
    {
        $customer = AccountingCustomer::findOrFail($id);

        $validated = $request->validate([
            'name'         => 'sometimes|required|string|max:150',
            'email'        => 'nullable|email|max:191',
            'phone'        => 'nullable|string|max:30',
            'gst_number'   => 'nullable|string|max:50',
            'pan'          => 'nullable|string|max:20',
            'contact_person' => 'nullable|string|max:150',
            'address'      => 'nullable|string|max:500',
            'city'         => 'nullable|string|max:100',
            'state'        => 'nullable|string|max:100',
            'country'      => 'nullable|string|max:100',
            'postal_code'  => 'nullable|string|max:20',
            'currency'     => 'nullable|string|max:10',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $customer->update($validated);

        return response()->json([
            'message' => 'Customer updated successfully',
            'data'    => $customer,
        ]);
    }

    /** DELETE /api/accounting/customers/{id} */
    public function destroy($id)
    {
        $customer = AccountingCustomer::findOrFail($id);
        $customer->delete();

        return response()->json(['message' => 'Customer deleted successfully']);
    }

    /** GET /api/accounting/customers/{id}/ledger */
    public function ledger(Request $request, $id)
    {
        $customer = AccountingCustomer::findOrFail($id);

        $query = $customer->invoices()->with('payments');

        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $invoices = $query->orderBy('date')->get();

        $ledger = [];
        $balance = 0;
        foreach ($invoices as $inv) {
            $balance += (float) $inv->total;
            $ledger[] = [
                'date'        => $inv->date->format('Y-m-d'),
                'type'        => 'invoice',
                'reference'   => $inv->invoice_number,
                'debit'       => (float) $inv->total,
                'credit'      => 0,
                'balance'     => round($balance, 2),
            ];
            foreach ($inv->payments as $pmt) {
                $balance -= (float) $pmt->amount;
                $ledger[] = [
                    'date'      => $pmt->date->format('Y-m-d'),
                    'type'      => 'payment',
                    'reference' => $pmt->reference ?? $inv->invoice_number,
                    'debit'     => 0,
                    'credit'    => (float) $pmt->amount,
                    'balance'   => round($balance, 2),
                ];
            }
        }

        return response()->json([
            'data' => [
                'customer' => $customer,
                'ledger'   => $ledger,
            ],
        ]);
    }

    /** GET /api/accounting/customers/{id}/invoices */
    public function invoices(Request $request, $id)
    {
        AccountingCustomer::findOrFail($id);

        $query = \App\Models\Accounting\Invoice::where('customer_id', $id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $invoices = $query->orderBy('date', 'desc')->get();

        return response()->json(['data' => $invoices]);
    }

    /** GET /api/accounting/customers/{id}/statement */
    public function statement(Request $request, $id)
    {
        $customer = AccountingCustomer::findOrFail($id);

        $query = \App\Models\Accounting\Invoice::where('customer_id', $id);

        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $invoices = $query->with('payments')->orderBy('date')->get();

        $totalInvoiced = $invoices->sum('total');
        $totalPaid     = $invoices->sum('amount_paid');
        $totalCredited = $invoices->sum('amount_credited');
        $outstanding   = $totalInvoiced - $totalPaid - $totalCredited;

        return response()->json([
            'data' => [
                'customer'       => $customer,
                'invoices'       => $invoices,
                'total_invoiced' => round($totalInvoiced, 2),
                'total_paid'     => round($totalPaid, 2),
                'outstanding'    => round($outstanding, 2),
            ],
        ]);
    }
}

