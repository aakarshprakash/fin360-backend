<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('quotations', 'converted_proforma_id')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->unsignedBigInteger('converted_proforma_id')->nullable()->after('converted_invoice_id');
            });
        }

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('converted_proforma_id')->references('id')->on('proforma_invoices')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['converted_proforma_id']);
            $table->dropColumn('converted_proforma_id');
        });
    }
};
