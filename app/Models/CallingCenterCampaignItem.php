<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallingCenterCampaignItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_DIALING = 'dialing';
    public const STATUS_OUTCOME_PENDING = 'outcome_pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'campaign_id',
        'lead_id',
        'task_id',
        'mcube_outbound_attempt_id',
        'call_log_id',
        'phone',
        'status',
        'call_status',
        'outcome',
        'remark',
        'next_call_at',
        'locked_at',
        'call_started_at',
        'call_ended_at',
        'outcome_submitted_at',
        'attempt_count',
        'meta',
    ];

    protected $casts = [
        'next_call_at' => 'datetime',
        'locked_at' => 'datetime',
        'call_started_at' => 'datetime',
        'call_ended_at' => 'datetime',
        'outcome_submitted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CallingCenterCampaign::class, 'campaign_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function mcubeOutboundAttempt(): BelongsTo
    {
        return $this->belongsTo(McubeOutboundAttempt::class, 'mcube_outbound_attempt_id');
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class);
    }
}
