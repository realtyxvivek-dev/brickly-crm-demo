<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InstagramAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ig_user_id',
        'ig_username',
        'account_type',
        'profile_picture_url',
        'page_id',
        'page_name',
        'access_token',
        'refresh_token',
        'token_expiry',
        'status',
        'last_event_at',
        'connected_by',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expiry' => 'datetime',
        'last_event_at' => 'datetime',
    ];

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function automationRules(): HasMany
    {
        return $this->hasMany(IgAutomationRule::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(IgConversation::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(IgWebhookEvent::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(IgApiLog::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && filled($this->access_token);
    }
}
