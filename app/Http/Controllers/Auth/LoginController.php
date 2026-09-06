<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserAvailability;
use App\Models\TelecallerProfile;
use App\Models\SystemSettings;
use App\Services\AuthRedirectService;
use App\Services\AuthSessionService;
use App\Services\EmailOtpLoginService;
use App\Services\LoginSecurityService;
use App\Services\UserAvailabilityService;
use Kreait\Firebase\Factory;

class LoginController extends Controller
{
    public function __construct(
        protected AuthRedirectService $authRedirectService,
        protected AuthSessionService $authSessionService,
        protected EmailOtpLoginService $emailOtpLoginService,
        protected LoginSecurityService $loginSecurityService,
    ) {
    }

    public function showLoginForm(Request $request)
    {
        // If user is already authenticated, redirect to dashboard
        if (Auth::check()) {
            $user = Auth::user();
            $redirectUrl = $this->authRedirectService->redirectPathFor($user);
            $redirect = redirect($redirectUrl);
            $redirect->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
            $redirect->headers->set('Pragma', 'no-cache');
            $redirect->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            return $redirect;
        }
        
        $response = response()->view('auth.login');
        
        // Add no-cache headers
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        
        return $response;
    }

    public function login(Request $request)
    {
        // Regenerate CSRF token to prevent 419 errors
        $request->session()->regenerateToken();

        if (!$request->session()->get('auth_flow.pending') || $request->session()->get('auth_flow.method') !== User::TWO_FACTOR_OFF) {
            $this->emailOtpLoginService->clearPendingSession($request);

            return redirect()->route('login');
        }

        $request->validate([
            'password' => 'required|string|max:64',
        ]);

        $email = (string) $request->session()->get('auth_flow.email_normalized', '');
        $user = $this->emailOtpLoginService->resolveActiveUserByEmail($email);
        $this->loginSecurityService->rememberAttempt($request, $email);

        if ($this->loginSecurityService->isLocked($user)) {
            return $this->loginSecurityService->redirectToLock($request, $user, $email);
        }

        if ($user && $user->usesEmailOtpLogin() && !$this->emailOtpLoginService->shouldForcePasswordLogin($user)) {
            $this->emailOtpLoginService->blockPasswordLoginAudit($request, $user);
            $this->emailOtpLoginService->clearPendingSession($request);

            return back()->withErrors([
                'email' => 'The provided credentials do not match our records or require another login method.',
            ]);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            $state = $this->loginSecurityService->recordFailure($request, $user, $email, 'password');
            if ($state['locked']) {
                return $this->loginSecurityService->redirectToLock($request, $user, $email);
            }

            return back()->withErrors([
                'password' => 'The provided credentials do not match our records.',
            ]);
        }
        
        // Check maintenance mode - only allow admin to login
        if (SystemSettings::isMaintenanceMode()) {
            if (!$user->isAdmin()) {
                return back()->withErrors([
                    'email' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can login during maintenance mode.'),
                ])->with('maintenance_mode', true);
            }
        }

        // Ensure role relationship is loaded before login
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        // Keep users signed in unless they explicitly log out.
        $remember = $request->boolean('remember', true);

        $this->authSessionService->loginUser($request, $user, $remember);
        $this->loginSecurityService->clearFailures($user);
        $this->emailOtpLoginService->clearPendingSession($request);

        // Redirect based on user role
        try {
            $redirectUrl = $this->authRedirectService->redirectPathFor($user);
            $redirect = redirect($redirectUrl);
            
            // Add no-cache headers to redirect response
            $redirect->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
            $redirect->headers->set('Pragma', 'no-cache');
            $redirect->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
            $redirect->headers->set('Location', $redirectUrl);
            
            // Force redirect with 302 status to prevent caching
            $redirect->setStatusCode(302);
            
            // Ensure session is committed before redirect
            $request->session()->save();
            
            Log::info('Login successful, redirecting', [
                'user_id' => $user->id,
                'email' => $user->email,
                'redirect_url' => $redirectUrl,
                'is_authenticated' => Auth::check(),
                'session_id' => $request->session()->getId(),
            ]);
            
            return $redirect;
        } catch (\Exception $e) {
            Log::error('Login redirect error', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Fallback redirect
            return redirect()->route('sales-manager.dashboard');
        }
    }

    public function loginWithFirebase(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
            $auth = $factory->createAuth();
            $verifiedToken = $auth->verifyIdToken($request->id_token);
            $firebaseClaims = $verifiedToken->claims();
            $email = $firebaseClaims->get('email');

            if (!$email) {
                return back()->withErrors(['email' => 'Google account does not have an email address.']);
            }

            $user = User::where('email', $email)->where('is_active', true)->first();
            if (!$user) {
                return back()->withErrors(['email' => 'No CRM account found for this email. Contact your admin.']);
            }

            $this->loginSecurityService->rememberAttempt($request, (string) $user->email);
            if ($this->loginSecurityService->isLocked($user)) {
                return $this->loginSecurityService->redirectToLock($request, $user, (string) $user->email);
            }

            if (SystemSettings::isMaintenanceMode() && !$user->isAdmin()) {
                return back()->withErrors([
                    'email' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can login.'),
                ]);
            }

            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }

            if ($user->usesEmailOtpLogin() && !$this->emailOtpLoginService->shouldForcePasswordLogin($user)) {
                return back()->withErrors(['email' => 'This account requires email OTP login.']);
            }

            $this->authSessionService->loginUser($request, $user, true);
            $this->loginSecurityService->clearFailures($user);
            $this->emailOtpLoginService->clearPendingSession($request);
            $redirectUrl = $this->authRedirectService->redirectPathFor($user);
            return redirect($redirectUrl);
        } catch (\Exception $e) {
            Log::error('Firebase login failed', ['error' => $e->getMessage()]);
            return back()->withErrors(['email' => 'Google Sign-In failed. Please try again.']);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Revoke all web session tokens from DB to prevent token accumulation
            if (Auth::check()) {
                Auth::user()->tokens()->where('name', 'web-session-token')->delete();
            }

            // Clear all API tokens and any legacy password from session
            $request->session()->forget(['api_token', 'telecaller_api_token', 'sales_executive_api_token', 'user_password_for_change']);
            $this->emailOtpLoginService->clearPendingSession($request);

            // Logout user
            Auth::logout();

            // Invalidate and regenerate session
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (\Exception $e) {
            // Even if there's an error (like expired session), try to logout
            Auth::logout();
        }

        // Always redirect to login page with no-cache headers
        $response = redirect()->route('login')->with('success', 'You have been logged out successfully.');
        
        // Add no-cache headers to prevent browser caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0, private');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 01 Jan 2000 00:00:00 GMT');
        
        return $response;
    }


    private function redirectBasedOnRole($user)
    {
        $url = $this->authRedirectService->redirectPathFor($user);
        return redirect($url);
    }
    
    private function getRedirectUrlForRole($user)
    {
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        $role = $user->role->slug ?? '';
        
        Log::info('Login redirect', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role_slug' => $role,
            'is_sales_head' => $user->isSalesHead(),
        ]);

        // Determine redirect URL based on role
        $redirectUrl = match($role) {
            'telecaller' => route('telecaller.dashboard'),
            'sales_executive' => route('sales-executive.dashboard'),
            'sales_manager' => $user->isSalesHead() 
                ? route('sales-head.dashboard')
                : route('sales-manager.dashboard'),
            'senior_manager' => route('sales-manager.dashboard'),  // Manager role → Sales Manager dashboard
            'assistant_sales_manager' => route('sales-manager.dashboard'),
            'admin' => route('admin.dashboard'),
            'hr_manager' => route('hr-manager.hiring.index'),
            'crm' => route('dashboard'),
            default => '/',
        };
        
        Log::info('Login redirect target', [
            'redirect_url' => $redirectUrl,
        ]);
        
        return $redirectUrl;
    }

    /**
     * Mark telecaller attendance on login
     */
    private function markTelecallerAttendance(User $user): void
    {
        // Update UserAvailability - mark as online
        $availability = UserAvailability::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_online' => false,
                'timezone' => 'Asia/Kolkata',
                'current_day_leads' => 0,
                'is_available' => false,
            ]
        );
        
        $availability->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
        $availability->updateAvailability();

    }
}
