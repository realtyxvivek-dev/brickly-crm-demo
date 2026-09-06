<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCrmRole
{
    public function handle(Request $request, Closure $next): Response
    {
        // Try multiple ways to get the authenticated user (for Sanctum stateful auth)
        // $request->user() is set by Sanctum middleware, auth('web')->user() uses session
        $user = $request->user() ?? auth('web')->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Check if user has a role
        if (!$user->role) {
            return response()->json(['message' => 'Forbidden. User role not found.'], 403);
        }

        // Admin has access to all CRM functionality
        if ($user->isAdmin()) {
            return $next($request);
        }

        // Check if user has CRM role
        if (!$user->isCrm()) {
            return response()->json(['message' => 'Forbidden. CRM role required.'], 403);
        }

        return $next($request);
    }
}
