<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaOauthForm extends Model
{
    protected $fillable = [
        'meta_oauth_page_id',
        'form_id',
        'form_name',
        'status',
        'meta_created_time',
        'raw_payload',
        'last_seen_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'meta_created_time' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaOauthPage::class, 'meta_oauth_page_id');
    }
}
