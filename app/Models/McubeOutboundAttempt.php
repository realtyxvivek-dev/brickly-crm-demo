<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McubeOutboundAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'lead_id',
        'task_id',
        'calling_center_campaign_item_id',
        'agent_number',
        'customer_number',
        'refid',
        'refurl',
        'status',
        'http_status',
        'request_payload',
        'response_payload',
        'error_message',
        'attempted_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function callingCenterCampaignItem(): BelongsTo
    {
        return $this->belongsTo(CallingCenterCampaignItem::class, 'calling_center_campaign_item_id');
    }
}
