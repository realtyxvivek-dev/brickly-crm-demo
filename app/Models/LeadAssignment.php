<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'assigned_to',
        'assigned_by',
        'assignment_type',
        'assignment_method',
        'notes',
        'assigned_at',
        'unassigned_at',
        'is_active',
        'sheet_config_id',
        'sheet_row_number',
        'sheet_assignment_config_id',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithLiveLead(Builder $query): Builder
    {
        return $query->whereHas('lead');
    }

    public function scopeActiveWithLiveLead(Builder $query): Builder
    {
        return $query->active()->withLiveLead();
    }

    public function sheetConfig(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'sheet_config_id');
    }

    public function sheetAssignmentConfig(): BelongsTo
    {
        return $this->belongsTo(SheetAssignmentConfig::class, 'sheet_assignment_config_id');
    }
}
