<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\BankTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankAccountController extends Controller
{
    /** GET /api/accounting/bank-accounts */
    public function index()
    {
        $accounts = BankAccount::orderBy('bank_name')->get();
        return response()->json(['data' => $accounts]);
    }

    /** GET /api/accounting/bank-accounts/{id} */
    public function show($id)
    {
        $account = BankAccount::with('account:id,name,code')->findOrFail($id);
        return response()->json(['data' => $account]);
    }

    /** POST /api/accounting/bank-accounts */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bank_name'       => 'required|string|max:150',
            'account_number'  => 'required|string|max:50',
            'account_type'    => 'nullable|string|max:50',
            'ifsc_code'       => 'nullable|string|max:20',
            'currency'        => 'nullable|string|max:10',
            'opening_balance' => 'nullable|numeric',
            'account_id'      => 'nullable|exists:accounting_accounts,id',
        ]);

        $validated['current_balance'] = $validated['opening_balance'] ?? 0;
        $validated['user_id'] = Auth::id();

        $account = BankAccount::create($validated);

        return response()->json([
            'message' => 'Bank account added successfully',
            'data'    => $account,
        ], 201);
    }

    /** PUT /api/accounting/bank-accounts/{id} */
    public function update(Request $request, $id)
    {
        $account = BankAccount::findOrFail($id);

        $validated = $request->validate([
            'bank_name'      => 'sometimes|required|string|max:150',
            'account_number' => 'sometimes|required|string|max:50',
            'account_type'   => 'nullable|string|max:50',
            'ifsc_code'      => 'nullable|string|max:20',
            'currency'       => 'nullable|string|max:10',
            'account_id'     => 'nullable|exists:accounting_accounts,id',
        ]);

        $account->update($validated);

        return response()->json([
            'message' => 'Bank account updated successfully',
            'data'    => $account,
        ]);
    }

    /** DELETE /api/accounting/bank-accounts/{id} */
    public function destroy($id)
    {
        $account = BankAccount::findOrFail($id);
        $account->delete();

        return response()->json(['message' => 'Bank account deleted successfully']);
    }

    /** GET /api/accounting/bank-accounts/{id}/transactions */
    public function transactions(Request $request, $id)
    {
        BankAccount::findOrFail($id);

        $query = BankTransaction::where('bank_account_id', $id);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $transactions = $query->orderBy('date', 'desc')->orderBy('id', 'desc')->get();

        return response()->json(['data' => $transactions]);
    }

    /** POST /api/accounting/bank-accounts/{id}/transactions */
    public function createTransaction(Request $request, $id)
    {
        $account = BankAccount::findOrFail($id);

        $validated = $request->validate([
            'date'        => 'required|date',
            'type'        => 'required|string|in:deposit,withdrawal,transfer',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'reference'   => 'nullable|string|max:100',
            'transferTo'  => 'nullable|exists:bank_accounts,id',
        ]);

        return DB::transaction(function () use ($account, $validated) {
            $transaction = BankTransaction::create([
                'bank_account_id' => $account->id,
                'date'            => $validated['date'],
                'type'            => $validated['type'],
                'amount'          => $validated['amount'],
                'description'     => $validated['description'] ?? null,
                'reference'       => $validated['reference'] ?? null,
                'transfer_to_id'  => $validated['transferTo'] ?? null,
                'user_id'         => Auth::id(),
            ]);

            // Update balance
            if ($validated['type'] === 'deposit') {
                $account->increment('current_balance', $validated['amount']);
            } elseif ($validated['type'] === 'withdrawal') {
                $account->decrement('current_balance', $validated['amount']);
            } elseif ($validated['type'] === 'transfer' && isset($validated['transferTo'])) {
                $account->decrement('current_balance', $validated['amount']);
                $targetAccount = BankAccount::find($validated['transferTo']);
                if ($targetAccount) {
                    $targetAccount->increment('current_balance', $validated['amount']);
                }
            }

            return response()->json([
                'message' => 'Transaction recorded successfully',
                'data'    => $transaction,
            ], 201);
        });
    }

    /** PUT /api/accounting/bank-accounts/{bankId}/transactions/{txnId} */
    public function updateTransaction(Request $request, $bankId, $txnId)
    {
        BankAccount::findOrFail($bankId);
        $transaction = BankTransaction::where('bank_account_id', $bankId)->findOrFail($txnId);

        $validated = $request->validate([
            'date'        => 'sometimes|required|date',
            'type'        => 'sometimes|required|string|in:deposit,withdrawal,transfer',
            'amount'      => 'sometimes|required|numeric|min:0.01',
            'description' => 'nullable|string|max:500',
            'reference'   => 'nullable|string|max:100',
        ]);

        $transaction->update($validated);

        return response()->json([
            'message' => 'Transaction updated successfully',
            'data'    => $transaction,
        ]);
    }

    /** DELETE /api/accounting/bank-accounts/{bankId}/transactions/{txnId} */
    public function deleteTransaction($bankId, $txnId)
    {
        $account = BankAccount::findOrFail($bankId);
        $transaction = BankTransaction::where('bank_account_id', $bankId)->findOrFail($txnId);

        // Reverse balance
        if ($transaction->type === 'deposit') {
            $account->decrement('current_balance', $transaction->amount);
        } elseif ($transaction->type === 'withdrawal') {
            $account->increment('current_balance', $transaction->amount);
        }

        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    /** POST /api/accounting/bank-accounts/{id}/reconcile */
    public function reconcile(Request $request, $id)
    {
        BankAccount::findOrFail($id);

        $validated = $request->validate([
            'statementBalance' => 'required|numeric',
            'date'             => 'required|date',
            'matchedTxns'      => 'required|array',
            'matchedTxns.*'    => 'integer|exists:bank_transactions,id',
        ]);

        BankTransaction::whereIn('id', $validated['matchedTxns'])
            ->where('bank_account_id', $id)
            ->update(['reconciled' => true]);

        return response()->json(['message' => 'Bank reconciliation completed']);
    }

    /** GET /api/accounting/bank-accounts/{id}/reconciliation */
    public function reconciliation(Request $request, $id)
    {
        $account = BankAccount::findOrFail($id);

        $query = BankTransaction::where('bank_account_id', $id);

        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $reconciled   = (clone $query)->where('reconciled', true)->get();
        $unreconciled = (clone $query)->where(function ($q) {
            $q->where('reconciled', false)->orWhereNull('reconciled');
        })->get();

        $reconciledTotal   = $reconciled->sum('amount');
        $unreconciledTotal = $unreconciled->sum('amount');

        return response()->json([
            'data' => [
                'bank_account'       => $account,
                'reconciled_count'   => $reconciled->count(),
                'reconciled_total'   => round($reconciledTotal, 2),
                'unreconciled_count' => $unreconciled->count(),
                'unreconciled_total' => round($unreconciledTotal, 2),
                'reconciled'         => $reconciled,
                'unreconciled'       => $unreconciled,
            ],
        ]);
    }
}

