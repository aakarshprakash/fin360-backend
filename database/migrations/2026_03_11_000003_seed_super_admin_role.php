<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Super Admin role and assigns all permissions to it.
 * Migration name matches the updater.php check so it won't double-run.
 */
class SeedSuperAdminRole extends Migration
{
    public function up()
    {
        $now = now();

        // Insert Super Admin role (skip if exists)
        $exists = DB::table('roles')->where('slug', 'super-admin')->first();

        if (!$exists) {
            DB::table('roles')->insert([
                'name'           => 'Super Admin',
                'slug'           => 'super-admin',
                'description'    => 'Unrestricted access to everything',
                'is_system'      => 1,
                'is_super_admin' => 1,
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        } else {
            // Ensure is_super_admin flag is set
            DB::table('roles')->where('slug', 'super-admin')->update([
                'is_super_admin' => 1,
                'updated_at'     => $now,
            ]);
        }

        // Assign ALL permissions to Super Admin via role_permission pivot
        $role = DB::table('roles')->where('slug', 'super-admin')->first();
        if ($role) {
            $permIds = DB::table('permissions')->pluck('id');
            foreach ($permIds as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id'       => $role->id,
                    'permission_id' => $permId,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down()
    {
        $role = DB::table('roles')->where('slug', 'super-admin')->first();
        if ($role) {
            DB::table('role_permission')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('slug', 'super-admin')->where('is_system', 1)->delete();
        }
    }
}

