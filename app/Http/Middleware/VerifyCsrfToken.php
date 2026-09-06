<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    /**
     * Determine if the request should be excluded from CSRF verification.
     * Skip CSRF check for API routes or when Bearer token is present.
     */
    protected function inExceptArray($request)
    {
        // Skip CSRF for all API routes - they use Bearer token authentication
        if ($request->is('api/*')) {
            return true;
        }

        // Skip CSRF if Bearer token is present (API authentication)
        if ($request->bearerToken() || $request->header('Authorization')) {
            return true;
        }

        return parent::inExceptArray($request);
    }

    /**
     * Determine if the session and input CSRF tokens match.
     * Override to skip for API routes or Bearer token requests.
     */
    protected function tokensMatch($request)
    {
        // Skip CSRF token check for API routes
        if ($request->is('api/*')) {
            return true;
        }

        // Skip CSRF if Bearer token is present (API authentication)
        if ($request->bearerToken() || $request->header('Authorization')) {
            return true;
        }

        return parent::tokensMatch($request);
    }

    /**
     * Handle an incoming request.
     * Override to skip CSRF verification for Bearer token requests.
     */
    public function handle($request, \Closure $next)
    {
        // Skip CSRF entirely for API routes or Bearer token requests
        if ($request->is('api/*') || $request->bearerToken() || $request->header('Authorization')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}

