<?php

namespace App\Services;

use App\Models\TelecallerDailyLimit;
use App\Models\SheetAssignmentConfig;
use App\Models\SheetPercentageConfig;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TelecallerLimitService
{
    /**
     * Get overall daily limit for telecaller
     */
    public function getOverallDailyLimit(int $userId): ?TelecallerDailyLimit
    {
        return TelecallerDailyLimit::firstOrCreate(
            ['user_id' => $userId],
            [
                'overall_daily_limit' => 0,
                'assigned_count_today' => 0,
                'last_reset_date' => Carbon::today(),
            ]
        );
    }

    /**
     * Get per-sheet daily limit
     */
    public function getPerSheetDailyLimit(?int $sheetConfigId): ?int
    {
        if (!$sheetConfigId) {
            return null;
        }

        $config = SheetAssignmentConfig::where('google_sheets_config_id', $sheetConfigId)->first();
        return $config?->per_sheet_daily_limit;
    }

    /**
     * Get percentage config daily limit
     */
    public function getPercentageConfigLimit(int $userId, int $sheetAssignmentConfigId): ?int
    {
        $config = SheetPercentageConfig::where('sheet_assignment_config_id', $sheetAssignmentConfigId)
            ->where('user_id', $userId)
            ->first();

        return $config?->daily_limit;
    }

    /**
     * Check if telecaller is within all daily limits
     * Returns the most restrictive limit and current count
     */
    public function checkDailyLimits(int $userId, ?int $sheetConfigId = null, ?int $sheetAssignmentConfigId = null): array
    {
        $overallLimit = $this->getOverallDailyLimit($userId);
        $overallLimit->resetIfNewDay();

        $limits = [
            'overall' => [
                'limit' => $overallLimit->overall_daily_limit,
                'current' => $overallLimit->assigned_count_today,
                'available' => $overallLimit->isWithinLimit(),
            ],
        ];

        // Check per-sheet limit
        if ($sheetConfigId) {
            $perSheetLimit = $this->getPerSheetDailyLimit($sheetConfigId);
            if ($perSheetLimit && $perSheetLimit > 0) {
                // Count assignments for this sheet today
                $perSheetCount = DB::table('lead_assignments')
                    ->where('assigned_to', $userId)
                    ->where('sheet_config_id', $sheetConfigId)
                    ->where('is_active', true)
                    ->whereDate('assigned_at', Carbon::today())
                    ->count();

                $limits['per_sheet'] = [
                    'limit' => $perSheetLimit,
                    'current' => $perSheetCount,
                    'available' => $perSheetCount < $perSheetLimit,
                ];
            }
        }

        // Check percentage config limit
        if ($sheetAssignmentConfigId) {
            $percentageLimit = $this->getPercentageConfigLimit($userId, $sheetAssignmentConfigId);
            if ($percentageLimit && $percentageLimit > 0) {
                $percentageConfig = SheetPercentageConfig::where('sheet_assignment_config_id', $sheetAssignmentConfigId)
                    ->where('user_id', $userId)
                    ->first();

                if ($percentageConfig) {
                    $percentageConfig->resetIfNewDay();
                    $limits['percentage'] = [
                        'limit' => $percentageLimit,
                        'current' => $percentageConfig->assigned_count_today,
                        'available' => $percentageConfig->isWithinLimit(),
                    ];
                }
            }
        }

        // Determine if assignment is allowed (all limits must be satisfied)
        $isAllowed = true;
        $mostRestrictive = null;
        $minAvailable = PHP_INT_MAX;

        foreach ($limits as $type => $limit) {
            if (!$limit['available']) {
                $isAllowed = false;
            }
            if ($limit['limit'] > 0 && $limit['limit'] < $minAvailable) {
                $minAvailable = $limit['limit'];
                $mostRestrictive = $type;
            }
        }

        return [
            'is_allowed' => $isAllowed,
            'most_restrictive' => $mostRestrictive,
            'limits' => $limits,
        ];
    }

    /**
     * Increment assigned count for all applicable limits
     */
    public function incrementAssignedCount(int $userId, ?int $sheetConfigId = null, ?int $sheetAssignmentConfigId = null): void
    {
        DB::transaction(function () use ($userId, $sheetConfigId, $sheetAssignmentConfigId) {
            // Increment overall limit
            $overallLimit = $this->getOverallDailyLimit($userId);
            $overallLimit->incrementCount();

            // Increment percentage config if applicable
            if ($sheetAssignmentConfigId) {
                $percentageConfig = SheetPercentageConfig::where('sheet_assignment_config_id', $sheetAssignmentConfigId)
                    ->where('user_id', $userId)
                    ->first();

                if ($percentageConfig) {
                    $percentageConfig->incrementCount();
                }
            }
        });
    }

    /**
     * Reset all daily counts (called by scheduled job)
     */
    public function resetDailyCounts(): void
    {
        $today = Carbon::today();

        // Reset overall limits
        TelecallerDailyLimit::where('last_reset_date', '<', $today)
            ->orWhereNull('last_reset_date')
            ->update([
                'assigned_count_today' => 0,
                'last_reset_date' => $today,
            ]);

        // Reset percentage config limits
        SheetPercentageConfig::where('last_reset_date', '<', $today)
            ->orWhereNull('last_reset_date')
            ->update([
                'assigned_count_today' => 0,
                'last_reset_date' => $today,
            ]);
    }
}

