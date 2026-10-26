<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $table = 'journal_entries';

    protected $fillable = [
        'entry_number', 'date', 'reference', 'description',
        'status', 'total_debit', 'total_credit',
        'source_type', 'source_id', 'user_id',
    ];

    protected $casts = [
        'date'         => 'date',
        'total_debit'  => 'decimal:2',
        'total_credit' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

