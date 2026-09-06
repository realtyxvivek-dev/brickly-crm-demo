<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaOauthConnection extends Model
{
    protected $fillable = [
        'user_id',
        'meta_user_id',
        'meta_user_name',
        'user_access_token',
        'granted_scopes',
        'token_expires_at',
        'status',
        'last_error',
        'last_connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'user_access_token' => 'encrypted',
        'granted_scopes' => 'array',
        'token_expires_at' => 'datetime',
        'last_connected_at' => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    protected $hidden = [
        'user_access_token',
    ];

    public function pages(): HasMany
    {
        return $this->hasMany(MetaOauthPage::class);
    }
}
