<?php

namespace App\Console\Commands;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time/idempotent backfill for the double-entry bookkeeping bug: prior to
 * LedgerPostingService, auto-generated journal entries (from invoices/bills/
 * quotations) were created with status='posted' directly, so their balance
 * impact was never applied to Account.balance. This recomputes every
 * account's balance from scratch off posted journal entry lines, so it's
 * safe to run repeatedly.
 */
class RecalculateAccountBalances extends Command
{
    protected $signature = 'accounting:recalculate-balances';

    protected $description = 'Recalculate every chart-of-accounts balance from posted journal entry lines';

    public function handle()
    {
        $sums = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->groupBy('journal_entry_lines.account_id')
            ->select('journal_entry_lines.account_id', DB::raw('SUM(journal_entry_lines.debit - journal_entry_lines.credit) as net'))
            ->pluck('net', 'account_id');

        $accounts = Account::all();
        $updated = 0;

        foreach ($accounts as $account) {
            $net = (float) ($sums[$account->id] ?? 0);
            $newBalance = round((float) $account->opening_balance + $net, 2);

            if ((float) $account->balance !== $newBalance) {
                $account->update(['balance' => $newBalance]);
                $updated++;
            }

            $this->line("  {$account->code} {$account->name}: balance = {$newBalance}");
        }

        $this->info("Recalculated {$accounts->count()} account(s), {$updated} changed.");

        return self::SUCCESS;
    }
}
