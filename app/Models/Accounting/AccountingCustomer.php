<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingCustomer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'accounting_customers';

    protected $fillable = [
        'name', 'email', 'phone', 'gst_number', 'address',
        'city', 'state', 'country', 'postal_code',
        'currency', 'credit_limit', 'balance',
        'crm_customer_id', 'user_id',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'balance'      => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'customer_id');
    }

    public function incomeEntries()
    {
        return $this->hasMany(IncomeEntry::class, 'customer_id');
    }
}

