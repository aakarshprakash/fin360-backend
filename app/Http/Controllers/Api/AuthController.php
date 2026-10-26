<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Models\Role;
use App\Models\UserActivityLog;

class AuthController extends Controller
{
    /**
     * POST /api/login — Login user and create token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is active
        if ($user->status !== 'Active') {
            return response()->json([
                'message' => 'Your account is not active. Please contact administrator.',
            ], 403);
        }

        // Update last active
        $user->update(['last_active' => now()]);

        // Create token
        $token = $user->createToken('auth-token')->plainTextToken;

        // Create refresh token (longer-lived)
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        // Load role with permissions
        $user->load('roleModel.permissions');

        // Log login activity
        UserActivityLog::log($user->id, 'login', 'auth');

        return response()->json([
            'message' => 'Login successful',
            'user'    => $this->formatAuthUser($user),
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
        ], 200);
    }

    /**
     * POST /api/register — Register new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'phone'      => 'nullable|string|max:20',
            'role'       => 'nullable|string|max:50',
            'department' => 'nullable|string|max:50',
        ]);

        // Find the default role (Sales Agent) or fallback
        $defaultRole = Role::where('slug', 'viewer')->first();

        $user = User::create([
            'name'        => $request->name,
            'email'       => $request->email,
            'password'    => Hash::make($request->password),
            'phone'       => $request->phone,
            'role'        => $request->role ?? 'Viewer',
            'role_id'     => $defaultRole ? $defaultRole->id : null,
            'department'  => $request->department ?? 'Finance',
            'status'      => 'Active',
            'permissions' => ['view_accounting', 'view_invoices'],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        $user->load('roleModel.permissions');

        UserActivityLog::log($user->id, 'register', 'auth');

        return response()->json([
            'message' => 'Registration successful',
            'user'    => $this->formatAuthUser($user),
            'token'   => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * POST /api/logout — Logout user (revoke current token)
     */
    public function logout(Request $request)
    {
        UserActivityLog::log($request->user()->id, 'logout', 'auth');

        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }

    /**
     * POST /api/logout-everywhere — Revoke ALL tokens for this user
     */
    public function logoutEverywhere(Request $request)
    {
        UserActivityLog::log($request->user()->id, 'logout_everywhere', 'auth');

        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices successfully',
        ], 200);
    }

    /**
     * POST /api/refresh-token — Issue a new auth token using refresh token
     */
    public function refreshToken(Request $request)
    {
        $user = $request->user();

        // Revoke the current token
        $user->currentAccessToken()->delete();

        // Issue new tokens
        $token        = $user->createToken('auth-token')->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'message'       => 'Token refreshed successfully',
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
        ], 200);
    }

    /**
     * GET /api/me — Get authenticated user with role & permissions
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roleModel.permissions');

        return response()->json([
            'user' => $this->formatAuthUser($user),
        ], 200);
    }

    /**
     * Format user for auth response — includes role & all resolved permissions.
     */
    private function formatAuthUser(User $user): array
    {
        $roleData = null;
        if ($user->roleModel) {
            $roleData = [
                'id'             => $user->roleModel->id,
                'name'           => $user->roleModel->name,
                'is_super_admin' => $user->roleModel->is_super_admin ?? false,
            ];
        }

        $allPerms = $user->getAllPermissions();

        return [
            'id'                => $user->id,
            'name'              => $user->name,
            'email'             => $user->email,
            'phone'             => $user->phone,
            'role'              => $user->role,
            'role_id'           => $user->role_id,
            'role_data'         => $roleData,
            'is_super_admin'    => $user->is_super_admin ?? false,
            'department'        => $user->department,
            'status'            => $user->status,
            'permissions'       => $allPerms,
            'all_permissions'   => $allPerms,
            'avatar'            => $user->avatar,
            'email_verified_at' => $user->email_verified_at,
            'last_active'       => $user->last_active,
            'created_at'        => $user->created_at,
        ];
    }
}
