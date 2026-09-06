<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_path',
        'mime_type',
        'file_size',
        'compressed_size',
        'width',
        'height',
        'compression_quality',
        'file_hash',
        'meta_json',
        'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
