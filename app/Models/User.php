<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'role_id',
        'is_super_admin',
        'department',
        'status',
        'permissions',
        'avatar',
        'last_active',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_active'       => 'datetime',
        'permissions'       => 'array',
        'is_super_admin'    => 'boolean',
    ];

    // ── Role Relationship (Dynamic RBAC) ─────────────────────

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ── Department & Job Title Relationships ─────────────────

    public function departmentModel()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function jobTitle()
    {
        return $this->belongsTo(JobTitle::class, 'title_id');
    }

    /**
     * Per-user permission overrides (pivot table).
     */
    public function userPermissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    // ── Permission Helpers ───────────────────────────────────

    /**
     * Get all permission slugs from the user's assigned role.
     */
    public function getRolePermissions(): array
    {
        if (!$this->role_id || !$this->roleModel) {
            return [];
        }

        return $this->roleModel->permissions()->pluck('slug')->toArray();
    }

    /**
     * Get merged permissions: role-based + per-user overrides (pivot table).
     * Falls back to JSON column if pivot is empty (backward compat).
     */
    public function getAllPermissions(): array
    {
        $rolePerms = $this->getRolePermissions();

        // Pivot-based user permission overrides
        $pivotPerms = $this->userPermissions()->pluck('slug')->toArray();

        // Fallback: legacy JSON column
        $jsonPerms = is_array($this->permissions) ? $this->permissions : [];

        return array_values(array_unique(array_merge($rolePerms, $pivotPerms, $jsonPerms)));
    }

    /**
     * Check if user has a specific permission (via role or direct).
     * Super Admin and Admin role bypass all checks.
     */
    public function hasPermission(string $slug): bool
    {
        // Super-admin bypass
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Admin role bypass
        if ($this->roleModel && $this->roleModel->name === 'Admin') {
            return true;
        }

        return in_array($slug, $this->getAllPermissions());
    }

    /**
     * Check if user has ANY of the given permissions.
     */
    public function hasAnyPermission(array $slugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return !empty(array_intersect($slugs, $this->getAllPermissions()));
    }

    /**
     * Check if user has ALL of the given permissions.
     */
    public function hasAllPermissions(array $slugs): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return empty(array_diff($slugs, $this->getAllPermissions()));
    }

    /**
     * Check if user has a specific role (by slug).
     */
    public function hasRole(string $slug): bool
    {
        if (!$this->role_id || !$this->roleModel) {
            // Fallback: check string `role` column
            return strtolower($this->role) === strtolower($slug);
        }

        return $this->roleModel->slug === $slug;
    }

    /**
     * Check if user has any of the given roles.
     */
    public function hasAnyRole(array $slugs): bool
    {
        foreach ($slugs as $slug) {
            if ($this->hasRole($slug)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user is super admin (user flag or role flag).
     */
    public function isSuperAdmin(): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($this->roleModel && $this->roleModel->is_super_admin) {
            return true;
        }

        // Legacy fallback
        return $this->hasRole('super-admin');
    }

    // ── Existing Relationships ───────────────────────────────

    public function invoices()
    {
        return $this->hasMany(\App\Models\Accounting\Invoice::class, 'user_id');
    }

    public function bills()
    {
        return $this->hasMany(\App\Models\Accounting\Bill::class, 'user_id');
    }

    public function expenseEntries()
    {
        return $this->hasMany(\App\Models\Accounting\ExpenseEntry::class, 'user_id');
    }

    public function incomeEntries()
    {
        return $this->hasMany(\App\Models\Accounting\IncomeEntry::class, 'user_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(UserActivityLog::class);
    }
}
