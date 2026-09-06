<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\FbForm;

class NewLeadSlaAutomationConfig extends Model
{
    use HasFactory;

    protected $table = 'new_lead_sla_automation_configs';

    protected $fillable = [
        'name',
        'source',
        'fb_form_id',
        'is_active',
        'sla_minutes',
        'business_start_time',
        'business_end_time',
        'weekends_off',
        'max_transfer_attempts',
        'email_enabled',
        'in_app_enabled',
        'dashboard_alert_enabled',
        'skip_inactive_users',
        'skip_absent_users',
        'last_round_robin_user_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'weekends_off' => 'boolean',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'dashboard_alert_enabled' => 'boolean',
        'skip_inactive_users' => 'boolean',
        'skip_absent_users' => 'boolean',
        'sla_minutes' => 'integer',
        'max_transfer_attempts' => 'integer',
    ];

    public function poolUsers(): HasMany
    {
        return $this->hasMany(NewLeadSlaAutomationPoolUser::class, 'config_id')->orderBy('sort_order');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(NewLeadSlaAutomationRecipient::class, 'config_id');
    }

    public function states(): HasMany
    {
        return $this->hasMany(NewLeadSlaAutomationState::class, 'config_id');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(NewLeadSlaAutomationAudit::class, 'config_id');
    }

    public function lastRoundRobinUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_round_robin_user_id');
    }

    public function fbForm(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }
}
