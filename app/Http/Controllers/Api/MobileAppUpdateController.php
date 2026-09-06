<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemSettings;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Http\Request;

class MobileAppUpdateController extends Controller
{
    public function show(Request $request)
    {
        $userId = $this->resolveUserIdFromBearer($request);
        $targetedForceUserIds = $this->targetedForceUserIds();
        $forceUpdate = SystemSettings::get('mobile_app_update_force', '0') === '1';
        if (!$forceUpdate && $userId !== null) {
            $forceUpdate = in_array($userId, $targetedForceUserIds, true);
        }

        return response()->json([
            'versionCode' => (int) SystemSettings::get('mobile_app_update_version_code', '1'),
            'versionName' => SystemSettings::get('mobile_app_update_version_name', '1.0.0'),
            'apkUrl' => SystemSettings::get('mobile_app_update_apk_url', url('/downloads/base-crm-latest.apk')),
            'message' => SystemSettings::get('mobile_app_update_message', 'New Base CRM app update is available.'),
            'forceUpdate' => $forceUpdate,
            'active' => SystemSettings::get('mobile_app_update_active', '0') === '1',
            'releasedAt' => SystemSettings::get('mobile_app_update_released_at'),
        ]);
    }

    private function resolveUserIdFromBearer(Request $request): ?int
    {
        $bearer = $request->bearerToken();
        if (!$bearer) {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearer);
        $user = $token?->tokenable;

        return $user ? (int) $user->id : null;
    }

    private function targetedForceUserIds(): array
    {
        $raw = (string) SystemSettings::get('mobile_app_update_force_user_ids', '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $decoded = explode(',', $raw);
        }

        return collect($decoded)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
