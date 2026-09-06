<?php

namespace App\Exceptions;

use App\Services\SystemErrorAlertService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e)
    {
        // Handle 419 CSRF token mismatch
        if ($e instanceof TokenMismatchException) {
            // For API routes with Bearer token auth, don't treat as session expired
            if ($request->expectsJson() || $request->is('api/*')) {
                // Check if request has Bearer token (API authentication)
                $hasBearerToken = $request->bearerToken() || $request->header('Authorization');
                
                if ($hasBearerToken) {
                    // Bearer token auth - CSRF not needed
                    // Return success with a note, or just continue
                    // Actually, if CSRF is being checked despite Bearer token, 
                    // it means VerifyCsrfToken middleware might not be skipping it properly
                    // So let's just return a friendly error without logging out
                    return response()->json([
                        'success' => false,
                        'message' => 'Please refresh the page and try again.',
                        'error' => 'Token mismatch'
                    ], 419);
                }
                
                // For stateful API requests without Bearer token, return error but don't auto-logout
                return response()->json([
                    'success' => false,
                    'message' => 'CSRF token mismatch. Please refresh the page and try again.',
                    'error' => 'Token mismatch'
                ], 419);
            }
            
            // For web routes: Don't logout user, just show error and redirect back
            // User can refresh page and try again without losing their session
            // Regenerate CSRF token so next request works
            if (Auth::check()) {
                $request->session()->regenerateToken();
            }
            
            $redirectUrl = $request->headers->get('referer') ?: route('dashboard');
            
            return redirect($redirectUrl)
                ->with('error', 'CSRF token mismatch. Please refresh the page and try again.')
                ->withInput();
        }

        $this->notifyServerError($request, $e);

        // Ensure API routes and install routes always return JSON, even for unexpected errors
        if ($request->expectsJson() || $request->is('api/*') || $request->is('install/*')) {
            return $this->handleApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    protected function notifyServerError($request, Throwable $e): void
    {
        if (
            $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException
            || $e instanceof ValidationException
        ) {
            return;
        }

        $statusCode = 500;
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
        } elseif (method_exists($e, 'getStatusCode')) {
            /** @phpstan-ignore-next-line */
            $statusCode = $e->getStatusCode();
        }

        if ($statusCode < 500) {
            return;
        }

        try {
            app(SystemErrorAlertService::class)->handle($e, $request, $statusCode);
        } catch (Throwable $alertException) {
            Log::warning('System error alert failed: ' . $alertException->getMessage());
        }
    }

    /**
     * Handle exceptions for API requests
     */
    protected function handleApiException($request, Throwable $e)
    {
        // Auth exceptions should be 401 (not 500)
        if ($e instanceof AuthenticationException) {
            if ($request->is('api/sales-manager/*') || $request->is('api/dashboard*')) {
                Log::warning('Manager API authentication failure', [
                    'user_id' => optional($request->user())->id,
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        }

        if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'message' => 'Resource not found.',
            ], 404);
        }

        // Log the exception
        \Log::error('API Exception', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Return JSON error response
        $statusCode = 500;
        if ($e instanceof HttpExceptionInterface) {
            $statusCode = $e->getStatusCode();
        } elseif (method_exists($e, 'getStatusCode')) {
            /** @phpstan-ignore-next-line */
            $statusCode = $e->getStatusCode();
        }

        if (($statusCode === 401 || $statusCode === 403) && ($request->is('api/sales-manager/*') || $request->is('api/dashboard*'))) {
            Log::warning('Manager API request rejected', [
                'user_id' => optional($request->user())->id,
                'path' => $request->path(),
                'method' => $request->method(),
                'status' => $statusCode,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'message' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => $e->getMessage() ?: 'An error occurred',
            'error' => config('app.debug') ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ] : null,
        ], $statusCode);
    }
}
