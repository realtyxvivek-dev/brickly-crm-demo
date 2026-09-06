<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

class FirebaseAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        try {
            $factory = (new Factory)->withServiceAccount(config('firebase.credentials'));
            $auth = $factory->createAuth();
            $verifiedToken = $auth->verifyIdToken($request->id_token);
            $claims = $verifiedToken->claims();
            $email = $claims->get('email');

            if (!$email) {
                return response()->json([
                    'success' => false,
                    'message' => 'Google account does not have an email address.',
                ], 422);
            }

            $user = User::where('email', $email)->where('is_active', true)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No CRM account found for this email. Contact your admin.',
                ], 404);
            }

            if (!$user->relationLoaded('role')) {
                $user->load('role');
            }

            $token = $user->createToken('firebase-login')->plainTextToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->slug ?? '',
                    'role_name' => $user->role->name ?? '',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Firebase API login failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Google Sign-In verification failed.',
            ], 401);
        }
    }
}
