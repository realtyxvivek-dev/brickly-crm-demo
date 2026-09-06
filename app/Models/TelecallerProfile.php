<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelecallerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'max_pending_leads',
        'is_absent',
        'absent_reason',
        'absent_until',
        'lead_off_start_at',
        'lead_off_end_at',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
        'max_pending_leads' => 'integer',
        'absent_until' => 'datetime',
        'lead_off_start_at' => 'datetime',
        'lead_off_end_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if telecaller is currently absent
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
}
