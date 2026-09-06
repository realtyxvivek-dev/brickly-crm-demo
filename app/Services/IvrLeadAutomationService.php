<?php

namespace App\Services;

use App\Models\IvrLeadAutomationAudit;
use App\Models\IvrLeadAutomationConfig;
use App\Models\IvrLeadAutomationPoolUser;
use App\Models\IvrLeadAutomationReceiverOverride;
use App\Models\Lead;
use App\Models\McubeWebhookLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IvrLeadAutomationService
{
    public function __construct(
        private readonly TelecallerStatusService $telecallerStatusService,
        private readonly TelecallerLimitService $telecallerLimitService,
        private readonly UserStatusService $userStatusService,
    ) {
    }

    public function getOrCreateConfig(): IvrLeadAutomationConfig
    {
        $config = IvrLeadAutomationConfig::query()->first();

        if ($config) {
            return $config;
        }

        return IvrLeadAutomationConfig::create([
            'is_enabled' => true,
            'default_mode' => IvrLeadAutomationConfig::MODE_RECEIVER,
            'distribution_method' => IvrLeadAutomationConfig::METHOD_RECEIVER,
            'fallback_mode' => IvrLeadAutomationConfig::FALLBACK_RECEIVER,
            'receiver_assignment_allowed' => true,
        ]);
    }

    public function getConfigForUi(): IvrLeadAutomationConfig
    {
        return $this->getOrCreateConfig()->load([
            'poolUsers.user.role',
            'receiverOverrides.receiver.role',
            'receiverOverrides.teamManager.role',
            'receiverOverrides.fixedUser.role',
            'receiverOverrides.fallbackUser.role',
            'fallbackUser.role',
            'fixedUser.role',
            'defaultTeamManager.role',
        ]);
    }

    public function getDashboardSummary(): array
    {
        $config = $this->getConfigForUi();
        $recentAudits = IvrLeadAutomationAudit::query()
            ->with(['lead', 'receiver', 'assignedUser'])
            ->latest('processed_at')
            ->limit(8)
            ->get();

        return [
            'pool_count' => $config->poolUsers->where('is_active', true)->count(),
            'override_count' => $config->receiverOverrides->where('is_enabled', true)->count(),
            'recent_count' => $recentAudits->count(),
            'recent_audits' => $recentAudits,
        ];
    }

    public function getAssignableUsers(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_EXECUTIVE,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
            ]))
            ->orderBy('name')
            ->get();
    }

    public function getReceiverUsers(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::ADMIN,
                Role::CRM,
                Role::SALES_EXECUTIVE,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
            ]))
            ->orderBy('name')
            ->get();
    }

    public function saveConfiguration(array $data, int $actorId): IvrLeadAutomationConfig
    {
        $this->validatePercentageConfiguration($data);

        return DB::transaction(function () use ($data, $actorId) {
            $config = $this->getOrCreateConfig();
            $config->update([
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'default_mode' => $data['default_mode'],
                'distribution_method' => $data['distribution_method'],
                'fallback_mode' => $data['fallback_mode'],
                'fallback_user_id' => $data['fallback_user_id'] ?? null,
                'fixed_user_id' => $data['fixed_user_id'] ?? null,
                'default_team_manager_user_id' => $data['default_team_manager_user_id'] ?? null,
                'receiver_assignment_allowed' => (bool) ($data['receiver_assignment_allowed'] ?? false),
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actorId,
                'created_by' => $config->created_by ?: $actorId,
            ]);

            $config->poolUsers()->delete();
            foreach (array_values($data['pool_users'] ?? []) as $index => $row) {
                if (empty($row['user_id'])) {
                    continue;
                }

                $config->poolUsers()->create([
                    'user_id' => (int) $row['user_id'],
                    'allocation_percentage' => $row['allocation_percentage'] !== null && $row['allocation_percentage'] !== ''
                        ? $row['allocation_percentage']
                        : null,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);
            }

            $config->receiverOverrides()->delete();
            foreach (array_values($data['receiver_overrides'] ?? []) as $row) {
                if (empty($row['user_id'])) {
                    continue;
                }

                $config->receiverOverrides()->create([
                    'user_id' => (int) $row['user_id'],
                    'is_enabled' => (bool) ($row['is_enabled'] ?? false),
                    'can_assign_to_self' => (bool) ($row['can_assign_to_self'] ?? false),
                    'target_type' => $row['target_type'],
                    'distribution_method' => $row['distribution_method'],
                    'team_manager_user_id' => $row['team_manager_user_id'] ?? null,
                    'fixed_user_id' => $row['fixed_user_id'] ?? null,
                    'fallback_mode' => $row['fallback_mode'] ?? null,
                    'fallback_user_id' => $row['fallback_user_id'] ?? null,
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            return $config->fresh([
                'poolUsers.user.role',
                'receiverOverrides.receiver.role',
                'receiverOverrides.teamManager.role',
                'receiverOverrides.fixedUser.role',
                'receiverOverrides.fallbackUser.role',
                'fallbackUser.role',
                'fixedUser.role',
                'defaultTeamManager.role',
            ]);
        });
    }

    public function routeLead(
        Lead $lead,
        User $receiver,
        ?McubeWebhookLog $webhookLog = null
    ): array {
        $config = IvrLeadAutomationConfig::query()
            ->with([
                'poolUsers.user.role',
                'receiverOverrides.receiver.role',
                'receiverOverrides.teamManager.role',
                'receiverOverrides.fixedUser.role',
                'receiverOverrides.fallbackUser.role',
            ])
            ->first();

        if (!$config || !$config->is_enabled) {
            return $this->buildHardDefaultResult($lead, $receiver, $webhookLog, $config);
        }

        $override = $config->receiverOverrides
            ->first(fn (IvrLeadAutomationReceiverOverride $row) => $row->is_enabled && (int) $row->user_id === (int) $receiver->id);

        $ruleSource = $override ? 'receiver_override' : 'global_default';
        $targetType = $override?->target_type ?? $this->mapModeToTargetType($config->default_mode);
        $strategy = $override?->distribution_method ?? $config->distribution_method;
        $fallbackMode = $override?->fallback_mode ?: $config->fallback_mode;
        $fallbackUserId = $override?->fallback_user_id ?: $config->fallback_user_id;
        $teamManagerUserId = $override?->team_manager_user_id ?: $config->default_team_manager_user_id;
        $fixedUserId = $override?->fixed_user_id ?: $config->fixed_user_id;
        $receiverAssignmentAllowed = $override
            ? (bool) $override->can_assign_to_self
            : (bool) $config->receiver_assignment_allowed;

        $selectedUser = match ($targetType) {
            IvrLeadAutomationReceiverOverride::TARGET_SELF => $receiverAssignmentAllowed
                ? $receiver
                : null,
            IvrLeadAutomationReceiverOverride::TARGET_FIXED_USER => $this->resolveFixedUser($fixedUserId),
            IvrLeadAutomationReceiverOverride::TARGET_TEAM => $this->selectFromCandidates(
                $this->resolveTeamCandidates($teamManagerUserId ?: $receiver->id),
                $strategy,
                $config,
                $override
            ),
            IvrLeadAutomationReceiverOverride::TARGET_POOL => $this->selectFromCandidates(
                $this->resolvePoolCandidates($config),
                $strategy,
                $config,
                $override
            ),
            default => $receiverAssignmentAllowed ? $receiver : null,
        };

        $leadOffFallbackUsed = false;
        if ($selectedUser && $this->userStatusService->isUserAbsent($selectedUser->id)) {
            $leadOffFallback = $this->userStatusService->leadOffFallbackUser($selectedUser->id);
            if ($leadOffFallback && $this->isUserEligible($leadOffFallback)) {
                $selectedUser = $leadOffFallback;
                $reason = 'Lead Off fallback recipient selected.';
                $leadOffFallbackUsed = true;
            }
        }

        if ($selectedUser && !$this->isUserEligible($selectedUser)) {
            $selectedUserWasReceiver = (int) $receiver->id === (int) $selectedUser->id;
            $selectedUser = null;
            $reason = $selectedUserWasReceiver
                ? 'Receiver is lead-off'
                : 'Selected IVR assignee is lead-off or unavailable.';
        }

        $reason ??= null;
        $fallbackUsed = $leadOffFallbackUsed;
        if (!$selectedUser) {
            $reason ??= $this->userStatusService->isUserAbsent($receiver->id)
                ? 'Receiver is lead-off'
                : 'No eligible assignee found for selected IVR routing rule.';
            $fallbackUsed = true;
            $selectedUser = match ($fallbackMode) {
                IvrLeadAutomationConfig::FALLBACK_BACKUP_USER => $this->resolveFixedUser($fallbackUserId),
                IvrLeadAutomationConfig::FALLBACK_UNASSIGNED => null,
                default => null,
            };

            if ($selectedUser && !$this->isUserEligible($selectedUser)) {
                $selectedUser = null;
            }
        }

        $audit = IvrLeadAutomationAudit::create([
            'config_id' => $config->id,
            'receiver_override_id' => $override?->id,
            'webhook_log_id' => $webhookLog?->id,
            'lead_id' => $lead->id,
            'receiver_user_id' => $receiver->id,
            'assigned_user_id' => $selectedUser?->id,
            'rule_source' => $ruleSource,
            'target_type' => $targetType,
            'strategy_used' => $strategy,
            'fallback_used' => $fallbackUsed,
            'fallback_mode' => $fallbackUsed ? $fallbackMode : null,
            'reason' => $reason,
            'meta' => [
                'receiver_assignment_allowed' => $receiverAssignmentAllowed,
                'team_manager_user_id' => $teamManagerUserId,
                'fixed_user_id' => $fixedUserId,
            ],
            'processed_at' => now(),
        ]);

        return [
            'config' => $config,
            'override' => $override,
            'assigned_user' => $selectedUser,
            'rule_source' => $ruleSource,
            'target_type' => $targetType,
            'strategy_used' => $strategy,
            'fallback_used' => $fallbackUsed,
            'fallback_mode' => $fallbackUsed ? $fallbackMode : null,
            'reason' => $reason,
            'audit' => $audit,
        ];
    }

    private function buildHardDefaultResult(Lead $lead, User $receiver, ?McubeWebhookLog $webhookLog, ?IvrLeadAutomationConfig $config): array
    {
        $receiverIsEligible = $this->isUserEligible($receiver);
        $fallbackUser = !$receiverIsEligible
            ? $this->userStatusService->leadOffFallbackUser($receiver->id)
            : null;
        $fallbackIsEligible = $fallbackUser && $this->isUserEligible($fallbackUser);
        $assignedUser = $receiverIsEligible ? $receiver : ($fallbackIsEligible ? $fallbackUser : null);

        $audit = IvrLeadAutomationAudit::create([
            'config_id' => $config?->id,
            'webhook_log_id' => $webhookLog?->id,
            'lead_id' => $lead->id,
            'receiver_user_id' => $receiver->id,
            'assigned_user_id' => $assignedUser?->id,
            'rule_source' => 'hard_default',
            'target_type' => IvrLeadAutomationReceiverOverride::TARGET_SELF,
            'strategy_used' => IvrLeadAutomationConfig::METHOD_RECEIVER,
            'fallback_used' => (bool) $fallbackIsEligible,
            'reason' => $receiverIsEligible ? null : ($fallbackIsEligible ? 'Lead Off fallback recipient selected.' : 'Receiver is lead-off'),
            'processed_at' => now(),
        ]);

        return [
            'config' => $config,
            'override' => null,
            'assigned_user' => $assignedUser,
            'rule_source' => 'hard_default',
            'target_type' => IvrLeadAutomationReceiverOverride::TARGET_SELF,
            'strategy_used' => IvrLeadAutomationConfig::METHOD_RECEIVER,
            'fallback_used' => (bool) $fallbackIsEligible,
            'fallback_mode' => $fallbackIsEligible ? 'lead_off_user_fallback' : null,
            'reason' => $receiverIsEligible ? null : ($fallbackIsEligible ? 'Lead Off fallback recipient selected.' : 'Receiver is lead-off'),
            'audit' => $audit,
        ];
    }

    private function selectFromCandidates(
        Collection $candidates,
        string $strategy,
        IvrLeadAutomationConfig $config,
        ?IvrLeadAutomationReceiverOverride $override = null
    ): ?User {
        $eligible = $candidates
            ->filter(fn (User $user) => $this->isUserEligible($user))
            ->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        return match ($strategy) {
            IvrLeadAutomationConfig::METHOD_FIRST_AVAILABLE => $eligible
                ->sortBy(fn (User $user) => $this->scoreUserAvailability($user))
                ->first(),
            IvrLeadAutomationConfig::METHOD_PERCENTAGE => $this->pickPercentageUser($eligible, $config),
            IvrLeadAutomationConfig::METHOD_FIXED_USER => $eligible->first(),
            default => $this->pickRoundRobinUser($eligible, $config, $override),
        };
    }

    private function resolvePoolCandidates(IvrLeadAutomationConfig $config): Collection
    {
        return $config->poolUsers
            ->where('is_active', true)
            ->map(fn (IvrLeadAutomationPoolUser $row) => $row->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function resolveTeamCandidates(int $managerId): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->where('manager_id', $managerId)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', [
                Role::SALES_EXECUTIVE,
                Role::SALES_MANAGER,
                Role::SENIOR_MANAGER,
                Role::ASSISTANT_SALES_MANAGER,
            ]))
            ->orderBy('name')
            ->get();
    }

    private function resolveFixedUser(?int $userId): ?User
    {
        if (!$userId) {
            return null;
        }

        $user = User::with('role')->find($userId);

        return $user && $this->isUserEligible($user) ? $user : null;
    }

    private function pickRoundRobinUser(
        Collection $eligible,
        IvrLeadAutomationConfig $config,
        ?IvrLeadAutomationReceiverOverride $override
    ): ?User {
        $lastUserId = $override?->last_round_robin_user_id ?: $config->last_round_robin_user_id;
        $eligibleIds = $eligible->pluck('id')->values();
        $startIndex = 0;

        if ($lastUserId && $eligibleIds->contains($lastUserId)) {
            $startIndex = ($eligibleIds->search($lastUserId) + 1) % $eligibleIds->count();
        }

        $selected = $eligible->get($startIndex) ?: $eligible->first();
        if ($selected) {
            if ($override) {
                $override->forceFill(['last_round_robin_user_id' => $selected->id])->save();
            } else {
                $config->forceFill(['last_round_robin_user_id' => $selected->id])->save();
            }
        }

        return $selected;
    }

    private function pickPercentageUser(Collection $eligible, IvrLeadAutomationConfig $config): ?User
    {
        $poolRows = $config->poolUsers->keyBy('user_id');
        $weighted = [];

        foreach ($eligible as $user) {
            $percentage = (float) ($poolRows[$user->id]->allocation_percentage ?? 0);
            $weight = max(0, (int) round($percentage * 100));
            for ($i = 0; $i < $weight; $i++) {
                $weighted[] = $user->id;
            }
        }

        if (empty($weighted)) {
            return null;
        }

        $selectedId = $weighted[array_rand($weighted)];

        return $eligible->firstWhere('id', $selectedId);
    }

    private function isUserEligible(User $user): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($this->userStatusService->isUserAbsent($user->id)) {
            return false;
        }

        if ($user->isSalesExecutive()) {
            $availability = $this->telecallerStatusService->canReceiveAssignment($user->id);
            $limitCheck = $this->telecallerLimitService->checkDailyLimits($user->id);

            return (bool) ($availability['can_receive'] ?? false) && (bool) ($limitCheck['is_allowed'] ?? false);
        }

        return true;
    }

    private function scoreUserAvailability(User $user): int
    {
        if ($user->isSalesExecutive()) {
            $availability = $this->telecallerStatusService->canReceiveAssignment($user->id);
            return (int) ($availability['pending_count'] ?? PHP_INT_MAX);
        }

        return (int) $user->activeAssignedLeads()->count();
    }

    private function mapModeToTargetType(string $mode): string
    {
        return match ($mode) {
            IvrLeadAutomationConfig::MODE_RECEIVER_TEAM => IvrLeadAutomationReceiverOverride::TARGET_TEAM,
            IvrLeadAutomationConfig::MODE_CUSTOM_POOL => IvrLeadAutomationReceiverOverride::TARGET_POOL,
            IvrLeadAutomationConfig::MODE_FIXED_USER => IvrLeadAutomationReceiverOverride::TARGET_FIXED_USER,
            default => IvrLeadAutomationReceiverOverride::TARGET_SELF,
        };
    }

    private function validatePercentageConfiguration(array $data): void
    {
        if (($data['distribution_method'] ?? null) === IvrLeadAutomationConfig::METHOD_PERCENTAGE) {
            $total = collect($data['pool_users'] ?? [])
                ->filter(fn ($row) => !empty($row['user_id']))
                ->sum(fn ($row) => (float) ($row['allocation_percentage'] ?? 0));

            if ((float) $total !== 100.0) {
                throw ValidationException::withMessages([
                    'pool_users' => 'Pool user percentages must total exactly 100.',
                ]);
            }
        }
    }
}
