<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingSetting;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\ProformaInvoice;
use App\Models\Accounting\ProformaInvoiceItem;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProformaInvoiceController extends Controller
{
    private LedgerPostingService $ledger;
    private DocumentNumberService $numbering;

    public function __construct(LedgerPostingService $ledger, DocumentNumberService $numbering)
    {
        $this->ledger = $ledger;
        $this->numbering = $numbering;
    }

    /**
     * GET /api/accounting/proforma-invoices
     */
    public function index(Request $request)
    {
        $query = ProformaInvoice::with('customer:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('proforma_number', 'like', "%{$s}%")
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
        $proformas = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $proformas->items(),
            'meta' => [
                'current_page' => $proformas->currentPage(),
                'last_page'    => $proformas->lastPage(),
                'per_page'     => $proformas->perPage(),
                'total'        => $proformas->total(),
            ],
        ]);
    }

    /**
     * GET /api/accounting/proforma-invoices/{id}
     */
    public function show($id)
    {
        $proforma = ProformaInvoice::with(['customer', 'items', 'quotation', 'convertedInvoice'])->findOrFail($id);
        return response()->json(['data' => $proforma]);
    }

    /**
     * POST /api/accounting/proforma-invoices
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
            $proformaNumber = $this->nextNumber();

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
            $validUntil = $validated['valid_until'] ?? date('Y-m-d', strtotime($validated['date'] . ' +15 days'));

            $proforma = ProformaInvoice::create([
                'proforma_number'      => $proformaNumber,
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
                $itemData['proforma_invoice_id'] = $proforma->id;
                ProformaInvoiceItem::create($itemData);
            }

            $proforma->load(['customer', 'items']);

            return response()->json([
                'message' => 'Proforma invoice created successfully',
                'data'    => $proforma,
            ], 201);
        });
    }

    /**
     * PUT /api/accounting/proforma-invoices/{id}
     */
    public function update(Request $request, $id)
    {
        $proforma = ProformaInvoice::findOrFail($id);

        if ($proforma->status === 'converted') {
            return response()->json([
                'message' => 'Cannot update a converted proforma invoice',
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

        return DB::transaction(function () use ($proforma, $validated) {
            $fieldsToUpdate = collect($validated)->except('items')->toArray();

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

                $discount = $validated['discount'] ?? $proforma->discount;
                $total = $subtotal - $discount + $totalTax;

                $fieldsToUpdate['subtotal'] = round($subtotal, 2);
                $fieldsToUpdate['tax']      = round($totalTax, 2);
                $fieldsToUpdate['discount'] = round($discount, 2);
                $fieldsToUpdate['total']    = round($total, 2);

                $proforma->items()->delete();
                foreach ($itemsData as $itemData) {
                    $itemData['proforma_invoice_id'] = $proforma->id;
                    ProformaInvoiceItem::create($itemData);
                }
            }

            $proforma->update($fieldsToUpdate);
            $proforma->load(['customer', 'items']);

            return response()->json([
                'message' => 'Proforma invoice updated successfully',
                'data'    => $proforma,
            ]);
        });
    }

    /**
     * DELETE /api/accounting/proforma-invoices/{id}
     */
    public function destroy($id)
    {
        $proforma = ProformaInvoice::findOrFail($id);
        $proforma->delete();

        return response()->json(['message' => 'Proforma invoice deleted successfully']);
    }

    /**
     * PATCH /api/accounting/proforma-invoices/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $proforma = ProformaInvoice::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,sent,accepted,rejected,expired',
        ]);

        $allowedTransitions = [
            'draft'    => ['sent', 'accepted', 'rejected', 'expired'],
            'sent'     => ['accepted', 'rejected', 'expired'],
            'accepted' => ['expired'],
            'rejected' => ['draft'],
            'expired'  => ['draft'],
        ];

        $currentStatus = $proforma->status;
        $newStatus = $validated['status'];

        if ($currentStatus === 'converted') {
            return response()->json([
                'message' => 'Cannot change status of a converted proforma invoice',
            ], 422);
        }

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            return response()->json([
                'message' => "Cannot transition from '{$currentStatus}' to '{$newStatus}'",
            ], 422);
        }

        $proforma->update(['status' => $newStatus]);

        return response()->json([
            'message' => 'Proforma invoice status updated',
            'data'    => $proforma,
        ]);
    }

    /**
     * POST /api/accounting/proforma-invoices/{id}/duplicate
     */
    public function duplicate($id)
    {
        $original = ProformaInvoice::with('items')->findOrFail($id);

        return DB::transaction(function () use ($original) {
            $newProforma = $original->replicate();
            $newProforma->proforma_number = $this->nextNumber();
            $newProforma->status = 'draft';
            $newProforma->date = now()->toDateString();
            $newProforma->valid_until = now()->addDays(15)->toDateString();
            $newProforma->quotation_id = null;
            $newProforma->converted_invoice_id = null;
            $newProforma->user_id = Auth::id();
            $newProforma->save();

            foreach ($original->items as $item) {
                $newItem = $item->replicate();
                $newItem->proforma_invoice_id = $newProforma->id;
                $newItem->save();
            }

            $newProforma->load(['customer', 'items']);

            return response()->json([
                'message' => 'Proforma invoice duplicated successfully',
                'data'    => $newProforma,
            ], 201);
        });
    }

    /**
     * POST /api/accounting/proforma-invoices/{id}/convert-to-invoice
     */
    public function convertToInvoice($id)
    {
        $proforma = ProformaInvoice::with('items')->findOrFail($id);

        if ($proforma->status === 'converted') {
            return response()->json([
                'message' => 'This proforma invoice has already been converted to an invoice',
            ], 422);
        }

        return DB::transaction(function () use ($proforma) {
            $invoiceNumber = $this->numbering->next(Invoice::class, 'invoice_number', 'INV-');

            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'customer_id'    => $proforma->customer_id,
                'date'           => now()->toDateString(),
                'due_date'       => now()->addDays(30)->toDateString(),
                'currency'       => $proforma->currency,
                'exchange_rate'  => $proforma->exchange_rate,
                'subtotal'       => $proforma->subtotal,
                'discount'       => $proforma->discount,
                'tax'            => $proforma->tax,
                'total'          => $proforma->total,
                'amount_paid'    => 0,
                'status'         => 'pending',
                'notes'          => $proforma->notes,
                'use_signature'  => $proforma->use_signature,
                'user_id'        => Auth::id(),
            ]);

            foreach ($proforma->items as $pItem) {
                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $pItem->description,
                    'hsn_code'    => $pItem->hsn_code,
                    'product_id'  => $pItem->product_id,
                    'quantity'    => $pItem->quantity,
                    'unit_price'  => $pItem->unit_price,
                    'discount'    => $pItem->discount,
                    'tax_rate'    => $pItem->tax_rate,
                    'tax_amount'  => $pItem->tax_amount,
                    'total'       => $pItem->total,
                ]);
            }

            $this->createJournalEntry($invoice);

            $proforma->update([
                'status'               => 'converted',
                'converted_invoice_id' => $invoice->id,
            ]);

            $invoice->load(['customer', 'items']);

            return response()->json([
                'message' => 'Proforma invoice converted to invoice successfully',
                'data'    => [
                    'invoice'  => $invoice,
                    'proforma' => $proforma->fresh(['customer', 'items']),
                ],
            ], 201);
        });
    }

    /**
     * POST /api/accounting/proforma-invoices/{id}/send
     */
    public function send($id)
    {
        $proforma = ProformaInvoice::findOrFail($id);

        if ($proforma->status === 'draft') {
            $proforma->update(['status' => 'sent']);
        }

        return response()->json([
            'message' => 'Proforma invoice sent successfully (stub)',
            'data'    => $proforma,
        ]);
    }

    /**
     * GET /api/accounting/proforma-invoices/{id}/pdf
     */
    public function pdf($id)
    {
        $proforma = ProformaInvoice::with(['customer', 'items'])->findOrFail($id);
        $settings = AccountingSetting::forCompany();
        $company  = optional($settings)->company ?? [];
        $terms    = optional($settings)->quotation ?? [];

        $pdf = Pdf::loadView('accounting.pdf.proforma-invoice', [
            'proforma' => $proforma,
            'company'  => $company,
            'terms'    => $terms,
        ]);

        return $pdf->download('proforma-' . $proforma->proforma_number . '.pdf');
    }

    private function nextNumber(): string
    {
        return $this->numbering->next(ProformaInvoice::class, 'proforma_number', 'PI-');
    }

    /**
     * Create a journal entry when converting a proforma invoice to an invoice.
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
            'description'  => 'Auto entry for invoice ' . $invoice->invoice_number . ' (from proforma invoice)',
            'total_debit'  => round($invoice->total, 2),
            'total_credit' => round($invoice->total, 2),
            'source_type'  => 'invoice',
            'source_id'    => $invoice->id,
            'user_id'      => Auth::id(),
        ], $lines);
    }
}
