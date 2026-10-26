<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    /**
     * GET /api/settings/company
     *
     * Public — no auth required (login page needs branding).
     * Returns the single company-settings row, creating defaults if none exists.
     */
    public function show()
    {
        $settings = CompanySetting::first();

        if (!$settings) {
            $settings = CompanySetting::create([
                'company_name'       => 'Fin360',
                'company_short_name' => 'F3',
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => $settings,
        ]);
    }

    /**
     * PUT /api/settings/company
     *
     * Admin or manage_company_settings permission required.
     * Upsert — creates if not present, updates otherwise.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name'       => 'required|string|max:255',
            'company_short_name' => 'nullable|string|max:10',
            'tagline'            => 'nullable|string|max:500',
            'email'              => 'nullable|email|max:255',
            'phone'              => 'nullable|string|max:50',
            'address'            => 'nullable|string|max:1000',
            'website'            => 'nullable|url|max:500',
            'logo_url'           => 'nullable|url|max:1000',
        ]);

        $settings = CompanySetting::first();

        if ($settings) {
            $settings->update($validated);
        } else {
            $settings = CompanySetting::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company settings updated successfully.',
            'data'    => $settings->fresh(),
        ]);
    }
}

