<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductStockMovement extends Model
{
    protected $table = 'product_stock_movements';

    protected $fillable = [
        'product_id', 'type', 'quantity', 'balance_after', 'date',
        'reference', 'note', 'invoice_id', 'user_id',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'balance_after' => 'decimal:3',
        'date'          => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
