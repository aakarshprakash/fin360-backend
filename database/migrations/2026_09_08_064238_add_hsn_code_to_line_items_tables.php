<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddHsnCodeToLineItemsTables extends Migration
{
    public function up()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable()->after('description');
            $table->unsignedBigInteger('product_id')->nullable()->after('hsn_code');
        });
        Schema::table('bill_items', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable()->after('description');
            $table->unsignedBigInteger('product_id')->nullable()->after('hsn_code');
        });
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->string('hsn_code', 20)->nullable()->after('description');
            $table->unsignedBigInteger('product_id')->nullable()->after('hsn_code');
        });
    }

    public function down()
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'product_id']);
        });
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'product_id']);
        });
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn(['hsn_code', 'product_id']);
        });
    }
}
