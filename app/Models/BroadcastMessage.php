<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class BroadcastMessage extends Model
{
    use HasFactory;

    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_IMPORTANT = 'important';
    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'sender_id',
        'title',
        'message',
        'priority',
        'banner_enabled',
        'requires_acknowledge',
        'action_label',
        'action_url',
        'attachment_path',
        'attachment_name',
        'target_type',
        'target_roles',
        'target_user_ids',
        'starts_at',
        'ends_at',
        'status',
        'read_by',
    ];

    protected $casts = [
        'banner_enabled' => 'boolean',
        'requires_acknowledge' => 'boolean',
        'target_roles' => 'array',
        'target_user_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'read_by' => 'array',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(BroadcastMessageUserState::class);
    }

    public function scopeActive($query)
    {
        $now = now();

        return $query
            ->where(function ($statusQuery) {
                $statusQuery->whereNull('status')
                    ->orWhere('status', 'active');
            })
            ->where(function ($startsQuery) use ($now) {
                $startsQuery->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($endsQuery) use ($now) {
                $endsQuery->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Check if user has read this broadcast.
     */
    public function isReadBy(int $userId): bool
    {
        $state = $this->userStates()
            ->where('user_id', $userId)
            ->first();

        if ($state) {
            return $state->read_at !== null || $state->dismissed_at !== null;
        }

        return in_array($userId, $this->read_by ?? [], true);
    }

    /**
     * Mark broadcast as read by user.
     */
    public function markAsReadBy(int $userId): void
    {
        BroadcastMessageUserState::query()->updateOrCreate(
            [
                'broadcast_message_id' => $this->id,
                'user_id' => $userId,
            ],
            [
                'read_at' => now(),
            ]
        );

        $readBy = $this->read_by ?? [];
        if (!in_array($userId, $readBy, true)) {
            $readBy[] = $userId;
            $this->update(['read_by' => $readBy]);
        }
    }

    public function isTargetedTo(User $user): bool
    {
        if ($this->target_type === 'all_users') {
            return true;
        }

        if ($this->target_type === 'specific_users') {
            $targetUserIds = array_map('strval', $this->target_user_ids ?? []);

            return in_array((string) $user->id, $targetUserIds, true);
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return in_array($user->role->slug ?? '', $this->target_roles ?? [], true);
    }

    public function resolveRequiresAcknowledge(): bool
    {
        if ($this->requires_acknowledge !== null) {
            return (bool) $this->requires_acknowledge;
        }

        return in_array($this->priority, [self::PRIORITY_IMPORTANT, self::PRIORITY_URGENT], true);
    }

    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    public function isCurrentlyActive(): bool
    {
        $now = now();

        if ($this->status && $this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function analyticsSummary(?Collection $states = null): array
    {
        $states ??= $this->relationLoaded('userStates')
            ? $this->userStates
            : $this->userStates()->get();

        $totalRecipients = $states->count();
        $delivered = $states->filter(fn (BroadcastMessageUserState $state) => $state->delivered_at !== null)->count();
        $read = $states->filter(fn (BroadcastMessageUserState $state) => $state->read_at !== null)->count();
        $acknowledged = $states->filter(fn (BroadcastMessageUserState $state) => $state->acknowledged_at !== null)->count();
        $clickedCta = $states->filter(fn (BroadcastMessageUserState $state) => $state->clicked_at !== null)->count();

        return [
            'total_recipients' => $totalRecipients,
            'delivered' => $delivered,
            'read' => $read,
            'acknowledged' => $acknowledged,
            'pending_acknowledge' => max($totalRecipients - $acknowledged, 0),
            'clicked_cta' => $clickedCta,
        ];
    }
}
