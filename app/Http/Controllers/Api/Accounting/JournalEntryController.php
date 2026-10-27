<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    private LedgerPostingService $ledger;

    public function __construct(LedgerPostingService $ledger)
    {
        $this->ledger = $ledger;
    }

    /** GET /api/accounting/journal-entries */
    public function index(Request $request)
    {
        $query = JournalEntry::with('user:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('dateFrom')) {
            $query->where('date', '>=', $request->dateFrom);
        }
        if ($request->filled('dateTo')) {
            $query->where('date', '<=', $request->dateTo);
        }

        $perPage = $request->get('per_page', 15);
        $entries = $query->orderBy('date', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
                'per_page'     => $entries->perPage(),
                'total'        => $entries->total(),
            ],
        ]);
    }

    /** GET /api/accounting/journal-entries/{id} */
    public function show($id)
    {
        $entry = JournalEntry::with(['lines.account:id,code,name,type', 'user:id,name'])->findOrFail($id);
        return response()->json(['data' => $entry]);
    }

    /** POST /api/accounting/journal-entries */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'date'                   => 'required|date',
            'reference'              => 'nullable|string|max:100',
            'description'            => 'nullable|string|max:1000',
            'lines'                  => 'required|array|min:2',
            'lines.*.account_id'     => 'required|exists:accounting_accounts,id',
            'lines.*.debit'          => 'required|numeric|min:0',
            'lines.*.credit'         => 'required|numeric|min:0',
            'lines.*.description'    => 'nullable|string|max:500',
        ]);

        // Validate balanced
        $totalDebit  = array_sum(array_column($validated['lines'], 'debit'));
        $totalCredit = array_sum(array_column($validated['lines'], 'credit'));

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            return response()->json([
                'message' => 'Journal entry must be balanced. Total debit must equal total credit.',
                'errors'  => ['lines' => ["Debit ({$totalDebit}) does not equal Credit ({$totalCredit})"]],
            ], 422);
        }

        return DB::transaction(function () use ($validated, $totalDebit, $totalCredit) {
            // Highest JE- number + 1 (a count repeats after deletions; JE-INV-… etc. are ignored)
            $entryNumber = app(\App\Services\Accounting\DocumentNumberService::class)->next(JournalEntry::class, 'entry_number', 'JE-');

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'date'         => $validated['date'],
                'reference'    => $validated['reference'] ?? null,
                'description'  => $validated['description'] ?? null,
                'status'       => 'draft',
                'total_debit'  => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'user_id'      => Auth::id(),
            ]);

            foreach ($validated['lines'] as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line['account_id'],
                    'debit'            => $line['debit'],
                    'credit'           => $line['credit'],
                    'description'      => $line['description'] ?? null,
                ]);
            }

            $entry->load('lines.account:id,code,name,type');

            return response()->json([
                'message' => 'Journal entry created successfully',
                'data'    => $entry,
            ], 201);
        });
    }

    /** PUT /api/accounting/journal-entries/{id} */
    public function update(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Cannot edit a posted journal entry'], 422);
        }

        $validated = $request->validate([
            'date'        => 'sometimes|required|date',
            'reference'   => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
        ]);

        $entry->update($validated);

        return response()->json([
            'message' => 'Journal entry updated successfully',
            'data'    => $entry,
        ]);
    }

    /** DELETE /api/accounting/journal-entries/{id} */
    public function destroy($id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Cannot delete a posted journal entry'], 422);
        }

        $entry->lines()->delete();
        $entry->delete();

        return response()->json(['message' => 'Journal entry deleted successfully']);
    }

    /** PATCH /api/accounting/journal-entries/{id}/post */
    public function post($id)
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);

        if ($entry->status === 'posted') {
            return response()->json(['message' => 'Already posted'], 422);
        }

        $entry = $this->ledger->postEntry($entry);
        $entry->load('lines.account:id,code,name,type');

        return response()->json([
            'message' => 'Journal entry posted successfully',
            'data'    => $entry,
        ]);
    }

    /** POST /api/accounting/journal-entries/{id}/reverse */
    public function reverse($id)
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);

        if ($entry->status !== 'posted') {
            return response()->json(['message' => 'Only posted entries can be reversed'], 422);
        }

        $reversal = $this->ledger->reverseEntry($entry);

        return response()->json([
            'message' => 'Journal entry reversed successfully',
            'data'    => $reversal,
        ], 201);
    }
}

