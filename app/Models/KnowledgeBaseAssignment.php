<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_item_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'is_required',
        'due_date',
        'completed_late',
        'last_reminded_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_required' => 'boolean',
        'due_date' => 'date',
        'completed_late' => 'boolean',
        'last_reminded_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseItem::class, 'knowledge_base_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
