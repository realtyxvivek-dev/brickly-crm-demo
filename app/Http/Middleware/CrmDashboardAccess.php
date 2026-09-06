<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allows access to CRM dashboard for all roles except CRM Admin and Sale Head.
 * Use for dashboard view and dashboard API (stats, telecaller-stats, daily-prospects).
 */
class CrmDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? auth('web')->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        if (!$user->role) {
            return response()->json(['message' => 'Forbidden. User role not found.'], 403);
        }

        // Sale Head cannot see the CRM dashboard (Sales Executive Performance); CRM dikhega
        if ($user->isSalesHead()) {
            return response()->json(['message' => 'Forbidden. CRM dashboard not available for Sale Head.'], 403);
        }

        return $next($request);
    }
}
