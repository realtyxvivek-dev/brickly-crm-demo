<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationDeviceOwnershipService;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    private NotificationDeviceOwnershipService $devices;

    public function __construct(?NotificationDeviceOwnershipService $devices = null)
    {
        $this->devices = $devices ?? app(NotificationDeviceOwnershipService::class);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
            'device_type' => 'sometimes|in:web,android,ios',
        ]);

        $user = $request->user();

        $this->devices->claimFcm($user, $request->fcm_token, $request->device_type ?? 'web', $this->context($request));

        return response()->json(['success' => true, 'message' => 'FCM token saved.']);
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        $this->devices->releaseFcm($request->user(), $request->fcm_token, $this->context($request));

        return response()->json(['success' => true, 'message' => 'FCM token removed.']);
    }

    private function context(Request $request): array
    {
        return ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()];
    }
}
