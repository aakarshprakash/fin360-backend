<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Quotation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     *
     * Finance alerts derived from live data: overdue and soon-due invoices and
     * bills, plus quotations about to lapse. Nothing is persisted.
     */
    public function index(Request $request)
    {
        $notifications = [];

        $user = $request->user();
        if (!$user || !$user->hasAnyPermission(['view_accounting', 'manage_accounting', 'view_invoices'])) {
            return response()->json(['data' => [], 'count' => 0]);
        }

        $today    = Carbon::today();
        $weekAway = Carbon::today()->addDays(7);
        $closed   = ['paid', 'cancelled'];

        // Overdue invoices (money owed to us)
        $overdueInvoices = Invoice::with('customer:id,name')
            ->whereNotIn('status', $closed)
            ->where('due_date', '<', $today)
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $notifications[] = [
                'id'         => 'invoice_overdue_' . $invoice->id,
                'type'       => 'invoice_overdue',
                'module'     => 'invoices',
                'title'      => 'Overdue Invoice',
                'message'    => $invoice->invoice_number . ($invoice->customer ? ' — ' . $invoice->customer->name : '') . ' was due ' . $invoice->due_date->format('M d, Y'),
                'invoice_id' => $invoice->id,
                'time'       => $invoice->due_date->format('M d, Y'),
                'read'       => false,
            ];
        }

        // Invoices due within a week
        $dueInvoices = Invoice::with('customer:id,name')
            ->whereNotIn('status', $closed)
            ->whereBetween('due_date', [$today, $weekAway])
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        foreach ($dueInvoices as $invoice) {
            $notifications[] = [
                'id'         => 'invoice_due_' . $invoice->id,
                'type'       => 'invoice_due',
                'module'     => 'invoices',
                'title'      => 'Invoice Due Soon',
                'message'    => $invoice->invoice_number . ($invoice->customer ? ' — ' . $invoice->customer->name : '') . ' is due ' . $invoice->due_date->format('M d, Y'),
                'invoice_id' => $invoice->id,
                'time'       => $invoice->due_date->diffForHumans(),
                'read'       => false,
            ];
        }

        // Overdue bills (money we owe)
        $overdueBills = Bill::with('vendor:id,name')
            ->whereNotIn('status', $closed)
            ->where('due_date', '<', $today)
            ->orderBy('due_date', 'asc')
            ->limit(10)
            ->get();

        foreach ($overdueBills as $bill) {
            $notifications[] = [
                'id'      => 'bill_overdue_' . $bill->id,
                'type'    => 'bill_overdue',
                'module'  => 'bills',
                'title'   => 'Overdue Bill',
                'message' => $bill->bill_number . ($bill->vendor ? ' — ' . $bill->vendor->name : '') . ' was due ' . $bill->due_date->format('M d, Y'),
                'bill_id' => $bill->id,
                'time'    => $bill->due_date->format('M d, Y'),
                'read'    => false,
            ];
        }

        // Quotations lapsing within a week
        $expiringQuotes = Quotation::with('customer:id,name')
            ->whereIn('status', ['draft', 'sent'])
            ->whereBetween('valid_until', [$today, $weekAway])
            ->orderBy('valid_until', 'asc')
            ->limit(10)
            ->get();

        foreach ($expiringQuotes as $quote) {
            $notifications[] = [
                'id'           => 'quotation_expiring_' . $quote->id,
                'type'         => 'quotation_expiring',
                'module'       => 'quotations',
                'title'        => 'Quotation Expiring',
                'message'      => $quote->quotation_number . ($quote->customer ? ' — ' . $quote->customer->name : '') . ' expires ' . $quote->valid_until->format('M d, Y'),
                'quotation_id' => $quote->id,
                'time'         => $quote->valid_until->diffForHumans(),
                'read'         => false,
            ];
        }

        return response()->json([
            'data'  => $notifications,
            'count' => count($notifications),
        ]);
    }
}
