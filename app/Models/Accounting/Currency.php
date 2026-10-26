<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $table = 'currencies';

    protected $fillable = [
        'code', 'name', 'symbol', 'exchange_rate', 'is_base', 'active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base'       => 'boolean',
        'active'        => 'boolean',
    ];
}

