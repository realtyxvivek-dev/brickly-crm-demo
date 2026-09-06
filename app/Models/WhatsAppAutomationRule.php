<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAutomationRule extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_automation_rules';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';

    protected $fillable = [
        'journey_id',
        'name',
        'trigger',
        'status',
        'is_active',
        'test_mode',
        'priority',
        'template_id',
        'meta_waba_account_id',
        'conditions',
        'variable_map',
        'send_timing',
        'quiet_hour_policy',
        'once_per_lead',
        'resend_cap',
        'cooldown_minutes',
        'daily_send_cap',
        'requires_session_window',
        'fallback_action',
        'stop_statuses',
        'last_executed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'test_mode' => 'boolean',
        'priority' => 'integer',
        'meta_waba_account_id' => 'integer',
        'conditions' => 'array',
        'variable_map' => 'array',
        'send_timing' => 'array',
        'quiet_hour_policy' => 'array',
        'once_per_lead' => 'boolean',
        'resend_cap' => 'integer',
        'cooldown_minutes' => 'integer',
        'daily_send_cap' => 'integer',
        'requires_session_window' => 'boolean',
        'stop_statuses' => 'array',
        'last_executed_at' => 'datetime',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAutomationJourney::class, 'journey_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    public function metaWabaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppAutomationLog::class, 'rule_id');
    }
}
