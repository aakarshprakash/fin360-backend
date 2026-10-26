<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    /**
     * GET /api/permissions — List all permissions (grouped by category)
     * Response format matches frontend spec: { permissions: [{ category, permissions: [{ key, label, description }] }] }
     */
    public function index(Request $request)
    {
        $query = Permission::query();

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $permissions = $query->orderBy('module')->orderBy('name')->get();

        // Group by category (module) per spec
        $grouped = $permissions->groupBy('module')->map(function ($perms, $module) {
            return [
                'category'    => $this->formatCategoryName($module),
                'permissions' => $perms->map(function ($p) {
                    return [
                        'key'         => $p->slug,
                        'label'       => $p->name,
                        'description' => $p->description,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'permissions' => $grouped,
        ]);
    }

    /**
     * Convert module slug to readable category name.
     */
    private function formatCategoryName(string $module): string
    {
        $map = [
            'reports'       => 'Reports & Analytics',
            'dashboard'     => 'Reports & Analytics',
            'users'         => 'User Management',
            'roles'         => 'User Management',
            'settings'      => 'Settings & Configuration',
            'accounting'    => 'Finance & Accounting',
            'notifications' => 'Notifications',
            'activity_logs' => 'Activity Logs',
        ];

        return $map[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }

    /**
     * GET /api/permissions/{id} — Get single permission
     */
    public function show($id)
    {
        $permission = Permission::with('roles:id,name,slug')->findOrFail($id);

        return response()->json([
            'data' => [
                'id'          => $permission->id,
                'name'        => $permission->name,
                'slug'        => $permission->slug,
                'module'      => $permission->module,
                'description' => $permission->description,
                'is_active'   => $permission->is_active,
                'roles'       => $permission->roles->map(function ($r) {
                    return ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug];
                }),
                'created_at'  => $permission->created_at ? $permission->created_at->format('M d, Y') : null,
            ],
        ]);
    }

    /**
     * POST /api/permissions — Create a new permission
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:150|unique:permissions,name',
            'module'      => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        $permission = Permission::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name'], '_'),
            'module'      => $validated['module'],
            'description' => $validated['description'] ?? null,
            'is_active'   => $validated['is_active'] ?? true,
        ]);

        UserActivityLog::log(
            $request->user()->id,
            'create',
            'permissions',
            Permission::class,
            $permission->id,
            null,
            $permission->toArray()
        );

        return response()->json([
            'message' => 'Permission created successfully',
            'data'    => [
                'id'          => $permission->id,
                'name'        => $permission->name,
                'slug'        => $permission->slug,
                'module'      => $permission->module,
                'description' => $permission->description,
                'is_active'   => $permission->is_active,
            ],
        ], 201);
    }

    /**
     * PUT /api/permissions/{id} — Update permission
     */
    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:150|unique:permissions,name,' . $id,
            'module'      => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'nullable|boolean',
        ]);

        $oldValues = $permission->toArray();

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name'], '_');
        }

        $permission->update($validated);

        UserActivityLog::log(
            $request->user()->id,
            'update',
            'permissions',
            Permission::class,
            $permission->id,
            $oldValues,
            $permission->toArray()
        );

        return response()->json([
            'message' => 'Permission updated successfully',
            'data'    => [
                'id'          => $permission->id,
                'name'        => $permission->name,
                'slug'        => $permission->slug,
                'module'      => $permission->module,
                'description' => $permission->description,
                'is_active'   => $permission->is_active,
            ],
        ]);
    }

    /**
     * DELETE /api/permissions/{id} — Delete permission
     */
    public function destroy(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        UserActivityLog::log(
            $request->user()->id,
            'delete',
            'permissions',
            Permission::class,
            $permission->id,
            $permission->toArray(),
            null
        );

        $permission->roles()->detach();
        $permission->delete();

        return response()->json(['message' => 'Permission deleted successfully']);
    }
}

