<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseProgress extends Model
{
    use HasFactory;

    protected $table = 'knowledge_base_progress';

    protected $fillable = [
        'knowledge_base_item_id',
        'user_id',
        'status',
        'article_seconds_viewed',
        'article_scroll_percent',
        'video_progress_percent',
        'video_last_position_seconds',
        'pdf_opened_at',
        'pdf_interacted_at',
        'pdf_seconds_viewed',
        'started_at',
        'last_interaction_at',
        'completed_at',
    ];

    protected $casts = [
        'article_seconds_viewed' => 'integer',
        'article_scroll_percent' => 'integer',
        'video_progress_percent' => 'integer',
        'video_last_position_seconds' => 'integer',
        'pdf_opened_at' => 'datetime',
        'pdf_interacted_at' => 'datetime',
        'pdf_seconds_viewed' => 'integer',
        'started_at' => 'datetime',
        'last_interaction_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseItem::class, 'knowledge_base_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
