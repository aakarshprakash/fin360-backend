<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * GET /api/departments
     * Optional filter: ?is_active=1
     */
    public function index(Request $request)
    {
        $departments = Department::orderBy('name')
            ->when($request->boolean('is_active', false), fn($q) => $q->where('is_active', true))
            ->get();

        return response()->json(['data' => $departments]);
    }

    /**
     * POST /api/departments
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:departments,name',
            'description' => 'nullable|string|max:255',
        ]);

        $validated['is_active'] = true;
        $department = Department::create($validated);

        return response()->json(['data' => $department], 201);
    }

    /**
     * GET /api/departments/{id}
     */
    public function show($id)
    {
        $department = Department::with(['jobTitles' => fn($q) => $q->orderBy('name')])
            ->findOrFail($id);

        return response()->json(['data' => $department]);
    }

    /**
     * PUT /api/departments/{id}
     */
    public function update(Request $request, $id)
    {
        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:departments,name,' . $id,
            'description' => 'nullable|string|max:255',
            'is_active'   => 'nullable|boolean',
        ]);

        $department->update($validated);

        return response()->json(['data' => $department]);
    }

    /**
     * DELETE /api/departments/{id}
     * Sets job_titles.department_id = NULL for any linked titles before deletion.
     */
    public function destroy($id)
    {
        $department = Department::findOrFail($id);
        $department->jobTitles()->update(['department_id' => null]);
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }

    /**
     * PATCH /api/departments/{id}/toggle
     */
    public function toggle($id)
    {
        $department = Department::findOrFail($id);
        $department->update(['is_active' => !$department->is_active]);

        return response()->json([
            'data' => ['id' => $department->id, 'is_active' => $department->is_active],
        ]);
    }

    /**
     * GET /api/departments/{id}/job-titles
     */
    public function jobTitles($id)
    {
        $department = Department::findOrFail($id);

        $jobTitles = $department->jobTitles()->orderBy('name')->get();

        return response()->json([
            'data'       => $jobTitles,
            'department' => ['id' => $department->id, 'name' => $department->name],
        ]);
    }
}

