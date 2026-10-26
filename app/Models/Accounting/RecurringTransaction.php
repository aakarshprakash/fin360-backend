<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    use HasFactory;

    protected $table = 'recurring_transactions';

    protected $fillable = [
        'type', 'category', 'amount', 'frequency',
        'start_date', 'end_date', 'next_due_date',
        'description', 'status', 'last_processed_at', 'user_id',
    ];

    protected $casts = [
        'amount'            => 'decimal:2',
        'start_date'        => 'date',
        'end_date'          => 'date',
        'next_due_date'     => 'date',
        'last_processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

