<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * Usage in routes: ->middleware('permission:view_leads,create_leads')
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$permissions  One or more permission slugs
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Super Admin bypasses everything
        if ($user->is_super_admin) {
            return $next($request);
        }

        // Check role-level super admin (with safe column check)
        try {
            if ($user->roleModel && $user->roleModel->is_super_admin) {
                return $next($request);
            }
        } catch (\Exception $e) {
            // is_super_admin column may not exist yet
        }

        // Admin role bypass (check roleModel first, then legacy string column)
        if ($user->roleModel && $user->roleModel->name === 'Admin') {
            return $next($request);
        }
        if (!$user->roleModel && strtolower($user->role ?? '') === 'admin') {
            return $next($request);
        }

        // Check if user has ANY of the required permissions
        if (!$user->hasAnyPermission($permissions)) {
            return response()->json([
                'message' => 'Forbidden.',
            ], 403);
        }

        return $next($request);
    }
}

