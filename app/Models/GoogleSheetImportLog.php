<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetImportLog extends Model
{
    use HasFactory;

    protected $table = 'google_sheet_import_logs';

    protected $fillable = [
        'google_sheets_config_id',
        'trigger_source',
        'started_at',
        'finished_at',
        'duration_ms',
        'timed_out',
        'status',
        'last_processed_row_before',
        'last_processed_row_after',
        'imported_count',
        'already_exists_count',
        'already_synced_count',
        'missing_required_count',
        'error_count',
        'error_message',
        'meta_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
        'timed_out' => 'boolean',
        'last_processed_row_before' => 'integer',
        'last_processed_row_after' => 'integer',
        'imported_count' => 'integer',
        'already_exists_count' => 'integer',
        'already_synced_count' => 'integer',
        'missing_required_count' => 'integer',
        'error_count' => 'integer',
        'meta_json' => 'array',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheets_config_id');
    }
}

