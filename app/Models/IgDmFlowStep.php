<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IgDmFlowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'flow_id',
        'step_order',
        'message_text',
        'save_reply_as',
        'action_type',
        'message_type',
        'media_url',
        'attachment_type',
    ];

    protected $casts = [
        'step_order' => 'integer',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(IgDmFlow::class, 'flow_id');
    }
}
