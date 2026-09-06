<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserAvailability extends Model
{
    use HasFactory;

    protected $table = 'user_availability';

    protected $fillable = [
        'user_id',
        'is_online',
        'last_seen_at',
        'office_hours_start',
        'office_hours_end',
        'timezone',
        'max_leads_per_day',
        'current_day_leads',
        'last_reset_date',
        'is_available',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'last_seen_at' => 'datetime',
        'last_reset_date' => 'date',
        'max_leads_per_day' => 'integer',
        'current_day_leads' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if user is within office hours
     */
    public function isWithinOfficeHours(): bool
    {
        if (!$this->office_hours_start || !$this->office_hours_end) {
            return true; // No office hours set, assume always available
        }

        $now = Carbon::now($this->timezone);
        $currentTime = $now->format('H:i:s');
        
        return $currentTime >= $this->office_hours_start && $currentTime <= $this->office_hours_end;
    }

    /**
     * Check if user is under daily limit
     */
    public function isUnderDailyLimit(): bool
    {
        if (!$this->max_leads_per_day) {
            return true; // No limit set
        }

        // Reset counter if it's a new day
        if ($this->last_reset_date != Carbon::today()->toDateString()) {
            $this->update([
                'current_day_leads' => 0,
                'last_reset_date' => Carbon::today(),
            ]);
            return true;
        }

        return $this->current_day_leads < $this->max_leads_per_day;
    }

    /**
     * Update availability status
     */
    public function updateAvailability(): void
    {
        $this->is_available = $this->is_online 
            && $this->isWithinOfficeHours() 
            && $this->isUnderDailyLimit();
        $this->save();
    }

    /**
     * Increment daily lead count
     */
    public function incrementDailyLeads(): void
    {
        // Reset if new day
        if ($this->last_reset_date != Carbon::today()->toDateString()) {
            $this->current_day_leads = 0;
            $this->last_reset_date = Carbon::today();
        }

        $this->increment('current_day_leads');
        $this->updateAvailability();
    }
}
