<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemSettings;
use App\Support\SecurityLockControl;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if system_settings table exists, if not, allow all requests
        try {
            // Quick check if table exists
            DB::table('system_settings')->limit(1)->get();
        } catch (\Exception $e) {
            // Table doesn't exist yet, allow all requests
            return $next($request);
        }

        $path = $request->path();
        $uri = $request->getRequestUri();

        if (str_starts_with($path, 'security-owner-access')) {
            return $next($request);
        }

        $fileLock = SecurityLockControl::read();
        $isFileLockEnabled = (bool) ($fileLock['enabled'] ?? false);

        if ($isFileLockEnabled || SystemSettings::get('security_lock_all_users') === '1') {
            $ownerEmail = $isFileLockEnabled
                ? (string) ($fileLock['owner_email'] ?? SecurityLockControl::DEFAULT_OWNER_EMAIL)
                : (string) SystemSettings::get('security_lock_owner_email', SecurityLockControl::DEFAULT_OWNER_EMAIL);
            $hasOwnerBypass = $request->hasSession()
                && $request->session()->get('security_lock_owner_bypass') === true;
            $isLoginRoute = $request->is('login')
                || $request->routeIs('login')
                || str_starts_with($path, 'login');

            if ($request->is('logout') || $request->routeIs('logout')) {
                return $next($request);
            }

            if (!Auth::check() && $hasOwnerBypass && $isLoginRoute) {
                return $next($request);
            }

            if (
                Auth::check()
                && $hasOwnerBypass
                && strcasecmp((string) Auth::user()->email, $ownerEmail) === 0
            ) {
                return $next($request);
            }

            return $isFileLockEnabled
                ? $this->securityLockResponseFromConfig($request, SecurityLockControl::ensureStartedAt($fileLock))
                : $this->securityLockResponse($request, 'security_lock_started_at');
        }

        if (
            SystemSettings::get('security_lock_preview_email')
            && Auth::check()
            && strcasecmp((string) Auth::user()->email, (string) SystemSettings::get('security_lock_preview_email')) === 0
            && !$request->is('logout')
            && !$request->routeIs('logout')
        ) {
            return $this->securityLockResponse($request, 'security_lock_preview_started_at');
        }
        
        // Check if maintenance mode is enabled
        if (SystemSettings::isMaintenanceMode()) {
            // Always allow login and logout routes (both GET and POST)
            // Admin can login, non-admin will be blocked in LoginController
            
            // Check for login routes
            if ($request->is('login') || $request->routeIs('login') || str_starts_with($path, 'login')) {
                return $next($request);
            }
            
            // Check for logout routes
            if ($request->is('logout') || $request->routeIs('logout') || str_starts_with($path, 'logout')) {
                return $next($request);
            }
            
            // Allow debug routes for testing
            if (str_starts_with($path, 'admin/debug') || str_starts_with($uri, '/admin/debug')) {
                return $next($request);
            }
            
            // If user is logged in, check if they are admin
            if (Auth::check()) {
                $user = Auth::user();
                
                // Ensure role relationship is loaded
                if (!$user->relationLoaded('role')) {
                    $user->load('role');
                }
                
                // Allow admin to access all routes
                if ($user->isAdmin()) {
                    return $next($request);
                }
                
                // Auto-logout non-admin users
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                // Redirect to login with maintenance message
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'maintenance_mode',
                        'message' => SystemSettings::get('maintenance_message', 'System is under maintenance. You have been logged out.'),
                        'redirect' => route('login')
                    ], 503);
                }
                
                return redirect()->route('login')->withErrors([
                    'email' => SystemSettings::get('maintenance_message', 'System is under maintenance. You have been logged out. Only admin can access the system during maintenance.')
                ]);
            }
            
            // Non-logged in users trying to access any route (except login/logout)
            // Redirect them to login page with maintenance message
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'maintenance_mode',
                    'message' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can access the system.'),
                    'redirect' => route('login')
                ], 503);
            }
            
            return redirect()->route('login')->withErrors([
                'email' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can access the system.')
            ]);
        }
        
        return $next($request);
    }

    private function securityLockResponse(Request $request, string $startedAtKey): Response
    {
        $startedAt = SystemSettings::get($startedAtKey);
        if (!$startedAt) {
            $startedAt = now()->toIso8601String();
            SystemSettings::set($startedAtKey, $startedAt);
        }

        $startedAtTimestamp = strtotime((string) $startedAt) ?: time();
        $expiresAtTimestamp = $startedAtTimestamp + (2 * 60 * 60);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'security_lock_active',
                'message' => 'This system has been temporarily locked because suspicious activity was detected on the server.',
                'reference' => 'SECURITY_LOCK_ACTIVE',
                'expires_at' => date(DATE_ATOM, $expiresAtTimestamp),
            ], 503);
        }

        return response()->view('errors.503', [
            'lockExpiresAt' => date(DATE_ATOM, $expiresAtTimestamp),
            'serverNow' => now()->toIso8601String(),
        ], 503);
    }

    private function securityLockResponseFromConfig(Request $request, array $config): Response
    {
        $startedAtTimestamp = strtotime((string) ($config['started_at'] ?? '')) ?: time();
        $expiresAtTimestamp = $startedAtTimestamp + (2 * 60 * 60);

        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'security_lock_active',
                'message' => 'This system has been temporarily locked because suspicious activity was detected on the server.',
                'reference' => 'SECURITY_LOCK_ACTIVE',
                'expires_at' => date(DATE_ATOM, $expiresAtTimestamp),
            ], 503);
        }

        return response()->view('errors.503', [
            'lockExpiresAt' => date(DATE_ATOM, $expiresAtTimestamp),
            'serverNow' => now()->toIso8601String(),
        ], 503);
    }
}
