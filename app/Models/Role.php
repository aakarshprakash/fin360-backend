<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'is_super_admin',
        'is_active',
    ];

    protected $casts = [
        'is_system'      => 'boolean',
        'is_super_admin' => 'boolean',
        'is_active'      => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
                    ->withTimestamps();
    }

    public function users()
    {
        return $this->hasMany(User::class, 'role_id');
    }

    // ── Helpers ──────────────────────────────────────────────

    public function hasPermission(string $slug): bool
    {
        return $this->permissions()->where('slug', $slug)->exists();
    }

    public function givePermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->syncWithoutDetaching($ids);
    }

    public function revokePermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->detach($ids);
    }

    public function syncPermissions(array $slugs): void
    {
        $ids = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->sync($ids);
    }
}

