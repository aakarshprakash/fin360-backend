<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuotationTables extends Migration
{
    public function up()
    {
        // ── Quotations ───────────────────────────────────────────
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 20)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 200)->nullable();
            $table->date('date');
            $table->date('valid_until')->nullable();
            $table->string('currency', 10)->default('INR');
            $table->decimal('exchange_rate', 18, 6)->default(1.000000);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, sent, accepted, rejected, expired, converted
            $table->text('notes')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('branch', 150)->nullable();
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('accounting_customers')->onDelete('set null');
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('status');
            $table->index('date');
            $table->index('valid_until');
        });

        // ── Quotation Items ──────────────────────────────────────
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id');
            $table->string('description', 500);
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
}

