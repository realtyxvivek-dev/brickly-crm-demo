<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPhonePrivacySetting extends Model
{
    public const CALL_CLOUD_PREFERRED = 'cloud_preferred';
    public const CALL_CLOUD_ONLY = 'cloud_only';
    public const WHATSAPP_DIRECT_ALLOWED = 'direct_allowed';
    public const WHATSAPP_API_ONLY = 'api_only';

    protected $fillable = [
        'user_id',
        'mask_enabled',
        'call_mode',
        'whatsapp_mode',
        'updated_by',
    ];

    protected $casts = [
        'mask_enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
