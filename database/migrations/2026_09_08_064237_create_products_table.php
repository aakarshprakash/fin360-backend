<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('hsn_code', 20)->nullable();
            $table->string('category', 150)->nullable();
            $table->string('unit', 30)->default('NOS');
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('tax_rate', 6, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('products');
    }
}
