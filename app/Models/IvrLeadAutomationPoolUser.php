<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IvrLeadAutomationPoolUser extends Model
{
    use HasFactory;

    protected $table = 'ivr_lead_automation_pool_users';

    protected $fillable = [
        'config_id',
        'user_id',
        'allocation_percentage',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(IvrLeadAutomationConfig::class, 'config_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
