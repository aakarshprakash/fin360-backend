<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // ═══════════════════════════════════════════════════════════
        // PERMISSIONS — grouped by module
        // ═══════════════════════════════════════════════════════════

        $permissions = [

            // ── Finance & Accounting ───────────────────────────
            ['name' => 'View Accounting',       'slug' => 'view_accounting',      'module' => 'accounting',  'description' => 'Can access dashboards, ledgers and finance records'],
            ['name' => 'Manage Accounting',     'slug' => 'manage_accounting',    'module' => 'accounting',  'description' => 'Can create invoices, bills and transactions'],
            ['name' => 'Accounting Settings',   'slug' => 'accounting_settings',  'module' => 'accounting',  'description' => 'Can manage taxes, currencies, branches and financial years'],
            ['name' => 'View Invoices',         'slug' => 'view_invoices',        'module' => 'accounting',  'description' => 'View invoices'],
            ['name' => 'Create Invoices',       'slug' => 'create_invoices',      'module' => 'accounting',  'description' => 'Create invoices'],
            ['name' => 'Manage Invoices',       'slug' => 'manage_invoices',      'module' => 'accounting',  'description' => 'Full invoice management'],
            ['name' => 'View Expenses',         'slug' => 'view_expenses',        'module' => 'accounting',  'description' => 'View expenses'],
            ['name' => 'Manage Expenses',       'slug' => 'manage_expenses',      'module' => 'accounting',  'description' => 'Full expense management'],
            ['name' => 'View Quotations',       'slug' => 'view_quotations',      'module' => 'accounting',  'description' => 'View quotations'],
            ['name' => 'Manage Quotations',     'slug' => 'manage_quotations',    'module' => 'accounting',  'description' => 'Full quotation management'],

            // ── Reports & Analytics ────────────────────────────
            ['name' => 'View Reports',        'slug' => 'view_reports',       'module' => 'reports',     'description' => 'Can access financial reports'],
            ['name' => 'View Dashboard',      'slug' => 'view_dashboard',     'module' => 'dashboard',   'description' => 'Can view the dashboard with KPIs'],
            ['name' => 'Export Reports',      'slug' => 'export_reports',     'module' => 'reports',     'description' => 'Can export data and reports'],

            // ── User Management ────────────────────────────────
            ['name' => 'View Users',          'slug' => 'view_users',         'module' => 'users',       'description' => 'Can view user list and profiles'],
            ['name' => 'Manage Users',        'slug' => 'manage_users',       'module' => 'users',       'description' => 'Can create, edit, and manage user accounts'],
            ['name' => 'Manage Roles',        'slug' => 'manage_roles',       'module' => 'users',       'description' => 'Can create, edit, and delete roles'],
            ['name' => 'Create Users',        'slug' => 'create_users',       'module' => 'users',       'description' => 'Create new staff accounts'],
            ['name' => 'Edit Users',          'slug' => 'edit_users',         'module' => 'users',       'description' => 'Edit user profiles and settings'],
            ['name' => 'Delete Users',        'slug' => 'delete_users',       'module' => 'users',       'description' => 'Delete staff accounts'],

            // ── Roles & Permissions (legacy keys for route guards) ──
            ['name' => 'View Roles',          'slug' => 'view_roles',         'module' => 'roles',       'description' => 'View roles and permissions'],
            ['name' => 'Create Roles',        'slug' => 'create_roles',       'module' => 'roles',       'description' => 'Create new roles'],
            ['name' => 'Edit Roles',          'slug' => 'edit_roles',         'module' => 'roles',       'description' => 'Edit roles and assign permissions'],
            ['name' => 'Delete Roles',        'slug' => 'delete_roles',       'module' => 'roles',       'description' => 'Delete roles'],
            ['name' => 'Manage Permissions',  'slug' => 'manage_permissions', 'module' => 'roles',       'description' => 'Manage permission definitions'],

            // ── Settings & Configuration ───────────────────────
            ['name' => 'View Settings',       'slug' => 'view_settings',        'module' => 'settings',    'description' => 'View application settings'],
            ['name' => 'Manage Settings',     'slug' => 'manage_settings',      'module' => 'settings',    'description' => 'Can modify system settings'],
            ['name' => 'View Departments',    'slug' => 'view_departments',     'module' => 'settings',    'description' => 'Can view department list'],
            ['name' => 'Manage Departments',  'slug' => 'manage_departments',   'module' => 'settings',    'description' => 'Can create, edit, delete and toggle departments'],
            ['name' => 'View Job Titles',     'slug' => 'view_job_titles',      'module' => 'settings',    'description' => 'Can view job title list'],
            ['name' => 'Manage Job Titles',   'slug' => 'manage_job_titles',    'module' => 'settings',    'description' => 'Can create, edit, delete and toggle job titles'],
            ['name' => 'Manage Company Settings', 'slug' => 'manage_company_settings', 'module' => 'settings', 'description' => 'Can update company branding and contact info'],

            // ── Notifications ──────────────────────────────────
            ['name' => 'View Notifications',  'slug' => 'view_notifications', 'module' => 'notifications','description' => 'View notifications'],

            // ── Activity Logs ──────────────────────────────────
            ['name' => 'View Activity Logs',  'slug' => 'view_activity_logs', 'module' => 'activity_logs','description' => 'View user activity audit trail'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['slug' => $perm['slug']],
                $perm
            );
        }

        // ═══════════════════════════════════════════════════════════
        // ROLES — system defaults
        // ═══════════════════════════════════════════════════════════

        // ── Super Admin ────────────────────────────────────────
        $superAdmin = Role::updateOrCreate(
            ['slug' => 'super-admin'],
            [
                'name'           => 'Super Admin',
                'description'    => 'Unrestricted access to everything',
                'is_system'      => true,
                'is_super_admin' => true,
                'is_active'      => true,
            ]
        );
        $superAdmin->syncPermissions(Permission::pluck('slug')->toArray());

        // ── Admin ──────────────────────────────────────────────
        $admin = Role::updateOrCreate(
            ['slug' => 'admin'],
            [
                'name'           => 'Admin',
                'description'    => 'Full access to finance, users and settings',
                'is_system'      => true,
                'is_super_admin' => false,
                'is_active'      => true,
            ]
        );
        $admin->syncPermissions(Permission::pluck('slug')->toArray());

        // ── Finance Manager ────────────────────────────────────
        $manager = Role::updateOrCreate(
            ['slug' => 'finance-manager'],
            [
                'name'           => 'Finance Manager',
                'description'    => 'Runs day-to-day finance, approves and reports',
                'is_system'      => false,
                'is_super_admin' => false,
                'is_active'      => true,
            ]
        );
        $manager->syncPermissions([
            'view_accounting', 'manage_accounting', 'accounting_settings',
            'view_invoices', 'create_invoices', 'manage_invoices',
            'view_expenses', 'manage_expenses',
            'view_quotations', 'manage_quotations',
            'view_reports', 'view_dashboard', 'export_reports',
            'view_users', 'view_notifications', 'view_settings',
            'view_departments', 'view_job_titles',
        ]);

        // ── Accountant ─────────────────────────────────────────
        $accountant = Role::updateOrCreate(
            ['slug' => 'accountant'],
            [
                'name'           => 'Accountant',
                'description'    => 'Records invoices, bills, income and expenses',
                'is_system'      => false,
                'is_super_admin' => false,
                'is_active'      => true,
            ]
        );
        $accountant->syncPermissions([
            'view_accounting', 'manage_accounting',
            'view_invoices', 'create_invoices',
            'view_expenses', 'manage_expenses',
            'view_quotations',
            'view_reports', 'view_dashboard',
            'view_notifications',
        ]);

        // ── Viewer ─────────────────────────────────────────────
        $viewer = Role::updateOrCreate(
            ['slug' => 'viewer'],
            [
                'name'           => 'Viewer',
                'description'    => 'Read-only access to dashboards and reports',
                'is_system'      => false,
                'is_super_admin' => false,
                'is_active'      => true,
            ]
        );
        $viewer->syncPermissions([
            'view_accounting',
            'view_invoices', 'view_expenses', 'view_quotations',
            'view_reports', 'view_dashboard',
            'view_notifications',
        ]);

        $this->command->info('Roles & Permissions seeded successfully!');
        $this->command->info("   Permissions: " . Permission::count());
        $this->command->info("   Roles: " . Role::count());
    }
}
