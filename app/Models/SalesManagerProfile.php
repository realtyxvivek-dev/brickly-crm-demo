<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesManagerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'team_size',
        'preferences',
    ];

    protected $casts = [
        'preferences' => 'array',
        'team_size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
