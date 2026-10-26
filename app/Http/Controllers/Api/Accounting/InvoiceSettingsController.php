<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\InvoiceTemplateSetting;
use Illuminate\Http\Request;

/** Organisation-wide invoice template (print layout, prefix, defaults). */
class InvoiceSettingsController extends Controller
{
    /** GET /api/accounting/invoice-settings */
    public function index()
    {
        $setting = InvoiceTemplateSetting::forCompany();


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

        $setting  = InvoiceTemplateSetting::forCompany();
        $defaults = [

            'invoice_prefix' => 'INV-', 'footer_text' => '', 'terms_and_conditions' => '',
            'default_payment_terms' => 'Net 30', 'invoice_logo' => '', 'invoice_theme' => 'default',
        ];

        $data = [];
        foreach ($fields as $snake => [$camel]) {
            $data[$snake] = $request->input($snake, $request->input($camel))
                ?? ($setting->{$snake} ?? $defaults[$snake]);
        }

        $setting->update($data);


        return response()->json(['message' => 'Invoice settings updated successfully']);
    }
}

