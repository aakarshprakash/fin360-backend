<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'module',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Log a user activity.
     */
    public static function log(
        int    $userId,
        string $action,
        string $module = null,
        string $targetType = null,
        int    $targetId = null,
        array  $oldValues = null,
        array  $newValues = null
    ): self {
        return self::create([
            'user_id'     => $userId,
            'action'      => $action,
            'module'      => $module,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'old_values'  => $oldValues,
            'new_values'  => $newValues,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}

