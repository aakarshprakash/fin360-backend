<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateCompanySettingsTable extends Migration
{
    public function up()
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 255)->default('Lead CRM');
            $table->string('company_short_name', 10)->nullable();
            $table->string('tagline', 500)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('logo_url', 1000)->nullable();
            $table->timestamps();
        });

        // Seed a default row
        DB::table('company_settings')->insert([
            'company_name'       => 'Lead CRM',
            'company_short_name' => 'LC',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('company_settings');
    }
}

