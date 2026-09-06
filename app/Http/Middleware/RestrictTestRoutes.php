<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictTestRoutes
{
    /**
     * Restrict test/diagnostic routes: allow in non-production, or when user is admin/crm.
     * Otherwise return 404.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment() !== 'production') {
            return $next($request);
        }

        $user = $request->user() ?? auth()->user();
        if ($user && $user->relationLoaded('role') === false) {
            $user->load('role');
        }
        $slug = $user?->role?->slug ?? null;
        if (in_array($slug, ['admin', 'crm'], true)) {
            return $next($request);
        }

        abort(404);
    }
}
