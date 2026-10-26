<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * GET /api/settings
     */
    public function index()
    {
        $userId   = Auth::id();
        $settings = DB::table('settings')->where('user_id', $userId)->first();

        if (!$settings) {
            $default = $this->defaultSettings($userId);
            DB::table('settings')->insert($default);
            $settings = (object) $default;
        }

        return response()->json([
            'data' => [
                'company'       => is_string($settings->company) ? json_decode($settings->company, true) : $settings->company,
                'appearance'    => is_string($settings->appearance) ? json_decode($settings->appearance, true) : $settings->appearance,
                'notifications' => is_string($settings->notifications) ? json_decode($settings->notifications, true) : $settings->notifications,
            ],
        ]);
    }

    /**
     * PUT /api/settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company'       => 'nullable|array',
            'appearance'    => 'nullable|array',
            'notifications' => 'nullable|array',
        ]);

        $userId   = Auth::id();
        $settings = DB::table('settings')->where('user_id', $userId)->first();

        $data = [
            'company'       => json_encode($validated['company'] ?? []),
            'appearance'    => json_encode($validated['appearance'] ?? []),
            'notifications' => json_encode($validated['notifications'] ?? []),
            'updated_at'    => now(),
        ];

        if ($settings) {
            DB::table('settings')->where('user_id', $userId)->update($data);
        } else {
            $data['user_id']    = $userId;
            $data['created_at'] = now();
            DB::table('settings')->insert($data);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    private function defaultSettings(int $userId): array
    {
        return [
            'user_id'       => $userId,
            'company'       => json_encode(['name' => 'My Company', 'email' => '', 'phone' => '', 'address' => '']),
            'appearance'    => json_encode(['theme' => 'light', 'language' => 'en', 'timezone' => 'UTC']),
            'notifications' => json_encode(['email_notifications' => true, 'invoice_due_reminders' => true, 'overdue_bill_alerts' => true]),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }
}

