<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Job titles that belong to this department.
     */
    public function jobTitles()
    {
        return $this->hasMany(JobTitle::class);
    }
}

