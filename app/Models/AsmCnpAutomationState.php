<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsmCnpAutomationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'lead_assignment_id',
        'config_id',
        'original_assigned_to',
        'current_assigned_to',
        'last_retry_task_id',
        'stage',
        'stage_record_id',
        'cnp_count',
        'assignment_started_at',
        'stage_started_at',
        'first_cnp_at',
        'last_cnp_at',
        'next_retry_at',
        'eligible_for_transfer_at',
        'last_processed_at',
        'transfer_eligible',
        'status',
        'cancel_reason',
        'reset_reason',
        'reset_at',
        'cancelled_at',
        'transferred_at',
        'quarantined_at',
    ];

    protected $casts = [
        'assignment_started_at' => 'datetime',
        'stage_started_at' => 'datetime',
        'first_cnp_at' => 'datetime',
        'last_cnp_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'eligible_for_transfer_at' => 'datetime',
        'last_processed_at' => 'datetime',
        'transfer_eligible' => 'boolean',
        'reset_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'transferred_at' => 'datetime',
        'quarantined_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function leadAssignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AsmCnpAutomationConfig::class, 'config_id');
    }

    public function originalAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_assigned_to');
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assigned_to');
    }

    public function lastRetryTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'last_retry_task_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationAudit::class, 'state_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationLeadHistory::class, 'state_id');
    }
}
