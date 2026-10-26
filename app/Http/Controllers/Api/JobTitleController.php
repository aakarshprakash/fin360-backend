<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use Illuminate\Http\Request;

class JobTitleController extends Controller
{
    /**
     * GET /api/job-titles
     * ?department_id={id} — filter by department (User modal dropdown)
     * ?is_active=1        — only active titles (default: all)
     */
    public function index(Request $request)
    {
        $query = JobTitle::with('department')
            ->when($request->boolean('is_active', false), fn($q) => $q->where('is_active', true))
            ->when($request->filled('department_id'),     fn($q) => $q->where('department_id', $request->department_id));

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    /**
     * POST /api/job-titles
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:100|unique:job_titles,name',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $validated['is_active'] = true;
        $jobTitle = JobTitle::create($validated);
        $jobTitle->load('department');

        return response()->json(['data' => $jobTitle], 201);
    }

    /**
     * PUT /api/job-titles/{id}
     */
    public function update(Request $request, $id)
    {
        $jobTitle = JobTitle::findOrFail($id);

        $validated = $request->validate([
            'name'          => 'required|string|max:100|unique:job_titles,name,' . $id,
            'department_id' => 'nullable|exists:departments,id',
            'is_active'     => 'nullable|boolean',
        ]);

        $jobTitle->update($validated);
        $jobTitle->load('department');

        return response()->json(['data' => $jobTitle]);
    }

    /**
     * DELETE /api/job-titles/{id}
     */
    public function destroy($id)
    {
        $jobTitle = JobTitle::findOrFail($id);
        $jobTitle->delete();

        return response()->json(['message' => 'Job title deleted successfully']);
    }

    /**
     * PATCH /api/job-titles/{id}/toggle
     */
    public function toggle($id)
    {
        $jobTitle = JobTitle::findOrFail($id);
        $jobTitle->update(['is_active' => !$jobTitle->is_active]);

        return response()->json([
            'data' => ['id' => $jobTitle->id, 'is_active' => $jobTitle->is_active],
        ]);
    }
}
