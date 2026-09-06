<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppWebEvent extends Model
{
    protected $table = 'whatsapp_web_events';

    protected $fillable = [
        'event_hash',
        'session_key',
        'phone',
        'contact_name',
        'message_preview',
        'message_timestamp',
        'decision',
        'lead_id',
        'processed_by_mode',
        'created_by_user_id',
        'processed_at',
        'payload_meta',
    ];

    protected $casts = [
        'message_timestamp' => 'datetime',
        'processed_at' => 'datetime',
        'payload_meta' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
