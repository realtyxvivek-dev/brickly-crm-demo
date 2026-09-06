<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McubeWebhookLog extends Model
{
    protected $table = 'mcube_webhook_logs';

    protected $fillable = [
        'callid',
        'emp_phone',
        'callto',
        'dialstatus',
        'direction',
        'recording_url',
        'call_starttime',
        'call_endtime',
        'status',
        'message',
        'lead_id',
        'agent_id',
        'call_log_id',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload'    => 'array',
        'call_starttime' => 'datetime',
        'call_endtime'   => 'datetime',
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
