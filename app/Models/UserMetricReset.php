<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMetricReset extends Model
{
    use HasFactory;

    public const METRIC_RESPONSE_TIME = 'response_time';

    protected $fillable = [
        'user_id',
        'metric_key',
        'reset_at',
        'reset_by',
    ];

    protected $casts = [
        'reset_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by');
    }
}
