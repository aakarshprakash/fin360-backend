<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingTables extends Migration
{
    public function up()
    {
        // ── Currencies ───────────────────────────────────────────
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name', 100);
            $table->string('symbol', 10)->nullable();
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->boolean('is_base')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ── Taxes ────────────────────────────────────────────────
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->decimal('rate', 8, 4);
            $table->string('type', 20)->default('GST'); // CGST, SGST, IGST, VAT, Other
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ── Financial Years ──────────────────────────────────────
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open, closed
            $table->boolean('is_default')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // ── Accounting Settings ──────────────────────────────────
        Schema::create('accounting_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->json('company')->nullable();
            $table->json('accounting')->nullable();
            $table->json('numbering')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });

        // ── Chart of Accounts ────────────────────────────────────
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('type', 50);       // Assets, Liabilities, Equity, Income, Expense
            $table->string('group', 100)->nullable();
            $table->text('description')->nullable();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('type');
            $table->index('group');
        });

        // ── Accounting Customers ─────────────────────────────────
        Schema::create('accounting_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->decimal('balance', 18, 2)->default(0);
            $table->unsignedBigInteger('crm_customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('name');
        });

        // ── Vendors ──────────────────────────────────────────────
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('payment_terms', 50)->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('name');
        });

        // ── Bank Accounts ────────────────────────────────────────
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 150);
            $table->string('account_number', 50);
            $table->string('account_type', 50)->default('savings'); // savings, current, credit
            $table->string('ifsc_code', 20)->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('current_balance', 18, 2)->default(0);
            $table->unsignedBigInteger('account_id')->nullable(); // linked COA
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Invoices ─────────────────────────────────────────────
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 50)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, pending, paid, overdue, cancelled
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('customer_id')->references('id')->on('accounting_customers')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('date');
        });

        // ── Invoice Items ────────────────────────────────────────
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('description', 500);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('set null');
        });

        // ── Invoice Payments ─────────────────────────────────────
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->decimal('amount', 18, 2);
            $table->date('date');
            $table->string('method', 50)->nullable(); // cash, bank, cheque, upi, card
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Credit Notes ─────────────────────────────────────────
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('credit_note_number', 50)->unique();
            $table->json('items')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Bills ────────────────────────────────────────────────
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 50)->unique();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('date');
        });

        // ── Bill Items ───────────────────────────────────────────
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->string('description', 500);
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedBigInteger('account_id')->nullable();
            $table->timestamps();
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('set null');
        });

        // ── Bill Payments ────────────────────────────────────────
        Schema::create('bill_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->decimal('amount', 18, 2);
            $table->date('date');
            $table->string('method', 50)->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Debit Notes ──────────────────────────────────────────
        Schema::create('debit_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bill_id');
            $table->string('debit_note_number', 50)->unique();
            $table->json('items')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('bill_id')->references('id')->on('bills')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        // ── Income Entries ───────────────────────────────────────
        Schema::create('income_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('category', 100);
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('payment_method', 50)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('accounting_customers')->onDelete('set null');
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('date');
            $table->index('category');
        });

        // ── Expense Entries ──────────────────────────────────────
        Schema::create('expense_entries', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('category', 100);
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1);
            $table->string('payment_method', 50)->nullable();
            $table->unsignedBigInteger('vendor_id')->nullable();
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('vendor_id')->references('id')->on('vendors')->onDelete('set null');
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('date');
            $table->index('category');
        });

        // ── Bank Transactions ────────────────────────────────────
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->date('date');
            $table->string('type', 20); // deposit, withdrawal, transfer
            $table->decimal('amount', 18, 2);
            $table->string('description', 500)->nullable();
            $table->string('reference', 100)->nullable();
            $table->unsignedBigInteger('transfer_to_id')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('cascade');
            $table->foreign('transfer_to_id')->references('id')->on('bank_accounts')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('date');
            $table->index('type');
        });

        // ── Journal Entries ──────────────────────────────────────
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 50)->unique();
            $table->date('date');
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->string('source_type', 50)->nullable(); // invoice, bill, income, expense
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('date');
        });

        // ── Journal Entry Lines ──────────────────────────────────
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('journal_entry_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('description', 500)->nullable();
            $table->timestamps();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounting_accounts')->onDelete('cascade');
        });

        // ── Recurring Transactions ───────────────────────────────
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // income, expense
            $table->string('category', 100);
            $table->decimal('amount', 18, 2);
            $table->string('frequency', 20); // daily, weekly, monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active'); // active, paused, completed
            $table->timestamp('last_processed_at')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('status');
            $table->index('next_due_date');
        });

        // ── Audit Logs ───────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 20); // create, update, delete
            $table->string('table_name', 100);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['table_name', 'record_id']);
            $table->index('action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('recurring_transactions');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('expense_entries');
        Schema::dropIfExists('income_entries');
        Schema::dropIfExists('debit_notes');
        Schema::dropIfExists('bill_payments');
        Schema::dropIfExists('bill_items');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('accounting_customers');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_settings');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('currencies');
    }
}

