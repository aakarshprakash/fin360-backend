<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'quotations';

    protected $fillable = [
        'quotation_number', 'customer_id', 'customer_name',
        'date', 'valid_until', 'currency', 'exchange_rate',
        'subtotal', 'discount', 'tax', 'total',
        'status', 'notes', 'terms_and_conditions',
        'branch', 'converted_invoice_id', 'converted_proforma_id', 'user_id',
        'use_signature',
    ];

    protected $casts = [
        'date'          => 'datetime',
        'valid_until'   => 'datetime',
        'exchange_rate' => 'decimal:6',
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
        'use_signature' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(AccountingCustomer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function convertedInvoice()
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function convertedProforma()
    {
        return $this->belongsTo(ProformaInvoice::class, 'converted_proforma_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the quotation has expired based on valid_until date.
     */
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}

