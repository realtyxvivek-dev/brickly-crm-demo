<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InterestedProjectName extends Model
{
    use HasFactory;

    protected $table = 'interested_project_names';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user who created this project name.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the prospects interested in this project.
     */
    public function prospects(): BelongsToMany
    {
        return $this->belongsToMany(Prospect::class, 'prospect_project', 'project_id', 'prospect_id')
            ->withTimestamps();
    }
}
