<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewLeadSlaAutomationRecipient extends Model
{
    use HasFactory;

    protected $table = 'new_lead_sla_automation_recipients';

    protected $fillable = [
        'config_id',
        'user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
