<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\SourceAutomationRuleUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class SourceAutomationService
{
    protected NotificationService $notificationService;
    protected LeadOwnerTaskService $leadOwnerTaskService;

    public function __construct(
        NotificationService $notificationService,
        LeadOwnerTaskService $leadOwnerTaskService
    )
    {
        $this->notificationService = $notificationService;
        $this->leadOwnerTaskService = $leadOwnerTaskService;
    }

    /**
     * Main entry point: find matching rule for a source and assign the lead.
     * Called from webhook controllers after lead creation.
     */
    public function assignFromSource(Lead $lead, string $source, ?int $fbFormId = null, ?int $googleSheetConfigId = null, ?string $sourceId = null): bool
    {
        try {
            $source = $this->normalizeAutomationSource($source);
            $sourceId = $sourceId ?: ($fbFormId ? (string) $fbFormId : ($googleSheetConfigId ? (string) $googleSheetConfigId : null));
            $rule = $this->findRule($source, $fbFormId, $googleSheetConfigId, $sourceId);
            if (!$rule) {
                Log::info("SourceAutomationService: no active rule found", [
                    'source'                  => $source,
                    'fb_form_id'              => $fbFormId,
                    'google_sheet_config_id'  => $googleSheetConfigId,
                    'lead_id'                 => $lead->id,
                ]);
                return false;
            }

            $userId = $this->pickUser($rule);

            if (!$userId && $rule->fallback_user_id) {
                $userId = $this->eligibleUserId($rule->fallback_user_id, $rule);
            }

            if (!$userId) {
                Log::warning('SourceAutomationService: no eligible user because selected users are lead-off or unavailable', [
                    'rule_id' => $rule->id,
                    'lead_id' => $lead->id,
                ]);
                return false;
            }

            $assignedUser = User::with('role')->find($userId);
            if (!$assignedUser) {
                Log::warning("SourceAutomationService: selected user missing", [
                    'rule_id' => $rule->id,
                    'lead_id' => $lead->id,
                    'user_id' => $userId,
                ]);
                return false;
            }

            DB::beginTransaction();

            // Create LeadAssignment record
            $lead->assignments()->update(['is_active' => false, 'unassigned_at' => now()]);
            LeadAssignment::create([
                'lead_id'           => $lead->id,
                'assigned_to'       => $userId,
                'assigned_by'       => $rule->created_by,
                'assignment_type'   => 'primary',
                'assignment_method' => $rule->effectiveDistributionMethod(),
                'assigned_at'       => now(),
                'is_active'         => true,
            ]);

            // Mark leads routed to HR through hiring automation as hiring candidates.
            if ($this->isHiringUser($assignedUser) && $source === 'facebook_lead_ads') {
                $lead->update([
                    'is_hiring_candidate' => true,
                    'hiring_status' => $lead->hiring_status ?: 'new',
                ]);
                $lead->refresh();
            }

            // Increment daily counter for rule user
            $this->incrementRuleUserCount($rule, $userId);

            // Fire LeadAssigned event → CreateTelecallerTask listener auto-runs
            if (!$lead->is_blocked && $rule->taskEnabled()) {
                try {
                    Event::dispatch(new LeadAssigned($lead, $userId, $rule->created_by));
                    $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                        $lead,
                        $assignedUser,
                        (int) $rule->created_by
                    );

                    // Send chatbot notification
                    if ($assignedUser && $rule->notificationEnabled()) {
                        $leadUrl = $this->getLeadActionUrl($assignedUser, $lead);
                        $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                    }
                } catch (\Exception $e) {
                    Log::error("SourceAutomationService: event dispatch failed", [
                        'lead_id' => $lead->id,
                        'error'   => $e->getMessage(),
                    ]);
                }
            } else {
                // No task creation — just send notification
                try {
                    if ($assignedUser && $rule->notificationEnabled()) {
                        $leadUrl = $this->getLeadActionUrl($assignedUser, $lead);
                        $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                    }
                } catch (\Exception $e) {
                    // Non-critical
                }
            }

            DB::commit();

            Log::info("SourceAutomationService: lead assigned", [
                'lead_id'    => $lead->id,
                'user_id'    => $userId,
                'rule_id'    => $rule->id,
                'source'     => $source,
                'method'     => $rule->effectiveDistributionMethod(),
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("SourceAutomationService: assignment failed", [
                'lead_id' => $lead->id,
                'source'  => $source,
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Find matching rule by priority:
     * 1. source + specific form/sheet exact match
     * 2. source match (no specific form/sheet)
     * 3. source = 'all' catch-all
     */
    protected function findRule(string $source, ?int $fbFormId = null, ?int $googleSheetConfigId = null, ?string $sourceId = null): ?SourceAutomationRule
    {
        $hasGenericSourceColumns = \Illuminate\Support\Facades\Schema::hasColumn('source_automation_rules', 'source_type')
            && \Illuminate\Support\Facades\Schema::hasColumn('source_automation_rules', 'source_id');

        if (
            $fbFormId
            && \Illuminate\Support\Facades\Schema::hasTable('source_automation_rule_forms')
        ) {
            $hasSourceTypeColumn = \Illuminate\Support\Facades\Schema::hasColumn('source_automation_rules', 'source_type');
            $rule = SourceAutomationRule::whereHas('fbForms', fn ($query) => $query->where('fb_forms.id', $fbFormId))
                ->where('is_active', true)
                ->where(function ($query) use ($source, $hasSourceTypeColumn) {
                    $query->where('source', $source);

                    if ($hasSourceTypeColumn) {
                        $query->orWhere('source_type', $source);
                    }
                })
                ->first();
            if ($rule) return $rule;
        }

        if ($hasGenericSourceColumns && $sourceId !== null && $sourceId !== '') {
            $rule = SourceAutomationRule::where('source_type', $source)
                ->where('source_id', (string) $sourceId)
                ->where('is_active', true)
                ->first();
            if ($rule) return $rule;
        }

        // Priority 1: exact match with specific form/sheet
        if ($fbFormId) {
            $rule = SourceAutomationRule::where('source', $source)
                ->where('fb_form_id', $fbFormId)
                ->where('is_active', true)
                ->first();
            if ($rule) return $rule;
        }

        if ($googleSheetConfigId) {
            $rule = SourceAutomationRule::where('source', $source)
                ->where('google_sheet_config_id', $googleSheetConfigId)
                ->where('is_active', true)
                ->first();
            if ($rule) return $rule;
        }

        // Priority 2: source match, no specific form/sheet
        if ($hasGenericSourceColumns) {
            $rule = SourceAutomationRule::where('source_type', $source)
                ->where(function ($query) {
                    $query->whereNull('source_id')->orWhere('source_id', '');
                })
                ->where('is_active', true)
                ->first();
            if ($rule) return $rule;
        }

        $legacySource = $source === 'ivr' ? 'mcube' : $source;
        $rule = SourceAutomationRule::where('source', $legacySource)
            ->whereNull('fb_form_id')
            ->whereNull('google_sheet_config_id')
            ->where('is_active', true)
            ->first();
        if ($rule) return $rule;

        // Priority 3: catch-all rule
        return SourceAutomationRule::where('source', 'all')
            ->where('is_active', true)
            ->first();
    }

    /**
     * Pick a user based on the rule's assignment method.
     */
    protected function pickUser(SourceAutomationRule $rule): ?int
    {
        return match($rule->effectiveDistributionMethod()) {
            'round_robin'     => $this->pickRoundRobin($rule),
            'first_available' => $this->pickFirstAvailable($rule),
            'percentage'      => $this->pickByPercentage($rule),
            'single_user'     => $this->eligibleUserId($rule->single_user_id, $rule),
            default           => null,
        };
    }

    /**
     * Round Robin: assign to user with least assigned_count_today (cycling).
     */
    protected function pickRoundRobin(SourceAutomationRule $rule): ?int
    {
        $ruleUsers = SourceAutomationRuleUser::where('rule_id', $rule->id)
            ->with('user')
            ->get()
            ->filter(fn($ru) => $this->ruleUserIsEligible($ru))
            ->each(fn($ru) => $ru->resetIfNewDay())
            ->filter(fn($ru) => $ru->isWithinLimit())
            ->sortBy('assigned_count_today');

        if ($ruleUsers->isEmpty()) return null;

        // Check rule-level daily limit
        if ($rule->daily_limit) {
            $totalToday = $ruleUsers->sum('assigned_count_today');
            if ($totalToday >= $rule->daily_limit) return null;
        }

        return $this->resolvedAutomationUserId($rule, $ruleUsers->first()->user_id);
    }

    /**
     * First Available: user with fewest active lead assignments overall.
     */
    protected function pickFirstAvailable(SourceAutomationRule $rule): ?int
    {
        $ruleUsers = SourceAutomationRuleUser::where('rule_id', $rule->id)
            ->with('user')
            ->get()
            ->filter(fn($ru) => $this->ruleUserIsEligible($ru))
            ->each(fn($ru) => $ru->resetIfNewDay())
            ->filter(fn($ru) => $ru->isWithinLimit());

        if ($ruleUsers->isEmpty()) return null;

        if ($rule->daily_limit) {
            $totalToday = $ruleUsers->sum('assigned_count_today');
            if ($totalToday >= $rule->daily_limit) return null;
        }

        $userIds = $ruleUsers->pluck('user_id')->toArray();

        // Count active assignments per user
        $counts = LeadAssignment::whereIn('assigned_to', $userIds)
            ->where('is_active', true)
            ->selectRaw('assigned_to, COUNT(*) as cnt')
            ->groupBy('assigned_to')
            ->pluck('cnt', 'assigned_to')
            ->toArray();

        // Pick user with minimum active assignments
        $bestUserId = null;
        $bestCount = PHP_INT_MAX;
        foreach ($userIds as $uid) {
            $cnt = $counts[$uid] ?? 0;
            if ($cnt < $bestCount) {
                $bestCount = $cnt;
                $bestUserId = $uid;
            }
        }

        return $this->resolvedAutomationUserId($rule, $bestUserId);
    }

    /**
     * Percentage: weighted random selection.
     */
    protected function pickByPercentage(SourceAutomationRule $rule): ?int
    {
        $ruleUsers = SourceAutomationRuleUser::where('rule_id', $rule->id)
            ->with('user')
            ->get()
            ->filter(fn($ru) => $this->ruleUserIsEligible($ru) && ($ru->percentage ?? 0) > 0)
            ->each(fn($ru) => $ru->resetIfNewDay())
            ->filter(fn($ru) => $ru->isWithinLimit());

        if ($ruleUsers->isEmpty()) return null;

        if ($rule->daily_limit) {
            $totalToday = $ruleUsers->sum('assigned_count_today');
            if ($totalToday >= $rule->daily_limit) return null;
        }

        $totalPercentage = $ruleUsers->sum('percentage');
        if ($totalPercentage <= 0) return null;

        $rand = mt_rand(0, (int)($totalPercentage * 100)) / 100;
        $cumulative = 0;

        foreach ($ruleUsers as $ru) {
            $cumulative += $ru->percentage;
            if ($rand <= $cumulative) {
                return $this->resolvedAutomationUserId($rule, $ru->user_id);
            }
        }

        return $this->resolvedAutomationUserId($rule, $ruleUsers->last()->user_id);
    }

    /**
     * Increment today's count for the selected user in this rule.
     */
    protected function incrementRuleUserCount(SourceAutomationRule $rule, int $userId): void
    {
        $ruleUser = SourceAutomationRuleUser::where('rule_id', $rule->id)
            ->where('user_id', $userId)
            ->first();

        if ($ruleUser) {
            $ruleUser->incrementCount();
        }
    }

    protected function eligibleUserId(?int $userId, ?SourceAutomationRule $rule = null): ?int
    {
        if (!$userId) {
            return null;
        }

        if ($rule) {
            return $this->resolvedAutomationUserId($rule, $userId);
        }

        $user = User::find($userId);

        return $this->userIsEligible($user) ? $user->id : null;
    }

    protected function ruleUserIsEligible(SourceAutomationRuleUser $ruleUser): bool
    {
        return $this->resolvedAutomationUserId($ruleUser->rule, $ruleUser->user_id) !== null;
    }

    protected function userIsEligible(?User $user): bool
    {
        return (bool) ($user && $user->is_active);
    }

    protected function automationUserIsEligible(SourceAutomationRule $rule, ?User $user): bool
    {
        if (!$user || !$user->is_active) {
            return false;
        }

        if (($rule->skip_lead_off_users ?? true) && app(UserStatusService::class)->isUserAbsent($user->id)) {
            return false;
        }

        return true;
    }

    /**
     * Keep the rule selection intact, but hand the lead to a user's configured
     * fallback when that selected user is currently Lead Off.
     */
    protected function resolvedAutomationUserId(SourceAutomationRule $rule, ?int $userId): ?int
    {
        if (!$userId) {
            return null;
        }

        $user = User::find($userId);
        if ($this->automationUserIsEligible($rule, $user)) {
            return $user->id;
        }

        if (!($rule->skip_lead_off_users ?? true) || !$user || !app(UserStatusService::class)->isUserAbsent($user->id)) {
            return null;
        }

        $fallback = app(UserStatusService::class)->leadOffFallbackUser($user->id);

        return $this->automationUserIsEligible($rule, $fallback) ? $fallback?->id : null;
    }

    protected function normalizeAutomationSource(string $source): string
    {
        return match ($source) {
            'mcube', 'call' => 'ivr',
            'csv' => 'manual_import',
            default => $source,
        };
    }

    protected function getLeadActionUrl(User $user, Lead $lead): string
    {
        if ($user->isHrManager() && $lead->is_hiring_candidate) {
            return route('hr-manager.hiring.show', $lead);
        }

        if ($user->isJuniorHr() && $lead->is_hiring_candidate) {
            return route('junior-hr.hiring.show', $lead);
        }

        return route('leads.show', $lead);
    }

    protected function isHiringUser(User $user): bool
    {
        return in_array($user->role->slug ?? null, [Role::HR_MANAGER, Role::JUNIOR_HR], true);
    }
}
