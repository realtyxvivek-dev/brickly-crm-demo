<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\SystemSettings;
use Illuminate\Support\Facades\Storage;

class NotificationSoundService
{
    public const FALLBACK_URL = '/sounds/lead-ringtone.mp3';

    public const DEFAULT_KEY = 'default';

    public const CATEGORIES = [
        'default' => 'Default',
        'new_lead' => 'New Lead',
        'followup' => 'Follow Up',
        'meeting' => 'Meeting',
        'site_visit' => 'Site Visit',
        'overdue' => 'Overdue',
        'call_reminder' => 'Call/CNP Reminder',
    ];

    public function settingKey(string $key): string
    {
        return 'notification_sound_' . $key;
    }

    public function isSupportedKey(string $key): bool
    {
        return array_key_exists($key, self::CATEGORIES);
    }

    public function settingsPayload(): array
    {
        return collect(self::CATEGORIES)
            ->mapWithKeys(function (string $label, string $key) {
                $path = $this->storedPath($key);

                return [$key => [
                    'key' => $key,
                    'label' => $label,
                    'path' => $path,
                    'url' => $path ? Storage::disk('public')->url($path) : null,
                    'effective_url' => $this->resolveUrlForKey($key),
                    'uses_default' => $key !== self::DEFAULT_KEY && !$path,
                ]];
            })
            ->all();
    }

    public function resolveForNotification(string $type, array $data = []): array
    {
        $soundKey = $this->soundKeyForNotification($type, $data);

        return [
            'sound_key' => $soundKey,
            'sound_url' => $this->resolveUrlForKey($soundKey),
        ];
    }

    public function resolveUrlForKey(string $key): string
    {
        $path = $this->storedPath($key);
        if ($path) {
            return Storage::disk('public')->url($path);
        }

        if ($key !== self::DEFAULT_KEY) {
            $defaultPath = $this->storedPath(self::DEFAULT_KEY);
            if ($defaultPath) {
                return Storage::disk('public')->url($defaultPath);
            }
        }

        return self::FALLBACK_URL;
    }

    public function saveSound(string $key, string $path): void
    {
        $oldPath = $this->storedPath($key);
        SystemSettings::set($this->settingKey($key), $path);
        $this->deleteIfUnused($oldPath);
    }

    public function resetSound(string $key): void
    {
        $oldPath = $this->storedPath($key);
        SystemSettings::set($this->settingKey($key), null);
        $this->deleteIfUnused($oldPath);
    }

    public function applyDefaultToAll(): void
    {
        $defaultPath = $this->storedPath(self::DEFAULT_KEY);
        if (!$defaultPath) {
            return;
        }

        foreach (array_keys(self::CATEGORIES) as $key) {
            if ($key === self::DEFAULT_KEY) {
                continue;
            }

            $oldPath = $this->storedPath($key);
            SystemSettings::set($this->settingKey($key), $defaultPath);
            $this->deleteIfUnused($oldPath);
        }
    }

    private function storedPath(string $key): ?string
    {
        $path = SystemSettings::get($this->settingKey($key));

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    private function soundKeyForNotification(string $type, array $data): string
    {
        $kind = (string) ($data['kind'] ?? '');

        if ($type === AppNotification::TYPE_NEW_LEAD) {
            return 'new_lead';
        }

        if (in_array($type, [
            AppNotification::TYPE_FOLLOWUP_REMINDER,
        ], true)) {
            return 'followup';
        }

        if (str_contains($type, 'overdue') || str_contains($kind, 'overdue')) {
            return 'overdue';
        }

        if ($type === AppNotification::TYPE_MEETING_REMINDER || $type === AppNotification::TYPE_MEETING) {
            return 'meeting';
        }

        if ($type === AppNotification::TYPE_SITE_VISIT_REMINDER || $type === AppNotification::TYPE_SITE_VISIT) {
            return 'site_visit';
        }

        if ($type === AppNotification::TYPE_CALL_REMINDER || str_contains($kind, 'call_reminder') || str_contains($kind, 'task_reminder')) {
            return 'call_reminder';
        }

        return self::DEFAULT_KEY;
    }

    private function deleteIfUnused(?string $path): void
    {
        if (!$path) {
            return;
        }

        $stillUsed = collect(array_keys(self::CATEGORIES))
            ->contains(fn (string $key) => $this->storedPath($key) === $path);

        if (!$stillUsed && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
