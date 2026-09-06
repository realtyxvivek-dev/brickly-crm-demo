<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Eloquent\SoftDeletes;

class TelecallerTask extends Model
{
    use HasFactory, SoftDeletes;

    public const OPEN_STATUSES = ['pending', 'in_progress', 'rescheduled'];

    protected static function booted(): void
    {
        static::saving(function (TelecallerTask $task) {
            $scheduledChanged = $task->isDirty('scheduled_at');
            $statusChanged = $task->isDirty('status');
            $completedChanged = $task->isDirty('completed_at');

            if (!$scheduledChanged && !$statusChanged && !$completedChanged) {
                return;
            }

            $isActive = in_array($task->status, self::OPEN_STATUSES, true) && $task->completed_at === null;
            $wasActive = in_array($task->getOriginal('status'), self::OPEN_STATUSES, true) && $task->getOriginal('completed_at') === null;

            if ($isActive && ($scheduledChanged || !$wasActive)) {
                $task->notification_sent_at = null;
                $task->overdue_notified_at = null;
            }

            if (!$isActive) {
                $task->overdue_notified_at = null;
            }
        });

        static::addGlobalScope('visible_in_queue', function (Builder $builder) {
            if (!static::supportsQueueArchiving()) {
                return;
            }

            $builder->whereNull($builder->getModel()->qualifyColumn('queue_hidden_at'));
        });

        static::updated(function (TelecallerTask $task) {
            if (!$task->wasChanged('status')) {
                return;
            }

            if (($task->task_type ?? null) !== 'calling') {
                return;
            }

            if (!in_array((string) $task->status, ['in_progress', 'completed'], true)) {
                return;
            }

            $lead = $task->lead;
            if ($lead && $lead->isFreshTransfer()) {
                $lead->acknowledgeFreshTransfer($task->assigned_to, 'telecaller_task_status_changed', 'connected');
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
            $supportsQueueArchiving = Schema::hasColumn('telecaller_tasks', 'queue_hidden_at');
        } catch (\Throwable $e) {
            $supportsQueueArchiving = false;
        }

        return $supportsQueueArchiving;
    }

    public static function supportsColumn(string $column): bool
    {
        static $columnSupport = [];

        if (array_key_exists($column, $columnSupport)) {
            return $columnSupport[$column];
        }

        try {
            $columnSupport[$column] = Schema::hasColumn('telecaller_tasks', $column);
        } catch (\Throwable $e) {
            $columnSupport[$column] = false;
        }

        return $columnSupport[$column];
    }

    protected $fillable = [
        'lead_id',
        'meeting_id',
        'site_visit_id',
        'follow_up_id',
        'assigned_to',
        'task_type',
        'status',
        'scheduled_at',
        'completed_at',
        'outcome',
        'notes',
        'created_by',
        'queue_hidden_at',
        'queue_hidden_reason',
        'notification_sent_at',
        'overdue_notified_at',
        'moved_to_pending_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'queue_hidden_at' => 'datetime',
        'notification_sent_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
        'moved_to_pending_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function siteVisit(): BelongsTo
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(FollowUp::class);
    }

    public function getNotesAttribute($value): ?string
    {
        return app(\App\Services\PhonePrivacyService::class)->maskText($value, auth()->user());
    }

    /**
     * Scope to get overdue tasks (more than 10 minutes old)
     */
    public function scopeOverdue($query)
    {
        $tenMinutesAgo = now()->subMinutes(10);
        return $query->where('status', '!=', 'completed')
            ->where('scheduled_at', '<', $tenMinutesAgo);
    }

    /**
     * Scope to get tasks due today
     */
    public function scopeDueToday($query)
    {
        return $query->whereDate('scheduled_at', today())
            ->where('status', '!=', 'completed');
    }

    /**
     * Scope to get urgent tasks (due within next hour)
     */
    public function scopeUrgent($query)
    {
        return $query->where('status', '!=', 'completed')
            ->whereBetween('scheduled_at', [now(), now()->addHour()]);
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
