<?php

namespace App\Console\Commands;

use App\Models\AttendancePolicy;
use App\Models\AttendanceWeekoff;
use App\Models\OfficeLocation;
use App\Models\Role;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupMarketingAttendancePolicy extends Command
{
    protected $signature = 'attendance:setup-marketing-policy
                            {--effective-from= : Attendance profile and week-off effective date, defaults to today}';

    protected $description = 'Create/update marketing attendance policy and assign it to marketing users';

    public function handle(): int
    {
        $effectiveFrom = $this->option('effective-from') ?: now()->toDateString();

        $defaultPolicy = AttendancePolicy::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?: AttendancePolicy::query()->where('is_active', true)->latest()->first();

        $office = $defaultPolicy?->officeLocation
            ?: OfficeLocation::query()->where('is_active', true)->orderBy('id')->first();

        $marketingUsers = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::MARKETING_MANAGER,
                Role::MARKETING_EXECUTIVE,
                Role::AD_MANAGER,
            ]))
            ->get();

        if ($marketingUsers->isEmpty()) {
            $this->warn('No active marketing users found. Policy will still be created/updated.');
        }

        DB::transaction(function () use ($defaultPolicy, $office, $marketingUsers, $effectiveFrom) {
            $policyDefaults = $defaultPolicy
                ? $defaultPolicy->only([
                    'geo_fence_required',
                    'allow_outside_punch_requests',
                    'outside_punch_permission_default_enabled',
                    'photo_required',
                    'selfie_required',
                    'face_review_required',
                    'payroll_block_on_pending_face_review',
                    'duplicate_photo_threshold',
                    'face_compare_provider',
                    'liveness_provider',
                    'provider_settings_json',
                    'compress_max_width',
                    'compress_max_height',
                    'compress_quality',
                    'suspicious_geo_threshold_meters',
                    'approval_mode_leave',
                    'approval_mode_regularization',
                    'regularization_abuse_threshold',
                    'late_penalty_type',
                    'late_penalty_threshold',
                    'overtime_enabled',
                    'overtime_after_minutes',
                    'overtime_min_minutes',
                ])
                : [];

            $policy = AttendancePolicy::query()->updateOrCreate(
                ['name' => 'Marketing Attendance Policy'],
                array_merge($policyDefaults, [
                    'office_location_id' => $office?->id,
                    'is_default' => false,
                    'reminder_time' => '09:45:00',
                    'late_after_time' => '10:00:00',
                    'grace_minutes' => 10,
                    'normal_window_end_time' => '11:30:00',
                    'half_day_start_time' => '13:00:00',
                    'half_day_end_time' => '16:00:00',
                    'is_active' => true,
                ])
            );

            foreach ($marketingUsers as $user) {
                $existingProfile = UserAttendanceProfile::query()
                    ->where('user_id', $user->id)
                    ->first();

                UserAttendanceProfile::query()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'office_location_id' => $existingProfile?->office_location_id ?: $office?->id,
                        'attendance_policy_id' => $policy->id,
                        'employee_code' => $existingProfile?->employee_code,
                        'salary_mode' => $existingProfile?->salary_mode ?: 'monthly',
                        'attendance_enabled' => true,
                        'allow_outside_punch_requests' => $existingProfile?->allow_outside_punch_requests ?? true,
                        'attendance_rollout_stage' => 'live',
                        'base_salary' => $existingProfile?->base_salary,
                        'effective_from' => $existingProfile?->effective_from ?: $effectiveFrom,
                    ]
                );

                AttendanceWeekoff::query()
                    ->where('user_id', $user->id)
                    ->whereNull('effective_to')
                    ->delete();

                AttendanceWeekoff::query()->create([
                    'user_id' => $user->id,
                    'day_of_week' => 0,
                    'effective_from' => $effectiveFrom,
                    'effective_to' => null,
                ]);
            }
        });

        $this->info('Marketing Attendance Policy ready: 10:00 AM to 07:00 PM working window, Sunday week-off.');
        $this->info('Marketing users mapped/enabled: ' . $marketingUsers->count());

        return self::SUCCESS;
    }
}
