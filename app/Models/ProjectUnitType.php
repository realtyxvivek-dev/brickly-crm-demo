<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectUnitType extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'display_order',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sizeVariants(): HasMany
    {
        return $this->hasMany(ProjectSizeVariant::class)->orderBy('display_order');
    }
}
