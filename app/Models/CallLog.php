<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\SoftDeletes;

class CallLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'call_logs';

    protected $fillable = [
        'telecaller_id',
        'user_id',
        'lead_id',
        'task_id',
        'phone_number',
        'call_type',
        'start_time',
        'end_time',
        'duration',
        'status',
        'notes',
        'recording_url',
        'call_outcome',
        'next_followup_date',
        'is_verified',
        'synced_from_mobile',
        'mcube_call_id',
        'mcube_agent_phone',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'next_followup_date' => 'datetime',
        'is_verified' => 'boolean',
        'synced_from_mobile' => 'boolean',
        'duration' => 'integer',
    ];

    /**
     * Get the telecaller (user who made the call - legacy field)
     */
    public function telecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'telecaller_id');
    }

    /**
     * Get the user who made the call (new field - supports all roles)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who made the call (prefer user_id, fallback to telecaller_id)
     * This is a computed attribute, not a relationship
     */
    public function getCallerUserAttribute(): ?User
    {
        if ($this->user_id) {
            return $this->user;
        }
        return $this->telecaller;
    }

    /**
     * Get the lead associated with this call
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the task associated with this call
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    /**
     * Format duration from seconds to "Xh Ym Zs"
     */
    public function getFormattedDurationAttribute(): string
    {
        $seconds = $this->duration ?? 0;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $secs);
        } else {
            return sprintf('%ds', $secs);
        }
    }

    /**
     * Get human readable call type
     */
    public function getCallTypeLabelAttribute(): string
    {
        return match($this->call_type) {
            'incoming' => 'Incoming',
            'outgoing' => 'Outgoing',
            default => ucfirst($this->call_type ?? 'Unknown'),
        };
    }

    /**
     * Get human readable status
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'completed' => 'Answered',
            'missed' => $this->call_type === 'outgoing' ? 'Unanswered' : 'Incoming Missed',
            'rejected' => 'Rejected',
            'busy' => 'Busy',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get human readable call outcome
     */
    public function getCallOutcomeLabelAttribute(): string
    {
        if (!$this->call_outcome) {
            return 'Not Set';
        }

        return match($this->call_outcome) {
            'interested' => 'Interested',
            'not_interested' => 'Not Interested',
            'callback' => 'Callback Requested',
            'no_answer' => 'No Answer',
            'busy' => 'Busy',
            'other' => 'Other',
            default => ucfirst($this->call_outcome),
        };
    }

    /**
     * Scope: Filter by user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhere('telecaller_id', $userId);
        });
    }

    /**
     * Scope: Filter by team members
     */
    public function scopeForTeam($query, array $teamMemberIds)
    {
        return $query->where(function($q) use ($teamMemberIds) {
            $q->whereIn('user_id', $teamMemberIds)
              ->orWhereIn('telecaller_id', $teamMemberIds);
        });
    }

    /**
     * Scope: Today's calls
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_time', Carbon::today());
    }

    /**
     * Scope: This week's calls
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek()
        ]);
    }

    /**
     * Scope: This month's calls
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('start_time', Carbon::now()->month)
                     ->whereYear('start_time', Carbon::now()->year);
    }

    /**
     * Scope: Completed calls only
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Calls synced from mobile
     */
    public function scopeSyncedFromMobile($query)
    {
        return $query->where('synced_from_mobile', true);
    }

    /**
     * Scope: Filter by call outcome
     */
    public function scopeByOutcome($query, $outcome)
    {
        return $query->where('call_outcome', $outcome);
    }

    /**
     * Scope: Filter by call type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('call_type', $type);
    }

    /**
     * Auto-calculate duration if end_time is set
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($callLog) {
            if ($callLog->start_time && $callLog->end_time && !$callLog->duration) {
                $start = Carbon::parse($callLog->start_time);
                $end = Carbon::parse($callLog->end_time);
                $callLog->duration = $end->diffInSeconds($start);
            }

            // Set user_id from telecaller_id if user_id is not set (backward compatibility)
            if (!$callLog->user_id && $callLog->telecaller_id) {
                $callLog->user_id = $callLog->telecaller_id;
            }
        });
    }
}
