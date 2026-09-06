<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteIntegrationRequestLog extends Model
{
    protected $fillable = [
        'website_integration_id',
        'request_id',
        'request_ip',
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

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WebsiteIntegration::class, 'website_integration_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
