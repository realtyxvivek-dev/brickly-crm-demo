<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IgAutomationRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'instagram_account_id',
        'media_id',
        'name',
        'keywords',
        'public_reply_message',
        'dm_flow_id',
        'priority',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'keywords' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class);
    }

    public function dmFlow(): BelongsTo
    {
        return $this->belongsTo(IgDmFlow::class, 'dm_flow_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(IgConversation::class, 'automation_rule_id');
    }
}
