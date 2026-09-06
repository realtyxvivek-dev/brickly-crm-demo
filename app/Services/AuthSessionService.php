<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthSessionService
{
    public function loginUser(Request $request, User $user, bool $remember = true): void
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        if ($user->isTelecaller()) {
            app(UserAvailabilityService::class)->setOnline($user->id, true);
        }

        $token = $user->createToken('web-session-token')->plainTextToken;
        $request->session()->put('api_token', $token);

        if ($user->isTelecaller()) {
            $request->session()->put('telecaller_api_token', $token);
        }

        if ($user->isSalesExecutive()) {
            $request->session()->put('sales_executive_api_token', $token);
        }

        $request->session()->regenerateToken();
        $request->session()->save();

        if (!Auth::check()) {
            Log::error('User not authenticated after session login', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            throw new \RuntimeException('Authentication failed after session login.');
        }
    }
}
