<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\InstallationChecker;
use Symfony\Component\HttpFoundation\Response;

class CheckInstallation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $isInstalled = InstallationChecker::isInstalled();
        
        // If trying to access install routes but already installed
        if ($request->is('install*') && $isInstalled) {
            return redirect('/')->with('info', 'System is already installed.');
        }
        
        // If trying to access other routes but not installed
        if (!$request->is('install*') && !$isInstalled) {
            // Allow welcome page and install routes
            if ($request->is('/') || $request->is('pwa-test') || $request->is('save-icons')) {
                return $next($request);
            }
            return redirect('/install');
        }
        
        return $next($request);
    }
}
