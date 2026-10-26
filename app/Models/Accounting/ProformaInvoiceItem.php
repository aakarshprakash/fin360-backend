<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProformaInvoiceItem extends Model
{
    use HasFactory;

    protected $table = 'proforma_invoice_items';

    protected $fillable = [
        'proforma_invoice_id', 'description', 'hsn_code', 'product_id',
        'quantity', 'unit_price', 'discount', 'tax_rate', 'tax_amount', 'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'tax_rate'   => 'decimal:4',
        'tax_amount' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public function proformaInvoice()
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
