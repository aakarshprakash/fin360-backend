<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingCustomer;
use App\Models\Accounting\AccountingSetting;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\BillPayment;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExpenseEntry;
use App\Models\Accounting\FinancialYear;
use App\Models\Accounting\IncomeEntry;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\InvoicePayment;
use App\Models\Accounting\PaymentMethod;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\QuotationItem;
use App\Models\Accounting\Tax;
use App\Models\Accounting\Vendor;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;

/**
 * Seeds reference data (chart of accounts, currencies, taxes, financial year)
 * and demo transactions for a fictional Kerala, India-based company, without
 * touching the pre-existing CRM/accounting data already in the database.
 *
 * Idempotent: reference/master data uses updateOrCreate keyed on a natural
 * unique field; demo transactions use a distinct "KL-" numbering namespace
 * and are only created if not already present, so re-running never
 * duplicates rows. No table is ever truncated.
 *
 * Run in isolation: `php artisan db:seed --class=AccountingSeeder`
 * (never a bare `db:seed` — that re-runs DatabaseSeeder::run(), which resets
 * every seeded user's password back to "password").
 */
class AccountingSeeder extends Seeder
{
    private LedgerPostingService $ledger;
    private int $ownerId;

    public function __construct()
    {
        $this->ledger = new LedgerPostingService();
    }

    public function run()
    {
        $owner = User::where('email', 'superadmin@fin360.app')->first() ?? User::orderBy('id')->first();
        $this->ownerId = $owner->id;

        $this->seedCurrencies();
        $this->seedTaxes();
        $this->seedFinancialYears();
        $accounts = $this->seedChartOfAccounts();
        $bankAccount = $this->seedBankAccount($accounts);
        $this->seedPaymentMethods($bankAccount);
        $vendors = $this->seedVendors();
        $customers = $this->seedCustomers();
        $this->seedTransactions($customers, $vendors, $accounts);
        $this->repairLegacyInvoicePayments();
        $this->seedCompanyProfile();

        $this->command->info('');
        $this->command->info('✅ Kerala demo accounting data seeded (Malabar Coast Enterprises Pvt Ltd).');
    }

    // ── Currencies ───────────────────────────────────────────────────────

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'exchange_rate' => 1.000000, 'is_base' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'exchange_rate' => 83.200000, 'is_base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'exchange_rate' => 90.100000, 'is_base' => false],
        ];

        foreach ($currencies as $c) {
            Currency::updateOrCreate(['code' => $c['code']], array_merge($c, ['active' => true]));
        }
    }

    // ── Taxes (Indian GST structure) ────────────────────────────────────

    private function seedTaxes(): void
    {
        $taxes = [
            ['name' => 'CGST 9%', 'rate' => 9.0000, 'type' => 'CGST'],
            ['name' => 'SGST 9%', 'rate' => 9.0000, 'type' => 'SGST'],
            ['name' => 'CGST 6%', 'rate' => 6.0000, 'type' => 'CGST'],
            ['name' => 'SGST 6%', 'rate' => 6.0000, 'type' => 'SGST'],
            ['name' => 'IGST 18%', 'rate' => 18.0000, 'type' => 'IGST'],
            ['name' => 'IGST 12%', 'rate' => 12.0000, 'type' => 'IGST'],
            ['name' => 'IGST 5%', 'rate' => 5.0000, 'type' => 'IGST'],
            ['name' => 'IGST 28%', 'rate' => 28.0000, 'type' => 'IGST'],
            ['name' => 'Exempt / No Tax', 'rate' => 0.0000, 'type' => 'Other'],
        ];

        foreach ($taxes as $t) {
            Tax::updateOrCreate(['name' => $t['name'], 'type' => $t['type']], array_merge($t, ['active' => true]));
        }
    }

    // ── Financial Years ──────────────────────────────────────────────────

    private function seedFinancialYears(): void
    {
        FinancialYear::updateOrCreate(
            ['name' => 'FY 2025-26'],
            [
                'start_date' => '2025-04-01',
                'end_date'   => '2026-03-31',
                'status'     => 'closed',
                'is_default' => false,
                'closed_at'  => '2026-04-05 10:00:00',
                'user_id'    => $this->ownerId,
            ]
        );

        FinancialYear::updateOrCreate(
            ['name' => 'FY 2026-27'],
            [
                'start_date' => '2026-04-01',
                'end_date'   => '2027-03-31',
                'status'     => 'open',
                'is_default' => true,
                'user_id'    => $this->ownerId,
            ]
        );
    }

    // ── Chart of Accounts ────────────────────────────────────────────────

    private function seedChartOfAccounts(): array
    {
        $chart = [
            // Assets
            '1000' => ['Cash in Hand', 'Assets'],
            '1050' => ['Kerala Bank Current A/c', 'Assets'],
            '1100' => ['Accounts Receivable', 'Assets'],
            '1300' => ['Input GST', 'Assets'],
            '1400' => ['Fixed Assets - Office Equipment', 'Assets'],
            // Liabilities
            '2100' => ['Accounts Payable', 'Liabilities'],
            '2200' => ['GST Payable', 'Liabilities'],
            '2300' => ['TDS Payable', 'Liabilities'],
            // Equity
            '3100' => ["Owner's Capital", 'Equity'],
            '3200' => ['Retained Earnings', 'Equity'],
            // Income
            '4100' => ['Sales Revenue', 'Income'],
            '4200' => ['Service Income', 'Income'],
            '4300' => ['Other Income', 'Income'],
            // Expense
            '5100' => ['Purchases', 'Expense'],
            '5200' => ['Salaries & Wages', 'Expense'],
            '5300' => ['Rent Expense', 'Expense'],
            '5400' => ['Office Supplies', 'Expense'],
            '5500' => ['Utilities', 'Expense'],
            '5600' => ['Professional Fees', 'Expense'],
            '5700' => ['Bank Charges', 'Expense'],
        ];

        $accounts = [];
        foreach ($chart as $code => [$name, $type]) {
            $accounts[$code] = Account::updateOrCreate(
                ['code' => $code],
                [
                    'name'      => $name,
                    'type'      => $type,
                    'is_system' => true,
                    'active'    => true,
                    'user_id'   => $this->ownerId,
                ]
            );
        }

        return $accounts;
    }

    // ── Bank Account ─────────────────────────────────────────────────────

    private function seedBankAccount(array $accounts): BankAccount
    {
        return BankAccount::updateOrCreate(
            ['account_number' => '15890200004521'],
            [
                'bank_name'        => 'Federal Bank',
                'account_type'     => 'current',
                'ifsc_code'        => 'FDRL0001589',
                'currency'         => 'INR',
                'opening_balance'  => 500000.00,
                'current_balance'  => 500000.00,
                'account_id'       => $accounts['1050']->id,
                'user_id'          => $this->ownerId,
            ]
        );
    }

    // ── Payment Methods ──────────────────────────────────────────────────

    private function seedPaymentMethods(BankAccount $bank): void
    {
        $methods = [
            ['name' => 'Cash', 'type' => 'cash'],
            [
                'name' => 'Federal Bank Transfer', 'type' => 'bank',
                'bank_name' => $bank->bank_name, 'account_number' => $bank->account_number, 'ifsc_code' => $bank->ifsc_code,
            ],
            ['name' => 'UPI', 'type' => 'upi', 'upi_id' => 'malabarcoast@fedbank'],
        ];

        foreach ($methods as $m) {
            PaymentMethod::updateOrCreate(
                ['name' => $m['name'], 'user_id' => $this->ownerId],
                array_merge($m, ['enabled' => true, 'user_id' => $this->ownerId])
            );
        }
    }

    // ── Vendors ───────────────────────────────────────────────────────────

    private function seedVendors(): array
    {
        $vendors = [
            [
                'name' => 'Kochi Spice Traders', 'email' => 'accounts@kochispicetraders.example.in',
                'phone' => '+91-484-555-0101', 'gst_number' => '32AABCK1234A1Z5',
                'city' => 'Kochi', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '682016',
                'address' => 'MG Road, Kochi, Kerala', 'payment_terms' => 'Net 30',
            ],
            [
                'name' => 'Malabar Coir Industries', 'email' => 'billing@malabarcoir.example.in',
                'phone' => '+91-477-555-0102', 'gst_number' => '32AABCM5678B1Z2',
                'city' => 'Alappuzha', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '688001',
                'address' => 'Beach Road, Alappuzha, Kerala', 'payment_terms' => 'Net 30',
            ],
            [
                'name' => 'Kozhikode Office Supplies Co.', 'email' => 'sales@kzhofficesupplies.example.in',
                'phone' => '+91-495-555-0103', 'gst_number' => '32AABCK9012C1Z8',
                'city' => 'Kozhikode', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '673001',
                'address' => 'Mavoor Road, Kozhikode, Kerala', 'payment_terms' => 'Net 15',
            ],
        ];

        $out = [];
        foreach ($vendors as $v) {
            $out[] = Vendor::updateOrCreate(['email' => $v['email']], array_merge($v, ['user_id' => $this->ownerId]));
        }

        return $out;
    }

    // ── Accounting Customers ────────────────────────────────────────────

    private function seedCustomers(): array
    {
        $customers = [
            [
                'name' => 'Backwater Resorts Pvt Ltd', 'email' => 'accounts@backwaterresorts.example.in',
                'phone' => '+91-477-555-0201', 'gst_number' => '32AABCB1111D1Z1',
                'city' => 'Alappuzha', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '688009',
                'address' => 'Punnamada, Alappuzha, Kerala', 'credit_limit' => 200000,
            ],
            [
                'name' => 'Spice Route Exports', 'email' => 'finance@spicerouteexports.example.in',
                'phone' => '+91-484-555-0202', 'gst_number' => '32AABCS2222E1Z4',
                'city' => 'Kochi', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '682003',
                'address' => 'Willingdon Island, Kochi, Kerala', 'credit_limit' => 150000,
            ],
            [
                'name' => 'Kerala Ayurveda Wellness Centre', 'email' => 'admin@keralaayurvedawellness.example.in',
                'phone' => '+91-471-555-0203', 'gst_number' => '32AABCK3333F1Z7',
                'city' => 'Thiruvananthapuram', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '695001',
                'address' => 'Vellayambalam, Thiruvananthapuram, Kerala', 'credit_limit' => 250000,
            ],
            [
                'name' => 'MalabarTech Solutions', 'email' => 'billing@malabartech.example.in',
                'phone' => '+91-495-555-0204', 'gst_number' => '32AABCM4444G1Z0',
                'city' => 'Kozhikode', 'state' => 'Kerala', 'country' => 'India', 'postal_code' => '673002',
                'address' => 'Cyberpark, Kozhikode, Kerala', 'credit_limit' => 300000,
            ],
        ];

        $out = [];
        foreach ($customers as $c) {
            $out[] = AccountingCustomer::updateOrCreate(
                ['email' => $c['email']],
                array_merge($c, ['currency' => 'INR', 'user_id' => $this->ownerId])
            );
        }

        return $out;
    }

    // ── Demo Transactions ────────────────────────────────────────────────

    private function seedTransactions(array $customers, array $vendors, array $accounts): void
    {
        [$backwater, $spiceRoute, $ayurveda, $malabarTech] = $customers;
        [$kochiSpice, $malabarCoir, $kzhSupplies] = $vendors;

        // ── Invoices ──
        $this->createInvoiceIfMissing('KL-INV-0001', $backwater->id, '2026-04-10', '2026-05-10',
            [['description' => 'Resort Booking Software License - Annual', 'quantity' => 1, 'unit_price' => 45000, 'tax_rate' => 18]],
            'paid', '2026-04-15');

        $this->createInvoiceIfMissing('KL-INV-0002', $spiceRoute->id, '2026-05-05', '2026-06-04',
            [['description' => 'Export Documentation & Compliance Consulting', 'quantity' => 10, 'unit_price' => 2500, 'tax_rate' => 18]],
            'paid', '2026-05-20');

        $this->createInvoiceIfMissing('KL-INV-0003', $ayurveda->id, '2026-06-12', '2026-07-12',
            [['description' => 'Website & Booking Platform Development', 'quantity' => 1, 'unit_price' => 85000, 'tax_rate' => 18]],
            'pending', null);

        $this->createInvoiceIfMissing('KL-INV-0004', $malabarTech->id, '2026-06-25', '2026-07-25',
            [['description' => 'IT Infrastructure Support - Q2', 'quantity' => 3, 'unit_price' => 15000, 'tax_rate' => 18]],
            'overdue', null);

        $this->createInvoiceIfMissing('KL-INV-0005', $backwater->id, '2026-07-20', '2026-08-19',
            [['description' => 'Monthly Software Maintenance - July', 'quantity' => 1, 'unit_price' => 8000, 'tax_rate' => 18]],
            'pending', null);

        $this->createInvoiceIfMissing('KL-INV-0006', $spiceRoute->id, '2026-08-05', '2026-09-04',
            [['description' => 'Export Documentation - August', 'quantity' => 5, 'unit_price' => 2500, 'tax_rate' => 18]],
            'paid', '2026-08-10');

        // ── Bills ──
        $this->createBillIfMissing('KL-BILL-0001', $kochiSpice->id, '2026-04-18', '2026-05-18',
            [['description' => 'Office Supplies & Stationery - Q1', 'quantity' => 1, 'unit_price' => 12000, 'tax_rate' => 12]],
            'paid', '2026-04-25');

        $this->createBillIfMissing('KL-BILL-0002', $malabarCoir->id, '2026-05-22', '2026-06-21',
            [['description' => 'Packaging Materials - Coir Products', 'quantity' => 1, 'unit_price' => 35000, 'tax_rate' => 12]],
            'pending', null);

        $this->createBillIfMissing('KL-BILL-0003', $kzhSupplies->id, '2026-07-08', '2026-08-07',
            [['description' => 'Furniture & Office Equipment', 'quantity' => 1, 'unit_price' => 28000, 'tax_rate' => 18]],
            'overdue', null);

        // ── Quotations ──
        $this->createQuotationIfMissing('KL-QUO-0001', $malabarTech->id, '2026-08-01', '2026-08-31',
            [['description' => 'Custom CRM Integration Package', 'quantity' => 1, 'unit_price' => 120000, 'tax_rate' => 18]],
            'sent');

        // Second quotation gets converted to an invoice to exercise that flow.
        $quotation2 = $this->createQuotationIfMissing('KL-QUO-0002', $ayurveda->id, '2026-07-15', '2026-08-14',
            [['description' => 'Annual Wellness Booking System', 'quantity' => 1, 'unit_price' => 65000, 'tax_rate' => 18]],
            'accepted');

        if ($quotation2 && !$quotation2->converted_invoice_id) {
            $this->convertQuotationIfNotConverted($quotation2, 'KL-INV-0007');
        }

        // ── Manual journal entries ──
        if (!\App\Models\Accounting\JournalEntry::where('entry_number', 'KL-JE-0001')->exists()) {
            $this->ledger->createAndPostEntry([
                'entry_number' => 'KL-JE-0001',
                'date'         => '2026-04-01',
                'reference'    => 'Opening Balance',
                'description'  => 'Opening capital investment',
                'total_debit'  => 500000,
                'total_credit' => 500000,
                'source_type'  => 'manual',
                'source_id'    => null,
                'user_id'      => $this->ownerId,
            ], [
                ['account_id' => $accounts['1050']->id, 'debit' => 500000, 'credit' => 0, 'description' => 'Opening capital deposited to Federal Bank a/c'],
                ['account_id' => $accounts['3100']->id, 'debit' => 0, 'credit' => 500000, 'description' => "Owner's opening capital"],
            ]);
        }

        if (!\App\Models\Accounting\JournalEntry::where('entry_number', 'KL-JE-0002')->exists()) {
            $this->ledger->createAndPostEntry([
                'entry_number' => 'KL-JE-0002',
                'date'         => '2026-05-15',
                'reference'    => 'Bank Charges',
                'description'  => 'Bank service charges - April',
                'total_debit'  => 500,
                'total_credit' => 500,
                'source_type'  => 'manual',
                'source_id'    => null,
                'user_id'      => $this->ownerId,
            ], [
                ['account_id' => $accounts['5700']->id, 'debit' => 500, 'credit' => 0, 'description' => 'Bank service charges'],
                ['account_id' => $accounts['1050']->id, 'debit' => 0, 'credit' => 500, 'description' => 'Bank service charges debited'],
            ]);
        }

        // ── Income / Expense entries ──
        $this->createIncomeIfMissing('2026-06-01', 'Consulting Income', 15000, $spiceRoute->id, $accounts['4200']->id, 'Ad-hoc consulting outside invoice cycle');
        $this->createIncomeIfMissing('2026-07-01', 'Interest Income', 1200, null, $accounts['4300']->id, 'Bank interest credited');

        $this->createExpenseIfMissing('2026-08-01', 'Office Rent', 25000, null, $accounts['5300']->id, 'Office rent - August 2026, Kochi');
        $this->createExpenseIfMissing('2026-07-10', 'Professional Fees', 3500, null, $accounts['5600']->id, 'Kerala GST filing fees - Q1 FY2026-27');
        $this->createExpenseIfMissing('2026-06-05', 'Utilities', 4200, null, $accounts['5500']->id, 'Electricity & Internet - May');
    }

    private function createInvoiceIfMissing(string $number, ?int $customerId, string $date, string $dueDate, array $items, string $status, ?string $paymentDate): ?Invoice
    {
        if (Invoice::where('invoice_number', $number)->exists()) {
            return null;
        }

        [$subtotal, $tax, $itemsData] = $this->computeLineItems($items, true);
        $total = round($subtotal + $tax, 2);

        $invoice = Invoice::create([
            'invoice_number' => $number,
            'customer_id'    => $customerId,
            'date'           => $date,
            'due_date'       => $dueDate,
            'currency'       => 'INR',
            'exchange_rate'  => 1,
            'subtotal'       => round($subtotal, 2),
            'discount'       => 0,
            'tax'            => round($tax, 2),
            'total'          => $total,
            'amount_paid'    => 0,
            'status'         => 'pending',
            'user_id'        => $this->ownerId,
        ]);

        foreach ($itemsData as $item) {
            $item['invoice_id'] = $invoice->id;
            InvoiceItem::create($item);
        }

        $this->postInvoiceJournalEntry($invoice, $subtotal, 0, $tax, $total, $accounts = null);

        if ($status === 'paid' && $paymentDate) {
            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount'     => $total,
                'date'       => $paymentDate,
                'method'     => 'bank',
                'reference'  => 'Federal Bank Transfer',
                'user_id'    => $this->ownerId,
            ]);
            $invoice->update(['amount_paid' => $total, 'status' => 'paid']);
        } else {
            $invoice->update(['status' => $status]);
        }

        return $invoice;
    }

    private function createBillIfMissing(string $number, ?int $vendorId, string $date, string $dueDate, array $items, string $status, ?string $paymentDate): ?Bill
    {
        if (Bill::where('bill_number', $number)->exists()) {
            return null;
        }

        [$subtotal, $tax, $itemsData] = $this->computeLineItems($items, false);
        $total = round($subtotal + $tax, 2);

        $bill = Bill::create([
            'bill_number' => $number,
            'vendor_id'   => $vendorId,
            'date'        => $date,
            'due_date'    => $dueDate,
            'subtotal'    => round($subtotal, 2),
            'tax'         => round($tax, 2),
            'total'       => $total,
            'amount_paid' => 0,
            'status'      => 'pending',
            'user_id'     => $this->ownerId,
        ]);

        foreach ($itemsData as $item) {
            $item['bill_id'] = $bill->id;
            BillItem::create($item);
        }

        $this->postBillJournalEntry($bill, $subtotal, $tax, $total);

        if ($status === 'paid' && $paymentDate) {
            BillPayment::create([
                'bill_id'   => $bill->id,
                'amount'    => $total,
                'date'      => $paymentDate,
                'method'    => 'bank',
                'reference' => 'Federal Bank Transfer',
                'user_id'   => $this->ownerId,
            ]);
            $bill->update(['amount_paid' => $total, 'status' => 'paid']);
        } else {
            $bill->update(['status' => $status]);
        }

        return $bill;
    }

    private function createQuotationIfMissing(string $number, ?int $customerId, string $date, string $validUntil, array $items, string $status): ?Quotation
    {
        if (Quotation::where('quotation_number', $number)->exists()) {
            return Quotation::where('quotation_number', $number)->first();
        }

        [$subtotal, $tax, $itemsData] = $this->computeLineItems($items, true);
        $total = round($subtotal + $tax, 2);

        $quotation = Quotation::create([
            'quotation_number' => $number,
            'customer_id'      => $customerId,
            'date'             => $date,
            'valid_until'      => $validUntil,
            'currency'         => 'INR',
            'exchange_rate'    => 1,
            'subtotal'         => round($subtotal, 2),
            'discount'         => 0,
            'tax'              => round($tax, 2),
            'total'            => $total,
            'status'           => $status,
            'user_id'          => $this->ownerId,
        ]);

        foreach ($itemsData as $item) {
            $item['quotation_id'] = $quotation->id;
            QuotationItem::create($item);
        }

        return $quotation;
    }

    private function convertQuotationIfNotConverted(Quotation $quotation, string $invoiceNumber): void
    {
        if (Invoice::where('invoice_number', $invoiceNumber)->exists()) {
            return;
        }

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
            'user_id'        => $this->ownerId,
        ]);

        foreach ($quotation->items as $qItem) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $qItem->description,
                'quantity'    => $qItem->quantity,
                'unit_price'  => $qItem->unit_price,
                'discount'    => $qItem->discount,
                'tax_rate'    => $qItem->tax_rate,
                'tax_amount'  => $qItem->tax_amount,
                'total'       => $qItem->total,
            ]);
        }

        $this->postInvoiceJournalEntry($invoice, $invoice->subtotal, $invoice->discount, $invoice->tax, $invoice->total, null);

        $quotation->update(['status' => 'converted', 'converted_invoice_id' => $invoice->id]);
    }

    /** Mirrors InvoiceController::createJournalEntry(), routed through LedgerPostingService. */
    private function postInvoiceJournalEntry(Invoice $invoice, float $subtotal, float $discount, float $tax, float $total, $unused): void
    {
        $lines = [
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1100', 'Accounts Receivable', 'Assets', $this->ownerId),
                'debit'       => round($total, 2), 'credit' => 0,
                'description' => 'Accounts Receivable - ' . $invoice->invoice_number,
            ],
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('4100', 'Sales Revenue', 'Income', $this->ownerId),
                'debit'       => 0, 'credit' => round($subtotal - $discount, 2),
                'description' => 'Sales Revenue - ' . $invoice->invoice_number,
            ],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('2200', 'GST Payable', 'Liabilities', $this->ownerId),
                'debit'       => 0, 'credit' => round($tax, 2),
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
            'user_id'      => $this->ownerId,
        ], $lines);
    }

    /** Mirrors BillController::createJournalEntry(), routed through LedgerPostingService. */
    private function postBillJournalEntry(Bill $bill, float $subtotal, float $tax, float $total): void
    {
        $lines = [
            [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('5100', 'Purchases', 'Expense', $this->ownerId),
                'debit'       => round($subtotal, 2), 'credit' => 0,
                'description' => 'Purchase - ' . $bill->bill_number,
            ],
        ];

        if ($tax > 0) {
            $lines[] = [
                'account_id'  => $this->ledger->getOrCreateSystemAccount('1300', 'Input GST', 'Assets', $this->ownerId),
                'debit'       => round($tax, 2), 'credit' => 0,
                'description' => 'Input GST - ' . $bill->bill_number,
            ];
        }

        $lines[] = [
            'account_id'  => $this->ledger->getOrCreateSystemAccount('2100', 'Accounts Payable', 'Liabilities', $this->ownerId),
            'debit'       => 0, 'credit' => round($total, 2),
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
            'user_id'      => $this->ownerId,
        ], $lines);
    }

    private function createIncomeIfMissing(string $date, string $category, float $amount, ?int $customerId, ?int $accountId, string $notes): void
    {
        $exists = IncomeEntry::where('date', $date)->where('category', $category)->where('amount', $amount)->exists();
        if ($exists) {
            return;
        }

        IncomeEntry::create([
            'date' => $date, 'category' => $category, 'amount' => $amount, 'currency' => 'INR',
            'exchange_rate' => 1, 'payment_method' => 'bank', 'customer_id' => $customerId,
            'account_id' => $accountId, 'notes' => $notes, 'user_id' => $this->ownerId,
        ]);
    }

    private function createExpenseIfMissing(string $date, string $category, float $amount, ?int $vendorId, ?int $accountId, string $notes): void
    {
        $exists = ExpenseEntry::where('date', $date)->where('category', $category)->where('amount', $amount)->exists();
        if ($exists) {
            return;
        }

        ExpenseEntry::create([
            'date' => $date, 'category' => $category, 'amount' => $amount, 'currency' => 'INR',
            'exchange_rate' => 1, 'payment_method' => 'bank', 'vendor_id' => $vendorId,
            'account_id' => $accountId, 'notes' => $notes, 'user_id' => $this->ownerId,
        ]);
    }

    /** Same subtotal/tax calculation formula as InvoiceController/BillController::store(). */
    private function computeLineItems(array $items, bool $withDiscount): array
    {
        $subtotal = 0;
        $totalTax = 0;
        $itemsData = [];

        foreach ($items as $item) {
            $discount = $withDiscount ? ($item['discount'] ?? 0) : 0;
            $lineSubtotal = ($item['quantity'] * $item['unit_price']) - $discount;
            $taxRate = $item['tax_rate'] ?? 0;
            $taxAmount = $lineSubtotal * ($taxRate / 100);
            $lineTotal = $lineSubtotal + $taxAmount;

            $subtotal += $lineSubtotal;
            $totalTax += $taxAmount;

            $row = [
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'tax_rate'    => $taxRate,
                'tax_amount'  => round($taxAmount, 2),
                'total'       => round($lineTotal, 2),
            ];
            if ($withDiscount) {
                $row['discount'] = $discount;
            }

            $itemsData[] = $row;
        }

        return [$subtotal, $totalTax, $itemsData];
    }

    // ── Legacy data repair (Phase A4) ───────────────────────────────────

    private function repairLegacyInvoicePayments(): void
    {
        foreach ([7, 8] as $invoiceId) {
            $invoice = Invoice::find($invoiceId);

            if (!$invoice || $invoice->status !== 'paid') {
                continue;
            }
            if (InvoicePayment::where('invoice_id', $invoiceId)->exists()) {
                continue;
            }

            InvoicePayment::create([
                'invoice_id' => $invoice->id,
                'amount'     => $invoice->total,
                'date'       => $invoice->date,
                'method'     => 'bank',
                'reference'  => 'Backfilled - historical record',
                'notes'      => 'Auto-repaired: invoice was marked paid with no recorded payment.',
                'user_id'    => $this->ownerId,
            ]);

            $invoice->update(['amount_paid' => $invoice->total]);
        }
    }

    // ── Company Profile ──────────────────────────────────────────────────

    private function seedCompanyProfile(): void
    {
        $companySettings = CompanySetting::first();
        $profile = [
            'company_name'       => 'Malabar Coast Enterprises Pvt Ltd',
            'company_short_name' => 'MCE',
            'tagline'            => 'Trade & Export, Rooted in Kerala',
            'address'            => 'MG Road, Kochi, Kerala 682016, India',
            'phone'              => '+91-484-555-0100',
            'email'              => 'info@malabarcoast.example.in',
            'website'            => 'https://malabarcoast.example.in',
        ];

        if ($companySettings) {
            $companySettings->update($profile);
        } else {
            CompanySetting::create($profile);
        }

        $accountingCompany = [
            'companyName'        => 'Malabar Coast Enterprises Pvt Ltd',
            'address'            => 'MG Road, Kochi, Kerala 682016, India',
            'gstNumber'          => '32ABCDE1234F1Z5',
            'pan'                => 'ABCDE1234F',
            'financialYearStart' => '04',
            'baseCurrency'       => 'INR',
        ];

        foreach (User::all() as $user) {
            $setting = AccountingSetting::firstOrNew(['user_id' => $user->id]);
            $setting->company = $accountingCompany;

            if (!$setting->exists) {
                $setting->accounting = [
                    'fiscalYear'        => 'April - March',
                    'accountingMethod'  => 'accrual',
                    'decimalPrecision'  => 2,
                    'defaultReceivable' => '1100',
                    'defaultPayable'    => '2100',
                    'defaultSales'      => '4100',
                    'defaultPurchase'   => '5100',
                ];
                $setting->numbering = [
                    'invoicePrefix' => 'INV-', 'invoiceNext' => 1,
                    'billPrefix'    => 'BILL-', 'billNext' => 1,
                    'journalPrefix' => 'JE-', 'journalNext' => 1,
                ];
            } else {
                $accounting = $setting->accounting ?? [];
                $accounting['fiscalYear'] = 'April - March';
                $accounting['baseCurrency'] = 'INR';
                $setting->accounting = $accounting;
            }

            $setting->save();
        }
    }
}
