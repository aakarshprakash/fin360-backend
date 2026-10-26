<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * GET /api/roles — List all roles with permissions & user count
     */
    public function index(Request $request)
    {
        $query = Role::withCount('users')->with('permissions');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $roles = $query->orderBy('name')->get()->map(function ($role) {
            return $this->formatRole($role);
        });

        return response()->json(['roles' => $roles]);
    }

    /**
     * GET /api/roles/{id} — Get single role with permissions & users
     */
    public function show($id)
    {
        $role = Role::withCount('users')
            ->with(['permissions', 'users:id,name,email,role,status,avatar'])
            ->findOrFail($id);

        $data = $this->formatRole($role);
        $data['users'] = $role->users->map(function ($u) {
            return [
                'id'     => $u->id,
                'name'   => $u->name,
                'email'  => $u->email,
                'role'   => $u->role,
                'status' => $u->status,
                'avatar' => $u->avatar,
            ];
        });

        return response()->json(['role' => $data]);
    }

    /**
     * POST /api/roles — Create a new role
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $role = Role::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system'   => false,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        // Assign permissions
        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');
        $role->loadCount('users');

        UserActivityLog::log(
            $request->user()->id,
            'create',
            'roles',
            Role::class,
            $role->id,
            null,
            $role->toArray()
        );

        return response()->json([
            'role' => $this->formatRole($role),
        ], 201);
    }

    /**
     * PUT /api/roles/{id} — Update role
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:100|unique:roles,name,' . $id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,slug',
        ]);

        $oldValues = $role->toArray();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        // Don't allow renaming system roles — return 403 per spec
        if ($role->is_system && isset($validated['name']) && $validated['name'] !== $role->name) {
            return response()->json([
                'message' => 'System roles cannot be renamed.',
            ], 403);
        }

        $role->update(collect($validated)->except('permissions')->toArray());

        // Sync permissions if provided
        if (array_key_exists('permissions', $validated)) {
            $role->syncPermissions($validated['permissions'] ?? []);
        }

        $role->load('permissions');
        $role->loadCount('users');

        UserActivityLog::log(
            $request->user()->id,
            'update',
            'roles',
            Role::class,
            $role->id,
            $oldValues,
            $role->toArray()
        );

        return response()->json([
            'role' => $this->formatRole($role),
        ]);
    }

    /**
     * DELETE /api/roles/{id} — Delete role
     */
    public function destroy(Request $request, $id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        // System roles cannot be deleted — 403 per spec
        if ($role->is_system) {
            return response()->json([
                'message' => 'System roles cannot be deleted.',
            ], 403);
        }

        // Cannot delete if users are assigned — 422 per spec
        if ($role->users_count > 0) {
            return response()->json([
                'message' => "Cannot delete role. {$role->users_count} users are assigned to this role.",
            ], 422);
        }

        UserActivityLog::log(
            $request->user()->id,
            'delete',
            'roles',
            Role::class,
            $role->id,
            $role->toArray(),
            null
        );

        $role->permissions()->detach();
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    /**
     * Format role for API response (matches frontend spec).
     */
    private function formatRole(Role $role): array
    {
        return [
            'id'             => $role->id,
            'name'           => $role->name,
            'description'    => $role->description,
            'is_system'      => $role->is_system,
            'is_super_admin' => $role->is_super_admin ?? false,
            'users_count'    => $role->users_count ?? 0,
            'permissions'    => $role->permissions->pluck('slug')->values()->toArray(),
            'created_at'     => $role->created_at ? $role->created_at->format('M d, Y') : null,
            'updated_at'     => $role->updated_at ? $role->updated_at->format('M d, Y') : null,
        ];
    }
}

