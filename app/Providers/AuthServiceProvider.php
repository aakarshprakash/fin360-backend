<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // ── Super-admin bypass ───────────────────────────────
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // ── Register dynamic permission gates ────────────────
        // Each permission slug becomes a Gate ability.
        // e.g. Gate::allows('view_leads') or @can('view_leads')
        try {
            if (\Schema::hasTable('permissions')) {
                $permissions = Permission::where('is_active', true)->get();

                foreach ($permissions as $permission) {
                    Gate::define($permission->slug, function ($user) use ($permission) {
                        return $user->hasPermission($permission->slug);
                    });
                }
            }
        } catch (\Exception $e) {
            // Table may not exist during migrations — silently skip
        }
    }
}
