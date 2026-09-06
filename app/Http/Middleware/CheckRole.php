<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Try multiple ways to get the authenticated user (for Sanctum stateful auth)
        // $request->user() is set by Sanctum middleware, auth()->user() uses default guard
        $user = $request->user() ?? auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        $userRole = $user->role->slug ?? null;
        
        // Handle sales_head role - if user is Sales Head, treat as sales_head role
        if ($user->isSalesHead() && in_array('sales_head', $roles)) {
            return $next($request);
        }

        if (
            $roles === ['admin']
            && method_exists($user, 'isAdManager')
            && $user->isAdManager()
            && $request->routeIs('integrations.facebook-lead-ads.*')
        ) {
            return $next($request);
        }

        if (!in_array($userRole, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. Insufficient permissions.'], 403);
            }
            abort(403, 'Forbidden. Insufficient permissions.');
        }

        return $next($request);
    }
}
