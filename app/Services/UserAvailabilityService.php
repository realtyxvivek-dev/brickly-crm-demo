<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserAvailability;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserAvailabilityService
{
    /**
     * Get or create user availability record
     */
    public function getOrCreateAvailability(int $userId): UserAvailability
    {
        return UserAvailability::firstOrCreate(
            ['user_id' => $userId],
            [
                'is_online' => false,
                'timezone' => 'Asia/Kolkata',
                'current_day_leads' => 0,
                'is_available' => false,
            ]
        );
    }

    /**
     * Check if user is available for assignment
     */
    public function isUserAvailable(int $userId): bool
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->updateAvailability();
        
        return $availability->is_available;
    }

    /**
     * Check if user is online
     */
    public function isUserOnline(int $userId): bool
    {
        $availability = $this->getOrCreateAvailability($userId);
        return $availability->is_online;
    }

    /**
     * Check if user is within office hours
     */
    public function isWithinOfficeHours(int $userId): bool
    {
        $availability = $this->getOrCreateAvailability($userId);
        return $availability->isWithinOfficeHours();
    }

    /**
     * Check if user is under daily limit
     */
    public function isUnderDailyLimit(int $userId): bool
    {
        $availability = $this->getOrCreateAvailability($userId);
        return $availability->isUnderDailyLimit();
    }

    /**
     * Get available users filtered by criteria
     */
    public function getAvailableUsers(array $userIds = null, bool $checkOnline = true, bool $checkOfficeHours = true, bool $checkLimits = true): array
    {
        $query = UserAvailability::query();

        if ($userIds !== null) {
            $query->whereIn('user_id', $userIds);
        }

        if ($checkOnline) {
            $query->where('is_online', true);
        }

        // Reset daily counters if needed
        $this->resetDailyCountersIfNeeded();

        // Filter by availability
        $availabilities = $query->get();

        $available = [];
        foreach ($availabilities as $availability) {
            $availability->updateAvailability();
            
            if (!$availability->is_available) {
                continue;
            }

            if ($checkOfficeHours && !$availability->isWithinOfficeHours()) {
                continue;
            }

            if ($checkLimits && !$availability->isUnderDailyLimit()) {
                continue;
            }

            $available[] = $availability->user_id;
        }

        return $available;
    }

    /**
     * Mark user as online
     */
    public function setOnline(int $userId, bool $online = true): void
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->update([
            'is_online' => $online,
            'last_seen_at' => $online ? Carbon::now() : $availability->last_seen_at,
        ]);
        $availability->updateAvailability();
    }

    /**
     * Increment daily lead count for user
     */
    public function incrementDailyLeads(int $userId): void
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->incrementDailyLeads();
    }

    /**
     * Reset daily counters for all users if it's a new day
     */
    public function resetDailyCountersIfNeeded(): void
    {
        $today = Carbon::today()->toDateString();
        
        UserAvailability::where('last_reset_date', '!=', $today)
            ->orWhereNull('last_reset_date')
            ->update([
                'current_day_leads' => 0,
                'last_reset_date' => $today,
            ]);
    }

    /**
     * Get user availability status with details
     */
    public function getUserAvailabilityStatus(int $userId): array
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->updateAvailability();

        return [
            'is_online' => $availability->is_online,
            'is_available' => $availability->is_available,
            'is_within_office_hours' => $availability->isWithinOfficeHours(),
            'is_under_limit' => $availability->isUnderDailyLimit(),
            'current_day_leads' => $availability->current_day_leads,
            'max_leads_per_day' => $availability->max_leads_per_day,
            'office_hours_start' => $availability->office_hours_start,
            'office_hours_end' => $availability->office_hours_end,
            'last_seen_at' => $availability->last_seen_at,
        ];
    }

    /**
     * Update office hours for user
     */
    public function updateOfficeHours(int $userId, ?string $start = null, ?string $end = null, ?string $timezone = null): void
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->update([
            'office_hours_start' => $start,
            'office_hours_end' => $end,
            'timezone' => $timezone ?? $availability->timezone,
        ]);
        $availability->updateAvailability();
    }

    /**
     * Update daily limit for user
     */
    public function updateDailyLimit(int $userId, ?int $maxLeads): void
    {
        $availability = $this->getOrCreateAvailability($userId);
        $availability->update([
            'max_leads_per_day' => $maxLeads,
        ]);
        $availability->updateAvailability();
    }
}

