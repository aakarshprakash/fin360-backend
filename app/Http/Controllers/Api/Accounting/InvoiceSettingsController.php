<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\InvoiceTemplateSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organisation-wide invoice template: the base layout preset, the template
 * builder's customisations (`layout`), and default texts.
 */
class InvoiceSettingsController extends Controller
{
    /**
     * Every option the template builder may store, as section.key => rule.
     * Rules: 'bool', 'hex', 'int:min:max', 'in:a,b,c', 'str:maxLength'.
     */
    private const LAYOUT_SCHEMA = [
        'style.accent_color'          => 'hex',
        'style.font'                  => 'in:modern,classic,clean',
        'style.text_size'             => 'in:small,normal,large',
        'style.table_style'           => 'in:solid,light,lines',
        'logo.show'                   => 'bool',
        'logo.size'                   => 'int:14:40',
        'logo.position'               => 'in:left,center,right',
        'header.title'                => 'str:40',
        'header.subtitle'             => 'str:60',
        'header.show_address'         => 'bool',
        'header.show_contact'         => 'bool',
        'header.show_state'           => 'bool',
        'header.tax_ids'              => 'in:letterhead,footer,hidden',
        'details.seller_gstin'        => 'bool',
        'details.place_of_supply'     => 'bool',
        'details.customer_gstin'      => 'bool',
        'columns.hsn'                 => 'bool',
        'columns.taxable_value'       => 'bool',
        'columns.gst_rate'            => 'bool',
        'columns.gst_amount'          => 'bool',
        'sections.amount_in_words'    => 'bool',
        'sections.bank_details'       => 'bool',
        'sections.payment_options'    => 'bool',
        'sections.terms'              => 'bool',
        'sections.notes'              => 'bool',
        'sections.footer_message'     => 'bool',
        'sections.computer_generated' => 'bool',
        'labels.bill_to'              => 'str:30',
        'labels.bank_details'         => 'str:30',
        'labels.terms_heading'        => 'str:40',
        'labels.signatory'            => 'str:40',
        'signature.show'              => 'bool',
        'signature.for_company'       => 'bool',
    ];

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
                // snake_case keys; the web client camelCases responses
                'layout'              => $setting->layout,
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
        $rules = ['layout' => 'nullable|array'];
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

        // An explicit null resets to the preset; an omitted key keeps the current layout
        if ($request->exists('layout')) {
            $layout = $request->input('layout');
            $data['layout'] = is_array($layout) ? $this->sanitizeLayout($layout) : null;
        }

        $setting->update($data);

        return response()->json(['message' => 'Invoice settings updated successfully']);
    }

    /**
     * Keep only known options with valid values. Unknown keys are dropped; an
     * invalid value is a validation error so the builder can show it.
     */
    private function sanitizeLayout(array $input): array
    {
        $input = $this->snakeKeys($input);
        $clean = [];

        foreach (self::LAYOUT_SCHEMA as $path => $rule) {
            [$section, $key] = explode('.', $path);
            if (!isset($input[$section]) || !is_array($input[$section]) || !array_key_exists($key, $input[$section])) {
                continue;
            }
            $value = $input[$section][$key];
            [$type, $arg1, $arg2] = array_pad(explode(':', $rule, 3), 3, null);

            $ok = false;
            switch ($type) {
                case 'bool':
                    $ok = is_bool($value);
                    break;
                case 'hex':
                    $ok = is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value);
                    break;
                case 'int':
                    $ok = is_numeric($value) && (int) $value >= (int) $arg1 && (int) $value <= (int) $arg2;
                    $value = (int) $value;
                    break;
                case 'in':
                    $ok = is_string($value) && in_array($value, explode(',', $arg1), true);
                    break;
                case 'str':
                    $ok = is_string($value) && mb_strlen($value) <= (int) $arg1;
                    $value = is_string($value) ? trim(strip_tags($value)) : $value;
                    break;
            }

            if (!$ok) {
                throw ValidationException::withMessages(["layout.{$path}" => "Invalid value for {$path}."]);
            }
            $clean[$section][$key] = $value;
        }

        return $clean;
    }

    private function snakeKeys(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $out[is_string($key) ? Str::snake($key) : $key] = is_array($value) ? $this->snakeKeys($value) : $value;
        }
        return $out;
    }
}
