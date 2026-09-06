<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleSheetsRequestLog extends Model
{
    use HasFactory;

    protected $table = 'google_sheets_request_logs';

    protected $fillable = [
        'google_sheets_config_id',
        'request_id',
        'request_ip',
        'raw_payload',
        'status',
        'is_test',
        'error_message',
        'lead_id',
        'response_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'is_test' => 'boolean',
        'response_payload' => 'array',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheets_config_id');
    }
}
