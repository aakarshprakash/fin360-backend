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
        $validated = $request->validate([
            'invoicePrefix'       => 'nullable|string|max:20',
            'footerText'          => 'nullable|string|max:2000',
            'termsAndConditions'  => 'nullable|string|max:5000',
            'defaultPaymentTerms' => 'nullable|string|max:100',
            'invoiceLogo'         => 'nullable|string|max:500',
            'invoiceTheme'        => 'nullable|string|max:50',
        ]);

        $userId  = Auth::id();
        $setting = InvoiceTemplateSetting::where('user_id', $userId)->first();

        $data = [
            'invoice_prefix'        => $validated['invoicePrefix'] ?? ($setting->invoice_prefix ?? 'INV-'),
            'footer_text'           => $validated['footerText'] ?? ($setting->footer_text ?? ''),
            'terms_and_conditions'  => $validated['termsAndConditions'] ?? ($setting->terms_and_conditions ?? ''),
            'default_payment_terms' => $validated['defaultPaymentTerms'] ?? ($setting->default_payment_terms ?? 'Net 30'),
            'invoice_logo'          => $validated['invoiceLogo'] ?? ($setting->invoice_logo ?? ''),
            'invoice_theme'         => $validated['invoiceTheme'] ?? ($setting->invoice_theme ?? 'default'),
        ];

        if ($setting) {
            $setting->update($data);
        } else {
            $data['user_id'] = $userId;
            InvoiceTemplateSetting::create($data);
        }

        return response()->json(['message' => 'Invoice settings updated successfully']);
    }
}

