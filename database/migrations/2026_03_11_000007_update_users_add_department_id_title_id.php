<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersAddDepartmentIdTitleId extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('phone');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            }

            if (!Schema::hasColumn('users', 'title_id')) {
                $table->unsignedBigInteger('title_id')->nullable()->after('department_id');
                $table->foreign('title_id')->references('id')->on('job_titles')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'title_id')) {
                $table->dropForeign(['title_id']);
                $table->dropColumn('title_id');
            }
            if (Schema::hasColumn('users', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
        });
    }
}
