<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallationChecker
{
    /**
     * Check if the application is already installed.
     * Uses lock file first; if missing (e.g. after deploy), falls back to
     * checking database (users table with data) so install wizard stays locked.
     */
    public static function isInstalled(): bool
    {
        $lockFile = storage_path('app/installed.lock');

        if (File::exists($lockFile)) {
            return true;
        }

        // Fallback: after deploy, storage may be fresh but DB already has data
        if (self::hasExistingInstallationInDatabase()) {
            self::recreateLockFile();
            return true;
        }

        return false;
    }

    /**
     * Check if database already has installation data (users table with rows).
     */
    protected static function hasExistingInstallationInDatabase(): bool
    {
        try {
            if (! config('database.default')) {
                return false;
            }
            if (! Schema::hasTable('users')) {
                return false;
            }
            return DB::table('users')->exists();
        } catch (\Throwable $e) {
            Log::debug('InstallationChecker: DB fallback check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Recreate installed.lock so next request uses lock file only.
     */
    protected static function recreateLockFile(): void
    {
        try {
            $dir = storage_path('app');
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true);
            }
            File::put($dir . '/installed.lock', now()->toDateTimeString());
        } catch (\Throwable $e) {
            Log::warning('InstallationChecker: Could not recreate installed.lock', ['error' => $e->getMessage()]);
        }
    }
}
