<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_absent',
        'absent_reason',
        'absent_until',
        'lead_off_start_at',
        'lead_off_end_at',
        'lead_off_source',
        'lead_off_set_by',
        'lead_off_fallback_user_id',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'absent_until' => 'datetime',
        'lead_off_start_at' => 'datetime',
        'lead_off_end_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leadOffFallbackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_off_fallback_user_id');
    }

    /**
     * Check if user is currently absent
     */
    public function isCurrentlyAbsent(?\Carbon\Carbon $at = null): bool
    {
        $at = $at ?? now();

        if (!$this->is_absent) {
            return false;
        }

        if ($this->lead_off_start_at || $this->lead_off_end_at) {
            if ($this->lead_off_start_at && $at->lt($this->lead_off_start_at)) {
                return false;
            }

            if ($this->lead_off_end_at && $at->gt($this->lead_off_end_at)) {
                return false;
            }

            return true;
        }

        if ($this->absent_until && $at->gt($this->absent_until)) {
            return false;
        }

        return true;
    }

    public function hasUpcomingLeadOffWindow(?\Carbon\Carbon $at = null): bool
    {
        $at = $at ?? now();

        return (bool) ($this->is_absent && $this->lead_off_start_at && $at->lt($this->lead_off_start_at));
    }

    public function leadOffEndsAt(): ?\Carbon\Carbon
    {
        return $this->lead_off_end_at ?? $this->absent_until;
    }

    public function returnsToday(?\Carbon\Carbon $at = null): bool
    {
        $at = $at ?? now();
        $endAt = $this->leadOffEndsAt();

        return (bool) ($this->is_absent && $endAt && $endAt->isSameDay($at));
    }
}
