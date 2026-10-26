<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'name', 'description', 'hsn_code', 'category', 'unit',
        'unit_price', 'tax_rate', 'active', 'user_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'tax_rate'   => 'decimal:2',
        'active'     => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
