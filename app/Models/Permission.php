<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Accessors (spec uses "key" and "category") ───────────

    /**
     * Alias: slug → key (for API spec compatibility)
     */
    public function getKeyAttribute()
    {
        return $this->slug;
    }

    /**
     * Alias: name → label (for API spec compatibility)
     */
    public function getLabelAttribute()
    {
        return $this->name;
    }

    /**
     * Alias: module → category (for API spec compatibility)
     */
    public function getCategoryAttribute()
    {
        return $this->module;
    }

    // ── Relationships ────────────────────────────────────────

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission')
                    ->withTimestamps();
    }

    // ── Scopes ───────────────────────────────────────────────

    public function scopeModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

