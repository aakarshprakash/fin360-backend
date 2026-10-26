<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\ExpenseEntry;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillPayment;
use App\Models\Accounting\InvoicePayment;
use App\Models\Accounting\JournalEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingDashboardController extends Controller
{
    /** GET /api/accounting/dashboard */
    public function index()
    {
        $today     = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $totalReceivable = Invoice::where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as total')
            ->value('total');

        $totalPayable = Bill::where('status', '!=', 'paid')
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as total')
            ->value('total');

        $monthlyIncome  = IncomeEntry::where('date', '>=', $monthStart)->sum('amount');
        $monthlyExpense = ExpenseEntry::where('date', '>=', $monthStart)->sum('amount');

        $bankBalance = BankAccount::sum('current_balance');

        $invoiceCount = Invoice::where('status', 'pending')->count();
        $overdueInvoices = Invoice::where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->count();

        $billCount = Bill::where('status', 'pending')->count();
        $overdueBills = Bill::where('status', '!=', 'paid')
            ->where('due_date', '<', $today)
            ->count();

        return response()->json([
            'data' => [
                'total_receivable'  => round($totalReceivable, 2),
                'total_payable'     => round($totalPayable, 2),
                'monthly_income'    => round($monthlyIncome, 2),
                'monthly_expense'   => round($monthlyExpense, 2),
                'net_income'        => round($monthlyIncome - $monthlyExpense, 2),
                'bank_balance'      => round($bankBalance, 2),
                'pending_invoices'  => $invoiceCount,
                'overdue_invoices'  => $overdueInvoices,
                'pending_bills'     => $billCount,
                'overdue_bills'     => $overdueBills,
            ],
        ]);
    }

    /**
     * GET /api/accounting/dashboard/overview?from=YYYY-MM-DD&to=YYYY-MM-DD
     *
     * Everything the main dashboard renders, in one round-trip. Income counts
     * invoice payments received plus manual income entries; expenses count bill
     * payments made plus manual expense entries (neither path creates the other,
     * so nothing is double-counted). Changes compare against the preceding
     * period of equal length.
     */
    public function overview(Request $request)
    {
        $to   = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : Carbon::today()->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : $to->copy()->startOfMonth();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $days     = $from->diffInDays($to) + 1;
        $prevTo   = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        $income      = $this->incomeBetween($from, $to);
        $expense     = $this->expenseBetween($from, $to);
        $prevIncome  = $this->incomeBetween($prevFrom, $prevTo);
        $prevExpense = $this->expenseBetween($prevFrom, $prevTo);

        $openInvoices = Invoice::whereNotIn('status', ['paid', 'cancelled']);
        $outstanding  = (clone $openInvoices)->selectRaw('COALESCE(SUM(total - amount_paid), 0) as t')->value('t');
        $openCount    = (clone $openInvoices)->count();

        // Six months ending with the selected period's month
        $monthly = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = $to->copy()->startOfMonth()->subMonths($i);
            $monthly[] = [
                'month'   => $m->format('M'),
                'income'  => round($this->incomeBetween($m->copy()->startOfMonth(), $m->copy()->endOfMonth()), 2),
                'expense' => round($this->expenseBetween($m->copy()->startOfMonth(), $m->copy()->endOfMonth()), 2),
            ];
        }

        // Expense breakdown: manual categories + vendor bill payments, top 4 + Others
        $byCategory = ExpenseEntry::whereBetween('date', [$from, $to])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->pluck('total', 'category')
            ->map(fn ($v) => (float) $v)
            ->toArray();
        $billPaid = (float) BillPayment::whereHas('bill')->whereBetween('date', [$from, $to])->sum('amount');
        if ($billPaid > 0) {
            $byCategory['Vendor Bills'] = ($byCategory['Vendor Bills'] ?? 0) + $billPaid;
        }
        arsort($byCategory);
        $breakdown = [];
        $others    = 0;
        foreach (array_values(array_keys($byCategory)) as $idx => $cat) {
            if ($idx < 4) {
                $breakdown[] = ['category' => $cat, 'amount' => round($byCategory[$cat], 2)];
            } else {
                $others += $byCategory[$cat];
            }
        }
        if ($others > 0) {
            $breakdown[] = ['category' => 'Others', 'amount' => round($others, 2)];
        }

        return response()->json([
            'data' => [
                'period'  => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'kpis'    => [
                    'total_income'      => round($income, 2),
                    'total_expense'     => round($expense, 2),
                    'net_balance'       => round($income - $expense, 2),
                    'outstanding'       => round($outstanding, 2),
                    'outstanding_count' => $openCount,
                    'income_change'     => $this->pctChange($income, $prevIncome),
                    'expense_change'    => $this->pctChange($expense, $prevExpense),
                    'net_change'        => $this->pctChange($income - $expense, $prevIncome - $prevExpense),
                    'bank_balance'      => round(BankAccount::sum('current_balance'), 2),
                ],
                'monthly'           => $monthly,
                'expense_breakdown' => $breakdown,
                'recent'            => $this->recentCashMovements(6),
                'upcoming_invoices' => $this->upcomingInvoices(5),
            ],
        ]);
    }

    private function incomeBetween(Carbon $from, Carbon $to): float
    {
        return (float) InvoicePayment::whereHas('invoice')->whereBetween('date', [$from, $to])->sum('amount')
             + (float) IncomeEntry::whereBetween('date', [$from, $to])->sum('amount');
    }

    private function expenseBetween(Carbon $from, Carbon $to): float
    {
        return (float) BillPayment::whereHas('bill')->whereBetween('date', [$from, $to])->sum('amount')
             + (float) ExpenseEntry::whereBetween('date', [$from, $to])->sum('amount');
    }

    private function pctChange(float $current, float $previous): ?float
    {
        if (abs($previous) < 0.005) {
            return null;
        }
        return round((($current - $previous) / abs($previous)) * 100, 1);
    }

    private function recentCashMovements(int $limit): array
    {
        $rows = [];

        foreach (InvoicePayment::whereHas('invoice')->with('invoice.customer:id,name')->orderByDesc('date')->orderByDesc('id')->limit($limit)->get() as $p) {
            $who = optional(optional($p->invoice)->customer)->name;
            $rows[] = [
                'id' => 'ip_' . $p->id, 'date' => $p->date, 'type' => 'income', 'amount' => (float) $p->amount,
                'description' => 'Payment' . ($p->invoice ? ' — ' . $p->invoice->invoice_number : '') . ($who ? ' · ' . $who : ''),
            ];
        }
        foreach (IncomeEntry::with('customer:id,name')->orderByDesc('date')->orderByDesc('id')->limit($limit)->get() as $e) {
            $rows[] = [
                'id' => 'ie_' . $e->id, 'date' => $e->date, 'type' => 'income', 'amount' => (float) $e->amount,
                'description' => $e->category . (optional($e->customer)->name ? ' · ' . $e->customer->name : ''),
            ];
        }
        foreach (BillPayment::whereHas('bill')->with('bill.vendor:id,name')->orderByDesc('date')->orderByDesc('id')->limit($limit)->get() as $p) {
            $who = optional(optional($p->bill)->vendor)->name;
            $rows[] = [
                'id' => 'bp_' . $p->id, 'date' => $p->date, 'type' => 'expense', 'amount' => (float) $p->amount,
                'description' => 'Bill payment' . ($p->bill ? ' — ' . $p->bill->bill_number : '') . ($who ? ' · ' . $who : ''),
            ];
        }
        foreach (ExpenseEntry::with('vendor:id,name')->orderByDesc('date')->orderByDesc('id')->limit($limit)->get() as $e) {
            $rows[] = [
                'id' => 'ee_' . $e->id, 'date' => $e->date, 'type' => 'expense', 'amount' => (float) $e->amount,
                'description' => $e->category . (optional($e->vendor)->name ? ' · ' . $e->vendor->name : ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

        return array_map(function ($r) {
            $r['date'] = Carbon::parse($r['date'])->toDateString();
            return $r;
        }, array_slice($rows, 0, $limit));
    }

    private function upcomingInvoices(int $limit): array
    {
        $today = Carbon::today();

        return Invoice::with('customer:id,name')
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->limit($limit)
            ->get()
            ->map(function ($inv) use ($today) {
                return [
                    'id'             => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'customer'       => optional($inv->customer)->name ?? $inv->customer_name,
                    'balance'        => round($inv->total - $inv->amount_paid, 2),
                    'due_date'       => $inv->due_date ? Carbon::parse($inv->due_date)->toDateString() : null,
                    'due_in_days'    => $inv->due_date ? $today->diffInDays(Carbon::parse($inv->due_date), false) : null,
                ];
            })
            ->all();
    }

    /** GET /api/accounting/dashboard/charts */
    public function charts(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $today  = Carbon::today();

        $data = [];
        for ($i = 11; $i >= 0; $i--) {
            $m     = $today->copy()->subMonths($i);
            $start = $m->copy()->startOfMonth();
            $end   = $m->copy()->endOfMonth();

            $income  = IncomeEntry::whereBetween('date', [$start, $end])->sum('amount');
            $expense = ExpenseEntry::whereBetween('date', [$start, $end])->sum('amount');

            $data[] = [
                'month'   => $m->format('M Y'),
                'income'  => round($income, 2),
                'expense' => round($expense, 2),
                'profit'  => round($income - $expense, 2),
            ];
        }

        return response()->json(['data' => $data]);
    }

    /** GET /api/accounting/dashboard/recent-transactions */
    public function recentTransactions(Request $request)
    {
        $limit = $request->get('limit', 20);

        $entries = JournalEntry::with('user:id,name')
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $entries]);
    }

    /** GET /api/accounting/reports/{type} */
    public function report(Request $request, $type)
    {
        switch ($type) {
            case 'trial-balance':
                return $this->trialBalance($request);
            case 'profit-loss':
                return $this->profitLoss($request);
            case 'balance-sheet':
                return $this->balanceSheet($request);
            case 'general-ledger':
                return $this->generalLedger($request);
            case 'cash-flow':
                return $this->cashFlow($request);
            case 'income-report':
                return $this->incomeReport($request);
            case 'expense-report':
                return $this->expenseReport($request);
            case 'gst-summary':
                return $this->gstSummary($request);
            default:
                return response()->json(['message' => 'Unknown report type'], 404);
        }
    }

    /** GET /api/accounting/reports/{type}/export */
    public function reportExport(Request $request, $type)
    {
        // Stub for CSV/PDF export
        $report = $this->report($request, $type);
        return $report; // Return JSON for now
    }

    private function trialBalance(Request $request)
    {
        $accounts = Account::where('active', true)->orderBy('code')->get();

        $data = $accounts->map(function ($account) {
            $balance = (float) $account->balance;
            return [
                'code'    => $account->code,
                'name'    => $account->name,
                'type'    => $account->type,
                'debit'   => $balance > 0 ? $balance : 0,
                'credit'  => $balance < 0 ? abs($balance) : 0,
            ];
        });

        $totalDebit  = $data->sum('debit');
        $totalCredit = $data->sum('credit');

        return response()->json([
            'data' => [
                'accounts'     => $data,
                'total_debit'  => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'balanced'     => round($totalDebit, 2) === round($totalCredit, 2),
            ],
        ]);
    }

    private function profitLoss(Request $request)
    {
        $dateFrom = $request->get('dateFrom', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo', Carbon::today()->format('Y-m-d'));

        $income  = IncomeEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');
        $expense = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');

        $incomeByCategory = IncomeEntry::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->get();

        $expenseByCategory = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->get();

        return response()->json([
            'data' => [
                'period'              => ['from' => $dateFrom, 'to' => $dateTo],
                'total_income'        => round($income, 2),
                'total_expense'       => round($expense, 2),
                'net_profit'          => round($income - $expense, 2),
                'income_by_category'  => $incomeByCategory,
                'expense_by_category' => $expenseByCategory,
            ],
        ]);
    }

    private function balanceSheet(Request $request)
    {
        $assets      = Account::where('type', 'Assets')->where('active', true)->get(['code', 'name', 'balance']);
        $liabilities = Account::where('type', 'Liabilities')->where('active', true)->get(['code', 'name', 'balance']);
        $equity      = Account::where('type', 'Equity')->where('active', true)->get(['code', 'name', 'balance']);

        // Account.balance is stored as debit-minus-credit for every account type.
        // Assets are debit-normal so that's already the right sign to display.
        // Liabilities/Equity are credit-normal, so flip sign for display —
        // matches how trialBalance() already splits debit/credit by sign.
        foreach ($liabilities as $account) {
            $account->balance = round(-1 * (float) $account->balance, 2);
        }
        foreach ($equity as $account) {
            $account->balance = round(-1 * (float) $account->balance, 2);
        }

        return response()->json([
            'data' => [
                'assets'            => $assets,
                'total_assets'      => round($assets->sum('balance'), 2),
                'liabilities'       => $liabilities,
                'total_liabilities' => round($liabilities->sum('balance'), 2),
                'equity'            => $equity,
                'total_equity'      => round($equity->sum('balance'), 2),
            ],
        ]);
    }

    private function generalLedger(Request $request)
    {
        $query = Account::where('active', true);

        if ($request->filled('account')) {
            $query->where('id', $request->account);
        }

        $accounts = $query->with(['journalLines' => function ($q) use ($request) {
            $q->with('journalEntry:id,entry_number,date,description');
            if ($request->filled('dateFrom')) {
                $q->whereHas('journalEntry', fn($je) => $je->where('date', '>=', $request->dateFrom));
            }
            if ($request->filled('dateTo')) {
                $q->whereHas('journalEntry', fn($je) => $je->where('date', '<=', $request->dateTo));
            }
        }])->orderBy('code')->get();

        return response()->json(['data' => $accounts]);
    }

    private function cashFlow(Request $request)
    {
        $dateFrom = $request->get('dateFrom', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo', Carbon::today()->format('Y-m-d'));

        $cashIn   = IncomeEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');
        $cashOut  = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');

        return response()->json([
            'data' => [
                'period'   => ['from' => $dateFrom, 'to' => $dateTo],
                'cash_in'  => round($cashIn, 2),
                'cash_out' => round($cashOut, 2),
                'net_cash' => round($cashIn - $cashOut, 2),
            ],
        ]);
    }

    private function incomeReport(Request $request)
    {
        return $this->profitLoss($request); // Reuse P&L income section
    }

    private function expenseReport(Request $request)
    {
        $dateFrom = $request->get('dateFrom', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo', Carbon::today()->format('Y-m-d'));

        $total = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])->sum('amount');
        $byCategory = ExpenseEntry::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('category, sum(amount) as total, count(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'period'      => ['from' => $dateFrom, 'to' => $dateTo],
                'total'       => round($total, 2),
                'by_category' => $byCategory,
            ],
        ]);
    }

    private function gstSummary(Request $request)
    {
        $dateFrom = $request->get('dateFrom', Carbon::today()->startOfMonth()->format('Y-m-d'));
        $dateTo   = $request->get('dateTo', Carbon::today()->format('Y-m-d'));

        $outputGst = Invoice::whereBetween('date', [$dateFrom, $dateTo])->sum('tax');
        $inputGst  = Bill::whereBetween('date', [$dateFrom, $dateTo])->sum('tax');

        return response()->json([
            'data' => [
                'period'     => ['from' => $dateFrom, 'to' => $dateTo],
                'output_gst' => round($outputGst, 2),
                'input_gst'  => round($inputGst, 2),
                'net_gst'    => round($outputGst - $inputGst, 2),
            ],
        ]);
    }
}

