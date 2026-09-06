<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileAppDiagnostic extends Model
{
    protected $fillable = [
        'user_id',
        'platform',
        'app_version_name',
        'app_version_code',
        'app_build_label',
        'device_model',
        'manufacturer',
        'android_version',
        'sdk_int',
        'health_status',
        'permissions',
        'features',
        'test_results',
        'last_error',
        'reported_at',
    ];

    protected $casts = [
        'app_version_code' => 'integer',
        'sdk_int' => 'integer',
        'permissions' => 'array',
        'features' => 'array',
        'test_results' => 'array',
        'reported_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
