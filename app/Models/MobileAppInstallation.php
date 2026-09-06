<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAppInstallation extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'installed_version_code',
        'installed_version_name',
        'last_download_version_code',
        'last_download_version_name',
        'download_click_count',
        'fcm_token',
        'app_build_label',
        'device_label',
        'last_opened_at',
        'last_reported_at',
        'last_download_clicked_at',
    ];

    protected $casts = [
        'installed_version_code' => 'integer',
        'last_download_version_code' => 'integer',
        'download_click_count' => 'integer',
        'last_opened_at' => 'datetime',
        'last_reported_at' => 'datetime',
        'last_download_clicked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
