<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountingExtraTables extends Migration
{
    public function up()
    {
        // Invoice Template Settings
        Schema::create('invoice_template_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('invoice_prefix', 20)->default('INV-');
            $table->text('footer_text')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->string('default_payment_terms', 100)->default('Net 30');
            $table->string('invoice_logo')->nullable();
            $table->string('invoice_theme', 50)->default('default');
            $table->timestamps();
        });

        // Payment Methods
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // bank, upi, cash, card, gateway
            $table->string('account_holder_name', 150)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->string('upi_id', 100)->nullable();
            $table->text('qr_code')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });

        // Branches / Multi-Location
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 20)->unique();
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('gst_number', 50)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active'); // active, inactive
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('invoice_template_settings');
    }
}

