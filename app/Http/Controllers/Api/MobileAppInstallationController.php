<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAppInstallation;
use Illuminate\Http\Request;

class MobileAppInstallationController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:android,ios,windows',
            'version_code' => 'required|integer|min:1|max:999999',
            'version_name' => 'nullable|string|max:50',
            'fcm_token' => 'nullable|string',
            'app_build_label' => 'nullable|string|max:100',
            'device_label' => 'nullable|string|max:150',
        ]);

        $installation = MobileAppInstallation::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? 'android',
            ],
            [
                'installed_version_code' => $validated['version_code'],
                'installed_version_name' => $validated['version_name'] ?? null,
                'fcm_token' => $validated['fcm_token'] ?? null,
                'app_build_label' => $validated['app_build_label'] ?? null,
                'device_label' => $validated['device_label'] ?? null,
                'last_opened_at' => now(),
                'last_reported_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'installation_id' => $installation->id,
        ]);
    }

    public function downloadClicked(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:android,ios,windows',
            'version_code' => 'required|integer|min:1|max:999999',
            'version_name' => 'nullable|string|max:50',
            'fcm_token' => 'nullable|string',
        ]);

        $platform = $validated['platform'] ?? 'android';
        $installation = MobileAppInstallation::query()->firstOrNew([
            'user_id' => $request->user()->id,
            'platform' => $platform,
        ]);

        $installation->fill([
            'last_download_version_code' => $validated['version_code'],
            'last_download_version_name' => $validated['version_name'] ?? null,
            'fcm_token' => $validated['fcm_token'] ?? $installation->fcm_token,
            'last_download_clicked_at' => now(),
        ]);
        $installation->download_click_count = ((int) $installation->download_click_count) + 1;
        $installation->save();

        return response()->json([
            'success' => true,
            'installation_id' => $installation->id,
        ]);
    }
}
