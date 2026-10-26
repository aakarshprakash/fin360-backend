<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->boolean('use_signature')->default(true)->after('branch');
        });
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->boolean('use_signature')->default(true)->after('branch');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('use_signature')->default(true)->after('notes');
        });
    }

    public function down()
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('use_signature');
        });
        Schema::table('proforma_invoices', function (Blueprint $table) {
            $table->dropColumn('use_signature');
        });
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('use_signature');
        });
    }
};
