<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbLead extends Model
{
    protected $table = 'fb_leads';

    protected $fillable = [
        'leadgen_id',
        'fb_form_id',
        'crm_lead_id',
        'ad_id',
        'ad_name',
        'adset_id',
        'adset_name',
        'campaign_id',
        'campaign_name',
        'platform',
        'meta_created_time',
        'field_data_json',
        'raw_response_json',
    ];

    protected $casts = [
        'field_data_json' => 'array',
        'raw_response_json' => 'array',
        'meta_created_time' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Lead::class, 'crm_lead_id');
    }
}
