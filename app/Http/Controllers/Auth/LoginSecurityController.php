<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\EmailOtpLoginService;
use App\Services\LoginSecurityService;
use Illuminate\Http\Request;

class LoginSecurityController extends Controller
{
    public function __construct(
        protected LoginSecurityService $loginSecurityService,
        protected EmailOtpLoginService $emailOtpLoginService,
    ) {
    }

    public function lock(Request $request)
    {
        $email = (string) $request->session()->get('login_security.email_normalized', $request->input('email', ''));
        $user = $email !== '' ? $this->emailOtpLoginService->resolveActiveUserByEmail($email) : null;
        $state = $this->loginSecurityService->lockState($user);

        return view('auth.security-lock', [
            'email' => $email,
            'maskedEmail' => $email !== '' ? $this->emailOtpLoginService->maskEmailForDisplay($email) : '',
            'state' => $state,
        ]);
    }

    public function requestAccess(Request $request)
    {
        $event = $this->loginSecurityService->createAccessRequest($request);
        $request->session()->put('login_security.access_request_submitted', true);

        return redirect()
            ->route('login.security-lock')
            ->with('success', 'Urgent access request sent to admin. Please wait for approval.');
    }
}
