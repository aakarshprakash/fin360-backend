<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $table = 'accounting_accounts';

    protected $fillable = [
        'code', 'name', 'type', 'group', 'description',
        'opening_balance', 'balance', 'is_system', 'active', 'user_id',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'balance'         => 'decimal:2',
        'is_system'       => 'boolean',
        'active'          => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function journalLines()
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}

