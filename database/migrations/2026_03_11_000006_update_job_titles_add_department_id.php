<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

// Runs AFTER 000004 (job_titles) AND 000005 (departments) — safe to add FK now.
class UpdateJobTitlesAddDepartmentId extends Migration
{
    public function up(): void
    {
        // Check if the FK already exists before adding (idempotent)
        $hasFk = collect(
            DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'job_titles'
                          AND COLUMN_NAME = 'department_id'
                          AND REFERENCED_TABLE_NAME = 'departments'")
        )->isNotEmpty();

        if ($hasFk) {
            return; // FK already exists — skip
        }

        Schema::table('job_titles', function (Blueprint $table) {
            $table->foreign('department_id')
                  ->references('id')
                  ->on('departments')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('job_titles', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
    }
}
