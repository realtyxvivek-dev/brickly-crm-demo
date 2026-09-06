<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SystemSettings;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardKpiModeService
{
    public const TARGET_BASED = 'target_based';
    public const ACTIVITY_BASED = 'activity_based';

    private const GLOBAL_KEY = 'dashboard_kpi_global_mode';
    private const ROLE_KEY = 'dashboard_kpi_role_modes';
    private const USER_KEY = 'dashboard_kpi_user_modes';

    public function getSupportedRoleSlugs(): array
    {
        return [
            Role::SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
        ];
    }

    public function getSupportedModes(): array
    {
        return [
            self::TARGET_BASED,
            self::ACTIVITY_BASED,
        ];
    }

    public function getGlobalMode(): string
    {
        return $this->normalizeMode(SystemSettings::get(self::GLOBAL_KEY, self::TARGET_BASED)) ?? self::TARGET_BASED;
    }

    public function getRoleModes(): array
    {
        return $this->normalizeScopedModes($this->decodeSetting(SystemSettings::get(self::ROLE_KEY, '{}')), $this->getSupportedRoleSlugs());
    }

    public function getUserModes(): array
    {
        return $this->normalizeScopedModes($this->decodeSetting(SystemSettings::get(self::USER_KEY, '{}')));
    }

    public function getSettingsPayload(): array
    {
        return [
            'global_mode' => $this->getGlobalMode(),
            'role_modes' => $this->getRoleModes(),
            'user_modes' => $this->getUserModes(),
        ];
    }

    public function saveSettings(string $globalMode, array $roleModes = [], array $userModes = []): array
    {
        $normalizedGlobal = $this->normalizeMode($globalMode) ?? self::TARGET_BASED;
        $normalizedRoleModes = $this->normalizeScopedModes($roleModes, $this->getSupportedRoleSlugs());
        $normalizedUserModes = $this->normalizeScopedModes($userModes);

        SystemSettings::set(self::GLOBAL_KEY, $normalizedGlobal);
        SystemSettings::set(self::ROLE_KEY, json_encode($normalizedRoleModes));
        SystemSettings::set(self::USER_KEY, json_encode($normalizedUserModes));

        return [
            'global_mode' => $normalizedGlobal,
            'role_modes' => $normalizedRoleModes,
            'user_modes' => $normalizedUserModes,
        ];
    }

    public function resolveModeForUser(?User $user): string
    {
        if (!$user) {
            return $this->getGlobalMode();
        }

        $userModes = $this->getUserModes();
        $userKey = (string) $user->id;
        if (isset($userModes[$userKey])) {
            return $userModes[$userKey];
        }

        $roleSlug = $user->role->slug ?? null;
        if ($roleSlug) {
            $roleModes = $this->getRoleModes();
            if (isset($roleModes[$roleSlug])) {
                return $roleModes[$roleSlug];
            }
        }

        return $this->getGlobalMode();
    }

    public function getRelevantUsers(): Collection
    {
        return User::with('role')
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', $this->getSupportedRoleSlugs());
            })
            ->orderBy('name')
            ->get();
    }

    public function getRelevantRoles(): Collection
    {
        return Role::whereIn('slug', $this->getSupportedRoleSlugs())
            ->orderByRaw("FIELD(slug, '" . implode("','", $this->getSupportedRoleSlugs()) . "')")
            ->get();
    }

    public function getEffectiveModeMapForUsers(Collection $users): array
    {
        $map = [];

        foreach ($users as $user) {
            $map[(string) $user->id] = $this->resolveModeForUser($user);
        }

        return $map;
    }

    public function normalizeMode($mode): ?string
    {
        if (!is_string($mode)) {
            return null;
        }

        $normalized = trim(strtolower($mode));
        if ($normalized === '' || $normalized === 'inherit') {
            return null;
        }

        return in_array($normalized, $this->getSupportedModes(), true) ? $normalized : null;
    }

    private function decodeSetting($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeScopedModes(array $modes, array $allowedKeys = []): array
    {
        $normalized = [];

        foreach ($modes as $key => $mode) {
            $key = (string) $key;
            if ($allowedKeys !== [] && !in_array($key, $allowedKeys, true)) {
                continue;
            }

            $normalizedMode = $this->normalizeMode($mode);
            if ($normalizedMode !== null) {
                $normalized[$key] = $normalizedMode;
            }
        }

        return $normalized;
    }
}
