<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $fillable = [
        'company_name',
        'company_short_name',
        'tagline',
        'email',
        'phone',
        'address',
        'website',
        'logo_url',
    ];
}

