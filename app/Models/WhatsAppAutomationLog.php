<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppAutomationLog extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_automation_logs';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'journey_id',
        'rule_id',
        'lead_id',
        'trigger',
        'related_type',
        'related_id',
        'template_id',
        'template_name',
        'meta_waba_account_id',
        'recipient_phone',
        'execution_key',
        'status',
        'provider_message_id',
        'failure_reason',
        'actor_type',
        'actor_id',
        'scheduled_for',
        'processed_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'replied_at',
        'context_snapshot',
        'resolved_variables',
        'payload_snapshot',
        'provider_response',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'processed_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'replied_at' => 'datetime',
        'context_snapshot' => 'array',
        'resolved_variables' => 'array',
        'payload_snapshot' => 'array',
        'provider_response' => 'array',
    ];

    public function journey(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAutomationJourney::class, 'journey_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAutomationRule::class, 'rule_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function metaWabaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }
}
