<?php

namespace App\Models;

use App\Services\AdminSessionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SystemSettings extends Model
{
    protected $fillable = ['key', 'value'];
    
    public $timestamps = true;
    
    /**
     * Get a setting value by key
     */
    public static function get($key, $default = null)
    {
        if (!Schema::hasTable('system_settings')) {
            return $default;
        }

        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }
    
    /**
     * Set a setting value by key
     */
    public static function set($key, $value)
    {
        if (!Schema::hasTable('system_settings')) {
            return null;
        }

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getPublicPageDefaultAssetUrl(string $key, $fallback = null): ?string
    {
        $storedValue = self::get('project_public_page_default_' . $key);

        if (!$storedValue) {
            return $fallback;
        }

        if (preg_match('/^https?:\/\//i', $storedValue)) {
            return $storedValue;
        }

        return Storage::disk('public')->url($storedValue);
    }
    
    /**
     * Check if maintenance mode is enabled
     */
    public static function isMaintenanceMode()
    {
        return self::get('maintenance_mode', '0') === '1';
    }
    
    /**
     * Enable maintenance mode and logout all non-admin users
     */
    public static function enableMaintenanceMode($message = null)
    {
        self::set('maintenance_mode', '1');
        if ($message) {
            self::set('maintenance_message', $message);
        }
        
        // Logout all non-admin users by invalidating their sessions
        self::logoutAllNonAdminUsers();
    }
    
    /**
     * Logout all non-admin users only (keep admin sessions active)
     */
    public static function logoutAllNonAdminUsers()
    {
        try {
            app(AdminSessionService::class)->revokeAllNonAdminSessions(
                actor: auth()->user() ?? new User(['id' => null]),
                preserveSessionId: auth()->check() ? session()->getId() : null,
            );
        } catch (\Exception $e) {
            // Log error but continue
            Log::error('Error logging out non-admin users: ' . $e->getMessage());
        }
    }
    
    /**
     * Disable maintenance mode
     */
    public static function disableMaintenanceMode()
    {
        self::set('maintenance_mode', '0');
    }
    
    /**
     * Logout all users by invalidating their sessions
     */
    public static function logoutAllUsers()
    {
        try {
            app(AdminSessionService::class)->revokeAllSessions(
                actor: auth()->user() ?? new User(['id' => null]),
                preserveSessionId: auth()->check() ? session()->getId() : null,
            );
        } catch (\Exception $e) {
            // Log error but continue
            \Log::error('Error logging out all users: ' . $e->getMessage());
        }
    }
}
