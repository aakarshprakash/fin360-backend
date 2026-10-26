<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // ── Step 1: Seed all roles & permissions first ──────────
        $this->call(RolesAndPermissionsSeeder::class);

        $roleId = fn (string $slug) => optional(Role::where('slug', $slug)->first())->id;

        // ── Step 2: Create Users ─────────────────────────────────
        User::updateOrCreate(['email' => 'superadmin@fin360.app'], [
            'name'           => 'Super Admin',
            'password'       => Hash::make('password'),
            'role'           => 'Super Admin',
            'role_id'        => $roleId('super-admin'),
            'is_super_admin' => true,
            'department'     => 'Management',
            'status'         => 'Active',
            'permissions'    => [],
        ]);

        User::updateOrCreate(['email' => 'admin@fin360.app'], [
            'name'           => 'Admin User',
            'password'       => Hash::make('password'),
            'role'           => 'Admin',
            'role_id'        => $roleId('admin'),
            'is_super_admin' => false,
            'department'     => 'Management',
            'status'         => 'Active',
            'permissions'    => [],
        ]);

        User::updateOrCreate(['email' => 'finance@fin360.app'], [
            'name'        => 'Finance Manager',
            'password'    => Hash::make('password'),
            'role'        => 'Finance Manager',
            'role_id'     => $roleId('finance-manager'),
            'department'  => 'Finance',
            'status'      => 'Active',
            'permissions' => [],
        ]);

        User::updateOrCreate(['email' => 'accountant@fin360.app'], [
            'name'        => 'Accountant',
            'password'    => Hash::make('password'),
            'role'        => 'Accountant',
            'role_id'     => $roleId('accountant'),
            'department'  => 'Finance',
            'status'      => 'Active',
            'permissions' => [],
        ]);

        // ── Step 3: Seed accounting reference data + demo data ──────────
        $this->call(AccountingSeeder::class);

        $this->command->info('');
        $this->command->info('Database seeded successfully!');
        $this->command->info('─────────────────────────────────────');
        $this->command->info('Login credentials (password: "password"):');
        $this->command->info('  Super Admin     : superadmin@fin360.app');
        $this->command->info('  Admin           : admin@fin360.app');
        $this->command->info('  Finance Manager : finance@fin360.app');
        $this->command->info('  Accountant      : accountant@fin360.app');
        $this->command->info('─────────────────────────────────────');
    }
}
