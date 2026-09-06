<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetAssignmentConfig extends Model
{
    use HasFactory;

    protected $table = 'sheet_assignment_config';

    protected $fillable = [
        'google_sheets_config_id',
        'assignment_method',
        'auto_assign_enabled',
        'linked_telecaller_id',
        'per_sheet_daily_limit',
        'percentage_config',
    ];

    protected $casts = [
        'auto_assign_enabled' => 'boolean',
        'per_sheet_daily_limit' => 'integer',
        'percentage_config' => 'array',
    ];

    public function googleSheetsConfig(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheets_config_id');
    }

    public function linkedTelecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_telecaller_id');
    }

    public function percentageConfigs(): HasMany
    {
        return $this->hasMany(SheetPercentageConfig::class, 'sheet_assignment_config_id');
    }

    public function leadAssignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class, 'sheet_assignment_config_id');
    }
}
