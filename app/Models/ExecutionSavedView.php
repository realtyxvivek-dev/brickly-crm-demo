<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutionSavedView extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'scope_tab',
        'filters',
        'is_shared',
    ];

    protected $casts = [
        'filters' => 'array',
        'is_shared' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
