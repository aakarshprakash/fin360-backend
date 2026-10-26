<?php

namespace App\Http\Controllers\Api\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountingSettingsController extends Controller
{
    /** GET /api/accounting/settings */
    public function index()
    {
        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        if (!$setting) {
            $setting = AccountingSetting::create([
                'user_id'    => $userId,
                'company'    => $this->defaultCompany(),
                'accounting' => $this->defaultAccounting(),
                'numbering'  => $this->defaultNumbering(),
                'quotation'  => $this->defaultQuotation(),
            ]);
        }

        return response()->json([
            'data' => [
                'company'    => array_merge($this->defaultCompany(), $setting->company ?? []),
                'accounting' => $setting->accounting,
                'numbering'  => $setting->numbering,
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

        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        if ($setting) {
            $setting->update($validated);
        } else {
            $validated['user_id'] = $userId;
            $setting = AccountingSetting::create(array_merge([
                'company'    => $this->defaultCompany(),
                'accounting' => $this->defaultAccounting(),
                'numbering'  => $this->defaultNumbering(),
                'quotation'  => $this->defaultQuotation(),
            ], $validated));
        }

        return response()->json(['message' => 'Accounting settings updated successfully']);
    }

    /** POST /api/accounting/settings/logo */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        $oldPath = $setting->company['companyLogo'] ?? null;
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->storeAs(
            'logos',
            $userId . '-' . Str::random(8) . '.' . $request->file('image')->extension(),
            'public'
        );

        $company = array_merge($this->defaultCompany(), $setting->company ?? [], ['companyLogo' => $path]);

        if ($setting) {
            $setting->update(['company' => $company]);
        } else {
            AccountingSetting::create([
                'user_id'    => $userId,
                'company'    => $company,
                'accounting' => $this->defaultAccounting(),
                'numbering'  => $this->defaultNumbering(),
                'quotation'  => $this->defaultQuotation(),
            ]);
        }

        return response()->json([
            'message' => 'Logo uploaded successfully',
            'path'    => $path,
            'url'     => Storage::disk('public')->url($path),
        ]);
    }

    /** DELETE /api/accounting/settings/logo */
    public function deleteLogo()
    {
        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        if ($setting) {
            $path = $setting->company['companyLogo'] ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $company = $setting->company ?? [];
            $company['companyLogo'] = '';
            $setting->update(['company' => $company]);
        }

        return response()->json(['message' => 'Logo removed successfully']);
    }

    /** POST /api/accounting/settings/signature */
    public function uploadSignature(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        $oldPath = $setting->company['sealSignatureImage'] ?? null;
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('image')->storeAs(
            'signatures',
            $userId . '-' . Str::random(8) . '.' . $request->file('image')->extension(),
            'public'
        );

        $company = array_merge($this->defaultCompany(), $setting->company ?? [], ['sealSignatureImage' => $path]);

        if ($setting) {
            $setting->update(['company' => $company]);
        } else {
            AccountingSetting::create([
                'user_id'    => $userId,
                'company'    => $company,
                'accounting' => $this->defaultAccounting(),
                'numbering'  => $this->defaultNumbering(),
                'quotation'  => $this->defaultQuotation(),
            ]);
        }

        return response()->json([
            'message' => 'Signature uploaded successfully',
            'path'    => $path,
            'url'     => Storage::disk('public')->url($path),
        ]);
    }

    /** DELETE /api/accounting/settings/signature */
    public function deleteSignature()
    {
        $userId  = Auth::id();
        $setting = AccountingSetting::where('user_id', $userId)->first();

        if ($setting) {
            $path = $setting->company['sealSignatureImage'] ?? null;
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $company = $setting->company ?? [];
            $company['sealSignatureImage'] = '';
            $setting->update(['company' => $company]);
        }

        return response()->json(['message' => 'Signature removed successfully']);
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

