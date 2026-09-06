<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationDeviceOwnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PushSubscriptionController extends Controller
{
    private NotificationDeviceOwnershipService $devices;

    public function __construct(?NotificationDeviceOwnershipService $devices = null)
    {
        $this->devices = $devices ?? app(NotificationDeviceOwnershipService::class);
    }
    /**
     * Store or update push subscription for the authenticated user (PWA Web Push).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'endpoint' => 'required|string|max:500',
            'keys' => 'nullable|array',
            'keys.p256dh' => 'nullable|string',
            'keys.auth' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $endpoint = $request->input('endpoint');
        $keys = $request->input('keys', []);
        $userAgent = $request->userAgent();

        try {
            $this->devices->claimPush($user, $endpoint, $keys, [
                'ip_address' => $request->ip(),
                'user_agent' => $userAgent,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), "doesn't exist") || $e->getCode() === '42S02') {
                return response()->json([
                    'success' => false,
                    'message' => 'Push subscriptions table is missing. Run database/push_subscriptions_table.sql in phpMyAdmin.',
                ], 503);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Push subscription saved.',
        ]);
    }

    /**
     * Remove push subscription by endpoint for the authenticated user.
     */
    public function destroy(Request $request): JsonResponse
    {
        $endpoint = $request->input('endpoint');
        if (!$endpoint) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint is required',
            ], 422);
        }

        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $this->devices->releasePush($user, $endpoint, [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), "doesn't exist") || $e->getCode() === '42S02') {
                return response()->json([
                    'success' => false,
                    'message' => 'Push subscriptions table is missing.',
                ], 503);
            }
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Push subscription removed.',
        ]);
    }
}
