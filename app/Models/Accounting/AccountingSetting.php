<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountingSetting extends Model
{
    use HasFactory;

    protected $table = 'accounting_settings';

    protected $fillable = [
        'user_id', 'company', 'accounting', 'numbering', 'quotation',
    ];

    protected $casts = [
        'company'    => 'array',
        'accounting' => 'array',
        'numbering'  => 'array',
        'quotation'  => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

