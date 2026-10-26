<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    use HasFactory;

    protected $table = 'taxes';

    protected $fillable = [
        'name', 'rate', 'type', 'active',
    ];

    protected $casts = [
        'rate'   => 'decimal:4',
        'active' => 'boolean',
    ];
}

