<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewLeadSlaAutomationState extends Model
{
    use HasFactory;

    protected $table = 'new_lead_sla_automation_states';

    protected $fillable = [
        'lead_id',
        'config_id',
        'current_assignment_id',
        'original_assignment_id',
        'current_assigned_to',
        'original_assigned_to',
        'attempt_number',
        'sla_started_at',
        'sla_deadline_at',
        'responded_at',
        'response_outcome',
        'response_task_model',
        'response_task_id',
        'last_transferred_at',
        'escalated_at',
        'cancelled_at',
        'cancel_reason',
        'last_checked_at',
        'status',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'sla_started_at' => 'datetime',
        'sla_deadline_at' => 'datetime',
        'responded_at' => 'datetime',
        'last_transferred_at' => 'datetime',
        'escalated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_checked_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(NewLeadSlaAutomationConfig::class, 'config_id');
    }

    public function currentAssignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'current_assignment_id');
    }

    public function originalAssignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'original_assignment_id');
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assigned_to');
    }

    public function originalAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_assigned_to');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(NewLeadSlaAutomationAudit::class, 'state_id');
    }
}
