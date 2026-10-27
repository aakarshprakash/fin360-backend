<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Organisation-wide accounting settings: business & tax details (letterhead),
 * accounting preferences, document numbering and quotation terms. One row is
 * shared by every user — see AccountingSetting::forCompany().
 */
class AccountingSettingsController extends Controller
{
    // File paths that only the upload endpoints may change
    private const UPLOAD_KEYS = [
        'company'   => ['companyLogo', 'sealSignatureImage'],
        'quotation' => ['headerImage', 'footerImage'],
    ];

    // Quotation template options: key => rule ('bool', 'str:max', 'in:a,b')
    private const QUOTATION_RULES = [
        'validity' => 'str:500', 'gstNote' => 'str:500', 'deliveryTime' => 'str:500',
        'deliveryMode' => 'str:500', 'warranty' => 'str:500', 'paymentTerms' => 'str:500',
        'computerConfiguration' => 'str:500', 'training' => 'str:500',
        'design'            => 'in:classic,letterhead',
        'headerType'        => 'in:image,company,none',
        'footerType'        => 'in:image,text,none',
        'footerText'        => 'str:300',
        'title'             => 'str:60',
        'showProductImages' => 'bool',
        'showFeatures'      => 'bool',
        'showTotals'        => 'bool',
        'termsPage'         => 'bool',
        'termsTitle'        => 'str:80',
        'showSignature'     => 'bool',
        'termsHtml'         => 'html:30000',
    ];

    // Rich text: tags kept as-is; anything else is unwrapped (its text kept)
    private const HTML_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ol', 'ul', 'li', 'h2', 'h3', 'h4',
        'blockquote', 'hr', 'div', 'table', 'colgroup', 'col', 'thead', 'tbody', 'tr', 'th', 'td',
    ];
    // Removed together with their content
    private const HTML_DROP = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'img', 'video', 'audio'];
    private const MAX_TERMS = 20;

    /** GET /api/accounting/settings */
    public function index()
    {
        $setting = $this->setting();

        return response()->json([
            'data' => [
                'company'    => array_merge($this->defaultCompany(), $setting->company ?? []),
                'accounting' => array_merge($this->defaultAccounting(), $setting->accounting ?? []),
                'numbering'  => array_merge($this->defaultNumbering(), $setting->numbering ?? []),
                'quotation'  => array_merge($this->defaultQuotation(), $setting->quotation ?? []),
            ],
        ]);
    }

    /** PUT /api/accounting/settings */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company'    => 'nullable|array',
            'accounting' => 'nullable|array',
            'numbering'  => 'nullable|array',
            'quotation'  => 'nullable|array',
        ]);

        $setting = $this->setting();
        $changes = [];
        foreach ($validated as $section => $values) {
            if (is_array($values)) {
                $values = AccountingSetting::normalizeKeys($values);
                foreach (self::UPLOAD_KEYS[$section] ?? [] as $key) {
                    unset($values[$key]);
                }
                if ($section === 'quotation') {
                    $values = $this->sanitizeQuotation($values);
                }
                // Merge so a partial payload never wipes keys it didn't send (e.g. logo paths)
                $changes[$section] = array_merge($setting->{$section} ?? [], $values);
            }
        }
        $setting->update($changes);

        return response()->json(['message' => 'Accounting settings updated successfully']);
    }

    /** POST /api/accounting/settings/logo */
    public function uploadLogo(Request $request)
    {
        return $this->storeCompanyImage($request, 'companyLogo', 'logos', 'Logo uploaded successfully');
    }

    /** DELETE /api/accounting/settings/logo */
    public function deleteLogo()
    {
        $this->clearCompanyImage('companyLogo');

        return response()->json(['message' => 'Logo removed successfully']);
    }

    /** POST /api/accounting/settings/signature */
    public function uploadSignature(Request $request)
    {
        return $this->storeCompanyImage($request, 'sealSignatureImage', 'signatures', 'Signature uploaded successfully');
    }

    /** DELETE /api/accounting/settings/signature */
    public function deleteSignature()
    {
        $this->clearCompanyImage('sealSignatureImage');

        return response()->json(['message' => 'Signature removed successfully']);
    }

    /** POST /api/accounting/settings/quotation-image/{slot}  (slot: header|footer) */
    public function uploadQuotationImage(Request $request, string $slot)
    {
        $request->validate(['image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096']);

        $key     = $slot . 'Image';
        $setting = $this->setting();
        $this->deleteStoredFile($setting->quotation[$key] ?? null);

        $path = $request->file('image')->storeAs(
            'letterheads',
            "quotation-{$slot}-" . Str::random(8) . '.' . $request->file('image')->extension(),
            'public'
        );
        $setting->update(['quotation' => array_merge($setting->quotation ?? [], [$key => $path])]);

        return response()->json(['message' => ucfirst($slot) . ' image uploaded', 'path' => $path]);
    }

    /** DELETE /api/accounting/settings/quotation-image/{slot} */
    public function deleteQuotationImage(string $slot)
    {
        $key     = $slot . 'Image';
        $setting = $this->setting();
        $this->deleteStoredFile($setting->quotation[$key] ?? null);
        $setting->update(['quotation' => array_merge($setting->quotation ?? [], [$key => ''])]);

        return response()->json(['message' => ucfirst($slot) . ' image removed']);
    }

    /** Keep known quotation options with valid values; terms is a list of {title, text, showBank} */
    private function sanitizeQuotation(array $input): array
    {
        $clean = [];
        foreach (self::QUOTATION_RULES as $key => $rule) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            [$type, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $value = $input[$key];
            if ($type === 'bool') {
                $ok = is_bool($value);
            } elseif ($type === 'in') {
                $ok = is_string($value) && in_array($value, explode(',', $arg), true);
            } elseif ($type === 'html') {
                $value = is_string($value) ? $this->sanitizeHtml($value) : ($value ?? '');
                $ok = is_string($value) && mb_strlen($value) <= (int) $arg;
            } else {
                // Laravel turns empty strings into null (ConvertEmptyStringsToNull)
                $value = is_string($value) ? trim(strip_tags($value)) : ($value ?? '');
                $ok = is_string($value) && mb_strlen($value) <= (int) $arg;
            }
            if (!$ok) {
                throw ValidationException::withMessages(["quotation.{$key}" => "Invalid value for {$key}."]);
            }
            $clean[$key] = $value;
        }

        if (array_key_exists('terms', $input)) {
            $terms = $input['terms'];
            if (!is_array($terms) || count($terms) > self::MAX_TERMS) {
                throw ValidationException::withMessages(['quotation.terms' => 'Up to ' . self::MAX_TERMS . ' terms are allowed.']);
            }
            $clean['terms'] = [];
            foreach (array_values($terms) as $i => $term) {
                $title = trim(strip_tags((string) ($term['title'] ?? '')));
                $text  = trim(strip_tags((string) ($term['text'] ?? '')));
                if (mb_strlen($title) > 120 || mb_strlen($text) > 3000) {
                    throw ValidationException::withMessages(["quotation.terms.{$i}" => 'A term is too long.']);
                }
                if ($title === '' && $text === '') {
                    continue;
                }
                $clean['terms'][] = [
                    'title'    => $title,
                    'text'     => $text,
                    'showBank' => (bool) ($term['showBank'] ?? $term['show_bank'] ?? false),
                ];
            }
        }

        return $clean;
    }

    /** Allow-list HTML cleaner (the web client also sanitises before printing) */
    private function sanitizeHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = (new \DOMXPath($doc))->query('//div[@id="__root"]')->item(0);
        if (!$root) {
            return '';
        }
        $this->cleanNode($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    private function cleanNode(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::HTML_DROP, true)) {
                $node->removeChild($child);
                continue;
            }
            $this->cleanNode($child);
            if (!in_array($tag, self::HTML_TAGS, true)) {
                // Unwrap: keep the (already cleaned) children
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name  = strtolower($attr->name);
                $value = trim($attr->value);
                $keep  = null;
                if ($name === 'style') {
                    $decls = array_filter(array_map('trim', explode(';', $value)), function ($d) {
                        return preg_match('/^(text-align:\s*(left|center|right|justify)|(min-)?width:\s*\d{1,4}px)$/i', $d);
                    });
                    $keep = $decls ? implode('; ', $decls) : null;
                } elseif (in_array($name, ['colspan', 'rowspan'], true) && ctype_digit($value) && (int) $value <= 20) {
                    $keep = $value;
                } elseif ($name === 'data-bank-details' && $tag === 'div') {
                    $keep = 'true';
                }
                $child->removeAttribute($attr->name);
                if ($keep !== null) {
                    $child->setAttribute($name, $keep);
                }
            }
        }
    }

    private function setting(): AccountingSetting
    {
        $setting = AccountingSetting::forCompany();

        if (empty($setting->company)) {
            $setting->update([
                'company'    => $this->defaultCompany(),
                'accounting' => $this->defaultAccounting(),
                'numbering'  => $this->defaultNumbering(),
                'quotation'  => $this->defaultQuotation(),
            ]);
        }

        return $setting;
    }

    private function storeCompanyImage(Request $request, string $key, string $folder, string $message)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $setting = $this->setting();
        $this->deleteStoredFile($setting->company[$key] ?? null);

        $path = $request->file('image')->storeAs(
            $folder,
            'company-' . Str::random(8) . '.' . $request->file('image')->extension(),
            'public'
        );

        $setting->update(['company' => array_merge($setting->company ?? [], [$key => $path])]);

        return response()->json([
            'message' => $message,
            'path'    => $path,
            'url'     => Storage::disk('public')->url($path),
        ]);
    }

    private function clearCompanyImage(string $key): void
    {
        $setting = $this->setting();
        $this->deleteStoredFile($setting->company[$key] ?? null);
        $setting->update(['company' => array_merge($setting->company ?? [], [$key => ''])]);
    }

    private function deleteStoredFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function defaultCompany(): array
    {
        return [
            'companyName'        => '',
            'address'            => '',
            'phone'              => '',
            'mobile'             => '',
            'email'              => '',
            'website'            => '',
            'state'              => '',
            'stateCode'          => '',
            'gstNumber'          => '',
            'pan'                => '',
            'cin'                => '',
            'bankDetails'        => '',
            'financialYearStart' => '04',
            'baseCurrency'       => 'INR',
            'sealSignatureImage' => '',
            'companyLogo'        => '',
        ];
    }

    private function defaultQuotation(): array
    {
        return [
            'validity'              => '30 Days',
            'gstNote'                => '',
            'deliveryTime'           => '',
            'deliveryMode'           => '',
            'warranty'               => '',
            'paymentTerms'           => '',
            'computerConfiguration'  => '',
            'training'               => '',
            // Letterhead design (header/footer artwork, product pictures, terms page)
            'design'            => 'classic',
            'headerType'        => 'company',
            'headerImage'       => '',
            'footerType'        => 'none',
            'footerImage'       => '',
            'footerText'        => '',
            'title'             => 'QUOTATION',
            'showProductImages' => true,
            'showFeatures'      => true,
            'showTotals'        => true,
            'termsPage'         => true,
            'termsTitle'        => 'TERMS AND CONDITIONS',
            'showSignature'     => true,
            'terms'             => [
                ['title' => 'PRICE', 'showBank' => false, 'text' => 'The equipment price is offered in INR and is exclusive of GST or any other local taxes, unless otherwise specified. The offer is valid for 60 days and thereafter subject to confirmation from our end.'],
                ['title' => 'PAYMENT TERMS', 'showBank' => true, 'text' => "Standard payment terms are 50% in advance and the balance against delivery.\nPayment to be made to the account below."],
                ['title' => 'EQUIPMENT DELIVERY', 'showBank' => false, 'text' => 'The ordered equipment shall be dispatched within 3-5 weeks from the date of the confirmed purchase order and receipt of the advance payment.'],
                ['title' => 'INSTALLATION AND DEMONSTRATION', 'showBank' => false, 'text' => 'The equipment shall be installed by our service representative at no cost upon receipt of the installation readiness letter. Additional visits or demonstrations on request will be charged. The purchaser shall provide a proper power supply and the specified environmental conditions.'],
                ['title' => 'WARRANTY', 'showBank' => false, 'text' => 'The equipment carries a standard warranty of 12 months from the date of installation or 15 months from the date of delivery, whichever is earlier, against manufacturing defects or workmanship. Accessories supplied with the product carry a 6-month warranty.'],
                ['title' => 'AMC', 'showBank' => false, 'text' => 'We recommend an annual maintenance contract for periodic maintenance of the equipment after the standard warranty period expires.'],
            ],
        ];
    }

    private function defaultAccounting(): array
    {
        return [
            'fiscalYear'        => 'April - March',
            'accountingMethod'  => 'accrual',
            'decimalPrecision'  => 2,
            'defaultReceivable' => '1100',
            'defaultPayable'    => '2100',
            'defaultSales'      => '4100',
            'defaultPurchase'   => '5100',
        ];
    }

    private function defaultNumbering(): array
    {
        return [
            'invoicePrefix' => 'INV-',
            'invoiceNext'   => 1,
            'billPrefix'    => 'BILL-',
            'billNext'      => 1,
            'journalPrefix' => 'JE-',
            'journalNext'   => 1,
        ];
    }
}

