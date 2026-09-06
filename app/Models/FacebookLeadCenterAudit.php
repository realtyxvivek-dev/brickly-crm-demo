<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacebookLeadCenterAudit extends Model
{
    protected $fillable = [
        'user_id',
        'source_url',
        'page_title',
        'total_rows',
        'matched_rows',
        'webhook_rows',
        'missing_rows',
        'possible_duplicate_rows',
        'unreadable_rows',
        'browser_meta',
    ];

    protected $casts = [
        'browser_meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FacebookLeadCenterAuditRow::class);
    }
}
