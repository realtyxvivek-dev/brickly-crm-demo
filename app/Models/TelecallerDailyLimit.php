<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class TelecallerDailyLimit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overall_daily_limit',
        'assigned_count_today',
        'last_reset_date',
    ];

    protected $casts = [
        'overall_daily_limit' => 'integer',
        'assigned_count_today' => 'integer',
        'last_reset_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Reset daily count if it's a new day
     */
    public function resetIfNewDay(): void
    {
        $today = Carbon::today();
        
        if (!$this->last_reset_date || $this->last_reset_date->lt($today)) {
            $this->update([
                'assigned_count_today' => 0,
                'last_reset_date' => $today,
            ]);
        }
    }

    /**
     * Check if telecaller is within overall daily limit
     */
    public function isWithinLimit(): bool
    {
        $this->resetIfNewDay();
        
        if ($this->overall_daily_limit <= 0) {
            return true; // No limit set
        }

        return $this->assigned_count_today < $this->overall_daily_limit;
    }

    /**
     * Increment assigned count
     */
    public function incrementCount(): void
    {
        $this->resetIfNewDay();
        $this->increment('assigned_count_today');
    }
}
