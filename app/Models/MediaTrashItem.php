<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaTrashItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_type',
        'source_id',
        'source_field',
        'module',
        'media_type',
        'disk',
        'file_path',
        'file_url',
        'file_name',
        'mime_type',
        'file_size',
        'is_protected',
        'protected_reason',
        'trashed_by',
        'trashed_at',
        'delete_after',
        'restored_at',
        'restored_by',
        'permanently_deleted_at',
        'permanently_deleted_by',
        'meta',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_protected' => 'boolean',
        'trashed_at' => 'datetime',
        'delete_after' => 'datetime',
        'restored_at' => 'datetime',
        'permanently_deleted_at' => 'datetime',
        'meta' => 'array',
    ];

    public function trashedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trashed_by');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by');
    }
}
