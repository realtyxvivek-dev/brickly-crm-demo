<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class SlaTracking extends Model
{
    use HasFactory;

    protected $table = 'sla_tracking';

    protected $fillable = [
        'lead_assignment_id',
        'sla_minutes',
        'started_at',
        'first_contact_at',
        'breached_at',
        'escalated_at',
        'escalated_to',
        'status',
    ];

    protected $casts = [
        'sla_minutes' => 'integer',
        'started_at' => 'datetime',
        'first_contact_at' => 'datetime',
        'breached_at' => 'datetime',
        'escalated_at' => 'datetime',
    ];

    public function leadAssignment(): BelongsTo
    {
        return null;
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    /**
     * Check if SLA is breached
     */
    public function checkBreach(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $deadline = $this->started_at->addMinutes($this->sla_minutes);
        
        if (Carbon::now()->greaterThan($deadline)) {
            $this->update([
                'status' => 'breached',
                'breached_at' => Carbon::now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Mark SLA as met
     */
    public function markAsMet(): void
    {
        if ($this->status === 'pending') {
            $this->update([
                'status' => 'met',
                'first_contact_at' => Carbon::now(),
            ]);
        }
    }
}
