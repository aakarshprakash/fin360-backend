# Company Settings API Documentation

## Status: ✅ Implemented

All backend components have been created and wired up:

| Component | File | Status |
|-----------|------|--------|
| Migration | `database/migrations/2026_03_12_000003_create_company_settings_table.php` | ✅ Done |
| Model | `app/Models/CompanySetting.php` | ✅ Done |
| Controller | `app/Http/Controllers/Api/CompanySettingController.php` | ✅ Done |
| Routes | `routes/api.php` — public GET + protected PUT | ✅ Done |
| Permission | `manage_company_settings` seeded in migration + seeder | ✅ Done |

---

## Overview

The Company Settings API allows administrators to manage the organization's branding and contact information. These settings are displayed across the application — on the login screen, in the footer of every page, and in any printed/exported documents.

---

## Base URL

```
/api/settings/company
```

---

## Endpoints

### 1. Get Company Settings

Retrieve the current company configuration.

**Endpoint:** `GET /api/settings/company`

**Authentication:** Bearer Token (any authenticated user)

**Response: `200 OK`**

```json
{
  "success": true,
  "data": {
    "company_name": "Acme Corp",
    "company_short_name": "AC",
    "tagline": "Your trusted CRM partner",
    "email": "info@acmecorp.com",
    "phone": "+91 98765 43210",
    "address": "123 Main Street, Tech Park, Bangalore, Karnataka 560001",
    "website": "https://www.acmecorp.com",
    "logo_url": "https://cdn.acmecorp.com/logo.png",
    "updated_at": "2026-03-10T12:30:00.000000Z"
  }
}
```

**Notes:**
- This endpoint is public-accessible (no auth required) so the login page can fetch branding before the user logs in.
- If no settings have been configured yet, return sensible defaults (e.g., `company_name: "Lead CRM"`).

---

### 2. Update Company Settings

Create or update the company settings. This is an **upsert** operation — if settings don't exist, they are created; otherwise, they are updated.

**Endpoint:** `PUT /api/settings/company`

**Authentication:** Bearer Token — **Admin only** (or permission: `manage_company_settings`)

**Request Body:**

```json
{
  "company_name": "Acme Corp",
  "company_short_name": "AC",
  "tagline": "Your trusted CRM partner",
  "email": "info@acmecorp.com",
  "phone": "+91 98765 43210",
  "address": "123 Main Street, Tech Park, Bangalore, Karnataka 560001",
  "website": "https://www.acmecorp.com",
  "logo_url": "https://cdn.acmecorp.com/logo.png"
}
```

**Validation Rules:**

| Field                | Type     | Required | Max Length | Notes                                      |
|----------------------|----------|----------|------------|---------------------------------------------|
| `company_name`       | string   | ✅ Yes   | 255        | The primary company/organization name       |
| `company_short_name` | string   | No       | 10         | Short name or initials (e.g., "AC", "LC")   |
| `tagline`            | string   | No       | 500        | A short motto or tagline                    |
| `email`              | string   | No       | 255        | Must be a valid email format                |
| `phone`              | string   | No       | 50         | Contact phone number                        |
| `address`            | string   | No       | 1000       | Full mailing/office address (multi-line OK) |
| `website`            | string   | No       | 500        | Must be a valid URL format                  |
| `logo_url`           | string   | No       | 1000       | URL to the company logo image               |

**Response: `200 OK`**

```json
{
  "success": true,
  "message": "Company settings updated successfully.",
  "data": {
    "company_name": "Acme Corp",
    "company_short_name": "AC",
    "tagline": "Your trusted CRM partner",
    "email": "info@acmecorp.com",
    "phone": "+91 98765 43210",
    "address": "123 Main Street, Tech Park, Bangalore, Karnataka 560001",
    "website": "https://www.acmecorp.com",
    "logo_url": "https://cdn.acmecorp.com/logo.png",
    "updated_at": "2026-03-12T08:15:00.000000Z"
  }
}
```

**Error Response: `422 Unprocessable Entity`**

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {
    "company_name": ["The company name field is required."],
    "email": ["The email must be a valid email address."],
    "website": ["The website must be a valid URL."]
  }
}
```

**Error Response: `403 Forbidden`**

```json
{
  "success": false,
  "message": "You do not have permission to manage company settings."
}
```

---

## Backend Implementation Guide (Laravel)

### 1. Migration

```php
// database/migrations/2026_03_12_000003_create_company_settings_table.php
Schema::create('company_settings', function (Blueprint $table) {
    $table->id();
    $table->string('company_name', 255)->default('Lead CRM');
    $table->string('company_short_name', 10)->nullable();
    $table->string('tagline', 500)->nullable();
    $table->string('email', 255)->nullable();
    $table->string('phone', 50)->nullable();
    $table->text('address')->nullable();
    $table->string('website', 500)->nullable();
    $table->string('logo_url', 1000)->nullable();
    $table->timestamps();
});

// Seed a default row
DB::table('company_settings')->insert([
    'company_name' => 'Lead CRM',
    'company_short_name' => 'LC',
    'created_at' => now(),
    'updated_at' => now(),
]);
```

### 2. Model

```php
// app/Models/CompanySetting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $table = 'company_settings';

    protected $fillable = [
        'company_name',
        'company_short_name',
        'tagline',
        'email',
        'phone',
        'address',
        'website',
        'logo_url',
    ];
}
```

### 3. Controller

```php
// app/Http/Controllers/Api/CompanySettingController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\Request;

class CompanySettingController extends Controller
{
    /**
     * GET /api/settings/company
     * Public — no auth required (login page needs it)
     */
    public function show()
    {
        $settings = CompanySetting::first();

        if (!$settings) {
            $settings = CompanySetting::create(['company_name' => 'Lead CRM', 'company_short_name' => 'LC']);
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * PUT /api/settings/company
     * Admin or manage_company_settings permission required
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
            'data' => $settings->fresh(),
        ]);
    }
}
```

### 4. Routes

```php
// routes/api.php
use App\Http\Controllers\Api\CompanySettingController;

// Public (no auth) — so login page can fetch branding
Route::get('/settings/company', [CompanySettingController::class, 'show']);

// Protected — admin only (inside the auth:sanctum group)
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/settings/company', [CompanySettingController::class, 'update'])
        ->middleware('permission:manage_company_settings');
});
```

### 5. Permission

The `manage_company_settings` permission is seeded via:

- **Migration:** `2026_03_11_000002_seed_rbac_permissions.php` — inserts the permission row into the `permissions` table.
- **Seeder:** `RolesAndPermissionsSeeder.php` — included in the Settings & Configuration permission group and auto-assigned to Super Admin and Admin roles.

```php
// In RolesAndPermissionsSeeder (already added)
['name' => 'Manage Company Settings', 'slug' => 'manage_company_settings', 'module' => 'settings', 'description' => 'Can update company branding and contact info'],
```

---

## Where Company Settings Are Used in the Frontend

| Location               | What's Shown                                           |
|------------------------|--------------------------------------------------------|
| **Login Screen**       | Logo/icon, company name, tagline, phone, email, website |
| **App Footer**         | Company name, phone, email, address                    |
| **Settings → Company** | Full edit form with live preview                       |

---

## Data Flow

```
Settings Page (admin) ──PUT──▶ /api/settings/company ──▶ Database
                                                            │
Login Page ──GET──▶ /api/settings/company ◀─────────────────┘
Layout Footer ──GET (cached)──▶ /api/settings/company ◀─────┘
```

- The frontend uses a **`useCompanySettings`** hook with a 5-minute cache to avoid re-fetching on every page navigation.
- After saving in Settings, the cache is invalidated and all components using the hook will re-fetch.

---

## Notes

1. The `GET` endpoint should be **publicly accessible** (no auth middleware) because the login screen needs to display company branding before the user is authenticated.
2. The `PUT` endpoint must be **admin-only** or gated behind `manage_company_settings` permission.
3. Only **one row** should ever exist in the `company_settings` table. The controller always operates on `CompanySetting::first()`.
4. The `logo_url` field stores a URL; file upload (if needed later) would be a separate endpoint that returns a URL.

