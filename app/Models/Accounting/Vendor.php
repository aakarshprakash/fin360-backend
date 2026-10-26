<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vendors';

    protected $fillable = [
        'name', 'email', 'phone', 'gst_number', 'address',
        'city', 'state', 'country', 'postal_code',
        'payment_terms', 'balance', 'user_id',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bills()
    {
        return $this->hasMany(Bill::class, 'vendor_id');
    }

    public function expenseEntries()
    {
        return $this->hasMany(ExpenseEntry::class, 'vendor_id');
    }
}

