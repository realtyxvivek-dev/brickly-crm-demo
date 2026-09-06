<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PabblyIntegrationSettings extends Model
{
    protected $table = 'pabbly_integration_settings';
    
    protected $fillable = [
        'webhook_secret',
        'is_active',
        'last_webhook_at',
        'webhook_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_webhook_at' => 'datetime',
        'webhook_count' => 'integer',
    ];

    /**
     * Get or create settings
     */
    public static function getSettings()
    {
        $settings = self::first();
        
        if (!$settings) {
            $settings = self::create([
                'webhook_secret' => null,
                'is_active' => true,
                'last_webhook_at' => null,
                'webhook_count' => 0,
            ]);
        }
        
        return $settings;
    }

    /**
     * Update settings
     */
    public static function updateSettings(array $data)
    {
        $settings = self::getSettings();
        $settings->update($data);
        return $settings;
    }

    /**
     * Record webhook call
     */
    public static function recordWebhook()
    {
        $settings = self::getSettings();
        $settings->increment('webhook_count');
        $settings->update(['last_webhook_at' => now()]);
        return $settings;
    }
}
