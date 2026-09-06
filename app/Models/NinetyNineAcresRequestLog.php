<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NinetyNineAcresRequestLog extends Model
{
    protected $fillable = [
        'ninety_nine_acres_setting_id',
        'request_id',
        'request_ip',
        'external_lead_id',
        'phone',
        'raw_payload',
        'mapped_payload',
        'validation_result',
        'assignment_result',
        'fallback_result',
        'status',
        'lead_id',
        'duplicate',
        'is_test',
        'response_time_ms',
        'error_message',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'mapped_payload' => 'array',
        'validation_result' => 'array',
        'assignment_result' => 'array',
        'fallback_result' => 'array',
        'duplicate' => 'boolean',
        'is_test' => 'boolean',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(NinetyNineAcresSetting::class, 'ninety_nine_acres_setting_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
