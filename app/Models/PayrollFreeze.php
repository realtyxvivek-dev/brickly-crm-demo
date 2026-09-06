<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollFreeze extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'month',
        'office_location_id',
        'freeze_scope',
        'status',
        'frozen_by',
        'frozen_at',
        'notes',
        'hr_finalized_by',
        'hr_finalized_at',
        'submitted_to_admin_by',
        'submitted_to_admin_at',
        'admin_reviewed_by',
        'admin_reviewed_at',
        'admin_remark',
        'locked_by_user_id',
        'locked_at',
        'lock_expires_at',
    ];

    protected $casts = [
        'frozen_at' => 'datetime',
        'hr_finalized_at' => 'datetime',
        'submitted_to_admin_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'locked_at' => 'datetime',
        'lock_expires_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollFreezeItem::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function frozenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PayrollVersion::class);
    }
}
