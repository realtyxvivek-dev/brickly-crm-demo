<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewLeadSlaAutomationPoolUser extends Model
{
    use HasFactory;

    protected $table = 'new_lead_sla_automation_pool_users';

    protected $fillable = [
        'config_id',
        'user_id',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(NewLeadSlaAutomationConfig::class, 'config_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
