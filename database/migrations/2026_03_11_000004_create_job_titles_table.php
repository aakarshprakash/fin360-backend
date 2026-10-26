<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobTitlesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_titles')) {
            return; // already exists from a previous run — skip safely
        }

        Schema::create('job_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            // FK to departments added in migration 000006 (after departments table exists)
            $table->unsignedBigInteger('department_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_titles');
    }
}
