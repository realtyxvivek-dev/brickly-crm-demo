<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

use Illuminate\Database\Eloquent\SoftDeletes;

class CrmAssignment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'customer_name',
        'phone',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'call_status',
        'called_at',
        'cnp_count',
        'follow_up_scheduled_at',
        'follow_up_completed',
        'not_interested_date',
        'shuffle_after_date',
        'notes',
        'sheet_config_id',
        'sheet_row_number',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'called_at' => 'datetime',
        'follow_up_scheduled_at' => 'datetime',
        'not_interested_date' => 'date',
        'shuffle_after_date' => 'date',
        'follow_up_completed' => 'boolean',
        'cnp_count' => 'integer',
    ];

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function sheetConfig(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'sheet_config_id');
    }

    public function prospect(): HasOne
    {
        return $this->hasOne(Prospect::class, 'assignment_id');
    }

    /**
     * Check if call status is pending
     */
    public function isPending(): bool
    {
        return $this->call_status === 'pending';
    }

    /**
     * Check if call status is completed
     */
    public function isCompleted(): bool
    {
        return $this->call_status === 'completed';
    }

    /**
     * Check if telecaller can call this assignment
     */
    public function canCall(): bool
    {
        // Check if blacklisted
        if (BlacklistedNumber::where('phone', $this->phone)->exists()) {
            return false;
        }

        // Check if within daily limit (handled in service)
        return $this->isPending();
    }

    /**
     * Increment CNP count with auto-logic
     */
    public function incrementCnp(): void
    {
        $this->cnp_count = $this->cnp_count + 1;
        
        // Auto-mark as not interested on 2nd CNP
        if ($this->cnp_count >= 2) {
            $this->call_status = 'called_not_interested';
            $this->called_at = now();
            $this->not_interested_date = now();
            $this->shuffle_after_date = now()->addDays(rand(3, 6));
        }
        
        $this->save();
    }

    /**
     * Schedule follow-up
     */
    public function scheduleFollowUp(Carbon $date, string $time, ?string $notes = null): void
    {
        $dateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $time);
        
        $this->follow_up_scheduled_at = $dateTime;
        $this->follow_up_completed = false;
        
        if ($notes) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . 
                "[" . now()->format('Y-m-d H:i:s') . "] Follow-up scheduled for " . $dateTime->format('Y-m-d H:i') . " - " . $notes;
        }
        
        $this->save();
    }
}
