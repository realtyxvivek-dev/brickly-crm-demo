<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Models\VerificationRoutingMapping;
use App\Models\VerificationRoutingSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class VerificationRoutingService
{
    public const WORKFLOW_MEETING = 'meeting';
    public const WORKFLOW_SITE_VISIT = 'site_visit';
    public const WORKFLOW_CLOSER = 'closer';
    public const WORKFLOW_CLOSING = 'closing';

    public const MODE_REPORTING_SENIOR = 'reporting_senior';
    public const MODE_FIXED_ROLE = 'fixed_role';
    public const MODE_FIXED_USER = 'fixed_user';
    public const MODE_CUSTOM_MAPPING = 'custom_mapping';

    public const SOURCE_USER = 'user';
    public const SOURCE_ROLE = 'role';
    public const SOURCE_TEAM = 'team';

    public const VERIFIER_USER = 'user';
    public const VERIFIER_ROLE = 'role';

    public static function workflows(): array
    {
        return [
            self::WORKFLOW_MEETING => 'Meeting Verification',
            self::WORKFLOW_SITE_VISIT => 'Site Visit Verification',
            self::WORKFLOW_CLOSER => 'Closer Verification',
            self::WORKFLOW_CLOSING => 'Closing / KYC Verification',
        ];
    }

    public static function modes(): array
    {
        return [
            self::MODE_REPORTING_SENIOR => 'Reporting Senior',
            self::MODE_FIXED_ROLE => 'Fixed Role',
            self::MODE_FIXED_USER => 'Fixed User',
            self::MODE_CUSTOM_MAPPING => 'Custom Mapping',
        ];
    }

    public function canVerify(User $actor, Model $item, string $workflowType): bool
    {
        return $this->eligibleVerifiers($item, $workflowType)
            ->pluck('id')
            ->contains((int) $actor->id);
    }

    public function userHasVerifierAccess(User $user): bool
    {
        if ($user->isAdmin() || $user->isCrm() || $user->isSalesHead()) {
            return true;
        }

        foreach (array_keys(self::workflows()) as $workflowType) {
            $setting = $this->settingFor($workflowType);

            if ((int) $setting->fixed_user_id === (int) $user->id || (int) $setting->fallback_user_id === (int) $user->id) {
                return true;
            }

            $roleIds = collect($setting->fixed_role_ids ?? [])
                ->merge($setting->fallback_role_ids ?? [])
                ->map(fn ($id) => (int) $id);
            if ($user->role_id && $roleIds->contains((int) $user->role_id)) {
                return true;
            }

            if ($setting->mode === self::MODE_REPORTING_SENIOR
                && ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager())
                && !empty($user->getAllTeamMemberIds())) {
                return true;
            }
        }

        return VerificationRoutingMapping::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user) {
                $query->where(function ($userQuery) use ($user) {
                    $userQuery->where('verifier_type', self::VERIFIER_USER)
                        ->where('verifier_user_id', $user->id);
                });

                if ($user->role_id) {
                    $query->orWhere(function ($roleQuery) use ($user) {
                        $roleQuery->where('verifier_type', self::VERIFIER_ROLE)
                            ->where(function ($jsonQuery) use ($user) {
                                $jsonQuery->whereJsonContains('verifier_role_ids', (int) $user->role_id)
                                    ->orWhereJsonContains('verifier_role_ids', (string) $user->role_id);
                            });
                    });
                }
            })
            ->exists();
    }

    public function eligibleVerifiers(Model $item, string $workflowType): Collection
    {
        return collect($this->resolve($item, $workflowType)['eligible_verifiers'] ?? []);
    }

    public function explain(Model $item, string $workflowType, ?User $actor = null): array
    {
        $resolved = $this->resolve($item, $workflowType);
        $eligible = collect($resolved['eligible_verifiers']);

        return array_merge($resolved, [
            'workflow_label' => self::workflows()[$workflowType] ?? $workflowType,
            'eligible_verifier_ids' => $eligible->pluck('id')->values()->all(),
            'eligible_verifier_names' => $eligible->pluck('name')->values()->all(),
            'actor_can_verify' => $actor ? $eligible->pluck('id')->contains((int) $actor->id) : null,
        ]);
    }

    public function settingFor(string $workflowType): VerificationRoutingSetting
    {
        $defaults = $this->defaultSettingPayload($workflowType);

        return VerificationRoutingSetting::query()->firstOrCreate(
            ['workflow_type' => $workflowType],
            $defaults
        );
    }

    public function ensureDefaultSettings(): void
    {
        foreach (array_keys(self::workflows()) as $workflowType) {
            $this->settingFor($workflowType);
        }
    }

    public function auditRuleChange(string $action, User $actor, Model $rule, array $oldValues = [], array $newValues = []): void
    {
        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'model_type' => class_basename($rule),
            'model_id' => $rule->id,
            'description' => 'Verification routing ' . str_replace('_', ' ', $action),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function auditVerification(string $action, User $actor, Model $item, string $workflowType): void
    {
        $explain = $this->explain($item, $workflowType, $actor);

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'model_type' => class_basename($item),
            'model_id' => $item->id,
            'description' => "Verification {$action} through routing: " . ($explain['matched_rule_label'] ?? 'unknown'),
            'new_values' => [
                'workflow_type' => $workflowType,
                'matched_rule_type' => $explain['matched_rule_type'] ?? null,
                'matched_rule_id' => $explain['matched_rule_id'] ?? null,
                'fallback_applied' => $explain['fallback_applied'] ?? false,
                'eligible_verifier_ids' => $explain['eligible_verifier_ids'] ?? [],
            ],
        ]);
    }

    private function resolve(Model $item, string $workflowType): array
    {
        $setting = $this->settingFor($workflowType);
        $creator = $this->sourceUserFor($item, $workflowType);

        $mappingResult = $this->resolveMapping($creator, $workflowType);
        if ($mappingResult && $mappingResult['eligible_verifiers']->isNotEmpty()) {
            return $mappingResult;
        }

        $defaultEligible = $this->resolveDefault($setting, $creator);
        if ($defaultEligible->isNotEmpty()) {
            return [
                'eligible_verifiers' => $defaultEligible,
                'matched_rule_type' => 'workflow_default',
                'matched_rule_id' => $setting->id,
                'matched_rule_label' => 'Workflow default: ' . (self::modes()[$setting->mode] ?? $setting->mode),
                'fallback_applied' => false,
            ];
        }

        $fallbackEligible = $this->resolveFallback($setting);
        if ($fallbackEligible->isNotEmpty()) {
            return [
                'eligible_verifiers' => $fallbackEligible,
                'matched_rule_type' => 'fallback',
                'matched_rule_id' => $setting->id,
                'matched_rule_label' => 'Fallback verifier',
                'fallback_applied' => true,
            ];
        }

        return [
            'eligible_verifiers' => $this->adminUsers(),
            'matched_rule_type' => 'admin_fallback',
            'matched_rule_id' => null,
            'matched_rule_label' => 'Admin fallback',
            'fallback_applied' => true,
        ];
    }

    private function resolveMapping(?User $creator, string $workflowType): ?array
    {
        if (!$creator) {
            return null;
        }

        $mappings = VerificationRoutingMapping::query()
            ->where('workflow_type', $workflowType)
            ->where('is_active', true)
            ->with(['sourceUser.role', 'sourceRole', 'sourceTeamUser.role', 'verifierUser.role'])
            ->orderByRaw("CASE source_type WHEN 'user' THEN 1 WHEN 'team' THEN 2 WHEN 'role' THEN 3 ELSE 4 END")
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        foreach ($mappings as $mapping) {
            if (!$this->mappingMatches($mapping, $creator)) {
                continue;
            }

            $eligible = $this->verifiersFromMapping($mapping);
            if ($eligible->isEmpty()) {
                continue;
            }

            return [
                'eligible_verifiers' => $eligible,
                'matched_rule_type' => 'custom_mapping',
                'matched_rule_id' => $mapping->id,
                'matched_rule_label' => 'Custom mapping #' . $mapping->id,
                'fallback_applied' => false,
            ];
        }

        return null;
    }

    private function mappingMatches(VerificationRoutingMapping $mapping, User $creator): bool
    {
        if ($mapping->source_type === self::SOURCE_USER) {
            return (int) $mapping->source_user_id === (int) $creator->id;
        }

        if ($mapping->source_type === self::SOURCE_ROLE) {
            return (int) $mapping->source_role_id === (int) $creator->role_id;
        }

        if ($mapping->source_type === self::SOURCE_TEAM && $mapping->sourceTeamUser) {
            if ((int) $mapping->source_team_user_id === (int) $creator->id) {
                return true;
            }

            return in_array((int) $creator->id, array_map('intval', $mapping->sourceTeamUser->getAllTeamMemberIds()), true);
        }

        return false;
    }

    private function resolveDefault(VerificationRoutingSetting $setting, ?User $creator): Collection
    {
        return match ($setting->mode) {
            self::MODE_REPORTING_SENIOR => $this->reportingSeniors($creator)
                ->merge($this->resolveFallback($setting))
                ->unique('id')
                ->values(),
            self::MODE_FIXED_ROLE => $this->usersByRoleIds($setting->fixed_role_ids ?? []),
            self::MODE_FIXED_USER => $setting->fixedUser ? $this->activeUserQuery()->whereKey($setting->fixed_user_id)->get() : collect(),
            self::MODE_CUSTOM_MAPPING => collect(),
            default => collect(),
        };
    }

    private function resolveFallback(VerificationRoutingSetting $setting): Collection
    {
        $users = collect();

        if ($setting->fallback_user_id) {
            $users = $users->merge($this->activeUserQuery()->whereKey($setting->fallback_user_id)->get());
        }

        $users = $users->merge($this->usersByRoleIds($setting->fallback_role_ids ?? []));

        return $users->unique('id')->values();
    }

    private function verifiersFromMapping(VerificationRoutingMapping $mapping): Collection
    {
        if ($mapping->verifier_type === self::VERIFIER_USER) {
            return $mapping->verifier_user_id
                ? $this->activeUserQuery()->whereKey($mapping->verifier_user_id)->get()
                : collect();
        }

        if ($mapping->verifier_type === self::VERIFIER_ROLE) {
            return $this->usersByRoleIds($mapping->verifier_role_ids ?? []);
        }

        return collect();
    }

    private function reportingSeniors(?User $creator): Collection
    {
        if (!$creator) {
            return collect();
        }

        $seniors = collect();
        $current = $creator->manager;

        while ($current) {
            $seniors->push($current);
            $current = $current->manager;
        }

        if ($seniors->isEmpty() && $creator->isSalesHead()) {
            $seniors->push($creator);
        }

        return $seniors
            ->filter(fn (User $user) => $this->isActive($user))
            ->unique('id')
            ->values();
    }

    private function sourceUserFor(Model $item, ?string $workflowType = null): ?User
    {
        if (in_array($workflowType, [self::WORKFLOW_MEETING, self::WORKFLOW_SITE_VISIT, self::WORKFLOW_CLOSER, self::WORKFLOW_CLOSING], true)) {
            if (method_exists($item, 'assignedTo')) {
                $item->loadMissing('assignedTo.role', 'assignedTo.manager.role');
                if ($item->assignedTo) {
                    return $item->assignedTo;
                }
            }

            if (isset($item->assigned_to)) {
                $assignedUser = User::with(['role', 'manager.role'])->find($item->assigned_to);
                if ($assignedUser) {
                    return $assignedUser;
                }
            }
        }

        if (method_exists($item, 'creator')) {
            $item->loadMissing('creator.role', 'creator.manager.role');
            return $item->creator;
        }

        if (isset($item->created_by)) {
            return User::with(['role', 'manager.role'])->find($item->created_by);
        }

        if (isset($item->assigned_to)) {
            return User::with(['role', 'manager.role'])->find($item->assigned_to);
        }

        return null;
    }

    private function usersByRoleIds(array $roleIds): Collection
    {
        $roleIds = collect($roleIds)->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        if (empty($roleIds)) {
            return collect();
        }

        return $this->activeUserQuery()
            ->whereIn('role_id', $roleIds)
            ->with('role')
            ->orderBy('name')
            ->get()
            ->values();
    }

    private function adminUsers(): Collection
    {
        $adminRoleId = Role::query()->where('slug', Role::ADMIN)->value('id');

        return $adminRoleId ? $this->usersByRoleIds([(int) $adminRoleId]) : collect();
    }

    private function activeUserQuery()
    {
        return User::query()->where('is_active', true);
    }

    private function isActive(User $user): bool
    {
        return (bool) ($user->is_active ?? true);
    }

    private function defaultSettingPayload(string $workflowType): array
    {
        $adminCrmRoleIds = $this->roleIdsBySlugs([Role::ADMIN, Role::CRM]);

        if (in_array($workflowType, [self::WORKFLOW_CLOSER, self::WORKFLOW_CLOSING], true)) {
            return [
                'mode' => self::MODE_FIXED_ROLE,
                'fixed_role_ids' => $adminCrmRoleIds,
                'fallback_role_ids' => $adminCrmRoleIds,
            ];
        }

        return [
            'mode' => self::MODE_REPORTING_SENIOR,
            'fallback_role_ids' => $adminCrmRoleIds,
        ];
    }

    private function roleIdsBySlugs(array $slugs): array
    {
        return Role::query()
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
