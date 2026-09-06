<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrLeadAutomationAudit extends Model
{
    use HasFactory;

    protected $table = 'ivr_lead_automation_audits';

    protected $fillable = [
        'config_id',
        'receiver_override_id',
        'webhook_log_id',
        'lead_id',
        'receiver_user_id',
        'assigned_user_id',
        'rule_source',
        'target_type',
        'strategy_used',
        'fallback_used',
        'fallback_mode',
        'reason',
        'meta',
        'processed_at',
    ];

    protected $casts = [
        'fallback_used' => 'boolean',
        'meta' => 'array',
        'processed_at' => 'datetime',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(IvrLeadAutomationConfig::class, 'config_id');
    }

    public function receiverOverride(): BelongsTo
    {
        return $this->belongsTo(IvrLeadAutomationReceiverOverride::class, 'receiver_override_id');
    }

    public function webhookLog(): BelongsTo
    {
        return $this->belongsTo(McubeWebhookLog::class, 'webhook_log_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
