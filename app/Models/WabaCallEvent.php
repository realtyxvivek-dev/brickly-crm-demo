<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WabaCallEvent extends Model
{
    protected $fillable = [
        'meta_call_id',
        'lead_id',
        'assigned_to',
        'telecaller_task_id',
        'phone',
        'customer_name',
        'direction',
        'event_type',
        'status',
        'duration_seconds',
        'raw_payload',
        'occurred_at',
        'task_created_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'occurred_at' => 'datetime',
        'task_created_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function telecallerTask(): BelongsTo
    {
        return $this->belongsTo(TelecallerTask::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', (string) ($this->status ?: 'unknown')));
    }

    public function getEventTypeLabelAttribute(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', (string) ($this->event_type ?: 'call')));
    }

    public function shouldCreateFollowUpTask(): bool
    {
        $status = strtolower((string) $this->status);
        $type = strtolower((string) $this->event_type);

        return str_contains($type, 'callback')
            || in_array($status, ['missed', 'missed_call', 'no_answer', 'unanswered', 'callback_requested'], true);
    }
}
