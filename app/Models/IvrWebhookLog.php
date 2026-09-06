<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IvrWebhookLog extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'external_call_id',
        'customer_phone',
        'agent_phone',
        'agent_name',
        'call_status',
        'direction',
        'recording_url',
        'dtmf_option',
        'call_starttime',
        'call_endtime',
        'duration',
        'status',
        'message',
        'lead_id',
        'agent_id',
        'call_log_id',
        'normalized_payload',
        'raw_payload',
    ];

    protected $casts = [
        'call_starttime' => 'datetime',
        'call_endtime' => 'datetime',
        'duration' => 'integer',
        'normalized_payload' => 'array',
        'raw_payload' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class, 'call_log_id');
    }
}
