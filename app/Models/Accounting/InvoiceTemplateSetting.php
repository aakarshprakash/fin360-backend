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

    /** The organisation's invoice template: the single row with no user_id. */
    public static function forCompany(): self
    {
        return static::whereNull('user_id')->first()
            ?? static::create([
                'user_id'               => null,
                'invoice_prefix'        => 'INV-',
                'footer_text'           => 'Thank you for your business!',
                'terms_and_conditions'  => 'Payment is due within 30 days of the invoice date.',
                'default_payment_terms' => 'Net 30',
                'invoice_logo'          => '',
                'invoice_theme'         => 'default',
            ]);
    }
}

