<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IgFailedJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_type',
        'related_type',
        'related_id',
        'payload',
        'attempts',
        'error_message',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'failed_at' => 'datetime',
    ];
}
