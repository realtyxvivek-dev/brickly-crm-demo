<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceAutomationRuleUser extends Model
{
    protected $fillable = [
        'rule_id',
        'user_id',
        'percentage',
        'daily_limit',
        'assigned_count_today',
        'last_reset_date',
    ];

    protected $casts = [
        'last_reset_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function rule()
    {
        return $this->belongsTo(SourceAutomationRule::class, 'rule_id');
    }

    public function resetIfNewDay(): void
    {
        $today = now()->toDateString();
        if ($this->last_reset_date?->toDateString() !== $today) {
            $this->update([
                'assigned_count_today' => 0,
                'last_reset_date' => $today,
            ]);
        }
    }

    public function isWithinLimit(): bool
    {
        if (!$this->daily_limit) {
            return true;
        }
        return $this->assigned_count_today < $this->daily_limit;
    }

    public function incrementCount(): void
    {
        $this->resetIfNewDay();
        $this->increment('assigned_count_today');
    }
}
