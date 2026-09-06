<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaAdInsightDaily extends Model
{
    protected $table = 'meta_ad_insights_daily';

    protected $fillable = [
        'date',
        'ad_account_id',
        'campaign_id',
        'campaign_name',
        'adset_id',
        'adset_name',
        'ad_id',
        'ad_name',
        'spend',
        'meta_leads',
        'actions_json',
        'synced_at',
    ];

    protected $casts = [
        'date' => 'date',
        'spend' => 'decimal:2',
        'meta_leads' => 'integer',
        'actions_json' => 'array',
        'synced_at' => 'datetime',
    ];
}
