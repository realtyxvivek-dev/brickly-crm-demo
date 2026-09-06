<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlacklistedNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'reason',
        'blacklisted_by',
        'blacklisted_at',
    ];

    protected $casts = [
        'blacklisted_at' => 'datetime',
    ];

    public function blacklistedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blacklisted_by');
    }
}
