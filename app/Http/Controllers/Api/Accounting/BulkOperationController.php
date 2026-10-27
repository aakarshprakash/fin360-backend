<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BulkOperationController extends Controller
{
    /** POST /api/accounting/accounts/bulk-delete */
    public function bulkDeleteAccounts(Request $request)
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:accounting_accounts,id',
        ]);

        $systemAccounts = Account::whereIn('id', $validated['ids'])->where('is_system', true)->count();
        if ($systemAccounts > 0) {
            return response()->json(['message' => 'Cannot delete system accounts'], 422);
        }

        Account::whereIn('id', $validated['ids'])->where('is_system', false)->delete();

        return response()->json(['message' => 'Accounts deleted successfully']);
    }

    /** POST /api/accounting/invoices/bulk-status */
    public function bulkUpdateInvoiceStatus(Request $request)
    {
        $validated = $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer|exists:invoices,id',
            'status' => 'required|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if ($validated['status'] === 'paid') {
            $unpaidCount = Invoice::whereIn('id', $validated['ids'])
                ->whereRaw('amount_paid + amount_credited < total - 0.005')
                ->count();

            if ($unpaidCount > 0) {
                return response()->json([
                    'message' => "{$unpaidCount} invoice(s) still have an amount outstanding. Record payments or credit notes for them first.",
                ], 422);
            }
        }

        Invoice::whereIn('id', $validated['ids'])->update(['status' => $validated['status']]);

        return response()->json(['message' => 'Invoice statuses updated successfully']);
    }

    /** POST /api/accounting/accounts/import */
    public function importAccounts(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls',
        ]);

        return response()->json(['message' => 'Accounts imported successfully (stub)']);
    }

    /** GET /api/accounting/accounts/export */
    public function exportAccounts(Request $request)
    {
        $accounts = Account::orderBy('code')->get();

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="accounts_' . now()->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($accounts) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Code', 'Name', 'Type', 'Group', 'Opening Balance', 'Balance', 'Active']);

            foreach ($accounts as $account) {
                fputcsv($file, [
                    $account->code,
                    $account->name,
                    $account->type,
                    $account->group,
                    $account->opening_balance,
                    $account->balance,
                    $account->active ? 'Yes' : 'No',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

