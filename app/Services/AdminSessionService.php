<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminSessionService
{
    public function usesDatabaseSessions(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable('sessions');
    }

    public function getUserSessionCountMap(iterable $userIds): array
    {
        if (!$this->usesDatabaseSessions()) {
            return [];
        }

        $ids = collect($userIds)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('sessions')
            ->select('user_id', DB::raw('COUNT(*) as aggregate'))
            ->whereNotNull('user_id')
            ->whereIn('user_id', $ids->all())
            ->where('last_activity', '>=', $this->activeThreshold())
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function getUserSessions(User $user, ?string $currentSessionId = null): Collection
    {
        if (!$this->usesDatabaseSessions()) {
            return collect();
        }

        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('last_activity', '>=', $this->activeThreshold())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => $this->mapSessionRecord($session, $currentSessionId));
    }

    public function getGroupedActiveSessions(?string $search = null, ?string $currentSessionId = null): Collection
    {
        if (!$this->usesDatabaseSessions()) {
            return collect();
        }

        $activeSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $this->activeThreshold())
            ->orderByDesc('last_activity')
            ->get();

        $users = User::withTrashed()
            ->with('role')
            ->whereIn('id', $activeSessions->pluck('user_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        $groups = $activeSessions
            ->groupBy('user_id')
            ->map(function (Collection $sessions, $userId) use ($users, $currentSessionId) {
                $user = $users->get((int) $userId);
                $mappedSessions = $sessions->map(fn ($session) => $this->mapSessionRecord($session, $currentSessionId));
                $latestSession = $mappedSessions->first();

                return (object) [
                    'user_id' => $userId ? (int) $userId : null,
                    'user' => $user,
                    'display_name' => $user?->name ?? 'Unknown User',
                    'email' => $user?->email,
                    'role_name' => $user?->getDisplayRoleName() ?? 'Unknown',
                    'session_count' => $mappedSessions->count(),
                    'latest_ip_address' => $latestSession->ip_address,
                    'latest_user_agent' => $latestSession->user_agent,
                    'latest_device' => $latestSession->device_label,
                    'last_activity_at' => $latestSession->last_activity_at,
                    'sessions' => $mappedSessions,
                ];
            })
            ->sortByDesc('last_activity_at')
            ->values();

        if (!filled($search)) {
            return $groups;
        }

        $needle = mb_strtolower(trim($search));

        return $groups->filter(function ($group) use ($needle) {
            return str_contains(mb_strtolower((string) $group->display_name), $needle)
                || str_contains(mb_strtolower((string) $group->email), $needle)
                || str_contains(mb_strtolower((string) $group->role_name), $needle);
        })->values();
    }

    public function revokeUserSessions(User $targetUser, User $actor, ?string $preserveSessionId = null): int
    {
        if (!$this->usesDatabaseSessions()) {
            return 0;
        }

        $sessionIds = DB::table('sessions')
            ->where('user_id', $targetUser->id)
            ->where('last_activity', '>=', $this->activeThreshold())
            ->when($preserveSessionId, fn ($query) => $query->where('id', '!=', $preserveSessionId))
            ->pluck('id')
            ->all();

        $deleted = $this->deleteSessionsByIds($sessionIds);

        if ($deleted > 0 && !($preserveSessionId && $actor->is($targetUser))) {
            $targetUser->tokens()->where('name', 'web-session-token')->delete();
            $targetUser->forceFill([
                'remember_token' => Str::random(60),
            ])->save();
        }

        $this->logRevokeAction(
            actor: $actor,
            action: 'admin_user_sessions_revoked',
            description: 'Revoked active sessions for a user.',
            targetUser: $targetUser,
            metadata: [
                'deleted_session_count' => $deleted,
                'preserved_session_id' => $preserveSessionId,
                // Phase 1 limitation: when preserving the current admin session, we avoid
                // token-wide cleanup because single-session token mapping is not reliable.
                'token_cleanup_skipped_for_preserved_admin_session' => $preserveSessionId && $actor->is($targetUser),
                'remember_token_rotated' => !($preserveSessionId && $actor->is($targetUser)),
            ],
        );

        return $deleted;
    }

    public function revokeAllNonAdminSessions(User $actor, ?string $preserveSessionId = null): int
    {
        if (!$this->usesDatabaseSessions()) {
            return 0;
        }

        $nonAdminUserIds = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', '!=', Role::ADMIN))
            ->pluck('id');

        $sessionIds = DB::table('sessions')
            ->whereIn('user_id', $nonAdminUserIds->all())
            ->where('last_activity', '>=', $this->activeThreshold())
            ->when($preserveSessionId, fn ($query) => $query->where('id', '!=', $preserveSessionId))
            ->pluck('id')
            ->all();

        $deleted = $this->deleteSessionsByIds($sessionIds);

        if ($nonAdminUserIds->isNotEmpty()) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->whereIn('tokenable_id', $nonAdminUserIds->all())
                ->where('name', 'web-session-token')
                ->delete();

            User::whereIn('id', $nonAdminUserIds->all())->get()->each(function (User $user) {
                $user->forceFill([
                    'remember_token' => Str::random(60),
                ])->save();
            });
        }

        $this->logRevokeAction(
            actor: $actor,
            action: 'admin_non_admin_sessions_revoked',
            description: 'Revoked active sessions for all non-admin users.',
            metadata: [
                'deleted_session_count' => $deleted,
                'preserved_session_id' => $preserveSessionId,
                'remember_token_rotated' => $nonAdminUserIds->isNotEmpty(),
            ],
        );

        return $deleted;
    }

    public function revokeAllSessions(User $actor, ?string $preserveSessionId = null): int
    {
        if (!$this->usesDatabaseSessions()) {
            return 0;
        }

        $sessionIds = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', $this->activeThreshold())
            ->when($preserveSessionId, fn ($query) => $query->where('id', '!=', $preserveSessionId))
            ->pluck('id')
            ->all();

        $deleted = $this->deleteSessionsByIds($sessionIds);

        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->where('name', 'web-session-token')
            ->when($actor->id && $preserveSessionId, function ($query) use ($actor) {
                $query->where('tokenable_id', '!=', $actor->id);
            })
            ->delete();

        User::query()
            ->when($actor->id && $preserveSessionId, fn ($query) => $query->where('id', '!=', $actor->id))
            ->get()
            ->each(function (User $user) {
                $user->forceFill([
                    'remember_token' => Str::random(60),
                ])->save();
            });

        $this->logRevokeAction(
            actor: $actor,
            action: 'admin_all_sessions_revoked',
            description: 'Revoked active sessions for all users.',
            metadata: [
                'deleted_session_count' => $deleted,
                'preserved_session_id' => $preserveSessionId,
                'remember_token_rotated' => true,
            ],
        );

        return $deleted;
    }

    private function deleteSessionsByIds(array $sessionIds): int
    {
        if (empty($sessionIds)) {
            return 0;
        }

        return DB::table('sessions')->whereIn('id', $sessionIds)->delete();
    }

    private function logRevokeAction(
        User $actor,
        string $action,
        string $description,
        ?User $targetUser = null,
        array $metadata = []
    ): void {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        ActivityLog::create([
            'user_id' => $actor->id,
            'action' => $action,
            'model_type' => User::class,
            'model_id' => $targetUser?->id,
            'description' => $description,
            'old_values' => null,
            'new_values' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);
    }

    private function mapSessionRecord(object $session, ?string $currentSessionId = null): object
    {
        $payload = $this->decodePayload($session->payload ?? null);
        $userAgent = (string) ($session->user_agent ?? '');

        return (object) [
            'id' => $session->id,
            'user_id' => $session->user_id ? (int) $session->user_id : null,
            'ip_address' => $session->ip_address ?: 'Unknown',
            'user_agent' => $userAgent,
            'device_label' => $this->summarizeUserAgent($userAgent),
            'last_activity_at' => now()->setTimestamp((int) $session->last_activity),
            'is_current' => $currentSessionId !== null && $session->id === $currentSessionId,
            'is_impersonating' => array_key_exists('impersonating_original_id', $payload),
        ];
    }

    private function decodePayload(?string $payload): array
    {
        if (!filled($payload)) {
            return [];
        }

        try {
            $decoded = base64_decode($payload, true);
            if ($decoded === false) {
                return [];
            }

            $data = @unserialize($decoded);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function summarizeUserAgent(string $userAgent): string
    {
        if ($userAgent === '') {
            return 'Unknown device';
        }

        $browser = 'Browser';
        $platform = 'Device';

        if (stripos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        }

        if (stripos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (stripos($userAgent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            $platform = 'iOS';
        } elseif (stripos($userAgent, 'Mac OS X') !== false) {
            $platform = 'macOS';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        }

        return trim($browser . ' on ' . $platform);
    }

    private function activeThreshold(): int
    {
        return now()->subMinutes((int) config('session.lifetime', 120))->getTimestamp();
    }
}
