<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaOauthPage extends Model
{
    protected $fillable = [
        'meta_oauth_connection_id',
        'page_id',
        'page_name',
        'page_access_token',
        'tasks',
        'leadgen_subscribed',
        'lead_mode',
        'auto_assign_leads',
        'subscribed_at',
        'last_seen_at',
        'last_error',
    ];

    protected $casts = [
        'page_access_token' => 'encrypted',
        'tasks' => 'array',
        'leadgen_subscribed' => 'boolean',
        'auto_assign_leads' => 'boolean',
        'subscribed_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    protected $hidden = [
        'page_access_token',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MetaOauthConnection::class, 'meta_oauth_connection_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MetaOauthEvent::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(MetaOauthForm::class);
    }
}
