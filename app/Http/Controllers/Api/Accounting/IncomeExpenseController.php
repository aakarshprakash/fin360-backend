<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\ExpenseEntry;
use Illuminate\Http\Request;
use Carbon\Carbon;

class IncomeExpenseController extends Controller
{
    /** GET /api/accounting/income-expenses/summary */
    public function summary(Request $request)
    {
        $period = $request->get('period', 'monthly');
        $today = Carbon::today();

        // Daily
        $dailyIncome  = IncomeEntry::whereDate('date', $today)->sum('amount');
        $dailyExpense = ExpenseEntry::whereDate('date', $today)->sum('amount');

        // Monthly
        $monthStart   = $today->copy()->startOfMonth();
        $monthlyIncome  = IncomeEntry::where('date', '>=', $monthStart)->sum('amount');
        $monthlyExpense = ExpenseEntry::where('date', '>=', $monthStart)->sum('amount');

        // Yearly
        $yearStart    = $today->copy()->startOfYear();
        $yearlyIncome  = IncomeEntry::where('date', '>=', $yearStart)->sum('amount');
        $yearlyExpense = ExpenseEntry::where('date', '>=', $yearStart)->sum('amount');

        // Income by category
        $incomeByCategory = IncomeEntry::where('date', '>=', $monthStart)
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Expense by category
        $expenseByCategory = ExpenseEntry::where('date', '>=', $monthStart)
            ->selectRaw('category, sum(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // Income trend (last 12 months)
        $incomeTrend = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = $today->copy()->subMonths($i);
            $start = $m->copy()->startOfMonth();
            $end = $m->copy()->endOfMonth();

            $income  = IncomeEntry::whereBetween('date', [$start, $end])->sum('amount');
            $expense = ExpenseEntry::whereBetween('date', [$start, $end])->sum('amount');

            $incomeTrend[] = [
                'month'   => $m->format('M Y'),
                'income'  => round($income, 2),
                'expense' => round($expense, 2),
            ];
        }

        return response()->json([
            'data' => [
                'dailyIncome'       => round($dailyIncome, 2),
                'monthlyIncome'     => round($monthlyIncome, 2),
                'yearlyIncome'      => round($yearlyIncome, 2),
                'dailyExpense'      => round($dailyExpense, 2),
                'monthlyExpense'    => round($monthlyExpense, 2),
                'yearlyExpense'     => round($yearlyExpense, 2),
                'incomeByCategory'  => $incomeByCategory,
                'expenseByCategory' => $expenseByCategory,
                'incomeTrend'       => $incomeTrend,
            ],
        ]);
    }
}

