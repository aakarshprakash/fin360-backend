<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    use HasFactory;

    protected $table = 'bill_items';

    protected $fillable = [
        'bill_id', 'description', 'hsn_code', 'product_id', 'quantity', 'unit_price',
        'tax_rate', 'tax_amount', 'total', 'account_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'tax_rate'   => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}

