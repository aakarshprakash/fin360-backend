<?php

namespace App\Console\Commands;

use App\Models\Accounting\ExpenseEntry;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\RecurringTransaction;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'accounting:process-recurring';
    protected $description = 'Process due recurring transactions and create income/expense entries';

    public function handle()
    {
        $today = Carbon::today();

        $due = RecurringTransaction::where('status', 'active')
            ->where('next_due_date', '<=', $today)
            ->get();

        $processed = 0;

        foreach ($due as $recurring) {
            // Check end_date
            if ($recurring->end_date && $today->greaterThan($recurring->end_date)) {
                $recurring->update(['status' => 'completed']);
                continue;
            }

            // Create the entry
            if ($recurring->type === 'income') {
                IncomeEntry::create([
                    'date'     => $today->format('Y-m-d'),
                    'category' => $recurring->category,
                    'amount'   => $recurring->amount,
                    'notes'    => 'Auto-generated from recurring: ' . ($recurring->description ?? $recurring->category),
                    'user_id'  => $recurring->user_id,
                ]);
            } else {
                ExpenseEntry::create([
                    'date'     => $today->format('Y-m-d'),
                    'category' => $recurring->category,
                    'amount'   => $recurring->amount,
                    'notes'    => 'Auto-generated from recurring: ' . ($recurring->description ?? $recurring->category),
                    'user_id'  => $recurring->user_id,
                ]);
            }

            // Advance next_due_date
            $nextDate = $this->calculateNextDate($today, $recurring->frequency);
            $recurring->update([
                'next_due_date'     => $nextDate,
                'last_processed_at' => now(),
            ]);

            $processed++;
        }

        $this->info("Processed {$processed} recurring transactions.");

        return 0;
    }

    private function calculateNextDate(Carbon $current, string $frequency): Carbon
    {
        switch ($frequency) {
            case 'daily':     return $current->copy()->addDay();
            case 'weekly':    return $current->copy()->addWeek();
            case 'monthly':   return $current->copy()->addMonth();
            case 'quarterly': return $current->copy()->addMonths(3);
            case 'yearly':    return $current->copy()->addYear();
            default:          return $current->copy()->addMonth();
        }
    }
}

