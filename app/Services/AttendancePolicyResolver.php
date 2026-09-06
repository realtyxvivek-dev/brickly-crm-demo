<?php

namespace App\Services;

use App\Models\AttendancePolicy;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\CarbonInterface;

class AttendancePolicyResolver
{
    public function resolveForUser(User $user, ?CarbonInterface $date = null): array
    {
        $date ??= now();

        $profile = UserAttendanceProfile::with(['attendancePolicy.officeLocation', 'officeLocation'])
            ->where('user_id', $user->id)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $date->toDateString());
            })
            ->first();

        $policy = $profile?->attendancePolicy;

        if (!$policy) {
            $policy = AttendancePolicy::with('officeLocation')
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
        }

        $office = $profile?->officeLocation ?: $policy?->officeLocation;

        return [
            'profile' => $profile,
            'policy' => $policy,
            'office' => $office,
        ];
    }
}
