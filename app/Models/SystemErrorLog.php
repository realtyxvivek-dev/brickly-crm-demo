<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemErrorLog extends Model
{
    protected $fillable = [
        'status_code',
        'method',
        'url',
        'path',
        'user_id',
        'user_name',
        'user_role',
        'ip',
        'exception_class',
        'message',
        'file',
        'line',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
