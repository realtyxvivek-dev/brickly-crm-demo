<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IgConversationState extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'current_step',
        'current_field',
        'completed',
        'last_reply_at',
        'retries',
        'flow_id',
    ];

    protected $casts = [
        'current_step' => 'integer',
        'completed' => 'boolean',
        'last_reply_at' => 'datetime',
        'retries' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(IgConversation::class, 'conversation_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(IgDmFlow::class, 'flow_id');
    }
}
