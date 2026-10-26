<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class InvoiceTemplateSetting extends Model
{
    protected $table = 'invoice_template_settings';

    protected $fillable = [
        'user_id',
        'invoice_prefix',
        'footer_text',
        'terms_and_conditions',
        'default_payment_terms',
        'invoice_logo',
        'invoice_theme',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

