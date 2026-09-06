<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttendanceOutsidePunchRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_date',
        'punch_type',
        'requested_at',
        'latitude',
        'longitude',
        'office_location_id',
        'geo_distance_meters',
        'reason',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
        'attendance_event_id',
        'attendance_record_id',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'geo_distance_meters' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceEvent::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(AttendanceApproval::class, 'approvable');
    }
}
