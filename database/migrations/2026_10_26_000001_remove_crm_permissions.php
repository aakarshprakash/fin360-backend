<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fin360 is finance-only: the Lead CRM module was removed. This drops the
 * CRM permission definitions (and their role/user grants) so they no longer
 * show up in the role editor. CRM data tables are intentionally left alone —
 * drop them manually once you've archived anything you need.
 */
return new class extends Migration
{
    private array $modules = ['leads', 'followups', 'call_logs', 'notes', 'customers'];

    private array $slugs = [
        'manage_lead_sources', 'view_lead_sources',
        'manage_lead_statuses', 'view_lead_statuses',
        'view_own_reports',
    ];

    public function up()
    {
        $ids = DB::table('permissions')
            ->whereIn('module', $this->modules)
            ->orWhereIn('slug', $this->slugs)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }

    public function down()
    {
        // Irreversible: re-run the old CRM seeders if you need these back.
    }
};
