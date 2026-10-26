<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorController extends Controller
{
    /** GET /api/accounting/vendors */
    public function index(Request $request)
    {
        $query = Vendor::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $vendors = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $vendors->items(),
            'meta' => [
                'current_page' => $vendors->currentPage(),
                'last_page'    => $vendors->lastPage(),
                'per_page'     => $vendors->perPage(),
                'total'        => $vendors->total(),
            ],
        ]);
    }

    /** GET /api/accounting/vendors/{id} */
    public function show($id)
    {
        $vendor = Vendor::with('bills')->findOrFail($id);
        return response()->json(['data' => $vendor]);
    }

    /** POST /api/accounting/vendors */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:150',
            'gst_number'    => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'postal_code'   => 'nullable|string|max:20',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:191',
            'payment_terms' => 'nullable|string|max:50',
        ]);

        $validated['user_id'] = Auth::id();
        $vendor = Vendor::create($validated);

        return response()->json([
            'message' => 'Vendor created successfully',
            'data'    => $vendor,
        ], 201);
    }

    /** PUT /api/accounting/vendors/{id} */
    public function update(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:150',
            'gst_number'    => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:500',
            'city'          => 'nullable|string|max:100',
            'state'         => 'nullable|string|max:100',
            'country'       => 'nullable|string|max:100',
            'postal_code'   => 'nullable|string|max:20',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:191',
            'payment_terms' => 'nullable|string|max:50',
        ]);

        $vendor->update($validated);

        return response()->json([
            'message' => 'Vendor updated successfully',
            'data'    => $vendor,
        ]);
    }

    /** DELETE /api/accounting/vendors/{id} */
    public function destroy($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->delete();

        return response()->json(['message' => 'Vendor deleted successfully']);
    }

    /** GET /api/accounting/vendors/{id}/ledger */
    public function ledger(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $query = $vendor->bills()->with('payments');

        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $bills = $query->orderBy('date')->get();

        $ledger = [];
        $balance = 0;
        foreach ($bills as $bill) {
            $balance += (float) $bill->total;
            $ledger[] = [
                'date'      => $bill->date->format('Y-m-d'),
                'type'      => 'bill',
                'reference' => $bill->bill_number,
                'debit'     => 0,
                'credit'    => (float) $bill->total,
                'balance'   => round($balance, 2),
            ];
            foreach ($bill->payments as $pmt) {
                $balance -= (float) $pmt->amount;
                $ledger[] = [
                    'date'      => $pmt->date->format('Y-m-d'),
                    'type'      => 'payment',
                    'reference' => $pmt->reference ?? $bill->bill_number,
                    'debit'     => (float) $pmt->amount,
                    'credit'    => 0,
                    'balance'   => round($balance, 2),
                ];
            }
        }

        return response()->json([
            'data' => [
                'vendor' => $vendor,
                'ledger' => $ledger,
            ],
        ]);
    }

    /** GET /api/accounting/vendors/{id}/bills */
    public function bills(Request $request, $id)
    {
        Vendor::findOrFail($id);

        $query = \App\Models\Accounting\Bill::where('vendor_id', $id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bills = $query->orderBy('date', 'desc')->get();

        return response()->json(['data' => $bills]);
    }

    /** GET /api/accounting/vendors/{id}/statement */
    public function statement(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);

        $query = \App\Models\Accounting\Bill::where('vendor_id', $id);

        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $bills = $query->with('payments')->orderBy('date')->get();

        $totalBilled = $bills->sum('total');
        $totalPaid   = $bills->sum('amount_paid');
        $outstanding = $totalBilled - $totalPaid;

        return response()->json([
            'data' => [
                'vendor'       => $vendor,
                'bills'        => $bills,
                'total_billed' => round($totalBilled, 2),
                'total_paid'   => round($totalPaid, 2),
                'outstanding'  => round($outstanding, 2),
            ],
        ]);
    }
}

