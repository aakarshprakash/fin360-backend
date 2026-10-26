<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncomeEntry extends Model
{
    use HasFactory;

    protected $table = 'income_entries';

    protected $fillable = [
        'date', 'category', 'amount', 'currency', 'exchange_rate',
        'payment_method', 'customer_id', 'reference', 'notes',
        'account_id', 'user_id',
    ];

    protected $casts = [
        'date'          => 'date',
        'amount'        => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function customer()
    {
        return $this->belongsTo(AccountingCustomer::class, 'customer_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

