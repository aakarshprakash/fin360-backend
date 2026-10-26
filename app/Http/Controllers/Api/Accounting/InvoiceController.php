<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\InvoicePayment;
use App\Models\Accounting\AccountingSetting;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    private LedgerPostingService $ledger;
    private DocumentNumberService $numbering;

    public function __construct(LedgerPostingService $ledger, DocumentNumberService $numbering)
    {
        $this->ledger = $ledger;
        $this->numbering = $numbering;
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
            'items'              => 'required|array|min:1',
            'items.*.description'=> 'required|string|max:500',
            'items.*.hsn_code'   => 'nullable|string|max:20',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
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
                'user_id'        => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $itemData['invoice_id'] = $invoice->id;
                InvoiceItem::create($itemData);
            }

            // Auto-create journal entry
            $this->createJournalEntry($invoice, $subtotal, $discount, $totalTax, $total);

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
            'status'        => 'nullable|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        $invoice->update($validated);
        $invoice->load(['customer', 'items', 'payments']);

        return response()->json([
            'message' => 'Invoice updated successfully',
            'data'    => $invoice,
        ]);
    }

    /** DELETE /api/accounting/invoices/{id} */
    public function destroy($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted successfully']);
    }

    /** PATCH /api/accounting/invoices/{id}/status */
    public function updateStatus(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if ($validated['status'] === 'paid' && (float) $invoice->amount_paid < (float) $invoice->total) {
            return response()->json([
                'message' => 'Cannot mark invoice as paid without a full payment recorded. Use the payments endpoint to record payment first.',
            ], 422);
        }

        $invoice->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Invoice status updated',
            'data'    => $invoice,
        ]);
    }

    /** POST /api/accounting/invoices/{id}/payments */
    public function addPayment(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $validated = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'date'      => 'required|date',
            'method'    => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes'     => 'nullable|string|max:500',
        ]);

        $validated['invoice_id'] = $invoice->id;
        $validated['user_id']    = Auth::id();

        $payment = DB::transaction(function () use ($validated, $invoice) {
            $payment = InvoicePayment::create($validated);

            // Update invoice amount_paid
            $totalPaid = $invoice->payments()->sum('amount');
            $status = $totalPaid >= $invoice->total ? 'paid' : 'pending';
            $invoice->update(['amount_paid' => $totalPaid, 'status' => $status]);

            // Post the cash movement to the ledger (debit Cash & Bank, credit Accounts Receivable)
            // so account balances and the bank balance actually reflect payments received.
            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-PAY-INV-' . $payment->id,
                'date'         => $validated['date'],
                'reference'    => $invoice->invoice_number,
                'description'  => 'Payment received for invoice ' . $invoice->invoice_number,
                'total_debit'  => round($validated['amount'], 2),
                'total_credit' => round($validated['amount'], 2),
                'source_type'  => 'invoice_payment',
                'source_id'    => $payment->id,
                'user_id'      => Auth::id(),
            ], [
                [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('1010', 'Cash & Bank', 'Assets'),
                    'debit'       => round($validated['amount'], 2),
                    'credit'      => 0,
                    'description' => 'Payment received - ' . $invoice->invoice_number,
                ],
                [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                    'debit'       => 0,
                    'credit'      => round($validated['amount'], 2),
                    'description' => 'Accounts Receivable settled - ' . $invoice->invoice_number,
                ],
            ]);

            $bankAccount = BankAccount::orderBy('id')->first();
            if ($bankAccount) {
                $bankAccount->increment('current_balance', $validated['amount']);
            }

            return $payment;
        });

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data'    => $payment,
        ], 201);
    }

    /** GET /api/accounting/invoices/{id}/payments */
    public function listPayments($id)
    {
        Invoice::findOrFail($id);
        $payments = InvoicePayment::where('invoice_id', $id)->orderBy('date', 'desc')->get();

        return response()->json(['data' => $payments]);
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

    /** POST /api/accounting/credit-notes */
    public function createCreditNote(Request $request)
    {
        $validated = $request->validate([
            'invoiceId' => 'required|exists:invoices,id',
            'items'     => 'required|array|min:1',
            'reason'    => 'nullable|string|max:500',
        ]);

        $nextNum = CreditNote::count() + 1;
        $creditNoteNumber = 'CN-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $total = 0;
        foreach ($validated['items'] as $item) {
            $total += ($item['total'] ?? $item['amount'] ?? 0);
        }

        $creditNote = CreditNote::create([
            'invoice_id'         => $validated['invoiceId'],
            'credit_note_number' => $creditNoteNumber,
            'items'              => $validated['items'],
            'reason'             => $validated['reason'] ?? null,
            'total'              => round($total, 2),
            'user_id'            => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Credit note created successfully',
            'data'    => $creditNote,
        ], 201);
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

