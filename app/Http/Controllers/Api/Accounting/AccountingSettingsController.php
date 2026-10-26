<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Organisation-wide accounting settings: business & tax details (letterhead),
 * accounting preferences, document numbering and quotation terms. One row is
 * shared by every user — see AccountingSetting::forCompany().
 */
class AccountingSettingsController extends Controller
{
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
                // Merge so a partial payload never wipes keys it didn't send (e.g. logo paths)
                $changes[$section] = array_merge(
                    $setting->{$section} ?? [],
                    AccountingSetting::normalizeKeys($values)
                );
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

