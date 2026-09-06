<?php

namespace App\Services;

use App\Models\FcmToken;
use App\Models\NotificationDeviceAudit;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationDeviceOwnershipService
{
    public function claimFcm(User $user, string $token, string $deviceType = 'web', array $context = []): FcmToken
    {
        $tokenHash = hash('sha256', $token);
        $hasHashColumn = Schema::hasColumn('fcm_tokens', 'token_hash');
        return $this->retryOnUniqueConflict(function () use ($user, $token, $tokenHash, $hasHashColumn, $deviceType, $context) {
            return DB::transaction(function () use ($user, $token, $tokenHash, $hasHashColumn, $deviceType, $context) {
                $record = FcmToken::query()
                    ->where($hasHashColumn ? 'token_hash' : 'fcm_token', $hasHashColumn ? $tokenHash : $token)
                    ->lockForUpdate()
                    ->first();
                $previousUserId = $record?->user_id;

                if (!$record) {
                    $record = new FcmToken(['fcm_token' => $token]);
                }

                $record->user_id = $user->id;
                if ($hasHashColumn) {
                    $record->token_hash = $tokenHash;
                }
                $record->device_type = $deviceType;
                $record->save();

                $this->audit(
                    $previousUserId && (int) $previousUserId !== (int) $user->id ? 'transferred' : 'registered',
                    'fcm',
                    $token,
                    $previousUserId && (int) $previousUserId !== (int) $user->id ? (int) $previousUserId : null,
                    (int) $user->id,
                    $deviceType,
                    $context
                );

                return $record;
            });
        });
    }

    public function claimPush(User $user, string $endpoint, array $keys, array $context = []): PushSubscription
    {
        $endpointHash = hash('sha256', $endpoint);
        $hasHashColumn = Schema::hasColumn('push_subscriptions', 'endpoint_hash');
        return $this->retryOnUniqueConflict(function () use ($user, $endpoint, $endpointHash, $hasHashColumn, $keys, $context) {
            return DB::transaction(function () use ($user, $endpoint, $endpointHash, $hasHashColumn, $keys, $context) {
                $record = PushSubscription::query()
                    ->where($hasHashColumn ? 'endpoint_hash' : 'endpoint', $hasHashColumn ? $endpointHash : $endpoint)
                    ->lockForUpdate()
                    ->first();
                $previousUserId = $record?->user_id;

                if (!$record) {
                    $record = new PushSubscription(['endpoint' => $endpoint]);
                }

                $record->user_id = $user->id;
                if ($hasHashColumn) {
                    $record->endpoint_hash = $endpointHash;
                }
                $record->keys = $keys;
                $record->user_agent = isset($context['user_agent'])
                    ? substr((string) $context['user_agent'], 0, 500)
                    : null;
                $record->save();

                $this->audit(
                    $previousUserId && (int) $previousUserId !== (int) $user->id ? 'transferred' : 'registered',
                    'web_push',
                    $endpoint,
                    $previousUserId && (int) $previousUserId !== (int) $user->id ? (int) $previousUserId : null,
                    (int) $user->id,
                    'web',
                    $context
                );

                return $record;
            });
        });
    }

    public function releaseFcm(User $user, string $token, array $context = []): bool
    {
        return DB::transaction(function () use ($user, $token, $context) {
            $record = FcmToken::query()
                ->where(
                    Schema::hasColumn('fcm_tokens', 'token_hash') ? 'token_hash' : 'fcm_token',
                    Schema::hasColumn('fcm_tokens', 'token_hash') ? hash('sha256', $token) : $token
                )
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return false;
            }

            $this->audit('logout', 'fcm', $token, (int) $user->id, null, $record->device_type, $context);
            $record->delete();

            return true;
        });
    }

    public function releasePush(User $user, string $endpoint, array $context = []): bool
    {
        return DB::transaction(function () use ($user, $endpoint, $context) {
            $record = PushSubscription::query()
                ->where(
                    Schema::hasColumn('push_subscriptions', 'endpoint_hash') ? 'endpoint_hash' : 'endpoint',
                    Schema::hasColumn('push_subscriptions', 'endpoint_hash') ? hash('sha256', $endpoint) : $endpoint
                )
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return false;
            }

            $this->audit('logout', 'web_push', $endpoint, (int) $user->id, null, 'web', $context);
            $record->delete();

            return true;
        });
    }

    private function retryOnUniqueConflict(callable $operation)
    {
        try {
            return $operation();
        } catch (QueryException $exception) {
            if (!in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            return $operation();
        }
    }

    private function audit(
        string $action,
        string $channel,
        string $identifier,
        ?int $fromUserId,
        ?int $toUserId,
        ?string $deviceType,
        array $context
    ): void {
        if (!Schema::hasTable('notification_device_audits')) {
            return;
        }

        NotificationDeviceAudit::create([
            'action' => $action,
            'channel' => $channel,
            'token_hash' => hash('sha256', $identifier),
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'device_type' => $deviceType,
            'metadata' => $context['metadata'] ?? null,
            'ip_address' => isset($context['ip_address']) ? substr((string) $context['ip_address'], 0, 45) : null,
            'user_agent' => isset($context['user_agent']) ? substr((string) $context['user_agent'], 0, 1000) : null,
            'created_at' => now(),
        ]);
    }
}
