<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    /** GET /api/accounting/accounts */
    public function index(Request $request)
    {
        $query = Account::query();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%");
            });
        }
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $accounts = $query->orderBy('code')->get();

        return response()->json(['data' => $accounts]);
    }

    /** GET /api/accounting/accounts/{id} */
    public function show($id)
    {
        $account = Account::findOrFail($id);
        return response()->json(['data' => $account]);
    }

    /** POST /api/accounting/accounts */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'            => 'required|string|max:20|unique:accounting_accounts,code',
            'name'            => 'required|string|max:150',
            'type'            => 'required|string|in:Assets,Liabilities,Equity,Income,Expense',
            'group'           => 'nullable|string|max:100',
            'description'     => 'nullable|string|max:1000',
            'opening_balance' => 'nullable|numeric',
        ]);

        $validated['balance'] = $validated['opening_balance'] ?? 0;
        $validated['user_id'] = Auth::id();

        $account = Account::create($validated);

        return response()->json([
            'message' => 'Account created successfully',
            'data'    => $account,
        ], 201);
    }

    /** PUT /api/accounting/accounts/{id} */
    public function update(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $validated = $request->validate([
            'code'        => 'sometimes|required|string|max:20|unique:accounting_accounts,code,' . $id,
            'name'        => 'sometimes|required|string|max:150',
            'type'        => 'sometimes|required|string|in:Assets,Liabilities,Equity,Income,Expense',
            'group'       => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'active'      => 'nullable|boolean',
        ]);

        $account->update($validated);

        return response()->json([
            'message' => 'Account updated successfully',
            'data'    => $account,
        ]);
    }

    /** DELETE /api/accounting/accounts/{id} */
    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        if ($account->is_system) {
            return response()->json(['message' => 'System accounts cannot be deleted'], 422);
        }

        // Check for journal lines
        $hasTransactions = JournalEntryLine::where('account_id', $id)->exists();
        if ($hasTransactions) {
            return response()->json(['message' => 'Cannot delete account with existing transactions'], 422);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted successfully']);
    }

    /** GET /api/accounting/accounts/{id}/ledger */
    public function ledger(Request $request, $id)
    {
        $account = Account::findOrFail($id);

        $query = JournalEntryLine::with('journalEntry:id,entry_number,date,description,status')
            ->where('account_id', $id)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            });

        if ($request->filled('dateFrom')) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->where('date', '>=', $request->dateFrom);
            });
        }
        if ($request->filled('dateTo')) {
            $query->whereHas('journalEntry', function ($q) use ($request) {
                $q->where('date', '<=', $request->dateTo);
            });
        }

        $entries = $query->orderBy('created_at', 'asc')->get();

        // Build running balance
        $runningBalance = (float) $account->opening_balance;
        $ledger = $entries->map(function ($line) use (&$runningBalance) {
            $runningBalance += ($line->debit - $line->credit);
            return [
                'id'            => $line->id,
                'date'          => $line->journalEntry ? $line->journalEntry->date->format('Y-m-d') : null,
                'entry_number'  => $line->journalEntry ? $line->journalEntry->entry_number : null,
                'description'   => $line->description ?: ($line->journalEntry ? $line->journalEntry->description : ''),
                'debit'         => (float) $line->debit,
                'credit'        => (float) $line->credit,
                'balance'       => round($runningBalance, 2),
            ];
        });

        return response()->json([
            'data' => [
                'account' => $account,
                'ledger'  => $ledger,
            ],
        ]);
    }
}

