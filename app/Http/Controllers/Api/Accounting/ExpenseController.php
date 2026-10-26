<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ExpenseEntry;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    private LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    /** GET /api/accounting/expenses */
    public function index(Request $request)
    {
        $query = ExpenseEntry::with(['vendor:id,name', 'account:id,name,code']);

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

    /** GET /api/accounting/expenses/{id} */
    public function show($id)
    {
        $entry = ExpenseEntry::with(['vendor', 'account'])->findOrFail($id);
        return response()->json(['data' => $entry]);
    }

    /** POST /api/accounting/expenses */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'category'       => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0.01',
            'currency'       => 'nullable|string|max:10',
            'exchange_rate'  => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'vendor_id'      => 'nullable|exists:vendors,id',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
            'account_id'     => 'nullable|exists:accounting_accounts,id',
        ]);

        $validated['user_id'] = Auth::id();

        $entry = DB::transaction(function () use ($validated) {
            $entry = ExpenseEntry::create($validated);

            $expenseAccountId = $this->ledger->getOrCreateAccountByName($entry->category, 'Expense');
            $cashAccountId = $this->ledger->getOrCreateSystemAccount('1010', 'Cash & Bank', 'Assets');

            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-EXP-' . $entry->id,
                'date'         => $entry->date,
                'reference'    => $entry->reference,
                'description'  => 'Expense recorded - ' . $entry->category,
                'total_debit'  => round($entry->amount, 2),
                'total_credit' => round($entry->amount, 2),
                'source_type'  => 'expense_entry',
                'source_id'    => $entry->id,
                'user_id'      => Auth::id(),
            ], [
                [
                    'account_id'  => $expenseAccountId,
                    'debit'       => round($entry->amount, 2),
                    'credit'      => 0,
                    'description' => $entry->category,
                ],
                [
                    'account_id'  => $cashAccountId,
                    'debit'       => 0,
                    'credit'      => round($entry->amount, 2),
                    'description' => 'Expense paid - ' . $entry->category,
                ],
            ]);

            $bankAccount = BankAccount::orderBy('id')->first();
            if ($bankAccount) {
                $bankAccount->decrement('current_balance', $entry->amount);
            }

            return $entry;
        });

        $entry->load(['vendor', 'account']);

        return response()->json([
            'message' => 'Expense recorded successfully',
            'data'    => $entry,
        ], 201);
    }

    /** PUT /api/accounting/expenses/{id} */
    public function update(Request $request, $id)
    {
        $entry = ExpenseEntry::findOrFail($id);

        $validated = $request->validate([
            'date'           => 'sometimes|required|date',
            'category'       => 'sometimes|required|string|max:100',
            'amount'         => 'sometimes|required|numeric|min:0.01',
            'currency'       => 'nullable|string|max:10',
            'exchange_rate'  => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'vendor_id'      => 'nullable|exists:vendors,id',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:1000',
            'account_id'     => 'nullable|exists:accounting_accounts,id',
        ]);

        $entry->update($validated);

        return response()->json([
            'message' => 'Expense updated successfully',
            'data'    => $entry,
        ]);
    }

    /** DELETE /api/accounting/expenses/{id} */
    public function destroy($id)
    {
        $entry = ExpenseEntry::findOrFail($id);
        $entry->delete();

        return response()->json(['message' => 'Expense entry deleted successfully']);
    }

    /** GET /api/accounting/expenses/categories */
    public function categories()
    {
        $categories = ExpenseEntry::selectRaw('category, count(*) as count, sum(amount) as total')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return response()->json(['data' => $categories]);
    }
}

