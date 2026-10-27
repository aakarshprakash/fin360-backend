<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product catalogue details (image, SKU, brand/model, feature list, cost) and
 * stock keeping: a running quantity on the product plus a movement history
 * (opening stock, stock in/out, adjustments, invoice deductions/reversals).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 60)->nullable()->after('name');
            $table->string('brand', 120)->nullable()->after('sku');
            $table->string('model', 120)->nullable()->after('brand');
            $table->text('features')->nullable()->after('description');
            $table->string('image', 255)->nullable()->after('features');
            $table->decimal('purchase_price', 15, 2)->nullable()->after('unit_price');
            $table->boolean('track_stock')->default(false)->after('tax_rate');
            $table->decimal('stock_quantity', 15, 3)->default(0)->after('track_stock');
            $table->decimal('reorder_level', 15, 3)->nullable()->after('stock_quantity');
            $table->index('sku');
        });

        Schema::create('product_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            // opening | in | out | adjustment | invoice | invoice_reversal
            $table->string('type', 20);
            $table->decimal('quantity', 15, 3);       // signed change
            $table->decimal('balance_after', 15, 3);
            $table->date('date');
            $table->string('reference', 100)->nullable();
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index('invoice_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_stock_movements');
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sku']);
            $table->dropColumn([
                'sku', 'brand', 'model', 'features', 'image', 'purchase_price',
                'track_stock', 'stock_quantity', 'reorder_level',
            ]);
        });
    }
};
