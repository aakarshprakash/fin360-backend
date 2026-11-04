<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\BillPayment;
use App\Models\Accounting\DebitNote;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\DocumentNumberService;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Accounting\LineAmounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BillController extends Controller
{
    private const ITEM_RULES = [
        'items.*.description'=> 'required|string|max:500',
        'items.*.hsn_code'   => 'nullable|string|max:20',
        'items.*.product_id' => 'nullable|integer|exists:products,id',
        'items.*.quantity'   => 'required|numeric|min:0.01',
        'items.*.unit_price' => 'required|numeric|min:0',
        'items.*.tax_rate'   => 'nullable|numeric|min:0',
        'items.*.account_id' => 'nullable|exists:accounting_accounts,id',
    ];

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
        $validated = $request->validate(array_merge([
            'vendor_id'          => 'nullable|exists:vendors,id',
            'date'               => 'required|date',
            'due_date'           => 'nullable|date|after_or_equal:date',
            'notes'              => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
        ], self::ITEM_RULES));

        return DB::transaction(function () use ($validated) {
            $billNumber = $this->numbering->next(Bill::class, 'bill_number', 'BILL-');

            ['items' => $itemsData, 'subtotal' => $subtotal, 'tax' => $totalTax, 'total' => $total] = $this->priceItems($validated['items']);

            $bill = Bill::create([
                'bill_number' => $billNumber,
                'vendor_id'   => $validated['vendor_id'] ?? null,
                'date'        => $validated['date'],
                'due_date'    => $validated['due_date'] ?? null,
                'subtotal'    => $subtotal,
                'tax'         => $totalTax,
                'total'       => $total,
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

    /**
     * PUT /api/accounting/bills/{id}
     * Sending items replaces the lines, recomputes the totals and posts the
     * difference to the ledger. The original entry stays as it was.
     */
    public function update(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $validated = $request->validate(array_merge([
            'vendor_id' => 'nullable|exists:vendors,id',
            'date'      => 'sometimes|required|date',
            'due_date'  => 'nullable|date',
            'notes'     => 'nullable|string|max:1000',
            'status'    => 'nullable|string|in:draft,pending,paid,overdue,cancelled',
            'items'     => 'sometimes|required|array|min:1',
        ], self::ITEM_RULES));

        if (!empty($validated['status']) && ($problem = $this->statusProblem($bill, $validated['status']))) {
            return response()->json(['message' => $problem], 422);
        }
        if (isset($validated['items'])) {
            if ((float) $bill->amount_paid > 0) {
                return response()->json(['message' => "This bill has payments, so its items can't be changed. Void them first."], 422);
            }
            if (in_array($bill->status, ['paid', 'cancelled'], true)) {
                return response()->json(['message' => "A {$bill->status} bill's items can't be changed."], 422);
            }
        }

        $newItems = $validated['items'] ?? null;
        unset($validated['items']);

        DB::transaction(function () use ($bill, $validated, $newItems) {
            $bill->update($validated);
            if ($newItems !== null) {
                $this->replaceItems($bill, $newItems);
            }
        });

        return response()->json([
            'message' => 'Bill updated successfully',
            'data'    => $this->billWithRelations($bill->id),
        ]);
    }

    /** Swap a bill's lines, then bring its totals and the ledger into line */
    private function replaceItems(Bill $bill, array $items): void
    {
        $oldSubtotal = (float) $bill->subtotal;
        $oldTax      = (float) $bill->tax;
        $oldTotal    = (float) $bill->total;

        $priced = $this->priceItems($items);

        $bill->items()->delete();
        foreach ($priced['items'] as $itemData) {
            $bill->items()->create($itemData);
        }
        $bill->update(['subtotal' => $priced['subtotal'], 'tax' => $priced['tax'], 'total' => $priced['total']]);

        $description = 'Items edited - ' . $bill->bill_number;
        $this->ledger->postAdjustment([
            'entry_number' => $this->numbering->next(JournalEntry::class, 'entry_number', 'JE-ADJ-BILL-' . $bill->id . '-'),
            'date'         => now()->toDateString(),
            'reference'    => $bill->bill_number,
            'description'  => 'Adjustment for edited bill ' . $bill->bill_number,
            'source_type'  => 'bill_adjustment',
            'source_id'    => $bill->id,
            'user_id'      => Auth::id(),
        ], [
            [$this->ledger->getOrCreateSystemAccount('5100', 'Purchases', 'Expense'), $priced['subtotal'] - $oldSubtotal, $description],
            [$this->ledger->getOrCreateSystemAccount('1300', 'Input GST', 'Assets'), $priced['tax'] - $oldTax, $description],
            [$this->ledger->getOrCreateSystemAccount('2100', 'Accounts Payable', 'Liabilities'), $oldTotal - $priced['total'], $description],
        ]);
    }

    /** Line rows and document totals for a set of submitted lines */
    private function priceItems(array $items): array
    {
        $subtotal = 0;
        $totalTax = 0;
        $itemsData = [];

        foreach ($items as $item) {
            $line = LineAmounts::of($item);

            $subtotal += $line['gross'];
            $totalTax += $line['tax'];

            $itemsData[] = [
                'description' => $item['description'],
                'hsn_code'    => $item['hsn_code'] ?? null,
                'product_id'  => $item['product_id'] ?? null,
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $item['tax_rate'] ?? 0,
                'tax_amount'  => $line['tax'],
                'total'       => $line['total'],
                'account_id'  => $item['account_id'] ?? null,
            ];
        }

        $subtotal = round($subtotal, 2);
        $totalTax = round($totalTax, 2);

        return [
            'items'    => $itemsData,
            'subtotal' => $subtotal,
            'tax'      => $totalTax,
            'total'    => round($subtotal + $totalTax, 2),
        ];
    }

    /** DELETE /api/accounting/bills/{id} */
    public function destroy($id)
    {
        $bill = Bill::findOrFail($id);
        if ($bill->payments()->exists()) {
            return response()->json(['message' => 'This bill has payments. Void them before deleting it.'], 422);
        }
        DB::transaction(function () use ($bill) {
            $this->ledger->reverseEntriesFor(['bill', 'bill_adjustment'], $bill->id);
            $bill->delete();
        });

        return response()->json(['message' => 'Bill deleted successfully']);
    }

    /** PATCH /api/accounting/bills/{id}/status */
    public function updateStatus(Request $request, $id)
    {
        $bill = Bill::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:draft,pending,paid,overdue,cancelled',
        ]);

        if ($problem = $this->statusProblem($bill, $validated['status'])) {
            return response()->json(['message' => $problem], 422);
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

        if (in_array($bill->status, ['cancelled', 'draft'], true)) {
            return response()->json(['message' => "A {$bill->status} bill can't take payments."], 422);
        }
        $outstanding = $this->outstanding($bill);
        if ((float) $validated['amount'] > $outstanding + 0.005) {
            return response()->json([
                'message' => 'The payment is more than the ' . number_format($outstanding, 2) . ' outstanding on this bill.',
                'errors'  => ['amount' => ['At most ' . number_format($outstanding, 2) . ' is outstanding.']],
            ], 422);
        }

        $validated['bill_id'] = $bill->id;
        $validated['user_id'] = Auth::id();

        $payment = DB::transaction(function () use ($validated, $bill) {
            $payment = BillPayment::create($validated);

            $this->refreshSettlement($bill);

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
            'bill'    => $this->billWithRelations($bill->id),
        ], 201);
    }

    /** DELETE /api/accounting/bills/{id}/payments/{paymentId}  (void a payment entered by mistake) */
    public function deletePayment($id, $paymentId)
    {
        $bill = Bill::findOrFail($id);
        $payment = BillPayment::where('bill_id', $bill->id)->findOrFail($paymentId);

        DB::transaction(function () use ($bill, $payment) {
            $this->ledger->reverseEntriesFor(['bill_payment'], $payment->id);

            // Bill payments come out of the first bank account
            $bankAccount = BankAccount::orderBy('id')->first();
            if ($bankAccount) {
                $bankAccount->increment('current_balance', (float) $payment->amount);
            }

            $payment->delete();
            $this->refreshSettlement($bill);
        });

        return response()->json([
            'message' => 'Payment voided',
            'bill'    => $this->billWithRelations($bill->id),
        ]);
    }

    /** Why a bill can't take this status, if so */
    private function statusProblem(Bill $bill, string $status): ?string
    {
        if ($status === 'paid' && $this->outstanding($bill) > 0.005) {
            return 'This bill still has ' . number_format($this->outstanding($bill), 2) . ' outstanding. Record a payment for it first.';
        }
        if ($status === 'cancelled' && (float) $bill->amount_paid > 0) {
            return 'This bill has payments. Void them before cancelling it.';
        }
        return null;
    }

    /** What is still owed to the vendor on a bill */
    private function outstanding(Bill $bill): float
    {
        return round((float) $bill->total - (float) $bill->amount_paid, 2);
    }

    /**
     * Recompute the paid total from the payments and set the status: settled →
     * paid; a paid bill that is no longer settled goes back to pending (or
     * overdue when past due); other statuses are kept.
     */
    private function refreshSettlement(Bill $bill): void
    {
        $paid = round((float) $bill->payments()->sum('amount'), 2);
        $bill->amount_paid = $paid;

        if ((float) $bill->total - $paid <= 0.005) {
            $bill->status = 'paid';
        } elseif ($bill->status === 'paid') {
            $bill->status = $bill->due_date && $bill->due_date->isPast() && !$bill->due_date->isToday() ? 'overdue' : 'pending';
        }
        $bill->save();
    }

    private function billWithRelations(int $id): Bill
    {
        return Bill::with(['vendor', 'items', 'payments', 'debitNotes'])->findOrFail($id);
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

        $debitNoteNumber = $this->numbering->next(DebitNote::class, 'debit_note_number', 'DN-');

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

