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

    /**
     * The organisation's settings: the single row with no user_id. Older
     * per-user rows are kept only as history (see the 2026_10_26_000002
     * migration) and are never read.
     */
    public static function forCompany(): self
    {
        return static::whereNull('user_id')->first()
            ?? static::create(['user_id' => null]);
    }

    /**
     * Store JSON sections with camelCase keys (what the PDF views and
     * defaults use). The web client sends snake_case, so convert on the way
     * in; when both spellings exist, a non-empty value wins.
     */
    public static function normalizeKeys(?array $data): array
    {
        $out = [];
        foreach ($data ?? [] as $key => $value) {
            $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            if (!array_key_exists($camel, $out) || ($out[$camel] === '' || $out[$camel] === null)) {
                $out[$camel] = $value;
            }
        }
        return $out;
    }
}

