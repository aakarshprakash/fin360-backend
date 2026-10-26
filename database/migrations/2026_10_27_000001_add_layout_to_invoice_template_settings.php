<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the invoice template builder's configuration (colours, fonts, logo,
 * which sections/columns print, custom labels). NULL means "use the preset
 * named in invoice_theme as-is".
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('invoice_template_settings', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('invoice_theme');
        });
    }

    public function down()
    {
        Schema::table('invoice_template_settings', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
