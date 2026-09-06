<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'allow_half_day',
        'annual_quota',
        'is_active',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
        'allow_half_day' => 'boolean',
        'is_active' => 'boolean',
        'annual_quota' => 'decimal:2',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
