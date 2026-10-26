<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersTableForCrm extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('email');
            $table->string('role', 50)->default('Sales Agent')->after('password');
            $table->string('department', 50)->nullable()->after('role');
            $table->string('status', 20)->default('Active')->after('department');
            $table->json('permissions')->nullable()->after('status');
            $table->text('avatar')->nullable()->after('permissions');
            $table->timestamp('last_active')->nullable()->after('avatar');
            $table->softDeletes();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'role', 'department', 'status', 'permissions', 'avatar', 'last_active', 'deleted_at']);
            $table->dropIndex(['status']);
        });
    }
}
