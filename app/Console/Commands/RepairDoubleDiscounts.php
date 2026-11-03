<?php

namespace App\Console\Commands;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\ProformaInvoice;
use App\Models\Accounting\Quotation;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Documents saved before 2.3.0 took line discounts off twice: the subtotal was
 * already net of them, and the same amount came off again as the document
 * discount. This finds those documents and sets subtotal to the amount before
 * discount and total to subtotal - discount + tax.
 *
 * Reports only unless told otherwise. Invoices need their own flag because
 * they are in the accounts, and any with payments or credit notes are only
 * listed: raising their total would change what the customer owes.
 */
class RepairDoubleDiscounts extends Command
{
    protected $signature = 'fin360:repair-double-discounts
                            {--apply : Correct quotations and proforma invoices}
                            {--invoices : With --apply, also correct unpaid invoices and post an adjusting journal entry}';

    protected $description = 'Find (and optionally correct) documents where line discounts were subtracted twice';

    public function handle(LedgerPostingService $ledger): int
    {
        $apply = (bool) $this->option('apply');
        $withInvoices = $apply && $this->option('invoices');
        $rows = [];

        foreach ([[Quotation::class, 'quotation_number'], [ProformaInvoice::class, 'proforma_number']] as [$model, $numberColumn]) {
            foreach ($model::with('items')->where('discount', '>', 0)->get() as $doc) {
                if (!$fix = $this->correction($doc)) {
                    continue;
                }
                $savedTotal = (float) $doc->total;
                if ($apply) {
                    $doc->update(['subtotal' => $fix['subtotal'], 'total' => $fix['total']]);
                }
                $rows[] = [$doc->{$numberColumn}, $doc->status, $savedTotal, $fix['total'], $apply ? 'corrected' : 'would correct'];
            }
        }

        foreach (Invoice::with('items')->where('discount', '>', 0)->get() as $invoice) {
            if (!$fix = $this->correction($invoice)) {
                continue;
            }
            $wrongTotal = (float) $invoice->total;
            $settled = (float) $invoice->amount_paid > 0 || (float) $invoice->amount_credited > 0;

            if ($settled) {
                $action = 'REVIEW BY HAND: has payments or credit notes';
            } elseif ($invoice->status === 'cancelled') {
                $action = 'skipped: cancelled';
            } elseif ($withInvoices) {
                $this->correctInvoice($invoice, $fix, $wrongTotal, $ledger);
                $action = 'corrected, adjusting entry posted';
            } else {
                $action = $apply ? 'not changed: needs --invoices' : 'would correct with --apply --invoices';
            }
            $rows[] = [$invoice->invoice_number, $invoice->status, $wrongTotal, $fix['total'], $action];
        }

        if (!$rows) {
            $this->info('No affected documents found.');
            return 0;
        }

        $this->table(['Document', 'Status', 'Saved total', 'Correct total', 'Action'], $rows);
        if (!$apply) {
            $this->line('Nothing was changed. Run with --apply (and --invoices for invoices) to correct them.');
        }
        return 0;
    }

    /** The corrected figures, or null when this document isn't affected */
    private function correction($doc): ?array
    {
        $gross = 0;
        $lineDiscounts = 0;
        foreach ($doc->items as $item) {
            $gross += (float) $item->quantity * (float) $item->unit_price;
            $lineDiscounts += (float) $item->discount;
        }
        $discount = (float) $doc->discount;

        // Double-counted: discount equals the line discounts and the subtotal is already net of them
        if ($lineDiscounts <= 0
            || abs($discount - $lineDiscounts) > 0.02
            || abs((float) $doc->subtotal - ($gross - $lineDiscounts)) > 0.02) {
            return null;
        }

        return [
            'subtotal' => round($gross, 2),
            'total'    => round($gross - $discount + (float) $doc->tax, 2),
        ];
    }

    private function correctInvoice(Invoice $invoice, array $fix, float $wrongTotal, LedgerPostingService $ledger): void
    {
        $difference = round($fix['total'] - $wrongTotal, 2);

        DB::transaction(function () use ($invoice, $fix, $difference, $ledger) {
            $invoice->update(['subtotal' => $fix['subtotal'], 'total' => $fix['total']]);

            // The original entry under-recorded both the receivable and the sale
            $ledger->createAndPostEntry([
                'entry_number' => 'JE-ADJ-INV-' . $invoice->id,
                'date'         => now()->toDateString(),
                'reference'    => $invoice->invoice_number,
                'description'  => 'Correction of double-counted discount on invoice ' . $invoice->invoice_number,
                'total_debit'  => $difference,
                'total_credit' => $difference,
                'source_type'  => 'invoice_adjustment',
                'source_id'    => $invoice->id,
            ], [
                [
                    'account_id'  => $ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                    'debit'       => $difference,
                    'credit'      => 0,
                    'description' => 'Discount correction - ' . $invoice->invoice_number,
                ],
                [
                    'account_id'  => $ledger->getOrCreateSystemAccount('4100', 'Sales Revenue', 'Income'),
                    'debit'       => 0,
                    'credit'      => $difference,
                    'description' => 'Discount correction - ' . $invoice->invoice_number,
                ],
            ]);
        });
    }
}
