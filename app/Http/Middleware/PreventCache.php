<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventCache
{
    /**
     * Handle an incoming request.
     * Add no-cache headers to prevent browser caching
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Don't cache redirects (especially login redirects)
        if ($response->isRedirection()) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            // Ensure redirect is not cached
            $response->setStatusCode(302);
        } else {
            // Add no-cache headers for all other responses
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // For login/logout pages, add additional headers
        if ($request->routeIs('login') || $request->routeIs('logout') || $request->is('login') || $request->is('logout')) {
            $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        }

        return $response;
    }
}
