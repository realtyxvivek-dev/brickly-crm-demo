<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallingCenterCampaign extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'assigned_to',
        'created_by',
        'source_type',
        'folder_type',
        'folder_key',
        'folder_tag_id',
        'filters',
        'status',
        'delay_seconds',
        'retry_policy',
        'max_retries',
        'started_at',
        'paused_at',
        'completed_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function folderTag(): BelongsTo
    {
        return $this->belongsTo(LeadTag::class, 'folder_tag_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CallingCenterCampaignItem::class, 'campaign_id');
    }
}
