<?php

namespace App\Services;

use App\Models\SystemSettings;
use App\Models\User;
use Carbon\Carbon;

class NotificationQuietHoursService
{
    public const ENABLED_KEY = 'notification_quiet_hours_enabled';
    public const START_KEY = 'notification_quiet_hours_start';
    public const END_KEY = 'notification_quiet_hours_end';

    public const DEFAULT_ENABLED = '1';
    public const DEFAULT_START = '21:00';
    public const DEFAULT_END = '08:30';

    public function shouldMute(?User $user = null, ?Carbon $now = null): bool
    {
        if ($user && $user->isAdmin()) {
            return false;
        }

        if (SystemSettings::get(self::ENABLED_KEY, self::DEFAULT_ENABLED) !== '1') {
            return false;
        }

        $now ??= now();
        $start = $this->timeToMinutes((string) SystemSettings::get(self::START_KEY, self::DEFAULT_START));
        $end = $this->timeToMinutes((string) SystemSettings::get(self::END_KEY, self::DEFAULT_END));
        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($start === $end) {
            return false;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    private function timeToMinutes(string $value): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            $value = self::DEFAULT_START;
            preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches);
        }

        $hour = min(23, max(0, (int) $matches[1]));
        $minute = min(59, max(0, (int) $matches[2]));

        return ($hour * 60) + $minute;
    }
}
