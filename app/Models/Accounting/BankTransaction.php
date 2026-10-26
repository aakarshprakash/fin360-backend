<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransaction extends Model
{
    use HasFactory;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'bank_account_id', 'date', 'type', 'amount',
        'description', 'reference', 'transfer_to_id',
        'reconciled', 'user_id',
    ];

    protected $casts = [
        'date'       => 'date',
        'amount'     => 'decimal:2',
        'reconciled' => 'boolean',
    ];

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transferTo()
    {
        return $this->belongsTo(BankAccount::class, 'transfer_to_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

