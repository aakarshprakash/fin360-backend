<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\ProformaInvoice;
use App\Models\Accounting\ProformaInvoiceItem;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationItem;
use App\Models\Accounting\AccountingSetting;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    private LedgerPostingService $ledger;
    private DocumentNumberService $numbering;

    public function __construct(LedgerPostingService $ledger, DocumentNumberService $numbering)
    {
        $this->ledger = $ledger;
        $this->numbering = $numbering;
    }

    /**
     * GET /api/accounting/quotations
     * List all quotations with filters.
     */
    public function index(Request $request)
    {
        $query = Quotation::with('customer:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('quotation_number', 'like', "%{$s}%")
                  ->orWhere('customer_name', 'like', "%{$s}%")
                  ->orWhereHas('customer', function ($cq) use ($s) {
                      $cq->where('name', 'like', "%{$s}%");
                  });
            });
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $perPage = $request->get('per_page', 15);
        $quotations = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $quotations->items(),
            'meta' => [
                'current_page' => $quotations->currentPage(),
                'last_page'    => $quotations->lastPage(),
                'per_page'     => $quotations->perPage(),
                'total'        => $quotations->total(),
            ],
        ]);
    }

    /**
     * GET /api/accounting/quotations/{id}
     * Get single quotation with items.
     */
    public function show($id)
    {
        $quotation = Quotation::with(['customer', 'items', 'convertedInvoice'])->findOrFail($id);
        return response()->json(['data' => $quotation]);
    }

    /**
     * POST /api/accounting/quotations
     * Create a new quotation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'            => 'nullable|exists:accounting_customers,id',
            'customer_name'          => 'nullable|string|max:200',
            'date'                   => 'required|date',
            'valid_until'            => 'nullable|date|after_or_equal:date',
            'currency'               => 'nullable|string|max:10',
            'exchange_rate'          => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string|max:2000',
            'terms_and_conditions'   => 'nullable|string|max:5000',
            'branch'                 => 'nullable|string|max:150',
            'discount'               => 'nullable|numeric|min:0',
            'use_signature'          => 'nullable|boolean',
            'items'                  => 'required|array|min:1',
            'items.*.description'    => 'required|string|max:500',
            'items.*.hsn_code'       => 'nullable|string|max:20',
            'items.*.product_id'     => 'nullable|integer|exists:products,id',
            'items.*.quantity'       => 'required|numeric|min:0.01',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.discount'       => 'nullable|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            // Generate quotation number
            $quotationNumber = $this->numbering->next(Quotation::class, 'quotation_number', 'QTN-');

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
                ];
            }

            $discount = $validated['discount'] ?? 0;
            $total = $subtotal - $discount + $totalTax;

            // Default valid_until to date + 30 days if not provided
            $validUntil = $validated['valid_until'] ?? date('Y-m-d', strtotime($validated['date'] . ' +30 days'));

            $quotation = Quotation::create([
                'quotation_number'     => $quotationNumber,
                'customer_id'          => $validated['customer_id'] ?? null,
                'customer_name'        => $validated['customer_name'] ?? null,
                'date'                 => $validated['date'],
                'valid_until'          => $validUntil,
                'currency'             => $validated['currency'] ?? 'INR',
                'exchange_rate'        => $validated['exchange_rate'] ?? 1,
                'subtotal'             => round($subtotal, 2),
                'discount'             => round($discount, 2),
                'tax'                  => round($totalTax, 2),
                'total'                => round($total, 2),
                'status'               => 'draft',
                'notes'                => $validated['notes'] ?? null,
                'terms_and_conditions' => $validated['terms_and_conditions'] ?? null,
                'branch'               => $validated['branch'] ?? null,
                'use_signature'        => $validated['use_signature'] ?? true,
                'user_id'              => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $itemData['quotation_id'] = $quotation->id;
                QuotationItem::create($itemData);
            }

            $quotation->load(['customer', 'items']);

            return response()->json([
                'message' => 'Quotation created successfully',
                'data'    => $quotation,
            ], 201);
        });
    }

    /**
     * PUT /api/accounting/quotations/{id}
     * Update a quotation.
     */
    public function update(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);

        // Cannot update converted quotations
        if ($quotation->status === 'converted') {
            return response()->json([
                'message' => 'Cannot update a converted quotation',
            ], 422);
        }

        $validated = $request->validate([
            'customer_id'            => 'nullable|exists:accounting_customers,id',
            'customer_name'          => 'nullable|string|max:200',
            'date'                   => 'sometimes|required|date',
            'valid_until'            => 'nullable|date',
            'currency'               => 'nullable|string|max:10',
            'exchange_rate'          => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string|max:2000',
            'terms_and_conditions'   => 'nullable|string|max:5000',
            'branch'                 => 'nullable|string|max:150',
            'discount'               => 'nullable|numeric|min:0',
            'use_signature'          => 'nullable|boolean',
            'items'                  => 'sometimes|required|array|min:1',
            'items.*.description'    => 'required|string|max:500',
            'items.*.hsn_code'       => 'nullable|string|max:20',
            'items.*.product_id'     => 'nullable|integer|exists:products,id',
            'items.*.quantity'       => 'required|numeric|min:0.01',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.discount'       => 'nullable|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($quotation, $validated) {
            // Update basic fields
            $fieldsToUpdate = collect($validated)->except('items')->toArray();

            // If items are provided, recalculate totals
            if (isset($validated['items'])) {
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
                    ];
                }

                $discount = $validated['discount'] ?? $quotation->discount;
                $total = $subtotal - $discount + $totalTax;

                $fieldsToUpdate['subtotal'] = round($subtotal, 2);
                $fieldsToUpdate['tax']      = round($totalTax, 2);
                $fieldsToUpdate['discount'] = round($discount, 2);
                $fieldsToUpdate['total']    = round($total, 2);

                // Remove old items and create new ones
                $quotation->items()->delete();
                foreach ($itemsData as $itemData) {
                    $itemData['quotation_id'] = $quotation->id;
                    QuotationItem::create($itemData);
                }
            }

            $quotation->update($fieldsToUpdate);
            $quotation->load(['customer', 'items']);

            return response()->json([
                'message' => 'Quotation updated successfully',
                'data'    => $quotation,
            ]);
        });
    }

    /**
     * DELETE /api/accounting/quotations/{id}
     * Soft-delete a quotation.
     */
    public function destroy($id)
    {
        $quotation = Quotation::findOrFail($id);
        $quotation->delete();

        return response()->json(['message' => 'Quotation deleted successfully']);
    }

    /**
     * PATCH /api/accounting/quotations/{id}/status
     * Change quotation status.
     */
    public function updateStatus(Request $request, $id)
    {
        $quotation = Quotation::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,sent,accepted,rejected,expired',
        ]);

        // Enforce valid status transitions
        $allowedTransitions = [
            'draft'    => ['sent', 'accepted', 'rejected', 'expired'],
            'sent'     => ['accepted', 'rejected', 'expired'],
            'accepted' => ['expired'],  // converted is handled by convert-to-invoice
            'rejected' => ['draft'],    // allow re-opening rejected quotation
            'expired'  => ['draft'],    // allow re-opening expired quotation
        ];

        $currentStatus = $quotation->status;
        $newStatus = $validated['status'];

        if ($currentStatus === 'converted') {
            return response()->json([
                'message' => 'Cannot change status of a converted quotation',
            ], 422);
        }

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'message' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'",
            ], 422);
        }

        $quotation->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Quotation status updated',
            'data'    => $quotation,
        ]);
    }

    /**
     * POST /api/accounting/quotations/{id}/duplicate
     * Duplicate a quotation.
     */
    public function duplicate($id)
    {
        $original = Quotation::with('items')->findOrFail($id);

        return DB::transaction(function () use ($original) {
            $quotationNumber = $this->numbering->next(Quotation::class, 'quotation_number', 'QTN-');

            $newQuotation = $original->replicate();
            $newQuotation->quotation_number = $quotationNumber;
            $newQuotation->status = 'draft';
            $newQuotation->date = now()->toDateString();
            $newQuotation->valid_until = now()->addDays(30)->toDateString();
            $newQuotation->converted_invoice_id = null;
            $newQuotation->user_id = Auth::id();
            $newQuotation->save();

            foreach ($original->items as $item) {
                $newItem = $item->replicate();
                $newItem->quotation_id = $newQuotation->id;
                $newItem->save();
            }

            $newQuotation->load(['customer', 'items']);

            return response()->json([
                'message' => 'Quotation duplicated successfully',
                'data'    => $newQuotation,
            ], 201);
        });
    }

    /**
     * POST /api/accounting/quotations/{id}/convert-to-invoice
     * Convert quotation to an invoice.
     */
    public function convertToInvoice($id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        // Cannot convert already-converted quotation
        if ($quotation->status === 'converted') {
            return response()->json([
                'message' => 'This quotation has already been converted to an invoice',
            ], 422);
        }

        return DB::transaction(function () use ($quotation) {
            // Generate invoice number
            $invoiceNumber = $this->numbering->next(Invoice::class, 'invoice_number', 'INV-');

            // Create the invoice from quotation data
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id'    => $quotation->customer_id,
                'date'           => now()->toDateString(),
                'due_date'       => now()->addDays(30)->toDateString(),
                'currency'       => $quotation->currency,
                'exchange_rate'  => $quotation->exchange_rate,
                'subtotal'       => $quotation->subtotal,
                'discount'       => $quotation->discount,
                'tax'            => $quotation->tax,
                'total'          => $quotation->total,
                'amount_paid'    => 0,
                'status'         => 'pending',
                'notes'          => $quotation->notes,
                'use_signature'  => $quotation->use_signature,
                'user_id'        => Auth::id(),
            ]);

            // Copy items to invoice items
            foreach ($quotation->items as $qItem) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $qItem->description,
                    'hsn_code'    => $qItem->hsn_code,
                    'product_id'  => $qItem->product_id,
                    'quantity'    => $qItem->quantity,
                    'unit_price'  => $qItem->unit_price,
                    'discount'    => $qItem->discount,
                    'tax_rate'    => $qItem->tax_rate,
                    'tax_amount'  => $qItem->tax_amount,
                    'total'       => $qItem->total,
                ]);
            }

            // Auto-create journal entry for the new invoice
            $this->createJournalEntry($invoice);

            // Mark quotation as converted
            $quotation->update([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
            ]);

            $invoice->load(['customer', 'items']);

            return response()->json([
                'message' => 'Quotation converted to invoice successfully',
                'data'    => [
                    'invoice'   => $invoice,
                    'quotation' => $quotation->fresh(['customer', 'items']),
                ],
            ], 201);
        });
    }

    /**
     * POST /api/accounting/quotations/{id}/convert-to-proforma
     * Convert quotation to a proforma invoice.
     */
    public function convertToProforma($id)
    {
        $quotation = Quotation::with('items')->findOrFail($id);

        if ($quotation->status === 'converted') {
            return response()->json([
                'message' => 'This quotation has already been converted',
            ], 422);
        }

        return DB::transaction(function () use ($quotation) {
            $proformaNumber = $this->numbering->next(ProformaInvoice::class, 'proforma_number', 'PI-');

            $proforma = ProformaInvoice::create([
                'proforma_number'      => $proformaNumber,
                'customer_id'          => $quotation->customer_id,
                'customer_name'        => $quotation->customer_name,
                'date'                 => now()->toDateString(),
                'valid_until'          => now()->addDays(15)->toDateString(),
                'currency'             => $quotation->currency,
                'exchange_rate'        => $quotation->exchange_rate,
                'subtotal'             => $quotation->subtotal,
                'discount'             => $quotation->discount,
                'tax'                  => $quotation->tax,
                'total'                => $quotation->total,
                'status'               => 'draft',
                'notes'                => $quotation->notes,
                'terms_and_conditions' => $quotation->terms_and_conditions,
                'branch'               => $quotation->branch,
                'use_signature'        => $quotation->use_signature,
                'quotation_id'         => $quotation->id,
                'user_id'              => Auth::id(),
            ]);

            foreach ($quotation->items as $qItem) {
                ProformaInvoiceItem::create([
                    'proforma_invoice_id' => $proforma->id,
                    'description'         => $qItem->description,
                    'hsn_code'            => $qItem->hsn_code,
                    'product_id'          => $qItem->product_id,
                    'quantity'            => $qItem->quantity,
                    'unit_price'          => $qItem->unit_price,
                    'discount'            => $qItem->discount,
                    'tax_rate'            => $qItem->tax_rate,
                    'tax_amount'          => $qItem->tax_amount,
                    'total'               => $qItem->total,
                ]);
            }

            $quotation->update([
                'status'                 => 'converted',
                'converted_proforma_id'  => $proforma->id,
            ]);

            $proforma->load(['customer', 'items']);

            return response()->json([
                'message' => 'Quotation converted to proforma invoice successfully',
                'data'    => [
                    'proforma'  => $proforma,
                    'quotation' => $quotation->fresh(['customer', 'items']),
                ],
            ], 201);
        });
    }

    /**
     * POST /api/accounting/quotations/{id}/send
     * Send quotation via email (stub).
     */
    public function send($id)
    {
        $quotation = Quotation::findOrFail($id);

        // Mark as sent if still draft
        if ($quotation->status === 'draft') {
            $quotation->update(['status' => 'sent']);
        }

        return response()->json([
            'message' => 'Quotation sent successfully (stub)',
            'data'    => $quotation,
        ]);
    }

    /**
     * GET /api/accounting/quotations/{id}/pdf
     * Download quotation as PDF (HTML stub).
     */
    public function pdf($id)
    {
        $quotation = Quotation::with(['customer', 'items'])->findOrFail($id);
        $settings  = AccountingSetting::forCompany();
        $company   = optional($settings)->company ?? [];
        $terms     = optional($settings)->quotation ?? [];

        $pdf = Pdf::loadView('accounting.pdf.quotation', [
            'quotation' => $quotation,
            'company'   => $company,
            'terms'     => $terms,
        ]);

        return $pdf->download('quotation-' . $quotation->quotation_number . '.pdf');
    }

    /**
     * Create a journal entry when converting quotation to invoice.
     */
    private function createJournalEntry(Invoice $invoice)
    {
        $revenueAmount = $invoice->subtotal - $invoice->discount;

        $lines = [
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets'),
                'debit'       => round($invoice->total, 2),
                'credit'      => 0,
                'description' => 'Accounts Receivable - ' . $invoice->invoice_number,
            ],
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('4100', 'Sales Revenue', 'Income'),
                'debit'       => 0,
                'credit'      => round($revenueAmount, 2),
                'description' => 'Sales Revenue - ' . $invoice->invoice_number,
            ],
        ];

        if ($invoice->tax > 0) {
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('2200', 'GST Payable', 'Liabilities'),
                'debit'       => 0,
                'credit'      => round($invoice->tax, 2),
                'description' => 'GST Payable - ' . $invoice->invoice_number,
            ];
        }

        $this->ledger->createAndPostEntry([
            'entry_number' => 'JE-INV-' . $invoice->id,
            'date'         => $invoice->date,
            'reference'    => $invoice->invoice_number,
            'description'  => 'Auto entry for invoice ' . $invoice->invoice_number . ' (from quotation)',
            'total_debit'  => round($invoice->total, 2),
            'total_credit' => round($invoice->total, 2),
            'source_type'  => 'invoice',
            'source_id'    => $invoice->id,
            'user_id'      => Auth::id(),
        ], $lines);
    }
}

