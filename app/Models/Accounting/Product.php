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

    // stock_quantity is changed only through StockService, never mass-assigned
    protected $fillable = [
        'name', 'sku', 'brand', 'model', 'description', 'features', 'image',
        'hsn_code', 'category', 'unit', 'unit_price', 'purchase_price', 'tax_rate',
        'track_stock', 'reorder_level', 'active', 'user_id',
    ];

    protected $casts = [
        'unit_price'     => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'tax_rate'       => 'decimal:2',
        'stock_quantity' => 'decimal:3',
        'reorder_level'  => 'decimal:3',
        'track_stock'    => 'boolean',
        'active'         => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(ProductStockMovement::class);
    }
}
