<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\InvoicePayment;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\AccountingSetting;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Accounting\StockService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    private LedgerPostingService $ledger;
    private DocumentNumberService $numbering;
    private StockService $stock;

    public function __construct(LedgerPostingService $ledger, DocumentNumberService $numbering, StockService $stock)
    {
        $this->ledger = $ledger;
        $this->numbering = $numbering;
        $this->stock = $stock;
    }

    /** GET /api/accounting/invoices */
    public function index(Request $request)
    {
        $query = Invoice::with('customer:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer')) {
            $query->where('customer_id', $request->customer);
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('invoice_number', 'like', "%{$s}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('name', 'like', "%{$s}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $invoices = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $invoices->items(),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page'    => $invoices->lastPage(),
                'per_page'     => $invoices->perPage(),
                'total'        => $invoices->total(),
            ],
        ]);
    }

    /** GET /api/accounting/invoices/{id} */
    public function show($id)
    {
        $invoice = Invoice::with(['customer', 'items', 'payments', 'creditNotes'])->findOrFail($id);
        return response()->json(['data' => $invoice]);
    }

    /** POST /api/accounting/invoices */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'        => 'nullable|exists:accounting_customers,id',
            'date'               => 'required|date',
            'due_date'           => 'nullable|date|after_or_equal:date',
            'currency'           => 'nullable|string|max:10',
            'exchange_rate'      => 'nullable|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'notes'              => 'nullable|string|max:1000',
            'use_signature'      => 'nullable|boolean',
            'ship_to'            => 'nullable|string|max:1000',
            'extra_fields'       => 'nullable|array|max:12',
            'extra_fields.*'     => 'nullable|string|max:200',
            'items'              => 'required|array|min:1',
            'items.*.description'=> 'required|string|max:500',
            'items.*.hsn_code'   => 'nullable|string|max:20',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.unit'       => 'nullable|string|max:20',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount'   => 'nullable|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0',
            'items.*.account_id' => 'nullable|exists:accounting_accounts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            // Generate invoice number
            $invoiceNumber = $this->numbering->next(Invoice::class, 'invoice_number', 'INV-');

            // Calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $lineSubtotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
                $taxRate = $item['tax_rate'] ?? 0;
                $taxAmount = $lineSubtotal * ($taxRate / 100);
                $lineTotal = $lineSubtotal + $taxAmount;

                $subtotal += $lineSubtotal;
                $totalTax += $taxAmount;

                $itemsData[] = [
                    'description' => $item['description'],
                    'hsn_code'    => $item['hsn_code'] ?? null,
                    'product_id'  => $item['product_id'] ?? null,
                    'quantity'    => $item['quantity'],
                    'unit'        => $item['unit'] ?? null,
                    'unit_price'  => $item['unit_price'],
                    'discount'    => $item['discount'] ?? 0,
                    'tax_rate'    => $taxRate,
                    'tax_amount'  => round($taxAmount, 2),
                    'total'       => round($lineTotal, 2),
                    'account_id'  => $item['account_id'] ?? null,
                ];
            }

            $discount = $validated['discount'] ?? 0;
            $total = $subtotal - $discount + $totalTax;

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id'    => $validated['customer_id'] ?? null,
                'date'           => $validated['date'],
                'due_date'       => $validated['due_date'] ?? null,
                'currency'       => $validated['currency'] ?? 'INR',
                'exchange_rate'  => $validated['exchange_rate'] ?? 1,
                'subtotal'       => round($subtotal, 2),
                'discount'       => round($discount, 2),
                'tax'            => round($totalTax, 2),
                'total'          => round($total, 2),
                'amount_paid'    => 0,
                'status'         => 'pending',
                'notes'          => $validated['notes'] ?? null,
                'use_signature'  => $validated['use_signature'] ?? true,
                'ship_to'        => $validated['ship_to'] ?? null,
                'extra_fields'   => $this->cleanExtraFields($validated['extra_fields'] ?? null),
                'user_id'        => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $itemData['invoice_id'] = $invoice->id;
                InvoiceItem::create($itemData);
            }

            // Auto-create journal entry
            $this->createJournalEntry($invoice, $subtotal, $discount, $totalTax, $total);

            // Take sold quantities out of stock for tracked products
            $this->stock->deductForInvoice($invoice);

            $invoice->load(['customer', 'items', 'payments']);

            return response()->json([
                'message' => 'Invoice created successfully',
                'data'    => $invoice,
            ], 201);
        });
    }

    /** PUT /api/accounting/invoices/{id} */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'customer_id'   => 'nullable|exists:accounting_customers,id',
            'date'          => 'sometimes|required|date',
            'due_date'      => 'nullable|date',
            'currency'      => 'nullable|string|max:10',
            'exchange_rate' => 'nullable|numeric|min:0',
            'discount'      => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string|max:1000',
            'use_signature' => 'nullable|boolean',
            'ship_to'       => 'nullable|string|max:1000',
            'extra_fields'  => 'nullable|array|max:12',
            'extra_fields.*'=> 'nullable|string|max:200',
            'status'        => 'nullable|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if (array_key_exists('extra_fields', $validated)) {
            $validated['extra_fields'] = $this->cleanExtraFields($validated['extra_fields']);
        }
        $invoice->update($validated);
        $invoice->load(['customer', 'items', 'payments']);

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data'    => $invoice,
        ]);
    }

    /**
     * Values for the print template's custom fields, keyed by field id
     * (lower-case letters/digits, as InvoiceSettingsController issues them).
     * Blank values are dropped.
     */
    private function cleanExtraFields(?array $fields): ?array
    {
        $clean = [];
        foreach ($fields ?? [] as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9]{0,23}$/', $key)) {
                continue;
            }
            $value = trim(strip_tags((string) $value));
            if ($value !== '') {
                $clean[$key] = $value;
            }
        }
        return $clean ?: null;
    }

    /** DELETE /api/accounting/invoices/{id} */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        if ($invoice->payments()->exists() || $invoice->creditNotes()->exists()) {
            return response()->json([
                'message' => 'This invoice has payments or credit notes. Void them before deleting it.',
            ], 422);
        }
        DB::transaction(function () use ($invoice) {
            $this->stock->restoreForInvoice($invoice);
            $invoice->delete();
        });

        return response()->json(['message' => 'Invoice deleted successfully']);
    }

    /**
     * PATCH /api/accounting/invoices/{id}/status
     * "Paid" is reached by recording payments / credit notes, not set by hand.
     */
    public function updateStatus(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if ($validated['status'] === 'paid' && $this->outstanding($invoice) > 0.005) {
            return response()->json([
                'message' => 'This invoice still has ' . number_format($this->outstanding($invoice), 2) . ' outstanding. Record a payment or a credit note for it first.',
            ], 422);
        }
        if ($validated['status'] === 'cancelled' && ((float) $invoice->amount_paid > 0 || (float) $invoice->amount_credited > 0)) {
            return response()->json([
                'message' => 'This invoice has payments or credit notes. Void them before cancelling it.',
            ], 422);
        }

        $invoice->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Invoice status updated',
            'data'    => $invoice,
        ]);
    }

    /** POST /api/accounting/invoices/{id}/payments  (full or part payment) */
    public function addPayment(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'amount'          => 'required|numeric|min:0.01',
            'date'            => 'required|date',
            'method'          => 'nullable|string|max:50',
            'bank_account_id' => 'nullable|integer|exists:bank_accounts,id,deleted_at,NULL',
            'reference'       => 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        if (in_array($invoice->status, ['cancelled', 'draft'], true)) {
            return response()->json(['message' => "A {$invoice->status} invoice can't take payments."], 422);
        }
        $outstanding = $this->outstanding($invoice);
        if ((float) $validated['amount'] > $outstanding + 0.005) {
            return response()->json([
                'message' => 'The payment is more than the ' . number_format($outstanding, 2) . ' outstanding on this invoice.',
                'errors'  => ['amount' => ['At most ' . number_format($outstanding, 2) . ' is outstanding.']],
            ], 422);
        }

        $bankAccount = isset($validated['bank_account_id'])
            ? BankAccount::find($validated['bank_account_id'])
            : BankAccount::orderBy('id')->first();
        $validated['bank_account_id'] = optional($bankAccount)->id;
        $validated['invoice_id'] = $invoice->id;
        $validated['user_id']    = Auth::id();
        $amount = round((float) $validated['amount'], 2);

        $payment = DB::transaction(function () use ($validated, $invoice, $bankAccount, $amount) {
            $payment = InvoicePayment::create($validated);

            // Debit the bank's ledger account (or Cash & Bank), credit Accounts Receivable
            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-PAY-INV-' . $payment->id,
                'date'         => $validated['date'],
                'reference'    => $invoice->invoice_number,
                'description'  => 'Payment received for invoice ' . $invoice->invoice_number,
                'total_debit'  => $amount,
                'total_credit' => $amount,
                'source_type'  => 'invoice_payment',
                'source_id'    => $payment->id,
                'user_id'      => Auth::id(),
            ], [
                [
                    'account_id'  => optional($bankAccount)->account_id
                        ?: $this->ledger->getOrCreateSystemAccount('1010', 'Cash & Bank', 'Assets'),
                    'debit'       => $amount,
                    'credit'      => 0,
                    'description' => 'Payment received - ' . $invoice->invoice_number,
                ],
                [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                    'debit'       => 0,
                    'credit'      => $amount,
                    'description' => 'Accounts Receivable settled - ' . $invoice->invoice_number,
                ],
            ]);

            if ($bankAccount) {
                $bankAccount->increment('current_balance', $amount);
            }

            $this->refreshSettlement($invoice);
            return $payment;
        });

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data'    => $payment,
            'invoice' => $this->invoiceWithRelations($invoice->id),
        ], 201);
    }

    /** DELETE /api/accounting/invoices/{id}/payments/{paymentId}  (void a payment entered by mistake) */
    public function deletePayment($id, $paymentId)
    {
        $invoice = Invoice::findOrFail($id);
        $payment = InvoicePayment::where('invoice_id', $invoice->id)->findOrFail($paymentId);

        DB::transaction(function () use ($invoice, $payment) {
            $this->reverseSourceEntry('invoice_payment', $payment->id);

            // Older payments did not store their bank; they went to the first account
            $bank = $payment->bank_account_id
                ? BankAccount::find($payment->bank_account_id)
                : BankAccount::orderBy('id')->first();
            if ($bank) {
                $bank->decrement('current_balance', (float) $payment->amount);
            }

            $payment->delete();
            $this->refreshSettlement($invoice);
        });

        return response()->json([
            'message' => 'Payment voided',
            'invoice' => $this->invoiceWithRelations($invoice->id),
        ]);
    }

    /** GET /api/accounting/invoices/{id}/payments */
    public function listPayments($id)
    {
        Invoice::findOrFail($id);
        $payments = InvoicePayment::where('invoice_id', $id)->orderBy('date', 'desc')->get();

        return response()->json(['data' => $payments]);
    }

    /**
     * POST /api/accounting/invoices/{id}/credit-notes
     * Lines: [{description, quantity, unit_price, tax_rate}]. Reduces what the
     * customer owes and reverses the sale in the ledger (debit Sales Revenue
     * and GST Payable, credit Accounts Receivable).
     */
    public function storeCreditNote(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'date'               => 'required|date',
            'reason_type'        => 'required|string|in:return,price_adjustment,discount,damaged,other',
            'reason'             => 'nullable|string|max:500',
            'items'              => 'required|array|min:1|max:100',
            'items.*.description'=> 'required|string|max:500',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0|max:100',
        ]);

        if (in_array($invoice->status, ['cancelled', 'draft'], true)) {
            return response()->json(['message' => "A {$invoice->status} invoice can't take credit notes."], 422);
        }

        $items = [];
        $subtotal = 0;
        $tax = 0;
        foreach ($validated['items'] as $line) {
            $amount  = round((float) $line['quantity'] * (float) $line['unit_price'], 2);
            $lineTax = round($amount * ((float) ($line['tax_rate'] ?? 0)) / 100, 2);
            $subtotal += $amount;
            $tax      += $lineTax;
            $items[] = [
                'description' => strip_tags($line['description']),
                'quantity'    => (float) $line['quantity'],
                'unit_price'  => (float) $line['unit_price'],
                'tax_rate'    => (float) ($line['tax_rate'] ?? 0),
                'tax_amount'  => $lineTax,
                'total'       => round($amount + $lineTax, 2),
            ];
        }
        $total = round($subtotal + $tax, 2);

        $outstanding = $this->outstanding($invoice);
        if ($total <= 0) {
            return response()->json(['message' => 'The credit note total must be more than zero.'], 422);
        }
        if ($total > $outstanding + 0.005) {
            return response()->json([
                'message' => 'The credit note (' . number_format($total, 2) . ') is more than the ' . number_format($outstanding, 2) . ' outstanding. Void a payment first if the customer is to be refunded.',
            ], 422);
        }

        $creditNote = DB::transaction(function () use ($invoice, $validated, $items, $subtotal, $tax, $total) {
            $creditNote = CreditNote::create([
                'invoice_id'         => $invoice->id,
                'credit_note_number' => $this->numbering->next(CreditNote::class, 'credit_note_number', 'CN-'),
                'date'               => $validated['date'],
                'reason_type'        => $validated['reason_type'],
                'reason'             => isset($validated['reason']) ? strip_tags($validated['reason']) : null,
                'items'              => $items,
                'subtotal'           => round($subtotal, 2),
                'tax'                => round($tax, 2),
                'total'              => $total,
                'user_id'            => Auth::id(),
            ]);

            $lines = [[
                'account_id'  => $this->ledger->getOrCreateSystemAccount('4100', 'Sales Revenue', 'Income'),
                'debit'       => round($subtotal, 2),
                'credit'      => 0,
                'description' => 'Sales reversed - ' . $creditNote->credit_note_number,
            ]];
            if ($tax > 0) {
                $lines[] = [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('2200', 'GST Payable', 'Liabilities'),
                    'debit'       => round($tax, 2),
                    'credit'      => 0,
                    'description' => 'GST reversed - ' . $creditNote->credit_note_number,
                ];
            }
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                'debit'       => 0,
                'credit'      => $total,
                'description' => 'Accounts Receivable credited - ' . $invoice->invoice_number,
            ];

            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-CN-' . $creditNote->id,
                'date'         => $validated['date'],
                'reference'    => $creditNote->credit_note_number,
                'description'  => 'Credit note ' . $creditNote->credit_note_number . ' against invoice ' . $invoice->invoice_number,
                'total_debit'  => $total,
                'total_credit' => $total,
                'source_type'  => 'credit_note',
                'source_id'    => $creditNote->id,
                'user_id'      => Auth::id(),
            ], $lines);

            $this->refreshSettlement($invoice);
            return $creditNote;
        });

        return response()->json([
            'message' => 'Credit note ' . $creditNote->credit_note_number . ' created',
            'data'    => $creditNote,
            'invoice' => $this->invoiceWithRelations($invoice->id),
        ], 201);
    }

    /** DELETE /api/accounting/invoices/{id}/credit-notes/{creditNoteId}  (void) */
    public function deleteCreditNote($id, $creditNoteId)
    {
        $invoice    = Invoice::findOrFail($id);
        $creditNote = CreditNote::where('invoice_id', $invoice->id)->findOrFail($creditNoteId);

        DB::transaction(function () use ($invoice, $creditNote) {
            $this->reverseSourceEntry('credit_note', $creditNote->id);
            $creditNote->delete();
            $this->refreshSettlement($invoice);
        });

        return response()->json([
            'message' => 'Credit note voided',
            'invoice' => $this->invoiceWithRelations($invoice->id),
        ]);
    }

    /** What the customer still owes on an invoice */
    private function outstanding(Invoice $invoice): float
    {
        return round((float) $invoice->total - (float) $invoice->amount_paid - (float) $invoice->amount_credited, 2);
    }

    /**
     * Recompute paid/credited totals from the records and set the status:
     * settled → paid; a paid invoice that is no longer settled goes back to
     * pending (or overdue when past due); other statuses are kept.
     */
    private function refreshSettlement(Invoice $invoice): void
    {
        $paid     = round((float) $invoice->payments()->sum('amount'), 2);
        $credited = round((float) $invoice->creditNotes()->sum('total'), 2);
        $invoice->amount_paid     = $paid;
        $invoice->amount_credited = $credited;

        if ((float) $invoice->total - $paid - $credited <= 0.005) {
            $invoice->status = 'paid';
        } elseif ($invoice->status === 'paid') {
            $invoice->status = $invoice->due_date && $invoice->due_date->isPast() && !$invoice->due_date->isToday() ? 'overdue' : 'pending';
        }
        $invoice->save();
    }

    /** Post the reversal of the journal entry created for a payment / credit note */
    private function reverseSourceEntry(string $sourceType, int $sourceId): void
    {
        $entry = JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)
            ->where('status', 'posted')->first();
        if ($entry) {
            $this->ledger->reverseEntry($entry);
        }
    }

    private function invoiceWithRelations(int $id): Invoice
    {
        return Invoice::with(['customer', 'items', 'payments', 'creditNotes'])->findOrFail($id);
    }

    /** POST /api/accounting/invoices/{id}/send */
    public function send($id)
    {
        Invoice::findOrFail($id);
        // Stub — in production, send email
        return response()->json(['message' => 'Invoice sent successfully (stub)']);
    }

    /** GET /api/accounting/invoices/{id}/pdf */
    public function pdf($id)
    {
        $invoice = Invoice::with(['customer', 'items'])->findOrFail($id);
        $company = AccountingSetting::forCompany()->company ?? [];

        $pdf = Pdf::loadView('accounting.pdf.invoice', [
            'invoice' => $invoice,
            'company' => $company,
        ]);

        return $pdf->download('invoice-' . $invoice->invoice_number . '.pdf');
    }

    /** POST /api/accounting/credit-notes  {invoice_id, …} — same as the invoice route */
    public function createCreditNote(Request $request)
    {
        $request->validate(['invoice_id' => 'required|integer|exists:invoices,id']);

        return $this->storeCreditNote($request, $request->input('invoice_id'));
    }

    /** GET /api/accounting/credit-notes */
    public function listCreditNotes(Request $request)
    {
        $query = CreditNote::with('invoice:id,invoice_number,customer_id');

        if ($request->filled('invoiceId')) {
            $query->where('invoice_id', $request->invoiceId);
        }

        $creditNotes = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $creditNotes]);
    }

    private function createJournalEntry(Invoice $invoice, float $subtotal, float $discount, float $tax, float $total)
    {
        $lines = [
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                'debit'       => round($total, 2),
                'credit'      => 0,
                'description' => 'Accounts Receivable - ' . $invoice->invoice_number,
            ],
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('4100', 'Sales Revenue', 'Income'),
                'debit'       => 0,
                'credit'      => round($subtotal - $discount, 2),
                'description' => 'Sales Revenue - ' . $invoice->invoice_number,
            ],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('2200', 'GST Payable', 'Liabilities'),
                'debit'       => 0,
                'credit'      => round($tax, 2),
                'description' => 'GST Payable - ' . $invoice->invoice_number,
            ];
        }

        $this->ledger->createAndPostEntry([
            'entry_number' => 'JE-INV-' . $invoice->id,
            'date'         => $invoice->date,
            'reference'    => $invoice->invoice_number,
            'description'  => 'Auto entry for invoice ' . $invoice->invoice_number,
            'total_debit'  => round($total, 2),
            'total_credit' => round($total, 2),
            'source_type'  => 'invoice',
            'source_id'    => $invoice->id,
            'user_id'      => Auth::id(),
        ], $lines);
    }
}

