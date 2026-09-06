<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use HasFactory, SoftDeletes;

    public const OPEN_STATUSES = ['pending', 'in_progress', 'rescheduled'];
    public const OVERDUE_GRACE_MINUTES = 10;

    protected $fillable = [
        'lead_id',
        'assigned_to',
        'type',
        'title',
        'description',
        'status',
        'outcome',
        'priority',
        'scheduled_at',
        'reminder_sent_at',
        'due_date',
        'completed_at',
        'outcome_recorded_at',
        'created_by',
        'notes',
        'outcome_remark',
        'next_action_at',
        'meeting_id',
        'site_visit_id',
        'follow_up_id',
        'queue_hidden_at',
        'queue_hidden_reason',
        'recurrence_pattern',
        'recurrence_end_date',
        'rescheduled_from',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'outcome_recorded_at' => 'datetime',
        'next_action_at' => 'datetime',
        'queue_hidden_at' => 'datetime',
        'recurrence_pattern' => 'array',
        'recurrence_end_date' => 'datetime',
        'rescheduled_from' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('visible_in_queue', function (Builder $builder) {
            if (!static::supportsQueueArchiving()) {
                return;
            }

            $builder->whereNull($builder->getModel()->qualifyColumn('queue_hidden_at'));
        });

        static::updated(function (Task $task) {
            if (!$task->wasChanged('status')) {
                return;
            }

            if (($task->type ?? null) !== 'phone_call') {
                return;
            }

            if (!in_array((string) $task->status, ['in_progress', 'completed'], true)) {
                return;
            }

            $lead = $task->lead;
            if ($lead && $lead->isFreshTransfer()) {
                $lead->acknowledgeFreshTransfer($task->assigned_to, 'task_status_changed', 'connected');
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
            $supportsQueueArchiving = Schema::hasColumn('tasks', 'queue_hidden_at');
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
            $columnSupport[$column] = Schema::hasColumn('tasks', $column);
        } catch (\Throwable $e) {
            $columnSupport[$column] = false;
        }

        return $columnSupport[$column];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
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

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    public function getDescriptionAttribute($value): ?string
    {
        return app(\App\Services\PhonePrivacyService::class)->maskText($value, auth()->user());
    }

    public function getNotesAttribute($value): ?string
    {
        return app(\App\Services\PhonePrivacyService::class)->maskText($value, auth()->user());
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TaskAttachment::class);
    }

    /**
     * Mark task as in progress
     */
    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
        ]);
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if task is overdue
     * A task is overdue if scheduled_at is more than the grace window in the past
     * and the task is still open.
     */
    public function isOverdue(): bool
    {
        if (!$this->scheduled_at) {
            return false;
        }

        $overdueCutoff = now()->subMinutes(self::OVERDUE_GRACE_MINUTES);
        
        return $this->scheduled_at->lt($overdueCutoff)
            && in_array($this->status, self::OPEN_STATUSES, true);
    }

    /**
     * Check if task is recurring
     */
    public function isRecurring(): bool
    {
        return !empty($this->recurrence_pattern);
    }

    /**
     * Get next occurrence date based on recurrence pattern
     */
    public function getNextOccurrenceDate(): ?\Carbon\Carbon
    {
        if (!$this->isRecurring() || !$this->scheduled_at) {
            return null;
        }

        $pattern = $this->recurrence_pattern;
        $currentDate = $this->scheduled_at->copy();

        switch ($pattern['frequency'] ?? null) {
            case 'daily':
                return $currentDate->addDay();
            case 'weekly':
                $days = $pattern['days'] ?? []; // e.g., [1,3,5] for Mon, Wed, Fri
                if (empty($days)) {
                    return $currentDate->addWeek();
                }
                // Find next matching day
                $nextDate = $currentDate->copy()->addDay();
                for ($i = 0; $i < 7; $i++) {
                    if (in_array($nextDate->dayOfWeek, $days)) {
                        return $nextDate;
                    }
                    $nextDate->addDay();
                }
                return null;
            case 'monthly':
                $dayOfMonth = $pattern['day_of_month'] ?? $currentDate->day;
                $nextDate = $currentDate->copy()->addMonth()->day($dayOfMonth);
                if (!$nextDate->isValid()) {
                    $nextDate = $currentDate->copy()->addMonth()->lastOfMonth();
                }
                return $nextDate;
            case 'yearly':
                return $currentDate->addYear();
            default:
                return null;
        }
    }

    /**
     * Check if recurrence should continue
     */
    public function shouldContinueRecurring(): bool
    {
        if (!$this->isRecurring()) {
            return false;
        }

        // Check end date
        if ($this->recurrence_end_date && now()->greaterThan($this->recurrence_end_date)) {
            return false;
        }

        // Check occurrence count
        if (isset($this->recurrence_pattern['count'])) {
            $count = $this->recurrence_pattern['count'] ?? null;
            if ($count !== null) {
                // This would need to track generated occurrences - simplified version
                return true;
            }
        }

        return true;
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
