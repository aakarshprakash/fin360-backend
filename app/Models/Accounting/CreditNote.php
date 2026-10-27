<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Voiding soft-deletes, so a credit note's number is never reused
class CreditNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'credit_notes';

    protected $fillable = [
        'invoice_id', 'credit_note_number', 'date', 'reason_type', 'items', 'reason',
        'subtotal', 'tax', 'total', 'user_id',
    ];

    protected $casts = [
        'items' => 'array',
        'date'     => 'date',
        'subtotal' => 'decimal:2',
        'tax'      => 'decimal:2',
        'total'    => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

