<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaReviewSyncLog extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'status',
        'reason',
        'previous_stage',
        'meta_stage',
        'note',
        'dedupe_key',
        'event_name',
        'meta_leadgen_id',
        'response_code',
        'response_summary',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
