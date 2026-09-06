<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileAppDiagnostic;
use Illuminate\Http\Request;

class MobileDiagnosticController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'platform' => 'nullable|string|in:android,ios,windows',
            'app_version_name' => 'nullable|string|max:50',
            'app_version_code' => 'nullable|integer|min:1|max:999999',
            'app_build_label' => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:150',
            'manufacturer' => 'nullable|string|max:80',
            'android_version' => 'nullable|string|max:50',
            'sdk_int' => 'nullable|integer|min:1|max:999',
            'health_status' => 'nullable|string|in:green,yellow,red,unknown',
            'permissions' => 'nullable|array',
            'features' => 'nullable|array',
            'test_results' => 'nullable|array',
            'last_error' => 'nullable|string|max:2000',
        ]);

        $diagnostic = MobileAppDiagnostic::query()->create([
            'user_id' => $request->user()->id,
            'platform' => $validated['platform'] ?? 'android',
            'app_version_name' => $validated['app_version_name'] ?? null,
            'app_version_code' => $validated['app_version_code'] ?? null,
            'app_build_label' => $validated['app_build_label'] ?? null,
            'device_model' => $validated['device_model'] ?? null,
            'manufacturer' => $validated['manufacturer'] ?? null,
            'android_version' => $validated['android_version'] ?? null,
            'sdk_int' => $validated['sdk_int'] ?? null,
            'health_status' => $validated['health_status'] ?? 'unknown',
            'permissions' => $validated['permissions'] ?? [],
            'features' => $validated['features'] ?? [],
            'test_results' => $validated['test_results'] ?? [],
            'last_error' => $validated['last_error'] ?? null,
            'reported_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'diagnostic_id' => $diagnostic->id,
        ]);
    }
}
