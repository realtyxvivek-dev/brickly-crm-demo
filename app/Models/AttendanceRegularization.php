<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AttendanceRegularization extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_date',
        'request_type',
        'requested_in_time',
        'requested_out_time',
        'requested_status',
        'reason',
        'proof_path',
        'status',
        'abuse_score_snapshot',
        'final_approved_at',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'requested_in_time' => 'datetime',
        'requested_out_time' => 'datetime',
        'final_approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(AttendanceApproval::class, 'approvable');
    }
}
