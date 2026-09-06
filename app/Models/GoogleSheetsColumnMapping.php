<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetsColumnMapping extends Model
{
    use HasFactory;

    protected $table = 'google_sheets_column_mappings';

    protected $fillable = [
        'google_sheets_config_id',
        'sheet_column',
        'lead_field_key',
        'field_type',
        'field_label',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Get the Google Sheets config that owns this column mapping
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheets_config_id');
    }
}
