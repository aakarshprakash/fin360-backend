<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProformaInvoiceTables extends Migration
{
    public function up()
    {
        // ── Proforma Invoices ────────────────────────────────────
        Schema::create('proforma_invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('proforma_number', 20)->unique();
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
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->unsignedBigInteger('converted_invoice_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('customer_id')->references('id')->on('accounting_customers')->onDelete('set null');
            $table->foreign('quotation_id')->references('id')->on('quotations')->onDelete('set null');
            $table->foreign('converted_invoice_id')->references('id')->on('invoices')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('status');
            $table->index('date');
            $table->index('valid_until');
        });

        // ── Proforma Invoice Items ───────────────────────────────
        Schema::create('proforma_invoice_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('proforma_invoice_id');
            $table->string('description', 500);
            $table->string('hsn_code', 20)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('proforma_invoice_id')->references('id')->on('proforma_invoices')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('proforma_invoice_items');
        Schema::dropIfExists('proforma_invoices');
    }
}
