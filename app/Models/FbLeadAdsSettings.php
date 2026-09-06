<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbLeadAdsSettings extends Model
{
    protected $table = 'fb_lead_ads_settings';

    protected $fillable = [
        'page_access_token',
        'marketing_access_token',
        'ad_account_id',
        'cpl_sync_enabled',
        'last_cpl_synced_at',
        'page_id',
        'graph_version',
        'webhook_verify_token',
        'app_secret',
        'signature_verification_enabled',
    ];

    protected $casts = [
        'signature_verification_enabled' => 'boolean',
        'cpl_sync_enabled' => 'boolean',
        'page_access_token' => 'encrypted',
        'marketing_access_token' => 'encrypted',
        'last_cpl_synced_at' => 'datetime',
    ];

    public static function getSettings(): self
    {
        $settings = self::first();
        if (!$settings) {
            $settings = self::create([
                'graph_version' => 'v18.0',
            ]);
        }
        return $settings;
    }
}
