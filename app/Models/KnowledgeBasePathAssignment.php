<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBasePathAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_base_path_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'is_required',
        'due_date',
        'last_reminded_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'is_required' => 'boolean',
        'due_date' => 'date',
        'last_reminded_at' => 'datetime',
    ];

    public function path(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBasePath::class, 'knowledge_base_path_id');
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
