<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendFcmNotificationJob;
use App\Models\FcmToken;
use App\Models\MobileAppInstallation;
use App\Models\SystemSettings;
use App\Models\User;
use App\Models\WhatsAppWebEvent;
use App\Services\FacebookLeadCenterAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\PersonalAccessToken;
use ZipArchive;

class MobileAppUpdateController extends Controller
{
    public function index()
    {
        return $this->renderIndex(false);
    }

    public function extensions()
    {
        $whatsAppExtension = $this->whatsAppExtensionPayload();
        $facebookLeadCenterExtension = $this->facebookLeadCenterExtensionPayload();

        return view('admin.extensions.index', compact('whatsAppExtension', 'facebookLeadCenterExtension'));
    }

    private function renderIndex(bool $extensionsOnly)
    {
        $settings = $this->settingsPayload();
        $installRows = $this->installRows($settings);
        $installStats = $this->installStats($installRows, $settings);
        $whatsAppExtension = $this->whatsAppExtensionPayload();
        $facebookLeadCenterExtension = $this->facebookLeadCenterExtensionPayload();

        return view('admin.mobile-app-update.index', compact('settings', 'installRows', 'installStats', 'whatsAppExtension', 'facebookLeadCenterExtension', 'extensionsOnly'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'version_code' => 'required|integer|min:1|max:999999',
            'version_name' => 'required|string|max:50',
            'apk_url' => 'nullable|url|max:2048',
            'message' => 'required|string|max:500',
            'apk_file' => 'nullable|file|max:120000',
            'force_update' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ]);

        $apkUrl = $validated['apk_url'] ?: SystemSettings::get('mobile_app_update_apk_url', url('/downloads/base-crm-latest.apk'));

        if ($request->hasFile('apk_file')) {
            $file = $request->file('apk_file');
            if (strtolower($file->getClientOriginalExtension()) !== 'apk') {
                return back()->with('error', 'Only .apk file upload allowed.')->withInput();
            }

            $targetDir = public_path('downloads');
            File::ensureDirectoryExists($targetDir);

            $versionSlug = $this->apkVersionSlug($validated['version_name'], (int) $validated['version_code']);
            $versionedFileName = 'base-crm-' . $versionSlug . '.apk';
            $versionedPath = $targetDir . DIRECTORY_SEPARATOR . $versionedFileName;

            $file->move($targetDir, $versionedFileName);
            File::copy($versionedPath, $targetDir . DIRECTORY_SEPARATOR . 'base-crm-latest.apk');

            $apkUrl = url('/downloads/' . $versionedFileName);
            SystemSettings::set('mobile_app_update_versioned_apk_url', url('/downloads/' . $versionedFileName));
        }

        SystemSettings::set('mobile_app_update_version_code', (string) $validated['version_code']);
        SystemSettings::set('mobile_app_update_version_name', $validated['version_name']);
        SystemSettings::set('mobile_app_update_apk_url', $apkUrl);
        SystemSettings::set('mobile_app_update_message', $validated['message']);
        SystemSettings::set('mobile_app_update_force', $request->boolean('force_update') ? '1' : '0');
        if (!$request->boolean('force_update')) {
            SystemSettings::set('mobile_app_update_force_user_ids', '[]');
        }
        SystemSettings::set('mobile_app_update_active', $request->boolean('active') ? '1' : '0');
        SystemSettings::set('mobile_app_update_released_at', now()->toIso8601String());

        return back()->with('success', 'Mobile app update settings saved.');
    }

    public function notifyUsers()
    {
        $settings = $this->settingsPayload();

        if (!$settings['active']) {
            return back()->with('error', 'Update inactive hai. Pehle Active on karo.');
        }

        $queuedCount = $this->queueUpdateNotifications($settings);

        return back()->with('success', 'Update notification queued for ' . $queuedCount . ' Android users.');
    }

    public function quickPublish()
    {
        SystemSettings::set('mobile_app_update_active', '1');
        SystemSettings::set('mobile_app_update_force', '0');
        SystemSettings::set('mobile_app_update_force_user_ids', '[]');
        SystemSettings::set('mobile_app_update_released_at', now()->toIso8601String());

        $settings = $this->settingsPayload();
        $queuedCount = $this->queueUpdateNotifications($settings);

        return back()->with('success', 'Publish done. Force update OFF hai; notification ' . $queuedCount . ' outdated Android users ko bheja gaya.');
    }

    public function notifyUser(User $user)
    {
        $settings = $this->settingsPayload();
        if (!$settings['active']) {
            return back()->with('error', 'Update inactive hai. Pehle Active on karo.');
        }

        if ($this->userHasLatestApp($user, (int) $settings['versionCode'])) {
            return back()->with('error', $user->name . ' ke phone me latest app already open ho chuka hai.');
        }

        $sent = $this->sendUpdateNotificationToUser($user, $settings, false);

        return back()->with($sent ? 'success' : 'error', $sent
            ? $user->name . ' ko update notification bhej diya.'
            : $user->name . ' ke Android FCM token nahi mile.');
    }

    public function forceUser(User $user)
    {
        $settings = $this->settingsPayload();
        if (!$settings['active']) {
            return back()->with('error', 'Update inactive hai. Pehle Active on karo.');
        }

        if ($this->userHasLatestApp($user, (int) $settings['versionCode'])) {
            return back()->with('error', $user->name . ' ke phone me latest app already open ho chuka hai. Force nahi bheja.');
        }

        $forceUserIds = $this->targetedForceUserIds();
        $forceUserIds[] = (int) $user->id;
        SystemSettings::set('mobile_app_update_force_user_ids', json_encode(collect($forceUserIds)->unique()->values()->all()));

        $sent = $this->sendUpdateNotificationToUser($user, $settings, true);

        return back()->with($sent ? 'success' : 'error', $sent
            ? $user->name . ' ke liye force update enable karke notification bhej diya.'
            : $user->name . ' ke liye force update enable hua, lekin Android FCM token nahi mile.');
    }

    public function enableDiagnostics(Request $request, User $user)
    {
        $validated = $request->validate([
            'duration' => 'required|string|in:24h,7d,none',
        ]);

        $flags = $this->diagnosticsFlags();
        $expiresAt = match ($validated['duration']) {
            '24h' => now()->addDay()->toIso8601String(),
            '7d' => now()->addDays(7)->toIso8601String(),
            default => null,
        };

        $flags[(string) $user->id] = [
            'enabled' => true,
            'caller_id' => true,
            'call_sync' => true,
            'recording' => true,
            'attendance' => true,
            'notification' => true,
            'expires_at' => $expiresAt,
            'enabled_at' => now()->toIso8601String(),
            'enabled_by' => auth()->id(),
        ];

        SystemSettings::set('mobile_app_diagnostics_user_flags', json_encode($flags));

        return back()->with('success', 'Diagnostics enabled for ' . $user->name . '.');
    }

    public function disableDiagnostics(User $user)
    {
        $flags = $this->diagnosticsFlags();
        unset($flags[(string) $user->id]);
        SystemSettings::set('mobile_app_diagnostics_user_flags', json_encode($flags));

        return back()->with('success', 'Diagnostics disabled for ' . $user->name . '.');
    }

    public function downloadWhatsAppExtension()
    {
        $extension = $this->whatsAppExtensionPayload();
        if (!$extension['available']) {
            abort(404, 'WhatsApp Web extension not found.');
        }

        $zipDir = storage_path('app/tmp-extension-downloads');
        File::ensureDirectoryExists($zipDir);

        $zipFileName = 'base-crm-whatsapp-web-extension-' . $extension['version'] . '.zip';
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $zipFileName;

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to build extension zip.');
        }

        $basePath = $extension['path'];
        foreach (File::allFiles($basePath) as $file) {
            $relativePath = ltrim(str_replace($basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), 'whatsapp-web-crm/' . str_replace('\\', '/', $relativePath));
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function generateWhatsAppExtensionToken(Request $request)
    {
        $user = $request->user();

        $user->tokens()->where('name', 'whatsapp-web-extension-token')->delete();
        $token = $user->createToken('whatsapp-web-extension-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'crm_base_url' => url('/'),
            'message' => 'Extension token generated successfully.',
        ]);
    }

    public function testWhatsAppExtensionConnection(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $accessToken = PersonalAccessToken::findToken($validated['token']);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Sanctum token.',
            ], 422);
        }

        $user = $accessToken->tokenable;
        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Token user is not active.',
            ], 422);
        }

        $eligible = $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();

        if (!$eligible) {
            return response()->json([
                'success' => false,
                'message' => 'This token user cannot use WhatsApp Web extension.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connection successful. Token is valid for WhatsApp Web extension.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role?->name,
            ],
            'crm_base_url' => url('/'),
            'lookup_endpoint' => url('/api/integrations/whatsapp-web/lookup'),
            'process_endpoint' => url('/api/integrations/whatsapp-web/process'),
        ]);
    }

    public function downloadFacebookLeadCenterExtension()
    {
        $extension = $this->facebookLeadCenterExtensionPayload();
        if (!$extension['available']) {
            abort(404, 'Facebook Lead Center extension not found.');
        }

        $zipDir = storage_path('app/tmp-extension-downloads');
        File::ensureDirectoryExists($zipDir);

        $zipFileName = 'base-crm-facebook-lead-center-extension-' . $extension['version'] . '.zip';
        $zipPath = $zipDir . DIRECTORY_SEPARATOR . $zipFileName;

        if (File::exists($zipPath)) {
            File::delete($zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Unable to build extension zip.');
        }

        $basePath = $extension['path'];
        foreach (File::allFiles($basePath) as $file) {
            $relativePath = ltrim(str_replace($basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $zip->addFile($file->getPathname(), 'facebook-lead-center-crm/' . str_replace('\\', '/', $relativePath));
        }

        $zip->close();

        return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    public function generateFacebookLeadCenterExtensionToken(Request $request)
    {
        $user = $request->user();

        $user->tokens()->where('name', 'facebook-lead-center-extension-token')->delete();
        $token = $user->createToken('facebook-lead-center-extension-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'crm_base_url' => url('/'),
            'message' => 'Facebook Lead Center extension token generated successfully.',
        ]);
    }

    public function testFacebookLeadCenterExtensionConnection(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
        ]);

        $accessToken = PersonalAccessToken::findToken($validated['token']);
        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Sanctum token.',
            ], 422);
        }

        $user = $accessToken->tokenable;
        if (!$user || !$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Token user is not active.',
            ], 422);
        }

        $eligible = $user->isAdmin()
            || $user->isCrm()
            || $user->isSalesManager()
            || $user->isSeniorManager()
            || $user->isAssistantSalesManager()
            || $user->isSalesExecutive();

        if (!$eligible) {
            return response()->json([
                'success' => false,
                'message' => 'This token user cannot use Facebook Lead Center extension.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connection successful. Token is valid for Facebook Lead Center extension.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role?->name,
            ],
            'crm_base_url' => url('/'),
            'compare_endpoint' => url('/api/integrations/facebook-lead-center/compare'),
            'import_endpoint' => url('/api/integrations/facebook-lead-center/import-selected'),
        ]);
    }

    private function settingsPayload(): array
    {
        return [
            'versionCode' => (int) SystemSettings::get('mobile_app_update_version_code', '2'),
            'versionName' => SystemSettings::get('mobile_app_update_version_name', '1.0.1'),
            'apkUrl' => SystemSettings::get('mobile_app_update_apk_url', url('/downloads/base-crm-latest.apk')),
            'latestApkUrl' => url('/downloads/base-crm-latest.apk'),
            'message' => SystemSettings::get('mobile_app_update_message', 'New Base CRM app update is available. Please update for latest punch popup and notification improvements.'),
            'forceUpdate' => SystemSettings::get('mobile_app_update_force', '0') === '1',
            'targetedForceUserIds' => $this->targetedForceUserIds(),
            'active' => SystemSettings::get('mobile_app_update_active', '0') === '1',
            'releasedAt' => SystemSettings::get('mobile_app_update_released_at'),
            'versionedApkUrl' => SystemSettings::get('mobile_app_update_versioned_apk_url'),
        ];
    }

    private function apkVersionSlug(string $versionName, int $versionCode): string
    {
        $slug = strtolower(trim($versionName));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?: 'version';
        $slug = trim($slug, '-');

        return $slug . '-' . $versionCode;
    }

    private function queueUpdateNotifications(array $settings): int
    {
        $targetVersionCode = (int) $settings['versionCode'];
        $updatedUserIds = MobileAppInstallation::query()
            ->where('platform', 'android')
            ->where('installed_version_code', '>=', $targetVersionCode)
            ->pluck('user_id')
            ->all();

        $userIds = FcmToken::query()
            ->where('device_type', 'android')
            ->when(!empty($updatedUserIds), fn ($query) => $query->whereNotIn('user_id', $updatedUserIds))
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            SendFcmNotificationJob::dispatchSync(
                (int) $userId,
                'Base CRM Update Available',
                $settings['message'],
                $settings['apkUrl'],
                'mobile-app-update-' . $settings['versionCode'] . '-' . $userId,
                [
                    'kind' => 'mobile_app_update',
                    'popup_type' => 'mobile_update',
                    'version_code' => $settings['versionCode'],
                    'version_name' => $settings['versionName'],
                    'primary_action_label' => 'Update Now',
                    'primary_action_url' => $settings['apkUrl'],
                    'secondary_action_label' => 'Open CRM',
                    'secondary_action_url' => url('/dashboard'),
                ]
            );
        }

        return $userIds->count();
    }

    private function sendUpdateNotificationToUser(User $user, array $settings, bool $force): bool
    {
        $hasToken = FcmToken::query()
            ->where('device_type', 'android')
            ->where('user_id', $user->id)
            ->exists();

        if (!$hasToken) {
            return false;
        }

        SendFcmNotificationJob::dispatchSync(
            (int) $user->id,
            $force ? 'Base CRM Update Required' : 'Base CRM Update Available',
            $settings['message'],
            $settings['apkUrl'],
            'mobile-app-update-' . $settings['versionCode'] . '-' . $user->id . ($force ? '-force' : ''),
            [
                'kind' => 'mobile_app_update',
                'popup_type' => 'mobile_update',
                'version_code' => $settings['versionCode'],
                'version_name' => $settings['versionName'],
                'force_update' => $force ? '1' : '0',
                'primary_action_label' => 'Update Now',
                'primary_action_url' => $settings['apkUrl'],
                'secondary_action_label' => 'Open CRM',
                'secondary_action_url' => url('/dashboard'),
            ]
        );

        return true;
    }

    private function userHasLatestApp(User $user, int $targetVersionCode): bool
    {
        $installation = $user->mobileAppInstallation;
        return $installation && (int) $installation->installed_version_code >= $targetVersionCode;
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

    private function installRows(array $settings)
    {
        $diagnosticsFlags = $this->diagnosticsFlags();

        return User::query()
            ->with(['role', 'mobileAppInstallation', 'latestMobileAppDiagnostic'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($settings, $diagnosticsFlags) {
                $installation = $user->mobileAppInstallation;
                $diagnostic = $user->latestMobileAppDiagnostic;
                $installedVersionCode = (int) ($installation?->installed_version_code ?? 0);
                $targetVersionCode = (int) $settings['versionCode'];
                $hasInstalled = $installedVersionCode > 0;
                $isUpdated = $hasInstalled && $installedVersionCode >= $targetVersionCode;
                $downloadedTarget = $installation
                    && (int) $installation->last_download_version_code >= $targetVersionCode;
                $diagnostics = $diagnosticsFlags[(string) $user->id] ?? null;
                $diagnosticsExpiresAt = $diagnostics['expires_at'] ?? null;
                $diagnosticsEnabled = is_array($diagnostics)
                    && (bool) ($diagnostics['enabled'] ?? false)
                    && (!$diagnosticsExpiresAt || now()->lessThan(\Illuminate\Support\Carbon::parse($diagnosticsExpiresAt)));

                $status = match (true) {
                    $isUpdated => 'Updated',
                    $downloadedTarget => 'Downloaded, not opened',
                    $hasInstalled => 'Old version',
                    default => 'Never opened app',
                };

                return [
                    'user' => $user,
                    'installation' => $installation,
                    'status' => $status,
                    'isUpdated' => $isUpdated,
                    'downloadedTarget' => $downloadedTarget,
                    'hasInstalled' => $hasInstalled,
                    'diagnostic' => $diagnostic,
                    'diagnosticsEnabled' => $diagnosticsEnabled,
                    'diagnosticsExpiresAt' => $diagnosticsExpiresAt,
                ];
            });
    }

    private function diagnosticsFlags(): array
    {
        $decoded = json_decode((string) SystemSettings::get('mobile_app_diagnostics_user_flags', '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function installStats($installRows, array $settings): array
    {
        return [
            'trackedUsers' => $installRows->count(),
            'updated' => $installRows->where('isUpdated', true)->count(),
            'oldVersion' => $installRows->filter(fn ($row) => $row['hasInstalled'] && !$row['isUpdated'])->count(),
            'downloadedNotOpened' => $installRows->where('downloadedTarget', true)->where('isUpdated', false)->count(),
            'neverOpened' => $installRows->where('hasInstalled', false)->count(),
            'targetVersion' => $settings['versionName'] . '+' . $settings['versionCode'],
        ];
    }

    private function whatsAppExtensionPayload(): array
    {
        $path = base_path('extensions/whatsapp-web-crm');
        $manifestPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!File::isDirectory($path) || !File::exists($manifestPath)) {
            return [
                'available' => false,
                'path' => $path,
                'version' => '0.0.0',
                'name' => 'WhatsApp Web CRM Assistant',
                'updatedAt' => null,
                'downloadRoute' => null,
                'crmBaseUrl' => url('/'),
            ];
        }

        $manifest = json_decode(File::get($manifestPath), true) ?: [];
        $updatedAt = collect(File::allFiles($path))
            ->map(fn ($file) => $file->getMTime())
            ->max();

        return [
            'available' => true,
            'path' => $path,
            'version' => (string) Arr::get($manifest, 'version', '0.1.0'),
            'name' => (string) Arr::get($manifest, 'name', 'WhatsApp Web CRM Assistant'),
            'updatedAt' => $updatedAt ? now()->createFromTimestamp($updatedAt) : null,
            'downloadRoute' => route('admin.mobile-app-update.whatsapp-extension.download'),
            'crmBaseUrl' => url('/'),
            'stats' => $this->whatsAppExtensionStatsPayload(),
        ];
    }

    private function whatsAppExtensionStatsPayload(): array
    {
        $todayStart = now()->startOfDay();
        $eventsQuery = WhatsAppWebEvent::query()->with('lead')->latest('processed_at')->latest('id');

        $todayQuery = (clone $eventsQuery)->where(function ($query) use ($todayStart) {
            $query->where('processed_at', '>=', $todayStart)
                ->orWhere('created_at', '>=', $todayStart);
        });

        $recentEvents = (clone $eventsQuery)
            ->limit(5)
            ->get()
            ->map(function (WhatsAppWebEvent $event) {
                return [
                    'phone' => $event->phone,
                    'contact_name' => $event->contact_name,
                    'decision' => $event->decision,
                    'lead_name' => $event->lead?->name,
                    'lead_id' => $event->lead_id,
                    'processed_at' => $event->processed_at,
                    'message_preview' => $event->message_preview,
                ];
            })
            ->values();

        $lastEvent = $recentEvents->first();

        return [
            'today_total' => (clone $todayQuery)->count(),
            'today_new_leads' => (clone $todayQuery)->where('decision', 'new_lead')->count(),
            'today_reenquiries' => (clone $todayQuery)->where('decision', 'reenquiry')->count(),
            'today_errors' => (clone $todayQuery)->where('decision', 'error')->count(),
            'processed_total' => WhatsAppWebEvent::query()->whereNotNull('processed_at')->count(),
            'last_processed_at' => $lastEvent['processed_at'] ?? null,
            'recent_events' => $recentEvents,
        ];
    }

    private function facebookLeadCenterExtensionPayload(): array
    {
        $path = base_path('extensions/facebook-lead-center-crm');
        $manifestPath = $path . DIRECTORY_SEPARATOR . 'manifest.json';

        if (!File::isDirectory($path) || !File::exists($manifestPath)) {
            return [
                'available' => false,
                'path' => $path,
                'version' => '0.0.0',
                'name' => 'Facebook Lead Center CRM Audit',
                'updatedAt' => null,
                'downloadRoute' => null,
                'crmBaseUrl' => url('/'),
                'stats' => app(FacebookLeadCenterAuditService::class)->statsPayload(),
            ];
        }

        $manifest = json_decode(File::get($manifestPath), true) ?: [];
        $updatedAt = collect(File::allFiles($path))
            ->map(fn ($file) => $file->getMTime())
            ->max();

        return [
            'available' => true,
            'path' => $path,
            'version' => (string) Arr::get($manifest, 'version', '0.1.0'),
            'name' => (string) Arr::get($manifest, 'name', 'Facebook Lead Center CRM Audit'),
            'updatedAt' => $updatedAt ? now()->createFromTimestamp($updatedAt) : null,
            'downloadRoute' => route('admin.mobile-app-update.facebook-lead-center-extension.download'),
            'crmBaseUrl' => url('/'),
            'stats' => app(FacebookLeadCenterAuditService::class)->statsPayload(),
        ];
    }
}
