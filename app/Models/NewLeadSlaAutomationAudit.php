<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewLeadSlaAutomationAudit extends Model
{
    use HasFactory;

    protected $table = 'new_lead_sla_automation_audits';

    protected $fillable = [
        'state_id',
        'lead_id',
        'config_id',
        'from_user_id',
        'to_user_id',
        'assignment_id',
        'task_model',
        'task_id',
        'action',
        'message',
        'meta',
        'acted_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'acted_at' => 'datetime',
    ];

    public function state(): BelongsTo
    {
        return $this->belongsTo(NewLeadSlaAutomationState::class, 'state_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(NewLeadSlaAutomationConfig::class, 'config_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class, 'assignment_id');
    }
}
