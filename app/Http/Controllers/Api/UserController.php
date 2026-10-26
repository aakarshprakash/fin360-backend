<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/users — List all users with role info
     */
    public function index(Request $request)
    {
        $query = User::query()
                     ->with('roleModel:id,name,slug');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('role_id')) {
            $query->where('role_id', $request->role_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->department);
        }

        $perPage = $request->get('per_page', 50);
        $paginated = $query->orderBy('name')->paginate($perPage);

        $users = collect($paginated->items())->map(function ($u) {
            return $this->formatUser($u);
        });

        return response()->json([
            'data' => $users,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total(),
            ],
        ]);
    }

    /**
     * GET /api/users/{id} — Get user detail + performance + role permissions
     */
    public function show($id)
    {
        $user = User::withCount(['invoices', 'bills', 'incomeEntries', 'expenseEntries'])
            ->with(['roleModel.permissions', 'userPermissions'])
            ->findOrFail($id);

        $data = $this->formatUser($user);
        $data['performance'] = [
            'invoices_created' => $user->invoices_count,
            'bills_recorded'   => $user->bills_count,
            'income_recorded'  => $user->income_entries_count,
            'expenses_recorded'=> $user->expense_entries_count,
        ];

        // Recent activity
        $data['recent_activity'] = $user->activityLogs()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($log) {
                return [
                    'id'         => $log->id,
                    'action'     => $log->action,
                    'module'     => $log->module,
                    'ip_address' => $log->ip_address,
                    'created_at' => $log->created_at ? $log->created_at->format('M d, Y h:i A') : null,
                ];
            });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /api/users — Create user (Admin)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|unique:users,email',
            'password'    => 'required|string|min:8|confirmed',
            'phone'       => 'nullable|string|max:20',
            'role'        => 'nullable|string|max:50',
            'role_id'     => 'nullable|integer|exists:roles,id',
            'department'  => 'nullable|string|max:50',
            'status'      => 'nullable|string|in:Active,Inactive',
            'permissions' => 'nullable|array',
            'avatar'      => 'nullable|string',
        ]);

        $validated['password']   = Hash::make($validated['password']);
        $validated['status']     = $validated['status'] ?? 'Active';

        // If role_id provided, sync the string role column from the Role model
        if (!empty($validated['role_id'])) {
            $roleModel = Role::find($validated['role_id']);
            if ($roleModel) {
                $validated['role'] = $roleModel->name;
            }
        } else {
            $validated['role']       = $validated['role'] ?? 'Sales Agent';
            $validated['department'] = $validated['department'] ?? 'Sales';
            // Try to find matching role
            $roleModel = Role::where('name', $validated['role'])->first();
            if ($roleModel) {
                $validated['role_id'] = $roleModel->id;
            }
        }

        $user = User::create($validated);
        $user->load('roleModel:id,name,slug', 'departmentModel:id,name', 'jobTitle:id,name');

        UserActivityLog::log(
            $request->user()->id,
            'create',
            'users',
            User::class,
            $user->id,
            null,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role]
        );

        return response()->json([
            'message' => 'User created successfully',
            'data'    => $this->formatUser($user),
        ], 201);
    }

    /**
     * PUT /api/users/{id} — Update user (Admin)
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'sometimes|required|email|unique:users,email,' . $id,
            'phone'         => 'nullable|string|max:20',
            'role'          => 'nullable|string|max:50',
            'role_id'       => 'nullable|integer|exists:roles,id',
            'department'    => 'nullable|string|max:50',
            'department_id' => 'nullable|integer|exists:departments,id',
            'title_id'      => 'nullable|integer|exists:job_titles,id',
            'status'        => 'nullable|string|in:Active,Inactive',
            'permissions'   => 'nullable|array',
            'avatar'        => 'nullable|string',
        ]);

        $oldValues = [
            'name' => $user->name, 'email' => $user->email,
            'role' => $user->role, 'role_id' => $user->role_id,
            'status' => $user->status,
        ];

        // If role_id changed, sync the string role column
        if (isset($validated['role_id'])) {
            $roleModel = Role::find($validated['role_id']);
            if ($roleModel) {
                $validated['role'] = $roleModel->name;
            }
        } elseif (isset($validated['role'])) {
            // If string role changed, try to match a Role model
            $roleModel = Role::where('name', $validated['role'])->first();
            if ($roleModel) {
                $validated['role_id'] = $roleModel->id;
            }
        }

        $user->update($validated);
        $user->load('roleModel:id,name,slug');

        UserActivityLog::log(
            $request->user()->id,
            'update',
            'users',
            User::class,
            $user->id,
            $oldValues,
            ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'role_id' => $user->role_id, 'status' => $user->status]
        );

        return response()->json([
            'message' => 'User updated successfully',
            'data'    => $this->formatUser($user),
        ]);
    }

    /**
     * DELETE /api/users/{id} — Soft delete user (Admin)
     */
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account'], 422);
        }

        UserActivityLog::log(
            $request->user()->id,
            'delete',
            'users',
            User::class,
            $user->id,
            ['name' => $user->name, 'email' => $user->email],
            null
        );

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * PUT /api/users/profile — Update own profile
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'email'  => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone'  => 'nullable|string|max:20',
            'avatar' => 'nullable|string',
        ]);

        $user->update($validated);
        $user->load('roleModel:id,name,slug', 'departmentModel:id,name', 'jobTitle:id,name');

        return response()->json([
            'message' => 'Profile updated successfully',
            'data'    => $this->formatUser($user),
        ]);
    }

    /**
     * PUT /api/users/password — Change own password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required|string',
            'newPassword'     => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->currentPassword, $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'errors'  => ['currentPassword' => ['Current password is incorrect']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->newPassword)]);

        UserActivityLog::log($user->id, 'password_change', 'auth');

        return response()->json(['message' => 'Password changed successfully']);
    }

    /**
     * POST /api/users/{id}/assign-role — Assign a role to a user
     */
    public function assignRole(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        $roleModel = Role::findOrFail($validated['role_id']);

        $oldRole = $user->role;
        $user->update([
            'role_id' => $roleModel->id,
            'role'    => $roleModel->name,
        ]);

        $user->load('roleModel.permissions');

        UserActivityLog::log(
            $request->user()->id,
            'assign_role',
            'users',
            User::class,
            $user->id,
            ['role' => $oldRole],
            ['role' => $roleModel->name, 'role_id' => $roleModel->id]
        );

        return response()->json([
            'message'         => "Role '{$roleModel->name}' assigned to {$user->name} successfully",
            'data'            => $this->formatUser($user),
            'all_permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * GET /api/users/{id}/permissions — Get user's resolved permissions
     */
    public function getUserPermissions($id)
    {
        $user = User::with('roleModel.permissions')->findOrFail($id);

        return response()->json([
            'data' => [
                'user_id'          => $user->id,
                'user_name'        => $user->name,
                'role'             => $user->role,
                'role_id'          => $user->role_id,
                'role_permissions' => $user->getRolePermissions(),
                'user_permissions' => $user->permissions ?? [],
                'all_permissions'  => $user->getAllPermissions(),
            ],
        ]);
    }

    /**
     * PUT /api/users/{id}/permissions — Update user's role & permission overrides
     *
     * Accepts: { role_id, role, permissions: ["view_leads", ...] }
     * - Updates the user's role_id (and syncs the role string column)
     * - Syncs the user_permissions pivot table with override permissions
     */
    public function updateUserPermissions(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'role'          => 'nullable|string|max:50',
            'role_id'       => 'nullable|integer|exists:roles,id',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $oldData = [
            'role'        => $user->role,
            'role_id'     => $user->role_id,
            'permissions' => $user->getAllPermissions(),
        ];

        // Update role if provided
        if (isset($validated['role_id'])) {
            $roleModel = Role::find($validated['role_id']);
            if ($roleModel) {
                $user->update([
                    'role_id' => $roleModel->id,
                    'role'    => $roleModel->name,
                ]);
            }
        } elseif (isset($validated['role'])) {
            $roleModel = Role::where('name', $validated['role'])->first();
            if ($roleModel) {
                $user->update([
                    'role_id' => $roleModel->id,
                    'role'    => $roleModel->name,
                ]);
            }
        }

        // Sync per-user permission overrides via pivot table
        if (isset($validated['permissions'])) {
            $permIds = Permission::whereIn('slug', $validated['permissions'])->pluck('id');
            $user->userPermissions()->sync($permIds);

            // Also keep the JSON column in sync for backward compat
            $user->update(['permissions' => $validated['permissions']]);
        }

        UserActivityLog::log(
            $request->user()->id,
            'update_permissions',
            'users',
            User::class,
            $user->id,
            $oldData,
            [
                'role'        => $user->role,
                'role_id'     => $user->role_id,
                'permissions' => $validated['permissions'] ?? [],
            ]
        );

        $user->load(['roleModel.permissions', 'userPermissions']);

        $allPerms = $user->getAllPermissions();

        return response()->json([
            'message' => 'Permissions updated successfully.',
            'user'    => [
                'id'              => $user->id,
                'name'            => $user->name,
                'role'            => $user->role,
                'role_id'         => $user->role_id,
                'permissions'     => $allPerms,
                'all_permissions' => $allPerms,
            ],
        ]);
    }

    private function formatUser(User $user): array
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
            'id'              => $user->id,
            'name'            => $user->name,
            'email'           => $user->email,
            'phone'           => $user->phone,
            'role'            => $user->role,
            'role_id'         => $user->role_id,
            'role_data'       => $roleData,
            'is_super_admin'  => $user->is_super_admin ?? false,
            'department'      => $user->departmentModel
                                    ? ['id' => $user->departmentModel->id, 'name' => $user->departmentModel->name]
                                    : null,
            'department_id'   => $user->department_id,
            'title_id'        => $user->title_id,
            'title'           => $user->jobTitle
                                    ? ['id' => $user->jobTitle->id, 'name' => $user->jobTitle->name]
                                    : null,
            'status'          => $user->status,
            'permissions'     => $allPerms,
            'all_permissions' => $allPerms,
            'avatar'          => $user->avatar,
            'last_active'     => $user->last_active ? $user->last_active->format('M d, Y h:i A') : null,
            'created_at'      => $user->created_at ? $user->created_at->format('M d, Y') : null,
        ];
    }
}

