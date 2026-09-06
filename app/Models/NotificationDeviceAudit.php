<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDeviceAudit extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'channel',
        'token_hash',
        'from_user_id',
        'to_user_id',
        'device_type',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
