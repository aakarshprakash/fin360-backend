<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round off: a quotation, proforma invoice or invoice can be rounded to the
 * nearest rupee, with the paise difference shown on its own line
 * (total = subtotal - discount + tax + round_off).
 */
return new class extends Migration
{
    private const TABLES = ['quotations', 'proforma_invoices', 'invoices'];

    public function up()
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'round_off')) {
                    $t->decimal('round_off', 8, 2)->default(0)->after('tax');
                }
            });
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (Schema::hasColumn($table, 'round_off')) {
                    $t->dropColumn('round_off');
                }
            });
        }
    }
};
