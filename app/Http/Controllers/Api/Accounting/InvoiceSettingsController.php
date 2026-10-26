<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\InvoiceTemplateSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceSettingsController extends Controller
{
    /** GET /api/accounting/invoice-settings */
    public function index()
    {
        $userId  = Auth::id();
        $setting = InvoiceTemplateSetting::where('user_id', $userId)->first();

        if (!$setting) {
            $setting = InvoiceTemplateSetting::create([
                'user_id'               => $userId,
                'invoice_prefix'        => 'INV-',
                'footer_text'           => 'Thank you for your business!',
                'terms_and_conditions'  => 'Payment is due within 30 days of the invoice date.',
                'default_payment_terms' => 'Net 30',
                'invoice_logo'          => '',
                'invoice_theme'         => 'default',
            ]);
        }

        return response()->json([
            'data' => [
                'invoicePrefix'       => $setting->invoice_prefix,
                'footerText'          => $setting->footer_text,
                'termsAndConditions'  => $setting->terms_and_conditions,
                'defaultPaymentTerms' => $setting->default_payment_terms,
                'invoiceLogo'         => $setting->invoice_logo,
                'invoiceTheme'        => $setting->invoice_theme,
            ],
        ]);
    }

    /** PUT /api/accounting/invoice-settings */
    public function update(Request $request)
    {
        // The web client sends snake_case (its axios layer converts keys); older
        // clients sent camelCase. Accept either, preferring snake_case.
        $fields = [
            'invoice_prefix'        => ['invoicePrefix', 'nullable|string|max:20'],
            'footer_text'           => ['footerText', 'nullable|string|max:2000'],
            'terms_and_conditions'  => ['termsAndConditions', 'nullable|string|max:5000'],
            'default_payment_terms' => ['defaultPaymentTerms', 'nullable|string|max:100'],
            'invoice_logo'          => ['invoiceLogo', 'nullable|string|max:500'],
            'invoice_theme'         => ['invoiceTheme', 'nullable|string|in:default,letterhead'],
        ];
        $rules = [];
        foreach ($fields as $snake => [$camel, $rule]) {
            $rules[$snake] = $rule;
            $rules[$camel] = $rule;
        }
        $request->validate($rules);

        $userId  = Auth::id();
        $setting = InvoiceTemplateSetting::where('user_id', $userId)->first();
        $defaults = [
            'invoice_prefix' => 'INV-', 'footer_text' => '', 'terms_and_conditions' => '',
            'default_payment_terms' => 'Net 30', 'invoice_logo' => '', 'invoice_theme' => 'default',
        ];

        $data = [];
        foreach ($fields as $snake => [$camel]) {
            $data[$snake] = $request->input($snake, $request->input($camel))
                ?? ($setting->{$snake} ?? $defaults[$snake]);
        }

        if ($setting) {
            $setting->update($data);
        } else {
            $data['user_id'] = $userId;
            InvoiceTemplateSetting::create($data);
        }

        return response()->json(['message' => 'Invoice settings updated successfully']);
    }
}

