<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AttendanceAccessService
{
    public function enabledProfilesQuery(?CarbonInterface $date = null): Builder
    {
        $date ??= now();

        return UserAttendanceProfile::query()
            ->whereHas('user')
            ->where('attendance_enabled', true)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            });
    }

    public function enabledProfilesInPeriodQuery(CarbonInterface $startDate, CarbonInterface $endDate): Builder
    {
        return UserAttendanceProfile::query()
            ->whereHas('user')
            ->where('attendance_enabled', true)
            ->where(function ($query) use ($endDate) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $endDate->toDateString());
            });
    }

    public function profileFor(User $user, ?CarbonInterface $date = null): ?UserAttendanceProfile
    {
        return $this->enabledProfilesQuery($date)
            ->where('user_id', $user->id)
            ->first();
    }

    public function isEnabledFor(User $user, ?CarbonInterface $date = null): bool
    {
        return $this->profileFor($user, $date) !== null;
    }

    public function ensureEnabledFor(User $user, ?CarbonInterface $date = null): void
    {
        if ($this->isEnabledFor($user, $date)) {
            return;
        }

        throw new AccessDeniedHttpException('Attendance access is not enabled for your account yet.');
    }
}
