<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class IgConversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'instagram_account_id',
        'lead_id',
        'duplicate_lead_id',
        'duplicate_checked_at',
        'instagram_user_id',
        'instagram_username',
        'original_comment',
        'comment_id',
        'media_id',
        'automation_rule_id',
        'collected_fields',
        'is_human_taken_over',
        'human_taken_over_by',
        'human_taken_over_at',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'collected_fields' => 'array',
        'is_human_taken_over' => 'boolean',
        'human_taken_over_at' => 'datetime',
        'duplicate_checked_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function instagramAccount(): BelongsTo
    {
        return $this->belongsTo(InstagramAccount::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function duplicateLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'duplicate_lead_id');
    }

    public function automationRule(): BelongsTo
    {
        return $this->belongsTo(IgAutomationRule::class, 'automation_rule_id');
    }

    public function state(): HasOne
    {
        return $this->hasOne(IgConversationState::class, 'conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(IgMessage::class, 'conversation_id');
    }
}
