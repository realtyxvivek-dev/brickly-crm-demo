<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Pipeline;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful as Middleware;

class CustomEnsureFrontendRequestsAreStateful extends Middleware
{
    /**
     * Handle the incoming requests.
     * Skip CSRF middleware if Bearer token is present.
     */
    public function handle($request, $next)
    {
        $this->configureSecureCookieSessions();

        // Skip stateful middleware (including CSRF) if Bearer token is present
        if ($request->bearerToken() || $request->header('Authorization')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Get the middleware that should be applied to requests from the "frontend".
     * Exclude CSRF middleware for Bearer token requests.
     */
    protected function frontendMiddleware()
    {
        $middleware = array_values(array_filter(array_unique([
            config('sanctum.middleware.encrypt_cookies', \Illuminate\Cookie\Middleware\EncryptCookies::class),
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // CSRF middleware removed - will be handled by VerifyCsrfToken which skips for Bearer tokens
            config('sanctum.middleware.authenticate_session'),
        ])));

        array_unshift($middleware, function ($request, $next) {
            $request->attributes->set('sanctum', true);

            return $next($request);
        });

        return $middleware;
    }

    /**
     * Determine if the given request is from the first-party application frontend.
     * Return false if Bearer token is present (treat as stateless API request).
     */
    public static function fromFrontend($request)
    {
        // Skip stateful handling for Bearer token requests
        if ($request->bearerToken() || $request->header('Authorization')) {
            return false;
        }

        return parent::fromFrontend($request);
    }
}

