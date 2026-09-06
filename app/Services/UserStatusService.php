<?php

namespace App\Services;

use App\Models\UserProfile;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\TelecallerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class UserStatusService
{
    private static ?array $userProfileColumns = null;
    private static ?array $telecallerProfileColumns = null;

    /**
     * Get or create user profile
     */
    public function getOrCreateProfile(int $userId): UserProfile
    {
        return UserProfile::firstOrCreate(
            ['user_id' => $userId],
            [
                'is_absent' => false,
            ]
        );
    }

    /**
     * Toggle absent status
     */
    public function toggleAbsentStatus(
        int $userId,
        bool $isAbsent,
        ?string $reason = null,
        ?Carbon $absentUntil = null,
        ?Carbon $leadOffStartAt = null,
        ?Carbon $leadOffEndAt = null,
        ?string $source = null,
        ?int $setByUserId = null,
        ?int $fallbackUserId = null
    ): UserProfile
    {
        $profile = $this->getOrCreateProfile($userId);

        $leadOffStartAt = $isAbsent
            ? ($leadOffStartAt ?? Carbon::now())
            : null;

        $leadOffEndAt = $isAbsent
            ? ($leadOffEndAt ?? $absentUntil)
            : null;

        $oldValues = [
            'is_absent' => (bool) $profile->is_absent,
            'reason' => $profile->absent_reason,
            'fallback_user_id' => $profile->lead_off_fallback_user_id,
            'start_at' => $profile->lead_off_start_at?->toDateTimeString(),
            'end_at' => $profile->leadOffEndsAt()?->toDateTimeString(),
        ];

        $payload = [
            'is_absent' => $isAbsent,
            'absent_reason' => $isAbsent ? $reason : null,
            'absent_until' => $isAbsent ? ($leadOffEndAt ?? $absentUntil) : null,
            'lead_off_start_at' => $leadOffStartAt,
            'lead_off_end_at' => $leadOffEndAt,
            'lead_off_source' => $isAbsent ? ($source ?? 'crm') : null,
            'lead_off_set_by' => $isAbsent ? $setByUserId : null,
            'lead_off_fallback_user_id' => $isAbsent ? $fallbackUserId : null,
        ];

        $profile->update($this->filterPayloadForTable('user_profiles', $payload));

        $this->syncLegacyTelecallerProfile($userId, $isAbsent, $reason, $leadOffStartAt, $leadOffEndAt);

        if (Schema::hasTable('activity_logs')) {
            ActivityLog::create([
                'user_id' => $setByUserId,
                'action' => $isAbsent ? 'lead_off_enabled' : 'lead_off_disabled',
                'model_type' => User::class,
                'model_id' => $userId,
                'description' => $isAbsent
                    ? 'Lead Off enabled with routing fallback and reason.'
                    : 'Lead Off disabled.',
                'old_values' => $oldValues,
                'new_values' => [
                    'is_absent' => $isAbsent,
                    'reason' => $isAbsent ? $reason : null,
                    'fallback_user_id' => $isAbsent ? $fallbackUserId : null,
                    'source' => $isAbsent ? ($source ?? 'crm') : null,
                    'start_at' => $leadOffStartAt?->toDateTimeString(),
                    'end_at' => $leadOffEndAt?->toDateTimeString(),
                ],
            ]);
        }

        return $profile;
    }

    /**
     * Mark user as present (not absent)
     */
    public function markAsPresent(int $userId): UserProfile
    {
        return $this->toggleAbsentStatus($userId, false);
    }

    /**
     * Check if user is absent
     */
    public function isUserAbsent(int $userId): bool
    {
        $profile = UserProfile::where('user_id', $userId)->first();
        
        if (!$profile) {
            return false; // No profile means not absent
        }

        return $profile->isCurrentlyAbsent();
    }

    /**
     * Check if user can receive leads (based on absent status only)
     */
    public function canUserReceiveLeads(int $userId): bool
    {
        return !$this->isUserAbsent($userId);
    }

    /** Return the active fallback configured for a currently Lead Off user. */
    public function leadOffFallbackUser(int $userId): ?User
    {
        $fallbackId = UserProfile::where('user_id', $userId)->value('lead_off_fallback_user_id');
        if (!$fallbackId || (int) $fallbackId === $userId) {
            return null;
        }

        $fallback = User::with('role')->find($fallbackId);

        return $fallback && $fallback->is_active && !$this->isUserAbsent($fallback->id)
            ? $fallback
            : null;
    }

    private function syncLegacyTelecallerProfile(
        int $userId,
        bool $isAbsent,
        ?string $reason,
        ?Carbon $leadOffStartAt,
        ?Carbon $leadOffEndAt
    ): void {
        $user = User::with('role')->find($userId);

        if (!$user) {
            return;
        }

        $hasLegacyProfile = TelecallerProfile::where('user_id', $userId)->exists();
        if (!$user->isSalesExecutive() && !$hasLegacyProfile) {
            return;
        }

        $profile = TelecallerProfile::firstOrCreate(
            ['user_id' => $userId],
            [
                'max_pending_leads' => 50,
                'is_absent' => false,
            ]
        );

        $payload = [
            'is_absent' => $isAbsent,
            'absent_reason' => $isAbsent ? $reason : null,
            'absent_until' => $isAbsent ? $leadOffEndAt : null,
            'lead_off_start_at' => $isAbsent ? $leadOffStartAt : null,
            'lead_off_end_at' => $isAbsent ? $leadOffEndAt : null,
        ];

        $profile->update($this->filterPayloadForTable('telecaller_profiles', $payload));
    }

    private function filterPayloadForTable(string $table, array $payload): array
    {
        $columns = match ($table) {
            'user_profiles' => self::$userProfileColumns ??= Schema::getColumnListing($table),
            'telecaller_profiles' => self::$telecallerProfileColumns ??= Schema::getColumnListing($table),
            default => Schema::getColumnListing($table),
        };

        return array_filter(
            $payload,
            static fn ($value, $column) => in_array($column, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
