<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bills';

    protected $fillable = [
        'bill_number', 'vendor_id', 'date', 'due_date',
        'subtotal', 'tax', 'total', 'amount_paid',
        'status', 'notes', 'user_id',
    ];

    protected $casts = [
        'date'        => 'date',
        'due_date'    => 'date',
        'subtotal'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'total'       => 'decimal:2',
        'amount_paid' => 'decimal:2',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(BillItem::class);
    }

    public function payments()
    {
        return $this->hasMany(BillPayment::class);
    }

    public function debitNotes()
    {
        return $this->hasMany(DebitNote::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

