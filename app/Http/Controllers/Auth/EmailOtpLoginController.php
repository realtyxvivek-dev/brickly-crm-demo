<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SystemSettings;
use App\Services\AuthRedirectService;
use App\Services\AuthSessionService;
use App\Services\EmailOtpLoginService;
use App\Services\LoginSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailOtpLoginController extends Controller
{
    public function __construct(
        protected EmailOtpLoginService $emailOtpLoginService,
        protected AuthSessionService $authSessionService,
        protected AuthRedirectService $authRedirectService,
        protected LoginSecurityService $loginSecurityService,
    ) {
    }

    public function resolveEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->emailOtpLoginService->resolveActiveUserByEmail((string) $request->input('email'));
        $this->loginSecurityService->rememberAttempt($request, (string) $request->input('email'));
        if ($this->loginSecurityService->isLocked($user)) {
            return $this->loginSecurityService->redirectToLock($request, $user, (string) $request->input('email'));
        }

        $payload = $this->emailOtpLoginService->resolveLoginFlow($request, (string) $request->input('email'));
        $this->emailOtpLoginService->setPendingSession($request, $payload);

        return redirect()->route('login')
            ->with('success', $payload['method'] === 'otp_email'
                ? 'If your account is eligible for email OTP login, a code has been sent to your email.'
                : 'Continue with your password to sign in.');
    }

    public function sendOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = $this->emailOtpLoginService->resolveActiveUserByEmail((string) $request->input('email'));
        $this->loginSecurityService->rememberAttempt($request, (string) $request->input('email'));
        if ($this->loginSecurityService->isLocked($user)) {
            return $this->loginSecurityService->redirectToLock($request, $user, (string) $request->input('email'));
        }

        $payload = $this->emailOtpLoginService->sendOtp($request, (string) $request->input('email'));
        $this->emailOtpLoginService->setPendingSession($request, $payload);

        return redirect()->route('login')
            ->with('success', 'If your account is eligible for email OTP login, a code has been sent to your email.');
    }

    public function verifyOtp(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if (!$request->session()->get('auth_flow.pending') || $request->session()->get('auth_flow.method') !== 'otp_email') {
            $this->emailOtpLoginService->clearPendingSession($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'OTP session expired. Please request a new code.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()->route('login');
        }

        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $sessionRequestId = (string) $request->session()->get('auth_flow.request_id', '');
        $sessionEmail = (string) $request->session()->get('auth_flow.email_normalized', '');
        $sessionUser = $this->emailOtpLoginService->resolveActiveUserByEmail($sessionEmail);
        $this->loginSecurityService->rememberAttempt($request, $sessionEmail);

        if ($this->loginSecurityService->isLocked($sessionUser)) {
            if ($request->expectsJson()) {
                return $this->loginSecurityService->jsonLockedResponse($sessionUser);
            }

            return $this->loginSecurityService->redirectToLock($request, $sessionUser, $sessionEmail);
        }

        $user = $this->emailOtpLoginService->verifyOtp(
            $request,
            $sessionRequestId,
            $sessionEmail,
            (string) $request->input('otp')
        );

        if (!$user) {
            $state = $this->loginSecurityService->recordFailure($request, $sessionUser, $sessionEmail, 'otp');
            if ($state['locked']) {
                if ($request->expectsJson()) {
                    return $this->loginSecurityService->jsonLockedResponse($sessionUser?->fresh());
                }

                return $this->loginSecurityService->redirectToLock($request, $sessionUser, $sessionEmail);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The OTP could not be verified. Please try again or request a new code.',
                    'errors' => [
                        'otp' => ['The OTP could not be verified. Please try again or request a new code.'],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()->route('login')
                ->withErrors([
                    'otp' => 'The OTP could not be verified. Please try again or request a new code.',
                ]);
        }

        if (SystemSettings::isMaintenanceMode() && !$user->isAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can login during maintenance mode.'),
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('login')
                ->withErrors([
                    'email' => SystemSettings::get('maintenance_message', 'System is under maintenance. Only admin can login during maintenance mode.'),
                ])
                ->with('maintenance_mode', true);
        }

        $this->authSessionService->loginUser($request, $user, true);
        $this->loginSecurityService->clearFailures($user);
        $this->emailOtpLoginService->clearPendingSession($request);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Login successful.',
                'redirect' => $this->authRedirectService->redirectPathFor($user),
            ]);
        }

        return redirect($this->authRedirectService->redirectPathFor($user));
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        if (!$request->session()->get('auth_flow.pending') || $request->session()->get('auth_flow.method') !== 'otp_email') {
            $this->emailOtpLoginService->clearPendingSession($request);

            return redirect()->route('login');
        }

        $requestId = (string) $request->session()->get('auth_flow.request_id', '');
        $email = (string) $request->session()->get('auth_flow.email_normalized', '');

        $payload = $this->emailOtpLoginService->resendOtp($request, $requestId, $email);
        $this->emailOtpLoginService->setPendingSession($request, $payload);

        return redirect()->route('login')
            ->with('success', 'If your account is eligible for email OTP login, a fresh code has been sent to your email.');
    }

    public function resetOtpState(Request $request): RedirectResponse
    {
        $this->emailOtpLoginService->clearPendingSession($request);

        return redirect()->route('login');
    }
}
