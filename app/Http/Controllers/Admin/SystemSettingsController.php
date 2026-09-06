<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSettings;
use App\Models\User;
use App\Services\DashboardKpiModeService;
use App\Services\MetaReviewAutoStageService;
use App\Services\MetaReviewStageService;
use App\Services\MailSettingsService;
use App\Services\NotificationQuietHoursService;
use App\Services\NotificationSoundService;
use App\Support\AppUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use ZipArchive;

class SystemSettingsController extends Controller
{
    private const PUBLIC_PAGE_DEFAULT_FIELDS = [
        'hero_image',
        'builder_logo',
        'floor_plan_image',
        'amenities_image',
        'media_preview',
        'gallery_image',
    ];

    /**
     * Display system settings page
     */
    public function index()
    {
        $maintenanceMode = SystemSettings::isMaintenanceMode();
        $maintenanceMessage = SystemSettings::get('maintenance_message');
        $sendWelcomeEmailToNewUser = filter_var(SystemSettings::get('send_welcome_email_to_new_user', '1'), FILTER_VALIDATE_BOOLEAN);
        $notifyAdminOnNewUser = filter_var(SystemSettings::get('notify_admin_on_new_user', '1'), FILTER_VALIDATE_BOOLEAN);
        $notificationQuietHoursEnabled = SystemSettings::get(NotificationQuietHoursService::ENABLED_KEY, NotificationQuietHoursService::DEFAULT_ENABLED) === '1';
        $notificationQuietHoursStart = SystemSettings::get(NotificationQuietHoursService::START_KEY, NotificationQuietHoursService::DEFAULT_START);
        $notificationQuietHoursEnd = SystemSettings::get(NotificationQuietHoursService::END_KEY, NotificationQuietHoursService::DEFAULT_END);
        $notificationSoundSettings = app(NotificationSoundService::class)->settingsPayload();
        $mailSettings = app(MailSettingsService::class)->payload(includePasswordState: true);
        $metaReviewOwner = SystemSettings::get('meta_review_owner', 'crm');
        $metaAutoMapping = app(MetaReviewAutoStageService::class)->getMapping();
        $metaStageOptions = app(MetaReviewStageService::class)->labels();
        $dashboardKpiModeService = app(DashboardKpiModeService::class);
        $dashboardKpiSettings = $dashboardKpiModeService->getSettingsPayload();
        $dashboardKpiRoles = $dashboardKpiModeService->getRelevantRoles();
        $dashboardKpiUsers = $dashboardKpiModeService->getRelevantUsers();
        $dashboardKpiEffectiveUserModes = $dashboardKpiModeService->getEffectiveModeMapForUsers($dashboardKpiUsers);
        $publicPageDefaultAssets = $this->getPublicPageDefaultAssetsPayload();
        $travelTimeSettings = $this->getTravelTimeSettingsPayload();

        return view('admin.system-settings.index', compact(
            'maintenanceMode',
            'maintenanceMessage',
            'sendWelcomeEmailToNewUser',
            'notifyAdminOnNewUser',
            'notificationQuietHoursEnabled',
            'notificationQuietHoursStart',
            'notificationQuietHoursEnd',
            'notificationSoundSettings',
            'mailSettings',
            'metaReviewOwner',
            'metaAutoMapping',
            'metaStageOptions',
            'dashboardKpiSettings',
            'dashboardKpiRoles',
            'dashboardKpiUsers',
            'dashboardKpiEffectiveUserModes',
            'publicPageDefaultAssets',
            'travelTimeSettings'
        ));
    }
    
    /**
     * Toggle maintenance mode
     */
    public function toggleMaintenanceMode(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500'
        ]);
        
        try {
            if ($request->enabled) {
                SystemSettings::enableMaintenanceMode($request->message);
                return response()->json([
                    'success' => true,
                    'message' => 'Maintenance mode enabled. All users have been logged out.',
                    'maintenance_mode' => true
                ]);
            } else {
                SystemSettings::disableMaintenanceMode();
                return response()->json([
                    'success' => true,
                    'message' => 'Maintenance mode disabled.',
                    'maintenance_mode' => false
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error toggling maintenance mode: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user notification settings (welcome email + notify admin on new user)
     */
    public function updateUserNotificationSettings(Request $request)
    {
        $request->validate([
            'send_welcome_email_to_new_user' => 'required|boolean',
            'notify_admin_on_new_user' => 'required|boolean',
            'notification_quiet_hours_enabled' => 'required|boolean',
            'notification_quiet_hours_start' => 'required|date_format:H:i',
            'notification_quiet_hours_end' => 'required|date_format:H:i',
        ]);

        try {
            SystemSettings::set('send_welcome_email_to_new_user', $request->send_welcome_email_to_new_user ? '1' : '0');
            SystemSettings::set('notify_admin_on_new_user', $request->notify_admin_on_new_user ? '1' : '0');
            SystemSettings::set(NotificationQuietHoursService::ENABLED_KEY, $request->notification_quiet_hours_enabled ? '1' : '0');
            SystemSettings::set(NotificationQuietHoursService::START_KEY, $request->string('notification_quiet_hours_start')->value());
            SystemSettings::set(NotificationQuietHoursService::END_KEY, $request->string('notification_quiet_hours_end')->value());

            return response()->json([
                'success' => true,
                'message' => 'User notification settings updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating user notification settings: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function uploadNotificationSound(Request $request, NotificationSoundService $soundService)
    {
        $allowedKeys = array_keys(NotificationSoundService::CATEGORIES);

        $request->validate([
            'sound_key' => ['required', 'string', Rule::in($allowedKeys)],
            'sound_file' => ['required', 'file', 'mimes:mp3,wav,ogg', 'max:3072'],
        ]);

        try {
            $key = $request->string('sound_key')->value();
            $path = $request->file('sound_file')->store('notification-sounds', 'public');
            $soundService->saveSound($key, $path);

            return response()->json([
                'success' => true,
                'message' => 'Notification sound updated successfully.',
                'sounds' => $soundService->settingsPayload(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error uploading notification sound: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function resetNotificationSound(Request $request, NotificationSoundService $soundService)
    {
        $allowedKeys = array_keys(NotificationSoundService::CATEGORIES);

        $request->validate([
            'sound_key' => ['required', 'string', Rule::in($allowedKeys)],
        ]);

        try {
            $soundService->resetSound($request->string('sound_key')->value());

            return response()->json([
                'success' => true,
                'message' => 'Notification sound reset successfully.',
                'sounds' => $soundService->settingsPayload(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error resetting notification sound: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function applyDefaultNotificationSound(NotificationSoundService $soundService)
    {
        try {
            $soundService->applyDefaultToAll();

            return response()->json([
                'success' => true,
                'message' => 'Default sound applied to all notification types.',
                'sounds' => $soundService->settingsPayload(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error applying default notification sound: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateMailSettings(Request $request, MailSettingsService $mailSettingsService)
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mailer' => ['required', 'string', Rule::in(['smtp'])],
            'host' => ['required_if:enabled,1', 'nullable', 'string', 'max:255'],
            'port' => ['required_if:enabled,1', 'nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', 'string', Rule::in(['', 'tls', 'ssl'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:500'],
            'from_address' => ['required_if:enabled,1', 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $mailSettingsService->update($validated);
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'SMTP settings saved successfully.',
                'settings' => $mailSettingsService->payload(includePasswordState: true),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating SMTP settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function testMailSettings(Request $request, MailSettingsService $mailSettingsService)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $mailSettingsService->apply();
            Mail::purge('smtp');

            $appName = config('app.name');
            Mail::mailer('smtp')->raw('CRM SMTP test email sent at ' . now()->format('d M Y h:i A'), function ($message) use ($request, $appName) {
                $message->to($request->email)
                    ->subject('[' . $appName . '] SMTP Test Email');
            });

            return response()->json([
                'success' => true,
                'message' => 'SMTP test email sent to ' . $request->email . '.',
            ]);
        } catch (\Throwable $e) {
            Log::error('SMTP test email failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_class' => get_class($e),
            ], 500);
        }
    }

    public function updateMetaReviewSettings(Request $request)
    {
        $request->validate([
            'meta_review_owner' => 'required|in:crm,sales_team',
        ]);

        try {
            SystemSettings::set('meta_review_owner', $request->string('meta_review_owner')->value());

            return response()->json([
                'success' => true,
                'message' => 'Meta review settings updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Meta review settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateMetaReviewMapping(Request $request, MetaReviewStageService $stageService)
    {
        $options = $stageService->options();

        $request->validate([
            'cnp_stage' => ['required', Rule::in($options)],
            'not_interested_stage' => ['required', Rule::in($options)],
            'interested_stage' => ['required', Rule::in($options)],
            'fresh_follow_up_stage' => ['required', Rule::in($options)],
            'follow_up_after_interested_stage' => ['required', Rule::in($options)],
            'visit_scheduled_stage' => ['required', Rule::in($options)],
        ]);

        try {
            $payload = [
                'cnp_stage' => $request->string('cnp_stage')->value(),
                'not_interested_stage' => $request->string('not_interested_stage')->value(),
                'interested_stage' => $request->string('interested_stage')->value(),
                'fresh_follow_up_stage' => $request->string('fresh_follow_up_stage')->value(),
                'follow_up_after_interested_stage' => $request->string('follow_up_after_interested_stage')->value(),
                'visit_scheduled_stage' => $request->string('visit_scheduled_stage')->value(),
            ];

            SystemSettings::set('meta_auto_mapping', json_encode($payload));

            return response()->json([
                'success' => true,
                'message' => 'Meta conversion mapping updated successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Meta review mapping: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateDashboardKpiSettings(Request $request, DashboardKpiModeService $dashboardKpiModeService)
    {
        $allowedModes = array_merge($dashboardKpiModeService->getSupportedModes(), ['inherit', '']);
        $allowedRoleKeys = $dashboardKpiModeService->getSupportedRoleSlugs();

        $request->validate([
            'global_mode' => ['required', Rule::in($dashboardKpiModeService->getSupportedModes())],
            'role_modes' => ['nullable', 'array'],
            'role_modes.*' => ['nullable', 'string', Rule::in($allowedModes)],
            'user_modes' => ['nullable', 'array'],
            'user_modes.*' => ['nullable', 'string', Rule::in($allowedModes)],
        ]);

        try {
            $roleModes = collect($request->input('role_modes', []))
                ->filter(function ($value, $key) use ($allowedRoleKeys) {
                    return in_array((string) $key, $allowedRoleKeys, true);
                })
                ->mapWithKeys(function ($value, $key) {
                    return [(string) $key => $value];
                })
                ->all();

            $userModes = collect($request->input('user_modes', []))
                ->filter(function ($value, $key) {
                    return User::whereKey($key)->exists();
                })
                ->mapWithKeys(function ($value, $key) {
                    return [(string) $key => $value];
                })
                ->all();

            $saved = $dashboardKpiModeService->saveSettings(
                $request->string('global_mode')->value(),
                $roleModes,
                $userModes
            );

            return response()->json([
                'success' => true,
                'message' => 'Dashboard KPI settings updated successfully.',
                'settings' => $saved,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating dashboard KPI settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updatePublicPageDefaults(Request $request)
    {
        $request->validate([
            'hero_image' => 'nullable|image|max:5120',
            'builder_logo' => 'nullable|image|max:5120',
            'floor_plan_image' => 'nullable|image|max:5120',
            'amenities_image' => 'nullable|image|max:5120',
            'media_preview' => 'nullable|image|max:5120',
            'gallery_image' => 'nullable|image|max:5120',
            'reset_fields' => 'nullable|array',
            'reset_fields.*' => ['string', Rule::in(self::PUBLIC_PAGE_DEFAULT_FIELDS)],
        ]);

        try {
            foreach ((array) $request->input('reset_fields', []) as $field) {
                $existingPath = SystemSettings::get('project_public_page_default_' . $field);

                if ($existingPath && Storage::disk('public')->exists($existingPath)) {
                    Storage::disk('public')->delete($existingPath);
                }

                SystemSettings::set('project_public_page_default_' . $field, null);
            }

            foreach (self::PUBLIC_PAGE_DEFAULT_FIELDS as $field) {
                if (!$request->hasFile($field)) {
                    continue;
                }

                $existingPath = SystemSettings::get('project_public_page_default_' . $field);
                $storedPath = $request->file($field)->store('project-public/defaults', 'public');

                SystemSettings::set('project_public_page_default_' . $field, $storedPath);

                if ($existingPath && $existingPath !== $storedPath && Storage::disk('public')->exists($existingPath)) {
                    Storage::disk('public')->delete($existingPath);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Public page default visuals updated successfully.',
                'assets' => $this->getPublicPageDefaultAssetsPayload(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating public page default visuals: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateTravelTimeSettings(Request $request)
    {
        $request->validate([
            'ola_maps_api_key' => 'nullable|string|max:500',
            'travel_time_city_defaults' => 'nullable|string',
        ]);

        try {
            $rawDefaults = trim((string) $request->input('travel_time_city_defaults', ''));
            $decoded = $rawDefaults !== '' ? json_decode($rawDefaults, true) : [];

            if ($rawDefaults !== '' && !is_array($decoded)) {
                return response()->json([
                    'success' => false,
                    'message' => 'City defaults JSON invalid hai.',
                ], 422);
            }

            SystemSettings::set('ola_maps_api_key', trim((string) $request->input('ola_maps_api_key', '')) ?: null);
            SystemSettings::set('travel_time_city_defaults', json_encode($decoded ?: new \stdClass()));

            return response()->json([
                'success' => true,
                'message' => 'Travel time settings updated successfully.',
                'settings' => $this->getTravelTimeSettingsPayload(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Error updating travel time settings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function getPublicPageDefaultAssetsPayload(): array
    {
        return collect(self::PUBLIC_PAGE_DEFAULT_FIELDS)
            ->mapWithKeys(function (string $field) {
                $settingKey = 'project_public_page_default_' . $field;
                $storedPath = SystemSettings::get($settingKey);

                return [$field => [
                    'stored_path' => $storedPath,
                    'url' => $storedPath ? SystemSettings::getPublicPageDefaultAssetUrl($field) : null,
                    'fallback_url' => config('project_public_page.defaults.' . $field),
                    'is_custom' => filled($storedPath),
                ]];
            })
            ->all();
    }

    private function getTravelTimeSettingsPayload(): array
    {
        $storedDefaults = json_decode((string) SystemSettings::get('travel_time_city_defaults', '{}'), true);
        if (!is_array($storedDefaults)) {
            $storedDefaults = [];
        }

        return [
            'ola_maps_api_key' => (string) SystemSettings::get('ola_maps_api_key', ''),
            'travel_time_city_defaults' => json_encode(
                $storedDefaults ?: config('travel_time.city_defaults', []),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
        ];
    }

    /**
     * Show test email page (send sample welcome mail to any email)
     */
    public function testEmailPage()
    {
        return view('admin.system-settings.test-email');
    }

    /**
     * Send a sample welcome email to the given address (1-click test)
     */
    public function sendTestEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $mailerName = config('mail.default') ?: env('MAIL_MAILER') ?: 'smtp';
        $mailerName = is_string($mailerName) ? $mailerName : 'smtp';

        try {
            $appName = config('app.name');
            $loginUrl = AppUrl::to('/login');
            $installAppUrl = AppUrl::to('/install-app');
            $user = (object) [
                'name' => 'Test User',
                'email' => $request->email,
                'phone' => '+91 98765 43210',
                'is_active' => true,
            ];

            Mail::mailer($mailerName)->send('emails.new-user-welcome', [
                'user' => $user,
                'plainPassword' => 'Test@12345',
                'roleName' => 'Sales Executive',
                'managerName' => 'John Manager',
                'loginUrl' => $loginUrl,
                'installAppUrl' => $installAppUrl,
                'appName' => $appName,
            ], function ($message) use ($request, $appName) {
                $message->to($request->email)
                    ->subject('[' . $appName . '] Test – Sample welcome email');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent to ' . $request->email . '. Check inbox (and spam).',
            ]);
        } catch (\Exception $e) {
            Log::error('Test email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mail debug page: show config and send test with full error detail
     */
    public function mailDebugPage()
    {
        $default = config('mail.default');
        $mailers = config('mail.mailers');
        $mailerKeys = is_array($mailers) ? array_keys($mailers) : [];
        $from = config('mail.from');
        $smtp = $mailers['smtp'] ?? [];
        $envMail = [
            'MAIL_MAILER' => env('MAIL_MAILER') ?: ('(not set → ' . config('mail.default') . ')'),
            'MAIL_HOST' => env('MAIL_HOST') ?: '(e.g. smtp.hostinger.com)',
            'MAIL_PORT' => env('MAIL_PORT') ?: '587',
            'MAIL_USERNAME' => env('MAIL_USERNAME') ?: 'support@crm.bihtech.in',
            'MAIL_PASSWORD' => env('MAIL_PASSWORD') ? '(set)' : '(empty)',
            'MAIL_ENCRYPTION' => env('MAIL_ENCRYPTION') !== null && env('MAIL_ENCRYPTION') !== '' ? env('MAIL_ENCRYPTION') : 'tls',
            'MAIL_FROM_ADDRESS' => env('MAIL_FROM_ADDRESS') ?: config('mail.from.address'),
            'MAIL_FROM_NAME' => env('MAIL_FROM_NAME') ?: config('mail.from.name'),
        ];
        return view('admin.system-settings.mail-debug', compact('default', 'mailerKeys', 'from', 'envMail', 'smtp'));
    }

    /**
     * Send test email and return full error detail for debug page
     */
    public function sendTestEmailDebug(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $mailerName = config('mail.default') ?: env('MAIL_MAILER') ?: 'smtp';
        $mailerName = is_string($mailerName) ? $mailerName : 'smtp';

        try {
            $appName = config('app.name');
            $user = (object) ['name' => 'Test User', 'email' => $request->email, 'phone' => '—', 'is_active' => true];
            Mail::mailer($mailerName)->send('emails.new-user-welcome', [
                'user' => $user,
                'plainPassword' => 'Test@12345',
                'roleName' => 'Sales Executive',
                'managerName' => 'John Manager',
                'loginUrl' => AppUrl::to('/login'),
                'installAppUrl' => AppUrl::to('/install-app'),
                'appName' => $appName,
            ], function ($message) use ($request, $appName) {
                $message->to($request->email)->subject('[' . $appName . '] Test – Sample welcome email');
            });
            return response()->json(['success' => true, 'message' => 'Email sent to ' . $request->email]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_detail' => $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }

    /**
     * Upload files (zip or regular files)
     */
    public function uploadFiles(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'file|max:102400', // 100MB max per file
        ]);
        
        try {
            $uploadedFiles = [];
            $tempPath = storage_path('app/temp_uploads');
            
            // Create temp directory
            if (!File::exists($tempPath)) {
                File::makeDirectory($tempPath, 0755, true);
            }
            
            foreach ($request->file('files') as $file) {
                // Check if it's a zip file
                if (strtolower($file->getClientOriginalExtension()) === 'zip') {
                    $zipPath = $file->storeAs('temp_uploads', $file->getClientOriginalName());
                    $fullPath = storage_path('app/' . $zipPath);
                    
                    $zip = new ZipArchive;
                    if ($zip->open($fullPath) === TRUE) {
                        $extractPath = storage_path('app/temp_extract/' . time() . '_' . uniqid());
                        File::makeDirectory($extractPath, 0755, true);
                        $zip->extractTo($extractPath);
                        $zip->close();
                        
                        $uploadedFiles[] = [
                            'name' => $file->getClientOriginalName(),
                            'type' => 'zip',
                            'extracted' => $extractPath,
                            'original_path' => $fullPath
                        ];
                    } else {
                        throw new \Exception('Failed to extract zip file: ' . $file->getClientOriginalName());
                    }
                } else {
                    // Regular file
                    $path = $file->storeAs('temp_uploads', time() . '_' . $file->getClientOriginalName());
                    $uploadedFiles[] = [
                        'name' => $file->getClientOriginalName(),
                        'type' => 'file',
                        'path' => storage_path('app/' . $path)
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => count($uploadedFiles) . ' file(s) uploaded successfully. Ready to deploy.',
                'files' => $uploadedFiles
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error uploading files: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error uploading files: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Deploy files to server
     */
    public function deployFiles(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'destination' => 'required|string|in:app,resources,public,database,config,root'
        ]);
        
        try {
            $destination = $request->destination;
            $basePath = base_path();
            $deployed = [];
            $errors = [];
            
            foreach ($request->files as $fileData) {
                try {
                    $source = $fileData['path'] ?? null;
                    $extracted = $fileData['extracted'] ?? null;
                    $fileName = $fileData['name'] ?? 'unknown';
                    
                    if ($extracted && File::exists($extracted)) {
                        // Handle extracted zip - copy directory structure
                        $destPath = $basePath . '/' . ($destination === 'root' ? '' : $destination);
                        $this->copyDirectory($extracted, $destPath);
                        $deployed[] = $fileName . ' (extracted)';
                    } elseif ($source && File::exists($source)) {
                        // Handle single file
                        $targetFileName = basename($source);
                        // Remove timestamp prefix if exists
                        if (preg_match('/^\d+_\d+_(.+)$/', $targetFileName, $matches)) {
                            $targetFileName = $matches[1];
                        }
                        $targetPath = $basePath . '/' . ($destination === 'root' ? '' : $destination . '/') . $targetFileName;
                        
                        File::ensureDirectoryExists(dirname($targetPath));
                        File::copy($source, $targetPath);
                        $deployed[] = $targetFileName;
                    }
                } catch (\Exception $e) {
                    $errors[] = 'Error deploying ' . ($fileName ?? 'file') . ': ' . $e->getMessage();
                    Log::error('Error deploying file: ' . $e->getMessage());
                }
            }
            
            // Clean up temp files
            $this->cleanupTempFiles();
            
            $message = count($deployed) . ' file(s) deployed successfully.';
            if (count($errors) > 0) {
                $message .= ' Errors: ' . implode(', ', $errors);
            }
            
            return response()->json([
                'success' => count($errors) === 0,
                'message' => $message,
                'deployed' => $deployed,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deploying files: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deploying files: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Copy directory recursively
     */
    private function copyDirectory($source, $destination)
    {
        if (!File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }
        
        $files = File::allFiles($source);
        
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $targetPath = $destination . '/' . $relativePath;
            
            // Ensure target directory exists
            File::ensureDirectoryExists(dirname($targetPath));
            
            // Copy file
            File::copy($file->getPathname(), $targetPath);
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles()
    {
        try {
            $tempUploads = storage_path('app/temp_uploads');
            $tempExtract = storage_path('app/temp_extract');
            
            if (File::exists($tempUploads)) {
                File::deleteDirectory($tempUploads);
            }
            
            if (File::exists($tempExtract)) {
                File::deleteDirectory($tempExtract);
            }
        } catch (\Exception $e) {
            Log::warning('Error cleaning up temp files: ' . $e->getMessage());
        }
    }
    
    /**
     * Run migrations
     */
    public function runMigrations(Request $request)
    {
        try {
            $force = $request->input('force', true);
            
            // Run migrations
            Artisan::call('migrate', [
                '--force' => $force
            ]);
            
            $output = Artisan::output();
            
            return response()->json([
                'success' => true,
                'message' => 'Migrations ran successfully.',
                'output' => $output
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error running migrations: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error running migrations: ' . $e->getMessage(),
                'output' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    /**
     * Run artisan commands
     */
    public function runCommand(Request $request)
    {
        $request->validate([
            'command' => 'required|string|in:migrate,optimize,clear-cache,config-cache,route-cache,view-cache,config-clear,route-clear,view-clear'
        ]);
        
        try {
            $command = $request->command;
            $output = '';
            
            switch ($command) {
                case 'migrate':
                    Artisan::call('migrate', ['--force' => true]);
                    break;
                case 'optimize':
                    Artisan::call('optimize');
                    break;
                case 'clear-cache':
                    Artisan::call('cache:clear');
                    Artisan::call('config:clear');
                    Artisan::call('route:clear');
                    Artisan::call('view:clear');
                    $output = 'All caches cleared successfully.';
                    break;
                case 'config-cache':
                    Artisan::call('config:cache');
                    break;
                case 'route-cache':
                    Artisan::call('route:cache');
                    break;
                case 'view-cache':
                    Artisan::call('view:cache');
                    break;
                case 'config-clear':
                    Artisan::call('config:clear');
                    break;
                case 'route-clear':
                    Artisan::call('route:clear');
                    break;
                case 'view-clear':
                    Artisan::call('view:clear');
                    break;
            }
            
            if (empty($output)) {
                $output = Artisan::output();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Command executed successfully.',
                'output' => $output ?: 'Command completed.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error executing command: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error executing command: ' . $e->getMessage(),
                'output' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Test database connection with new credentials
     */
    public function testDatabaseConnection(Request $request)
    {
        // Security check: Only admin users
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin users can test database connections.',
            ], 403);
        }

        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        try {
            // Test connection with provided credentials
            config([
                'database.connections.mysql.host' => $request->host,
                'database.connections.mysql.port' => $request->port,
                'database.connections.mysql.database' => $request->database,
                'database.connections.mysql.username' => $request->username,
                'database.connections.mysql.password' => $request->password ?? '',
            ]);

            DB::purge('mysql');
            DB::connection('mysql')->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database connection successful!',
            ]);
        } catch (\Exception $e) {
            Log::error('Database connection test failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Update database settings
     */
    public function updateDatabaseSettings(Request $request)
    {
        // Security check: Only admin users
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin users can update database settings.',
            ], 403);
        }

        $request->validate([
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'database' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'password' => 'nullable|string|max:255',
        ]);

        try {
            // Backup current .env file
            $envPath = base_path('.env');
            if (File::exists($envPath)) {
                File::copy($envPath, $envPath . '.backup.' . date('Y-m-d_H-i-s'));
            }

            // Read current .env
            $envContent = File::get($envPath);

            // Update database settings
            $envContent = preg_replace('/^DB_HOST=.*/m', 'DB_HOST=' . $request->host, $envContent);
            $envContent = preg_replace('/^DB_PORT=.*/m', 'DB_PORT=' . $request->port, $envContent);
            $envContent = preg_replace('/^DB_DATABASE=.*/m', 'DB_DATABASE=' . $request->database, $envContent);
            $envContent = preg_replace('/^DB_USERNAME=.*/m', 'DB_USERNAME=' . $request->username, $envContent);
            $envContent = preg_replace('/^DB_PASSWORD=.*/m', 'DB_PASSWORD=' . ($request->password ?? ''), $envContent);

            // Write updated .env
            File::put($envPath, $envContent);

            // Clear config cache
            Artisan::call('config:clear');

            // Test new connection
            config([
                'database.connections.mysql.host' => $request->host,
                'database.connections.mysql.port' => $request->port,
                'database.connections.mysql.database' => $request->database,
                'database.connections.mysql.username' => $request->username,
                'database.connections.mysql.password' => $request->password ?? '',
            ]);

            DB::purge('mysql');
            DB::connection('mysql')->getPdo();

            return response()->json([
                'success' => true,
                'message' => 'Database settings updated successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating database settings', ['error' => $e->getMessage()]);
            
            // Restore backup if exists
            $backupFiles = glob(base_path('.env.backup.*'));
            if (!empty($backupFiles)) {
                $latestBackup = end($backupFiles);
                File::copy($latestBackup, base_path('.env'));
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update database settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get environment variables
     */
    public function getEnvSettings()
    {
        // Security check: Only admin users
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin users can view environment settings.',
            ], 403);
        }

        try {
            $envPath = base_path('.env');
            if (!File::exists($envPath)) {
                return response()->json([
                    'success' => false,
                    'message' => '.env file not found',
                ], 404);
            }

            $envContent = File::get($envPath);
            $lines = explode("\n", $envContent);
            $settings = [];

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || strpos($line, '#') === 0) {
                    continue;
                }

                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    $settings[$key] = $value;
                }
            }

            return response()->json([
                'success' => true,
                'settings' => $settings,
            ]);
        } catch (\Exception $e) {
            Log::error('Error reading env settings', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error reading environment settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update environment variables
     */
    public function updateEnvSettings(Request $request)
    {
        // Security check: Only admin users
        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only admin users can update environment settings.',
            ], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:1000', // Limit value length
        ]);

        // Security: Block certain critical keys from being changed via web interface
        $blockedKeys = ['APP_KEY']; // Add more if needed
        foreach ($blockedKeys as $key) {
            if (isset($request->settings[$key])) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot update {$key} via web interface for security reasons.",
                ], 400);
            }
        }

        try {
            $envPath = base_path('.env');
            if (!File::exists($envPath)) {
                return response()->json([
                    'success' => false,
                    'message' => '.env file not found',
                ], 404);
            }

            // Backup current .env
            File::copy($envPath, $envPath . '.backup.' . date('Y-m-d_H-i-s'));

            // Read current .env
            $envContent = File::get($envPath);
            $lines = explode("\n", $envContent);
            $updatedLines = [];

            // Track which keys we've updated
            $updatedKeys = [];

            foreach ($lines as $line) {
                $originalLine = $line;
                $line = trim($line);

                // Keep comments and empty lines as is
                if (empty($line) || strpos($line, '#') === 0) {
                    $updatedLines[] = $originalLine;
                    continue;
                }

                // Check if this line has a key we need to update
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);

                    if (isset($request->settings[$key])) {
                        $newValue = $request->settings[$key];
                        // Add quotes if value contains spaces or special characters
                        if (preg_match('/[\s=#]/', $newValue)) {
                            $newValue = '"' . $newValue . '"';
                        }
                        $updatedLines[] = $key . '=' . $newValue;
                        $updatedKeys[] = $key;
                    } else {
                        $updatedLines[] = $originalLine;
                    }
                } else {
                    $updatedLines[] = $originalLine;
                }
            }

            // Add any new settings that weren't in the file
            foreach ($request->settings as $key => $value) {
                if (!in_array($key, $updatedKeys)) {
                    if (preg_match('/[\s=#]/', $value)) {
                        $value = '"' . $value . '"';
                    }
                    $updatedLines[] = $key . '=' . $value;
                }
            }

            // Write updated .env
            File::put($envPath, implode("\n", $updatedLines));

            // Clear config cache
            Artisan::call('config:clear');

            return response()->json([
                'success' => true,
                'message' => 'Environment settings updated successfully!',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating env settings', ['error' => $e->getMessage()]);
            
            // Restore backup if exists
            $backupFiles = glob(base_path('.env.backup.*'));
            if (!empty($backupFiles)) {
                $latestBackup = end($backupFiles);
                File::copy($latestBackup, base_path('.env'));
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update environment settings: ' . $e->getMessage(),
            ], 500);
        }
    }
}
