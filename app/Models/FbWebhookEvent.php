<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbWebhookEvent extends Model
{
    protected $table = 'fb_webhook_events';

    protected $fillable = [
        'raw_payload',
        'leadgen_id',
        'status',
        'error',
    ];

    protected $casts = [
        'raw_payload' => 'array',
    ];
}
