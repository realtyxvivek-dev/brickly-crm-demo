<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadBankCooldown extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'cooldown_until',
        'reason',
        'source_type',
        'created_by',
    ];

    protected $casts = [
        'cooldown_until' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
