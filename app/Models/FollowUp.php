<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Services\WhatsAppAutomationTriggerService;

class FollowUp extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('visible_in_queue', function (Builder $builder) {
            if (!static::supportsQueueArchiving()) {
                return;
            }

            $builder->whereNull($builder->getModel()->qualifyColumn('queue_hidden_at'));
        });

        static::saving(function (FollowUp $followUp) {
            $scheduledChanged = $followUp->isDirty('scheduled_at');
            $statusChanged = $followUp->isDirty('status');
            $completedChanged = $followUp->isDirty('completed_at');

            if (!$scheduledChanged && !$statusChanged && !$completedChanged) {
                return;
            }

            $isScheduledOpen = $followUp->status === 'scheduled' && $followUp->completed_at === null;
            $wasScheduledOpen = $followUp->getOriginal('status') === 'scheduled' && $followUp->getOriginal('completed_at') === null;

            if ($isScheduledOpen && ($scheduledChanged || !$wasScheduledOpen)) {
                $followUp->reminder_sent_at = null;
                $followUp->first_reminder_sent_at = null;
                $followUp->final_reminder_sent_at = null;
                $followUp->overdue_notified_at = null;
            }

            if (!$isScheduledOpen) {
                $followUp->first_reminder_sent_at = null;
                $followUp->final_reminder_sent_at = null;
                $followUp->overdue_notified_at = null;
            }
        });

        static::created(function (FollowUp $followUp) {
            try {
                $followUp->loadMissing('lead.activeAssignments.assignedTo.role');
                app(WhatsAppAutomationTriggerService::class)->handleTrigger('follow_up_created', [
                    'lead' => $followUp->lead,
                    'follow_up' => $followUp,
                    'related_type' => 'follow_up',
                    'related_id' => $followUp->id,
                    'actor_type' => 'system',
                    'actor_id' => $followUp->created_by,
                ]);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp follow-up automation dispatch failed', [
                    'follow_up_id' => $followUp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public static function supportsQueueArchiving(): bool
    {
        static $supportsQueueArchiving = null;

        if ($supportsQueueArchiving !== null) {
            return $supportsQueueArchiving;
        }

        try {
            $supportsQueueArchiving = Schema::hasColumn('follow_ups', 'queue_hidden_at');
        } catch (\Throwable $e) {
            $supportsQueueArchiving = false;
        }

        return $supportsQueueArchiving;
    }

    protected $fillable = [
        'lead_id',
        'created_by',
        'type',
        'notes',
        'scheduled_at',
        'reminder_sent_at',
        'first_reminder_sent_at',
        'final_reminder_sent_at',
        'overdue_notified_at',
        'completed_at',
        'status',
        'outcome',
        'queue_hidden_at',
        'queue_hidden_reason',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'first_reminder_sent_at' => 'datetime',
        'final_reminder_sent_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
        'completed_at' => 'datetime',
        'queue_hidden_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeWithQueueHidden(Builder $query): Builder
    {
        if (!static::supportsQueueArchiving()) {
            return $query;
        }

        return $query->withoutGlobalScope('visible_in_queue');
    }

    public function archiveForQueue(string $reason): void
    {
        if (!static::supportsQueueArchiving()) {
            return;
        }

        $this->update([
            'queue_hidden_at' => now(),
            'queue_hidden_reason' => $reason,
        ]);
    }

    public function restoreToQueue(): void
    {
        if (!static::supportsQueueArchiving()) {
            return;
        }

        $this->update([
            'queue_hidden_at' => null,
            'queue_hidden_reason' => null,
        ]);
    }
}
