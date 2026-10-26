<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds all RBAC permissions and the Super Admin role.
 * Migration name matches the updater.php check so it won't double-run.
 */
class SeedRbacPermissions extends Migration
{
    public function up()
    {
        $now = now();

        $permissions = [
            // Leads
            ['name' => 'View Leads',          'slug' => 'view_leads',           'module' => 'leads',        'description' => 'Can view lead information and details'],
            ['name' => 'View Own Leads',       'slug' => 'view_own_leads',       'module' => 'leads',        'description' => 'Can only view leads assigned to themselves'],
            ['name' => 'View All Leads',       'slug' => 'view_all_leads',       'module' => 'leads',        'description' => 'Can view leads assigned to any user'],
            ['name' => 'Create Leads',         'slug' => 'create_leads',         'module' => 'leads',        'description' => 'Can create new leads'],
            ['name' => 'Edit Leads',           'slug' => 'edit_leads',           'module' => 'leads',        'description' => 'Can modify any lead'],
            ['name' => 'Edit Own Leads',       'slug' => 'edit_own_leads',       'module' => 'leads',        'description' => 'Can only edit leads assigned to themselves'],
            ['name' => 'Delete Leads',         'slug' => 'delete_leads',         'module' => 'leads',        'description' => 'Can permanently remove leads'],
            ['name' => 'Assign Leads',         'slug' => 'assign_leads',         'module' => 'leads',        'description' => 'Can assign leads to team members'],
            ['name' => 'Export Leads',         'slug' => 'export_leads',         'module' => 'leads',        'description' => 'Can export leads as CSV/Excel'],
            ['name' => 'Convert Leads',        'slug' => 'convert_leads',        'module' => 'leads',        'description' => 'Convert leads to customers'],

            // Customers
            ['name' => 'View Customers',       'slug' => 'view_customers',       'module' => 'customers',    'description' => 'Can view all customer information'],
            ['name' => 'View Own Customers',   'slug' => 'view_own_customers',   'module' => 'customers',    'description' => 'Can only view customers they created'],
            ['name' => 'Manage Customers',     'slug' => 'manage_customers',     'module' => 'customers',    'description' => 'Can edit and manage all customers'],
            ['name' => 'Create Customers',     'slug' => 'create_customers',     'module' => 'customers',    'description' => 'Create customers'],
            ['name' => 'Edit Customers',       'slug' => 'edit_customers',       'module' => 'customers',    'description' => 'Edit customer data'],
            ['name' => 'Delete Customers',     'slug' => 'delete_customers',     'module' => 'customers',    'description' => 'Delete customers'],

            // Follow-ups
            ['name' => 'Manage Follow-ups',     'slug' => 'manage_followups',     'module' => 'followups',    'description' => 'Can create, edit and complete all follow-ups'],
            ['name' => 'Manage Own Follow-ups', 'slug' => 'manage_own_followups', 'module' => 'followups',    'description' => 'Can only manage their own follow-ups'],
            ['name' => 'View Follow-ups',       'slug' => 'view_followups',       'module' => 'followups',    'description' => 'View follow-up schedules'],
            ['name' => 'Create Follow-ups',     'slug' => 'create_followups',     'module' => 'followups',    'description' => 'Create follow-up tasks'],
            ['name' => 'Edit Follow-ups',       'slug' => 'edit_followups',       'module' => 'followups',    'description' => 'Edit follow-ups'],
            ['name' => 'Delete Follow-ups',     'slug' => 'delete_followups',     'module' => 'followups',    'description' => 'Delete follow-ups'],

            // Call Logs
            ['name' => 'Manage Call Logs',     'slug' => 'manage_call_logs',     'module' => 'call_logs',    'description' => 'Can log calls, emails and communication'],
            ['name' => 'View Call Logs',       'slug' => 'view_call_logs',       'module' => 'call_logs',    'description' => 'View call logs and activity timeline'],
            ['name' => 'Create Call Logs',     'slug' => 'create_call_logs',     'module' => 'call_logs',    'description' => 'Log new communications'],
            ['name' => 'Edit Call Logs',       'slug' => 'edit_call_logs',       'module' => 'call_logs',    'description' => 'Edit call log entries'],
            ['name' => 'Delete Call Logs',     'slug' => 'delete_call_logs',     'module' => 'call_logs',    'description' => 'Delete call log entries'],

            // Notes
            ['name' => 'View Notes',           'slug' => 'view_notes',           'module' => 'notes',        'description' => 'View lead notes'],
            ['name' => 'Create Notes',         'slug' => 'create_notes',         'module' => 'notes',        'description' => 'Create notes on leads'],
            ['name' => 'Edit Notes',           'slug' => 'edit_notes',           'module' => 'notes',        'description' => 'Edit existing notes'],
            ['name' => 'Delete Notes',         'slug' => 'delete_notes',         'module' => 'notes',        'description' => 'Delete notes'],

            // Reports
            ['name' => 'View Reports',         'slug' => 'view_reports',         'module' => 'reports',      'description' => 'Can access analytics and reports'],
            ['name' => 'View Own Reports',     'slug' => 'view_own_reports',     'module' => 'reports',      'description' => 'Can only view their own performance reports'],
            ['name' => 'View Dashboard',       'slug' => 'view_dashboard',       'module' => 'dashboard',    'description' => 'Can view the dashboard with KPIs'],
            ['name' => 'Export Reports',       'slug' => 'export_reports',       'module' => 'reports',      'description' => 'Can export data and reports'],

            // Users
            ['name' => 'View Users',           'slug' => 'view_users',           'module' => 'users',        'description' => 'Can view user list and profiles'],
            ['name' => 'Manage Users',         'slug' => 'manage_users',         'module' => 'users',        'description' => 'Can create, edit, and manage user accounts'],
            ['name' => 'Manage Roles',         'slug' => 'manage_roles',         'module' => 'users',        'description' => 'Can create, edit, and delete roles'],
            ['name' => 'Create Users',         'slug' => 'create_users',         'module' => 'users',        'description' => 'Create new staff accounts'],
            ['name' => 'Edit Users',           'slug' => 'edit_users',           'module' => 'users',        'description' => 'Edit user profiles and settings'],
            ['name' => 'Delete Users',         'slug' => 'delete_users',         'module' => 'users',        'description' => 'Delete staff accounts'],

            // Roles
            ['name' => 'View Roles',           'slug' => 'view_roles',           'module' => 'roles',        'description' => 'View roles and permissions'],
            ['name' => 'Create Roles',         'slug' => 'create_roles',         'module' => 'roles',        'description' => 'Create new roles'],
            ['name' => 'Edit Roles',           'slug' => 'edit_roles',           'module' => 'roles',        'description' => 'Edit roles and assign permissions'],
            ['name' => 'Delete Roles',         'slug' => 'delete_roles',         'module' => 'roles',        'description' => 'Delete roles'],
            ['name' => 'Manage Permissions',   'slug' => 'manage_permissions',   'module' => 'roles',        'description' => 'Manage permission definitions'],

            // Settings
            ['name' => 'View Settings',        'slug' => 'view_settings',        'module' => 'settings',     'description' => 'View application settings'],
            ['name' => 'Manage Settings',      'slug' => 'manage_settings',      'module' => 'settings',     'description' => 'Can modify system settings'],
            ['name' => 'Manage Lead Sources',  'slug' => 'manage_lead_sources',  'module' => 'settings',     'description' => 'Can add, edit and delete lead sources'],
            ['name' => 'View Lead Sources',    'slug' => 'view_lead_sources',    'module' => 'lead_sources', 'description' => 'View lead source configuration'],
            ['name' => 'Manage Company Settings', 'slug' => 'manage_company_settings', 'module' => 'settings', 'description' => 'Can update company branding and contact info'],

            // Notifications & Logs
            ['name' => 'View Notifications',   'slug' => 'view_notifications',   'module' => 'notifications','description' => 'View notifications'],
            ['name' => 'View Activity Logs',   'slug' => 'view_activity_logs',   'module' => 'activity_logs','description' => 'View user activity audit trail'],

            // Accounting
            ['name' => 'View Accounting',      'slug' => 'view_accounting',      'module' => 'accounting',   'description' => 'Can access the accounting module'],
            ['name' => 'Manage Accounting',    'slug' => 'manage_accounting',    'module' => 'accounting',   'description' => 'Can create invoices, bills, transactions'],
            ['name' => 'Accounting Settings',  'slug' => 'accounting_settings',  'module' => 'accounting',   'description' => 'Can manage accounting configuration'],
            ['name' => 'View Invoices',        'slug' => 'view_invoices',        'module' => 'accounting',   'description' => 'View invoices'],
            ['name' => 'Create Invoices',      'slug' => 'create_invoices',      'module' => 'accounting',   'description' => 'Create invoices'],
            ['name' => 'Manage Invoices',      'slug' => 'manage_invoices',      'module' => 'accounting',   'description' => 'Full invoice management'],
            ['name' => 'View Expenses',        'slug' => 'view_expenses',        'module' => 'accounting',   'description' => 'View expenses'],
            ['name' => 'Manage Expenses',      'slug' => 'manage_expenses',      'module' => 'accounting',   'description' => 'Full expense management'],
            ['name' => 'View Quotations',      'slug' => 'view_quotations',      'module' => 'accounting',   'description' => 'View quotations'],
            ['name' => 'Manage Quotations',    'slug' => 'manage_quotations',    'module' => 'accounting',   'description' => 'Full quotation management'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->insertOrIgnore([
                'name'        => $perm['name'],
                'slug'        => $perm['slug'],
                'module'      => $perm['module'],
                'description' => $perm['description'],
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down()
    {
        // Remove only the permissions seeded here (by slug)
        $slugs = [
            'view_leads','view_own_leads','view_all_leads','create_leads','edit_leads','edit_own_leads',
            'delete_leads','assign_leads','export_leads','convert_leads',
            'view_customers','view_own_customers','manage_customers','create_customers','edit_customers','delete_customers',
            'manage_followups','manage_own_followups','view_followups','create_followups','edit_followups','delete_followups',
            'manage_call_logs','view_call_logs','create_call_logs','edit_call_logs','delete_call_logs',
            'view_notes','create_notes','edit_notes','delete_notes',
            'view_reports','view_own_reports','view_dashboard','export_reports',
            'view_users','manage_users','manage_roles','create_users','edit_users','delete_users',
            'view_roles','create_roles','edit_roles','delete_roles','manage_permissions',
            'view_settings','manage_settings','manage_lead_sources','view_lead_sources','manage_company_settings',
            'view_notifications','view_activity_logs',
            'view_accounting','manage_accounting','accounting_settings',
            'view_invoices','create_invoices','manage_invoices',
            'view_expenses','manage_expenses','view_quotations','manage_quotations',
        ];

        DB::table('permissions')->whereIn('slug', $slugs)->delete();
    }
}

