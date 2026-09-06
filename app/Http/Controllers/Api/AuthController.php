<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Services\LoginSecurityService;

class AuthController extends Controller
{
    public function __construct(private readonly LoginSecurityService $loginSecurityService)
    {
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|max:64',
        ]);

        $email = $this->loginSecurityService->normalizeEmail((string) $request->email);
        $user = User::whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();
        $this->loginSecurityService->rememberAttempt($request, $email);

        if ($this->loginSecurityService->isLocked($user)) {
            return $this->loginSecurityService->jsonLockedResponse($user);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            $state = $this->loginSecurityService->recordFailure($request, $user, $email, 'api_password');
            if ($state['locked']) {
                return $this->loginSecurityService->jsonLockedResponse($user?->fresh());
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->loginSecurityService->clearFailures($user);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user->load('role'),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->validate([
            'fcm_token' => 'nullable|string',
            'push_endpoint' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $devices = app(\App\Services\NotificationDeviceOwnershipService::class);
        $context = ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()];
        if ($request->filled('fcm_token')) {
            $devices->releaseFcm($user, (string) $request->fcm_token, $context);
        }
        if ($request->filled('push_endpoint')) {
            $devices->releasePush($user, (string) $request->push_endpoint, $context);
        }

        $user->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load('role', 'manager'));
    }
}
