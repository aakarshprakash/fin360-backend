<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\BillPayment;
use App\Models\Accounting\DebitNote;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    private LedgerPostingService $ledger;
    private DocumentNumberService $numbering;

    public function __construct(LedgerPostingService $ledger, DocumentNumberService $numbering)
    {
        $this->ledger = $ledger;
        $this->numbering = $numbering;
    }

    /** GET /api/accounting/bills */
    public function index(Request $request)
    {
        $query = Bill::with('vendor:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vendor')) {
            $query->where('vendor_id', $request->vendor);
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $perPage = $request->get('per_page', 15);
        $bills = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $bills->items(),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'last_page'    => $bills->lastPage(),
                'per_page'     => $bills->perPage(),
                'total'        => $bills->total(),
            ],
        ]);
    }

    /** GET /api/accounting/bills/{id} */
    public function show($id)
    {
        $bill = Bill::with(['vendor', 'items', 'payments', 'debitNotes'])->findOrFail($id);
        return response()->json(['data' => $bill]);
    }

    /** POST /api/accounting/bills */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id'          => 'nullable|exists:vendors,id',
            'date'               => 'required|date',
            'due_date'           => 'nullable|date|after_or_equal:date',
            'notes'              => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.description'=> 'required|string|max:500',
            'items.*.hsn_code'   => 'nullable|string|max:20',
            'items.*.product_id' => 'nullable|integer|exists:products,id',
            'items.*.quantity'   => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0',
            'items.*.account_id' => 'nullable|exists:accounting_accounts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $billNumber = $this->numbering->next(Bill::class, 'bill_number', 'BILL-');

            $subtotal = 0;
            $totalTax = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $lineSubtotal = $item['quantity'] * $item['unit_price'];
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
                    'tax_rate'    => $taxRate,
                    'tax_amount'  => round($taxAmount, 2),
                    'total'       => round($lineTotal, 2),
                    'account_id'  => $item['account_id'] ?? null,
                ];
            }

            $total = $subtotal + $totalTax;

            $bill = Bill::create([
                'bill_number' => $billNumber,
                'vendor_id'   => $validated['vendor_id'] ?? null,
                'date'        => $validated['date'],
                'due_date'    => $validated['due_date'] ?? null,
                'subtotal'    => round($subtotal, 2),
                'tax'         => round($totalTax, 2),
                'total'       => round($total, 2),
                'amount_paid' => 0,
                'status'      => 'pending',
                'notes'       => $validated['notes'] ?? null,
                'user_id'     => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $itemData['bill_id'] = $bill->id;
                BillItem::create($itemData);
            }

            // Auto-create journal entry
            $this->createJournalEntry($bill, $subtotal, $totalTax, $total);

            $bill->load(['vendor', 'items', 'payments']);

            return response()->json([
                'message' => 'Bill created successfully',
                'data'    => $bill,
            ], 201);
        });
    }

    /** PUT /api/accounting/bills/{id} */
    public function update(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $validated = $request->validate([
            'vendor_id' => 'nullable|exists:vendors,id',
            'date'      => 'sometimes|required|date',
            'due_date'  => 'nullable|date',
            'notes'     => 'nullable|string|max:1000',
            'status'    => 'nullable|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        $bill->update($validated);
        $bill->load(['vendor', 'items', 'payments']);

        return response()->json([
            'message' => 'Bill updated successfully',
            'data'    => $bill,
        ]);
    }

    /** DELETE /api/accounting/bills/{id} */
    public function destroy($id)
    {
        $bill = Bill::findOrFail($id);
        $bill->delete();

        return response()->json(['message' => 'Bill deleted successfully']);
    }

    /** PATCH /api/accounting/bills/{id}/status */
    public function updateStatus(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if ($validated['status'] === 'paid' && (float) $bill->amount_paid < (float) $bill->total) {
            return response()->json([
                'message' => 'Cannot mark bill as paid without a full payment recorded. Use the payments endpoint to record payment first.',
            ], 422);
        }

        $bill->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Bill status updated',
            'data'    => $bill,
        ]);
    }

    /** POST /api/accounting/bills/{id}/payments */
    public function addPayment(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $validated = $request->validate([
            'amount'    => 'required|numeric|min:0.01',
            'date'      => 'required|date',
            'method'    => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
            'notes'     => 'nullable|string|max:500',
        ]);

        $validated['bill_id'] = $bill->id;
        $validated['user_id'] = Auth::id();

        $payment = DB::transaction(function () use ($validated, $bill) {
            $payment = BillPayment::create($validated);

            $totalPaid = $bill->payments()->sum('amount');
            $status = $totalPaid >= $bill->total ? 'paid' : 'pending';
            $bill->update(['amount_paid' => $totalPaid, 'status' => $status]);

            // Post the cash movement to the ledger (debit Accounts Payable, credit Cash & Bank)
            // so account balances and the bank balance actually reflect payments made.
            $this->ledger->createAndPostEntry([
                'entry_number' => 'JE-PAY-BILL-' . $payment->id,
                'date'         => $validated['date'],
                'reference'    => $bill->bill_number,
                'description'  => 'Payment made for bill ' . $bill->bill_number,
                'total_debit'  => round($validated['amount'], 2),
                'total_credit' => round($validated['amount'], 2),
                'source_type'  => 'bill_payment',
                'source_id'    => $payment->id,
                'user_id'      => Auth::id(),
            ], [
                [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('2100', 'Accounts Payable', 'Liabilities'),
                    'debit'       => round($validated['amount'], 2),
                    'credit'      => 0,
                    'description' => 'Accounts Payable settled - ' . $bill->bill_number,
                ],
                [
                    'account_id'  => $this->ledger->getOrCreateSystemAccount('1010', 'Cash & Bank', 'Assets'),
                    'debit'       => 0,
                    'credit'      => round($validated['amount'], 2),
                    'description' => 'Payment made - ' . $bill->bill_number,
                ],
            ]);

            $bankAccount = BankAccount::orderBy('id')->first();
            if ($bankAccount) {
                $bankAccount->decrement('current_balance', $validated['amount']);
            }

            return $payment;
        });

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data'    => $payment,
        ], 201);
    }

    /** GET /api/accounting/bills/{id}/payments */
    public function listPayments($id)
    {
        Bill::findOrFail($id);
        $payments = BillPayment::where('bill_id', $id)->orderBy('date', 'desc')->get();

        return response()->json(['data' => $payments]);
    }

    /** POST /api/accounting/debit-notes */
    public function createDebitNote(Request $request)
    {
        $validated = $request->validate([
            'billId' => 'required|exists:bills,id',
            'items'  => 'required|array|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        $nextNum = DebitNote::count() + 1;
        $debitNoteNumber = 'DN-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

        $total = 0;
        foreach ($validated['items'] as $item) {
            $total += ($item['total'] ?? $item['amount'] ?? 0);
        }

        $debitNote = DebitNote::create([
            'bill_id'           => $validated['billId'],
            'debit_note_number' => $debitNoteNumber,
            'items'             => $validated['items'],
            'reason'            => $validated['reason'] ?? null,
            'total'             => round($total, 2),
            'user_id'           => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Debit note created successfully',
            'data'    => $debitNote,
        ], 201);
    }

    /** GET /api/accounting/debit-notes */
    public function listDebitNotes(Request $request)
    {
        $query = DebitNote::with('bill:id,bill_number,vendor_id');

        if ($request->filled('billId')) {
            $query->where('bill_id', $request->billId);
        }

        $debitNotes = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $debitNotes]);
    }

    private function createJournalEntry(Bill $bill, float $subtotal, float $tax, float $total)
    {
        $lines = [
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('5100', 'Purchases', 'Expense'),
                'debit'       => round($subtotal, 2),
                'credit'      => 0,
                'description' => 'Purchase - ' . $bill->bill_number,
            ],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1300', 'Input GST', 'Assets'),
                'debit'       => round($tax, 2),
                'credit'      => 0,
                'description' => 'Input GST - ' . $bill->bill_number,
            ];
        }

        $lines[] = [
            'account_id'  => $this->ledger->getOrCreateSystemAccount('2100', 'Accounts Payable', 'Liabilities'),
            'debit'       => 0,
            'credit'      => round($total, 2),
            'description' => 'Accounts Payable - ' . $bill->bill_number,
        ];

        $this->ledger->createAndPostEntry([
            'entry_number' => 'JE-BILL-' . $bill->id,
            'date'         => $bill->date,
            'reference'    => $bill->bill_number,
            'description'  => 'Auto entry for bill ' . $bill->bill_number,
            'total_debit'  => round($total, 2),
            'total_credit' => round($total, 2),
            'source_type'  => 'bill',
            'source_id'    => $bill->id,
            'user_id'      => Auth::id(),
        ], $lines);
    }
}

