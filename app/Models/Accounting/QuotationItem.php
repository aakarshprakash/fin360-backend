<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $table = 'quotation_items';

    protected $fillable = [
        'quotation_id', 'description', 'hsn_code', 'product_id', 'quantity', 'unit_price',
        'discount', 'tax_rate', 'tax_amount', 'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'tax_rate'   => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    // Kept even if the product is later deleted, so old quotations still print
    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}

