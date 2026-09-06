<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsmCnpAutomationLeadHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'user_id',
        'config_id',
        'state_id',
        'lead_assignment_id',
        'completed_cnp_count',
        'max_hit_at',
    ];

    protected $casts = [
        'max_hit_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(AsmCnpAutomationConfig::class, 'config_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(AsmCnpAutomationState::class, 'state_id');
    }

    public function leadAssignment(): BelongsTo
    {
        return $this->belongsTo(LeadAssignment::class);
    }
}
