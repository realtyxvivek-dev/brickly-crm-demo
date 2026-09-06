<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WabaCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'template_id',
        'meta_waba_account_id',
        'created_by',
        'status',
        'audience_criteria',
        'variable_mapping',
        'total_recipients',
        'queued_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'rate_limit_per_minute',
        'scheduled_at',
        'started_at',
        'completed_at',
        'last_error',
    ];

    protected $casts = [
        'audience_criteria' => 'array',
        'variable_mapping' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    public function metaWabaAccount(): BelongsTo
    {
        return $this->belongsTo(MetaWabaAccount::class, 'meta_waba_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WabaCampaignRecipient::class, 'campaign_id');
    }
}
