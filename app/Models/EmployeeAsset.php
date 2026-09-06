<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeAsset extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_LOST = 'lost';
    public const STATUS_DAMAGED = 'damaged';

    protected $fillable = [
        'employee_profile_id',
        'asset_type',
        'asset_name',
        'serial_number',
        'vendor',
        'asset_condition',
        'status',
        'issued_at',
        'returned_at',
        'attachment_path',
        'notes',
        'issued_by',
        'returned_by',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'returned_at' => 'date',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmployeeAssetLog::class)->orderByDesc('performed_at')->orderByDesc('id');
    }
}
