<?php

namespace App\Console\Commands;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Before 2.4.0, deleting an invoice or bill left its journal entries posted, so
 * the ledger's Accounts Receivable / Payable kept counting it. This finds those
 * documents and reverses their entries.
 *
 * Reports only unless told otherwise. A deleted document with payments or
 * credit notes is only listed: the money may really have moved, and reversing
 * the sale alone would leave the receivable or payable wrong the other way.
 */
class RepairDeletedDocuments extends Command
{
    protected $signature = 'fin360:repair-deleted-documents
                            {--apply : Reverse the entries of deleted documents that have no payments or credit notes}';

    protected $description = 'Find (and optionally reverse) journal entries left behind by deleted invoices and bills';

    public function handle(LedgerPostingService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $rows = [];
        $reversible = 0;

        foreach (Invoice::onlyTrashed()->withCount(['payments', 'creditNotes'])->get() as $invoice) {
            $settlements = $invoice->payments_count + $invoice->credit_notes_count;
            $rows[] = $this->process($ledger, ['invoice', 'invoice_adjustment'], $invoice->id, $invoice->invoice_number, $invoice->total, $settlements, $apply, $reversible);
        }
        foreach (Bill::onlyTrashed()->withCount('payments')->get() as $bill) {
            $rows[] = $this->process($ledger, ['bill', 'bill_adjustment'], $bill->id, $bill->bill_number, $bill->total, $bill->payments_count, $apply, $reversible);
        }
        $rows = array_values(array_filter($rows));

        if (!$rows) {
            $this->info('No deleted documents with journal entries found.');
            return 0;
        }

        $this->table(['Document', 'Total', 'Entries', 'Action'], $rows);
        if (!$apply && $reversible) {
            $this->line('Nothing was changed. Run with --apply to reverse the ' . $reversible . ' listed as "would reverse".');
        }
        return 0;
    }

    /** The report row for one deleted document, or null when it left nothing behind */
    private function process(LedgerPostingService $ledger, array $sourceTypes, int $id, string $number, $total, int $settlements, bool $apply, int &$reversible): ?array
    {
        $entries = $ledger->unreversedEntries($sourceTypes, $id);
        if ($entries->isEmpty()) {
            return null;
        }

        if ($settlements > 0) {
            $action = 'REVIEW BY HAND: has payments or credit notes';
        } elseif ($apply) {
            DB::transaction(fn () => $ledger->reverseEntriesFor($sourceTypes, $id));
            $action = 'reversed';
        } else {
            $reversible++;
            $action = 'would reverse';
        }

        return [$number, (float) $total, $entries->pluck('entry_number')->implode(', '), $action];
    }
}
