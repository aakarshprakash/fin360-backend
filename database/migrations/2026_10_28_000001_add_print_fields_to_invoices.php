<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data the configurable invoice templates can print:
 *  - invoices.ship_to       consignee / delivery address (empty = same as Bill To)
 *  - invoices.extra_fields  values for the template's custom fields
 *                           (e.g. Order No & Date, Lot Code, LR No), keyed by field id
 *  - invoice_items.unit     unit of measure (NOS, KG, BOX…)
 *  - accounting_customers.contact_person / pan
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'ship_to')) {
                $table->text('ship_to')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('invoices', 'extra_fields')) {
                $table->json('extra_fields')->nullable()->after('ship_to');
            }
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_items', 'unit')) {
                $table->string('unit', 20)->nullable()->after('quantity');
            }
        });

        Schema::table('accounting_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('accounting_customers', 'contact_person')) {
                $table->string('contact_person', 150)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('accounting_customers', 'pan')) {
                $table->string('pan', 20)->nullable()->after('gst_number');
            }
        });
    }

    public function down()
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['ship_to', 'extra_fields']);
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit');
        });
        Schema::table('accounting_customers', function (Blueprint $table) {
            $table->dropColumn(['contact_person', 'pan']);
        });
    }
};
