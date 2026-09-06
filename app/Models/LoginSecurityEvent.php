<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginSecurityEvent extends Model
{
    use HasFactory;

    public const TYPE_FAILED = 'failed_attempt';
    public const TYPE_LOCKED = 'locked';
    public const TYPE_ACCESS_REQUEST = 'access_request';
    public const TYPE_UNLOCKED = 'unlocked';
    public const TYPE_REJECTED = 'rejected';
    public const TYPE_SUCCESS = 'success';

    public const LOCK_NONE = 'none';
    public const LOCK_TWO_MIN = '2_min';
    public const LOCK_FIFTEEN_MIN = '15_min';
    public const LOCK_ADMIN = 'admin_locked';

    protected $fillable = [
        'user_id',
        'email_normalized',
        'event_type',
        'login_method',
        'lock_level',
        'status',
        'failed_count',
        'lock_until',
        'ip_address',
        'user_agent',
        'device_summary',
        'latitude',
        'longitude',
        'location_accuracy',
        'selfie_path',
        'reason',
        'meta',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'lock_until' => 'datetime',
        'reviewed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
