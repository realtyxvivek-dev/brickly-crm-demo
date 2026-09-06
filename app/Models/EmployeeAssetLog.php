<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAssetLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_asset_id',
        'employee_profile_id',
        'action',
        'status',
        'notes',
        'meta_json',
        'performed_by',
        'performed_at',
    ];

    protected $casts = [
        'meta_json' => 'array',
        'performed_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(EmployeeAsset::class, 'employee_asset_id');
    }

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
