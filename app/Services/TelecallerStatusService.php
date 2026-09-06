<?php

namespace App\Services;

use App\Models\TelecallerProfile;
use App\Models\User;
use App\Models\Role;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Carbon\Carbon;

class TelecallerStatusService
{
    protected $userStatusService;

    /**
     * Get or create telecaller profile
     */
    public function __construct(UserStatusService $userStatusService)
    {
        $this->userStatusService = $userStatusService;
    }

    public function getOrCreateProfile(int $userId): TelecallerProfile
    {
        return TelecallerProfile::firstOrCreate(
            ['user_id' => $userId],
            [
                'max_pending_leads' => 50,
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
    ): TelecallerProfile
    {
        $this->userStatusService->toggleAbsentStatus(
            $userId,
            $isAbsent,
            $reason,
            $absentUntil,
            $leadOffStartAt,
            $leadOffEndAt,
            $source,
            $setByUserId,
            $fallbackUserId
        );

        return $this->getOrCreateProfile($userId)->fresh();
    }

    /**
     * Check if telecaller is absent
     */
    public function isTelecallerAbsent(int $userId): bool
    {
        return $this->userStatusService->isUserAbsent($userId);
    }

    /**
     * Get pending leads count for telecaller
     */
    public function getPendingLeadsCount(int $userId): int
    {
        return LeadAssignment::where('assigned_to', $userId)
            ->where('is_active', true)
            ->whereHas('lead', function ($query) {
                $query->whereIn('status', ['new', 'contacted']);
            })
            ->count();
    }

    /**
     * Check if telecaller has reached pending threshold
     */
    public function hasReachedPendingThreshold(int $userId): bool
    {
        $profile = $this->getOrCreateProfile($userId);
        $pendingCount = $this->getPendingLeadsCount($userId);

        if ($profile->max_pending_leads <= 0) {
            return false; // No threshold set
        }

        return $pendingCount >= $profile->max_pending_leads;
    }

    /**
     * Get available telecallers (not absent, within limits, not at threshold)
     */
    public function getAvailableTelecallers(?array $excludeUserIds = []): \Illuminate\Database\Eloquent\Collection
    {
        $salesExecutiveRoleId = Role::where('slug', Role::SALES_EXECUTIVE)->value('id');

        $query = User::where('role_id', $salesExecutiveRoleId)
            ->where('is_active', true);

        if (!empty($excludeUserIds)) {
            $query->whereNotIn('id', $excludeUserIds);
        }

        return $query->get()->filter(function (User $user) {
            return $this->userStatusService->canUserReceiveLeads($user->id);
        })->values();
    }

    /**
     * Check if telecaller can receive assignment
     */
    public function canReceiveAssignment(int $userId): array
    {
        $isAbsent = $this->userStatusService->isUserAbsent($userId);
        $hasReachedThreshold = $this->hasReachedPendingThreshold($userId);
        $pendingCount = $this->getPendingLeadsCount($userId);
        $profile = $this->getOrCreateProfile($userId);

        return [
            'can_receive' => !$isAbsent && !$hasReachedThreshold,
            'is_absent' => $isAbsent,
            'has_reached_threshold' => $hasReachedThreshold,
            'pending_count' => $pendingCount,
            'max_pending' => $profile->max_pending_leads,
        ];
    }
}
