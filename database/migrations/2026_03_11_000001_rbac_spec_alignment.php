<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RbacSpecAlignment extends Migration
{
    public function up()
    {
        // Add is_super_admin to roles table
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('is_system');
        });

        // Add is_super_admin to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('role_id');
        });

        // Create user_permissions pivot table for per-user permission overrides
        Schema::create('user_permissions', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_permissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_super_admin');
        });
    }
}

