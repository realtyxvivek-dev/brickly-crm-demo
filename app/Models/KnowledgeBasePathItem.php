<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBasePathItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_path_id',
        'knowledge_base_item_id',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function path(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBasePath::class, 'knowledge_base_path_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseItem::class, 'knowledge_base_item_id');
    }
}
