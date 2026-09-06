<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExecutionTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_code',
        'title',
        'description',
        'assigned_by',
        'assigned_to',
        'priority',
        'status',
        'due_at',
        'estimated_time_minutes',
        'actual_time_minutes',
        'waiting_reason',
        'waiting_on_user',
        'completed_at',
        'closed_at',
        'last_status_changed_at',
        'context_label',
        'source_module',
        'source_id',
        'trigger_type',
        'is_private',
        'due_reminder_sent_at',
        'overdue_notified_at',
        'meta',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_status_changed_at' => 'datetime',
        'is_private' => 'boolean',
        'meta' => 'array',
        'due_reminder_sent_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (self $task): void {
            if (!$task->task_code) {
                $task->forceFill([
                    'task_code' => 'EX-' . str_pad((string) $task->id, 6, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function waitingOnUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiting_on_user');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ExecutionTaskActivity::class, 'task_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ExecutionTaskAttachment::class, 'task_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(ExecutionTaskChecklist::class, 'task_id')->orderBy('sort_order')->orderBy('id');
    }

    public function isOpenForWork(): bool
    {
        return in_array($this->status, config('execution-desk.open_statuses', []), true);
    }
}
