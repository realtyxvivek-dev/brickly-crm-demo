<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadBankImportSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_file_name',
        'stored_path',
        'source_type',
        'headers',
        'column_mapping',
        'default_tags',
        'folder_name',
        'folder_color',
        'folder_tag_id',
        'import_batch_id',
        'status',
        'total_rows',
        'processed_rows',
        'processing_error',
        'included_rows',
        'duplicate_rows',
        'invalid_rows',
        'imported_rows',
        'skipped_rows',
        'failed_rows',
        'imported_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'column_mapping' => 'array',
        'default_tags' => 'array',
        'imported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(LeadBankImportRow::class);
    }

    public function folderTag(): BelongsTo
    {
        return $this->belongsTo(\App\Models\LeadTag::class, 'folder_tag_id');
    }
}
