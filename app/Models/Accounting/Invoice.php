<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'invoices';

    protected $fillable = [
        'invoice_number', 'customer_id', 'date', 'due_date',
        'currency', 'exchange_rate', 'subtotal', 'discount',
        'tax', 'total', 'amount_paid', 'status', 'notes', 'user_id',
        'use_signature',
    ];

    protected $casts = [
        'date'          => 'date',
        'due_date'      => 'date',
        'exchange_rate' => 'decimal:6',
        'subtotal'      => 'decimal:2',
        'discount'      => 'decimal:2',
        'tax'           => 'decimal:2',
        'total'         => 'decimal:2',
        'amount_paid'   => 'decimal:2',
        'use_signature' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(AccountingCustomer::class, 'customer_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

