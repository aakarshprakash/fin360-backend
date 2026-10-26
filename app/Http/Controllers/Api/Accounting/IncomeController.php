<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\IncomeEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IncomeController extends Controller
{
    private LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    /** GET /api/accounting/income */
    public function index(Request $request)
    {
        $query = IncomeEntry::with(['customer:id,name', 'account:id,name,code']);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $perPage = $request->get('per_page', 15);
        $entries = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
                'per_page'     => $entries->perPage(),
                'total'        => $entries->total(),
            ],
        ]);
    }

    /** GET /api/accounting/income/{id} */
    public function show($id)
    {
        $entry = IncomeEntry::with(['customer', 'account'])->findOrFail($id);
        return response()->json(['data' => $entry]);
    }

    /** POST /api/accounting/income */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'category'       => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0.01',
            'currency'       => 'nullable|string|max:10',
            'exchange_rate'  => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'customer_id'    => 'nullable|exists:accounting_customers,id',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
            'account_id'     => 'nullable|exists:accounting_accounts,id',
        ]);

        $validated['user_id'] = Auth::id();

        $entry = DB::transaction(function () use ($validated) {
            $entry = IncomeEntry::create($validated);

            $incomeAccountId = $this->ledger->getOrCreateAccountByName($entry->category, 'Income');
            $cashAccountId = $this->ledger->getOrCreateSystemAccount('1010', 'Cash & Bank', 'Assets');

            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-INC-' . $entry->id,
                'date'         => $entry->date,
                'reference'    => $entry->reference,
                'description'  => 'Income recorded - ' . $entry->category,
                'total_debit'  => round($entry->amount, 2),
                'total_credit' => round($entry->amount, 2),
                'source_type'  => 'income_entry',
                'source_id'    => $entry->id,
                'user_id'      => Auth::id(),
            ], [
                [
                    'account_id'  => $cashAccountId,
                    'debit'       => round($entry->amount, 2),
                    'credit'      => 0,
                    'description' => 'Income received - ' . $entry->category,
                ],
                [
                    'account_id'  => $incomeAccountId,
                    'debit'       => 0,
                    'credit'      => round($entry->amount, 2),
                    'description' => $entry->category,
                ],
            ]);

            $bankAccount = BankAccount::orderBy('id')->first();
            if ($bankAccount) {
                $bankAccount->increment('current_balance', $entry->amount);
            }

            return $entry;
        });

        $entry->load(['customer', 'account']);

        return response()->json([
            'message' => 'Income recorded successfully',
            'data'    => $entry,
        ], 201);
    }

    /** PUT /api/accounting/income/{id} */
    public function update(Request $request, $id)
    {
        $entry = IncomeEntry::findOrFail($id);

        $validated = $request->validate([
            'date'           => 'sometimes|required|date',
            'category'       => 'sometimes|required|string|max:100',
            'amount'         => 'sometimes|required|numeric|min:0.01',
            'currency'       => 'nullable|string|max:10',
            'exchange_rate'  => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'customer_id'    => 'nullable|exists:accounting_customers,id',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
            'account_id'     => 'nullable|exists:accounting_accounts,id',
        ]);

        $entry->update($validated);

        return response()->json([
            'message' => 'Income updated successfully',
            'data'    => $entry,
        ]);
    }

    /** DELETE /api/accounting/income/{id} */
    public function destroy($id)
    {
        $entry = IncomeEntry::findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Income entry deleted successfully']);
    }

    /** GET /api/accounting/income/categories */
    public function categories()
    {
        $categories = IncomeEntry::selectRaw('category, count(*) as count, sum(amount) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json(['data' => $categories]);
    }
}

