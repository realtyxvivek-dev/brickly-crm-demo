<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use App\Models\Prospect;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Lead;
use App\Models\Role;

class Target extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'target_month',
        'target_visits',
        'target_meetings',
        'target_closers',
        'target_prospects_extract',
        'target_prospects_verified',
        'target_calls',
        'manager_target_calculation_logic',
        'manager_junior_scope',
        'incentive_per_closer',
        'incentive_per_visit',
    ];

    protected $casts = [
        'target_month' => 'date',
        'target_visits' => 'integer',
        'target_meetings' => 'integer',
        'target_closers' => 'integer',
        'target_prospects_extract' => 'integer',
        'target_prospects_verified' => 'integer',
        'target_calls' => 'integer',
        'manager_target_calculation_logic' => 'string',
        'manager_junior_scope' => 'string',
        'incentive_per_closer' => 'decimal:2',
        'incentive_per_visit' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get count of prospects extracted by user in target month
     */
    public function getProspectsExtractedCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        return Prospect::where('telecaller_id', $this->user_id)
            ->whereYear('created_at', $targetMonth->year)
            ->whereMonth('created_at', $targetMonth->month)
            ->count();
    }

    /**
     * Get count of prospects verified by user in target month
     */
    public function getProspectsVerifiedCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        return Prospect::where('telecaller_id', $this->user_id)
            ->where('verification_status', 'verified')
            ->whereYear('verified_at', $targetMonth->year)
            ->whereMonth('verified_at', $targetMonth->month)
            ->count();
    }

    /**
     * Get count of calls completed by user in target month
     */
    public function getCallsCompletedCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        return \App\Models\TelecallerTask::where('assigned_to', $this->user_id)
            ->where('task_type', 'calling')
            ->where('status', 'completed')
            ->whereYear('completed_at', $targetMonth->year)
            ->whereMonth('completed_at', $targetMonth->month)
            ->count();
    }

    /**
     * Get progress percentage for a specific field
     */
    public function getProgressPercentage(string $field): float
    {
        $targetField = 'target_' . $field;
        $targetValue = $this->$targetField ?? 0;

        if ($targetValue == 0) {
            return 0;
        }

        $actualValue = match($field) {
            'prospects_extract' => $this->getProspectsExtractedCount(),
            'prospects_verified' => $this->getProspectsVerifiedCount(),
            'calls' => $this->getCallsCompletedCount(),
            default => 0,
        };

        return min(100, round(($actualValue / $targetValue) * 100, 2));
    }

    /**
     * Get count of meetings completed (verified) by user in target month
     * Excludes rescheduled meetings from achievement counts
     */
    public function getMeetingsCompletedCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        
        // For managers, check multiple ways meetings can be linked:
        // 1. Direct: created_by or assigned_to
        // 2. Through lead: if meeting has lead_id, check if lead is assigned to manager
        $query = Meeting::where(function($q) {
                $q->where('created_by', $this->user_id)
                  ->orWhere('assigned_to', $this->user_id)
                  // Also check if meeting's lead is assigned to this manager
                  ->orWhereHas('lead', function($leadQuery) {
                      $leadQuery->whereHas('activeAssignments', function($assignmentQuery) {
                          $assignmentQuery->where('assigned_to', $this->user_id);
                      });
                  });
            })
            ->where('status', 'completed')
            ->where('is_converted', false) // Exclude converted meetings
            ->whereNotNull('completed_at') // Ensure completed_at is not null
            ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
            ->where(function ($q) {
                $q->whereNull('verification_status')
                  ->orWhere('verification_status', '!=', 'rejected');
            });
        
        // Only filter by is_rescheduled if column exists
        if (SchemaFacade::hasColumn('meetings', 'is_rescheduled')) {
            $query->where('is_rescheduled', false);
        }
        
        return $query->count();
    }

    /**
     * Get count of site visits completed (verified) by user in target month
     * Excludes rescheduled site visits from achievement counts
     */
    public function getSiteVisitsCompletedCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        
        // For managers, check multiple ways site visits can be linked:
        // 1. Direct: created_by or assigned_to
        // 2. Through lead: if site visit has lead_id, check if lead is assigned to manager
        $query = SiteVisit::where(function($q) {
                $q->where('created_by', $this->user_id)
                  ->orWhere('assigned_to', $this->user_id)
                  // Also check if site visit's lead is assigned to this manager
                  ->orWhereHas('lead', function($leadQuery) {
                      $leadQuery->whereHas('activeAssignments', function($assignmentQuery) {
                          $assignmentQuery->where('assigned_to', $this->user_id);
                      });
                  });
            })
            ->where('verification_status', 'verified')
            ->whereNotNull('verified_at') // Ensure verified_at is not null
            ->whereBetween('verified_at', [$startOfMonth, $endOfMonth]);
        
        // Only filter by is_rescheduled if column exists
        if (SchemaFacade::hasColumn('site_visits', 'is_rescheduled')) {
            $query->where('is_rescheduled', false);
        }
        
        return $query->count();
    }

    /**
     * Get count of closers (verified closers) by user in target month
     * Excludes rescheduled site visits from achievement counts
     */
    public function getClosersCount(): int
    {
        $targetMonth = \Carbon\Carbon::parse($this->target_month);
        $startOfMonth = $targetMonth->copy()->startOfMonth();
        $endOfMonth = $targetMonth->copy()->endOfMonth();
        
        // Closers = Site visits with closer_status = 'verified'
        // For managers, check multiple ways site visits can be linked:
        // 1. Direct: created_by or assigned_to
        // 2. Through lead: if site visit has lead_id, check if lead is assigned to manager
        $query = SiteVisit::where(function($q) {
                $q->where('created_by', $this->user_id)
                  ->orWhere('assigned_to', $this->user_id)
                  // Also check if site visit's lead is assigned to this manager
                  ->orWhereHas('lead', function($leadQuery) {
                      $leadQuery->whereHas('activeAssignments', function($assignmentQuery) {
                          $assignmentQuery->where('assigned_to', $this->user_id);
                      });
                  });
            })
            ->whereIn('closer_status', ['approved', 'verified'])
            ->where(function ($dateQuery) use ($startOfMonth, $endOfMonth) {
                if (SchemaFacade::hasColumn('site_visits', 'actual_closer_date')) {
                    $dateQuery->whereBetween('actual_closer_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                        ->orWhere(function ($legacyQuery) use ($startOfMonth, $endOfMonth) {
                            $legacyQuery->whereNull('actual_closer_date')
                                ->whereNotNull('closer_verified_at')
                                ->whereBetween('closer_verified_at', [$startOfMonth, $endOfMonth]);
                        });
                    return;
                }

                $dateQuery->whereNotNull('closer_verified_at')
                    ->whereBetween('closer_verified_at', [$startOfMonth, $endOfMonth]);
            });
        
        // Only filter by is_rescheduled if column exists
        if (SchemaFacade::hasColumn('site_visits', 'is_rescheduled')) {
            $query->where('is_rescheduled', false);
        }
        
        return $query->count();
    }

    /**
     * Get progress percentage for meetings, visits, closers
     */
    public function getAchievementProgress(string $type): array
    {
        $targetField = match($type) {
            'meetings' => 'target_meetings',
            'visits' => 'target_visits',
            'closers' => 'target_closers',
            default => null,
        };

        if (!$targetField) {
            return ['target' => 0, 'achieved' => 0, 'percentage' => 0];
        }

        // For managers with calculation logic, use calculated target
        $target = 0;
        if ($this->usesTeamCalculatedTargets() && in_array($type, ['meetings', 'visits', 'closers'], true)) {
            $target = $this->getFinalTargetValue($type);
        } else {
            $target = $this->$targetField ?? 0;
        }
        
        $achieved = match($type) {
            'meetings' => $this->getMeetingsCompletedCount(),
            'visits' => $this->getSiteVisitsCompletedCount(),
            'closers' => $this->getClosersCount(),
            default => 0,
        };

        $percentage = $target > 0 ? min(100, round(($achieved / $target) * 100, 2)) : 0;

        return [
            'target' => $target,
            'achieved' => $achieved,
            'percentage' => $percentage,
        ];
    }

    /**
     * Get all progress data
     */
    public function getProgressData(): array
    {
        return [
            'prospects_extract' => [
                'target' => $this->target_prospects_extract,
                'actual' => $this->getProspectsExtractedCount(),
                'percentage' => $this->getProgressPercentage('prospects_extract'),
            ],
            'prospects_verified' => [
                'target' => $this->target_prospects_verified,
                'actual' => $this->getProspectsVerifiedCount(),
                'percentage' => $this->getProgressPercentage('prospects_verified'),
            ],
            'calls' => [
                'target' => $this->target_calls,
                'actual' => $this->getCallsCompletedCount(),
                'percentage' => $this->getProgressPercentage('calls'),
            ],
            'meetings' => $this->getAchievementProgress('meetings'),
            'visits' => $this->getAchievementProgress('visits'),
            'closers' => $this->getAchievementProgress('closers'),
        ];
    }

    /**
     * Calculate daily target from monthly target
     */
    public function getDailyCallsTarget(): float
    {
        $daysInMonth = \Carbon\Carbon::now()->daysInMonth;
        return $this->target_calls > 0 ? round($this->target_calls / $daysInMonth, 2) : 0;
    }

    /**
     * Calculate daily target from monthly target for prospects
     */
    public function getDailyProspectsTarget(): float
    {
        $daysInMonth = \Carbon\Carbon::now()->daysInMonth;
        return $this->target_prospects_verified > 0 ? round($this->target_prospects_verified / $daysInMonth, 2) : 0;
    }

    /**
     * Calculate weekly target from monthly target for visits
     */
    public function getWeeklyVisitsTarget(): float
    {
        $daysInMonth = \Carbon\Carbon::now()->daysInMonth;
        // More accurate: (monthly * 7) / days_in_month
        return $this->target_visits > 0 ? round(($this->target_visits * 7) / $daysInMonth, 2) : 0;
    }

    /**
     * Get count of completed calling tasks for a specific day (with CNP logic)
     * CNP tasks: Only count as 1 completed task when 2 calls happened on the same day
     */
    public function getDailyCallsCompletedCount($date = null): int
    {
        $date = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::today();
        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $tasks = \App\Models\TelecallerTask::where('assigned_to', $this->user_id)
            ->where('task_type', 'calling')
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfDay, $endOfDay])
            ->with('lead')
            ->get();

        $count = 0;
        $processedCnpLeads = [];

        foreach ($tasks as $task) {
            // For CNP tasks, verify 2 calls on same day
            if ($task->outcome === 'cnp') {
                $leadId = $task->lead_id;
                
                // Skip if we already counted this lead's CNP for today
                if (isset($processedCnpLeads[$leadId])) {
                    continue;
                }

                // Check if both CNP calls happened on same day
                $cnpTasksSameDay = \App\Models\TelecallerTask::where('lead_id', $leadId)
                    ->where('outcome', 'cnp')
                    ->where('status', 'completed')
                    ->whereBetween('completed_at', [$startOfDay, $endOfDay])
                    ->count();

                // Only count if 2 or more CNP calls on same day
                if ($cnpTasksSameDay >= 2) {
                    $count++;
                    $processedCnpLeads[$leadId] = true;
                }
            } else {
                // Non-CNP tasks count normally
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get count of verified prospects for a specific day
     */
    public function getDailyProspectsVerifiedCount($date = null): int
    {
        $date = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::today();
        return Prospect::where('telecaller_id', $this->user_id)
            ->where('verification_status', 'verified')
            ->whereDate('verified_at', $date->format('Y-m-d'))
            ->count();
    }

    /**
     * Get count of completed visits for current week
     */
    public function getWeeklyVisitsCompletedCount($weekStart = null): int
    {
        $weekStart = $weekStart ? \Carbon\Carbon::parse($weekStart) : \Carbon\Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        return \App\Models\SiteVisit::where(function($q) {
                $q->where('assigned_to', $this->user_id)
                  ->orWhere('created_by', $this->user_id);
            })
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$weekStart, $weekEnd])
            ->count();
    }

    /**
     * Get manager's juniors based on junior scope
     */
    public function getManagerJuniors(): \Illuminate\Support\Collection
    {
        // Ensure user relationship is loaded
        if (!$this->relationLoaded('user')) {
            $this->load('user.role');
        }
        
        if (!$this->user || !$this->supportsTeamTargetAggregation()) {
            return collect();
        }
        
        $manager = $this->user;
        $juniors = collect();
        
        if ($this->manager_junior_scope === 'executives_only') {
            $juniors = \App\Models\User::where('manager_id', $manager->id)
                ->whereHas('role', function($q) {
                    $q->where('slug', \App\Models\Role::ASSISTANT_SALES_MANAGER);
                })
                ->get();
        } elseif ($this->manager_junior_scope === 'executives_and_telecallers') {
            $juniors = \App\Models\User::where('manager_id', $manager->id)
                ->whereHas('role', function($q) {
                    $q->whereIn('slug', [\App\Models\Role::ASSISTANT_SALES_MANAGER, \App\Models\Role::SALES_EXECUTIVE]);
                })
                ->get();
        }
        
        return $juniors;
    }

    /**
     * Calculate manager target based on selected logic
     * @param string $targetType - 'visits', 'meetings', etc.
     * @return int - Calculated target value
     */
    public function calculateManagerTarget(string $targetType = 'visits'): int
    {
        // Ensure user relationship is loaded
        if (!$this->relationLoaded('user')) {
            $this->load('user.role');
        }
        
        if (!$this->user || !$this->supportsTeamTargetAggregation() || !$this->manager_target_calculation_logic) {
            return $this->{'target_' . $targetType} ?? 0;
        }
        
        if ($this->manager_target_calculation_logic === 'juniors_sum') {
            // Logic 1: Sum of juniors' targets
            $juniors = $this->getManagerJuniors();
            $sum = 0;
            
            foreach ($juniors as $junior) {
                $juniorTarget = Target::where('user_id', $junior->id)
                    ->whereYear('target_month', $this->target_month->year)
                    ->whereMonth('target_month', $this->target_month->month)
                    ->first();
                
                if ($juniorTarget) {
                    $sum += $juniorTarget->{'target_' . $targetType} ?? 0;
                }
            }
            
            return $sum;
        } elseif ($this->manager_target_calculation_logic === 'individual_plus_team') {
            // Logic 2: Individual + Team consolidated
            $individual = $this->{'target_' . $targetType} ?? 0;
            
            $juniors = $this->getManagerJuniors();
            $teamSum = 0;
            
            foreach ($juniors as $junior) {
                $juniorTarget = Target::where('user_id', $junior->id)
                    ->whereYear('target_month', $this->target_month->year)
                    ->whereMonth('target_month', $this->target_month->month)
                    ->first();
                
                if ($juniorTarget) {
                    $teamSum += $juniorTarget->{'target_' . $targetType} ?? 0;
                }
            }
            
            return $individual + $teamSum;
        }
        
        return $this->{'target_' . $targetType} ?? 0;
    }

    public function supportsTeamTargetAggregation(): bool
    {
        if (!$this->relationLoaded('user')) {
            $this->load('user.role');
        }

        return (bool) $this->user
            && ($this->user->isSalesManager() || $this->user->isSeniorManager());
    }

    public function usesTeamCalculatedTargets(): bool
    {
        return $this->supportsTeamTargetAggregation()
            && filled($this->manager_target_calculation_logic);
    }

    public function getSelfTargetValue(string $targetType): int
    {
        return (int) ($this->{'target_' . $targetType} ?? 0);
    }

    public function getTeamTargetValue(string $targetType): int
    {
        if (!$this->usesTeamCalculatedTargets()) {
            return 0;
        }

        return max(0, $this->calculateManagerTarget($targetType) - $this->getSelfTargetValue($targetType));
    }

    public function getFinalTargetValue(string $targetType): int
    {
        if (!$this->usesTeamCalculatedTargets()) {
            return $this->getSelfTargetValue($targetType);
        }

        return $this->calculateManagerTarget($targetType);
    }

    public function getTargetBreakdown(string $targetType): array
    {
        return [
            'self' => $this->getSelfTargetValue($targetType),
            'team' => $this->getTeamTargetValue($targetType),
            'final' => $this->getFinalTargetValue($targetType),
            'uses_team_sum' => $this->usesTeamCalculatedTargets(),
        ];
    }
}
