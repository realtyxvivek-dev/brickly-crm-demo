<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tower extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'tower_name',
        'tower_number',
        'floor_count',
        'unit_count',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'tower_number' => 'integer',
        'floor_count' => 'integer',
        'unit_count' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the project that owns the tower.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the unit types for the tower.
     */
    public function unitTypes(): HasMany
    {
        return $this->hasMany(UnitType::class);
    }

    /**
     * Check if tower has any unit types.
     */
    public function getHasUnitsAttribute(): bool
    {
        return $this->unitTypes()->count() > 0;
    }

    /**
     * Check if tower is coming soon (no units).
     */
    public function getIsComingSoonAttribute(): bool
    {
        return !$this->hasUnits;
    }
}
