<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit notes that actually settle invoices, and payments that know which
 * bank account they went into:
 *  - invoices.amount_credited          total of the invoice's credit notes
 *                                      (outstanding = total - amount_paid - amount_credited)
 *  - invoice_payments.bank_account_id  where the money was deposited
 *  - credit_notes.date / subtotal / tax / reason_type, and soft deletes so a
 *    voided credit note keeps its number (never reused) and stays on record
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'amount_credited')) {
                $table->decimal('amount_credited', 15, 2)->default(0)->after('amount_paid');
            }
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_payments', 'bank_account_id')) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('method');
            }
        });

        Schema::table('credit_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_notes', 'date')) {
                $table->date('date')->nullable()->after('credit_note_number');
            }
            if (!Schema::hasColumn('credit_notes', 'reason_type')) {
                $table->string('reason_type', 30)->nullable()->after('date');
            }
            if (!Schema::hasColumn('credit_notes', 'subtotal')) {
                $table->decimal('subtotal', 18, 2)->default(0)->after('reason');
            }
            if (!Schema::hasColumn('credit_notes', 'tax')) {
                $table->decimal('tax', 18, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('credit_notes', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down()
    {
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->dropColumn(['date', 'reason_type', 'subtotal', 'tax', 'deleted_at']);
        });
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('bank_account_id');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_credited');
        });
    }
};
