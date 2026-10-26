<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounting_settings', function (Blueprint $table) {
            $table->json('quotation')->nullable()->after('numbering');
        });
    }

    public function down()
    {
        Schema::table('accounting_settings', function (Blueprint $table) {
            $table->dropColumn('quotation');
        });
    }
};
