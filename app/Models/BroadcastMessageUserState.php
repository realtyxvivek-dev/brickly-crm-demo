<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastMessageUserState extends Model
{
    use HasFactory;

    protected $fillable = [
        'broadcast_message_id',
        'user_id',
        'delivered_at',
        'read_at',
        'acknowledged_at',
        'clicked_at',
        'dismissed_at',
        'delivery_channel',
        'last_popup_shown_at',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'clicked_at' => 'datetime',
        'dismissed_at' => 'datetime',
        'last_popup_shown_at' => 'datetime',
    ];

    public function broadcastMessage(): BelongsTo
    {
        return $this->belongsTo(BroadcastMessage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
