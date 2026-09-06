<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetImportState extends Model
{
    use HasFactory;

    protected $table = 'google_sheet_import_state';

    protected $fillable = [
        'google_sheets_config_id',
        'last_processed_row',
        'last_run_at',
        'last_error',
    ];

    protected $casts = [
        'last_processed_row' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheets_config_id');
    }
}

