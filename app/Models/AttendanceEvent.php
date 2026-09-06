<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceEvent extends Model
{
    use HasFactory;

    public const TYPE_PUNCH_IN = 'punch_in';
    public const TYPE_PUNCH_OUT = 'punch_out';
    public const TYPE_REMINDER_SENT = 'reminder_sent';

    protected $fillable = [
        'user_id',
        'event_date',
        'event_type',
        'event_time',
        'source',
        'latitude',
        'longitude',
        'office_location_id',
        'geo_distance_meters',
        'inside_geo_fence',
        'photo_id',
        'device_fingerprint',
        'ip_address',
        'user_agent',
        'meta_json',
    ];

    protected $casts = [
        'event_date' => 'date',
        'event_time' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'inside_geo_fence' => 'boolean',
        'meta_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(AttendancePhoto::class, 'photo_id');
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }
}
