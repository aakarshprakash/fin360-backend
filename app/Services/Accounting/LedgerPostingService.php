<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Single place responsible for mutating Account.balance when journal entries
 * are posted or reversed. Previously this logic only ran from
 * JournalEntryController::post()/reverse(); auto-generated entries from
 * Invoice/Bill/QuotationController were created with status='posted' directly,
 * so their balance impact was silently skipped. Every code path that posts a
 * journal entry — manual or automatic — must go through this service.
 */
class LedgerPostingService
{
    /**
     * Create a journal entry with its lines and post it atomically in one step.
     * Used by auto-journal call sites (invoice/bill/quotation creation) which
     * previously created entries with status='posted' without applying balances.
     *
     * @param array $header JournalEntry fields (entry_number, date, reference, description, source_type, source_id, user_id, total_debit, total_credit)
     * @param array $lines  List of ['account_id', 'debit', 'credit', 'description']
     */
    public function createAndPostEntry(array $header, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($header, $lines) {
            $entry = JournalEntry::create(array_merge($header, ['status' => 'posted']));

            foreach ($lines as $line) {
                JournalEntryLine::create(array_merge($line, ['journal_entry_id' => $entry->id]));

                if (!empty($line['account_id'])) {
                    Account::where('id', $line['account_id'])
                        ->increment('balance', ($line['debit'] ?? 0) - ($line['credit'] ?? 0));
                }
            }

            return $entry->load('lines.account:id,code,name,type');
        });
    }

    /**
     * Post an already-draft journal entry, applying its balance impact.
     * Caller is responsible for the "already posted" guard/response.
     */
    public function postEntry(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            $entry->update(['status' => 'posted']);

            foreach ($entry->lines as $line) {
                if ($line->account) {
                    $line->account->increment('balance', $line->debit - $line->credit);
                }
            }

            return $entry;
        });
    }

    /**
     * Reverse a posted journal entry by creating an offsetting entry.
     * Caller is responsible for the "must be posted" guard/response.
     */
    public function reverseEntry(JournalEntry $entry): JournalEntry
    {
        return DB::transaction(function () use ($entry) {
            // Highest JE-REV number + 1: a count-based number repeats after deletions
            $entryNumber = app(DocumentNumberService::class)->next(JournalEntry::class, 'entry_number', 'JE-REV-');

            $reversal = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date'         => now()->format('Y-m-d'),
                'reference'    => 'Reversal of ' . $entry->entry_number,
                'description'  => 'Reversal of ' . $entry->description,
                'status'       => 'posted',
                'total_debit'  => $entry->total_credit,
                'total_credit' => $entry->total_debit,
                'user_id'      => Auth::id(),
            ]);

            foreach ($entry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversal->id,
                    'account_id'       => $line->account_id,
                    'debit'            => $line->credit,  // Swap
                    'credit'           => $line->debit,   // Swap
                    'description'      => 'Reversal: ' . ($line->description ?? ''),
                ]);

                if ($line->account) {
                    $line->account->increment('balance', $line->credit - $line->debit);
                }
            }

            return $reversal->load('lines.account:id,code,name,type');
        });
    }

    /**
     * Get or create a system chart-of-accounts entry by code, for use by
     * auto-journal postings (invoices, bills, quotations).
     */
    public function getOrCreateSystemAccount(string $code, string $name, string $type, ?int $userId = null): int
    {
        $account = Account::where('code', $code)->first();

        if (!$account) {
            $account = Account::create([
                'code'      => $code,
                'name'      => $name,
                'type'      => $type,
                'is_system' => true,
                'active'    => true,
                'user_id'   => $userId ?? Auth::id(),
            ]);
        }

        return $account->id;
    }

    /**
     * Get or create a chart-of-accounts entry by name within a type, for
     * postings keyed by free-text category (income/expense entries) rather
     * than a fixed code. Reuses an existing account whose name matches the
     * category (case-insensitive) — e.g. the seeded "Service Income" account —
     * otherwise creates a new one with the next available code in that type's
     * numeric range (Assets 1xxx, Liabilities 2xxx, Equity 3xxx, Income 4xxx,
     * Expense 5xxx).
     */
    public function getOrCreateAccountByName(string $name, string $type, ?int $userId = null): int
    {
        $account = Account::where('type', $type)
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->first();

        if ($account) {
            return $account->id;
        }

        $rangeStart = ['Assets' => 1000, 'Liabilities' => 2000, 'Equity' => 3000, 'Income' => 4000, 'Expense' => 5000][$type] ?? 9000;

        $maxCode = Account::where('type', $type)
            ->pluck('code')
            ->map(fn ($c) => ctype_digit((string) $c) ? (int) $c : 0)
            ->max();

        $nextCode = (string) (($maxCode && $maxCode >= $rangeStart) ? $maxCode + 10 : $rangeStart + 10);

        $account = Account::create([
            'code'      => $nextCode,
            'name'      => $name,
            'type'      => $type,
            'is_system' => true,
            'active'    => true,
            'user_id'   => $userId ?? Auth::id(),
        ]);

        return $account->id;
    }
}
