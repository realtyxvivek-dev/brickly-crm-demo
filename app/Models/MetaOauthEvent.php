<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaOauthEvent extends Model
{
    protected $fillable = [
        'meta_oauth_page_id',
        'crm_lead_id',
        'page_id',
        'form_id',
        'leadgen_id',
        'raw_payload',
        'lead_payload',
        'field_data',
        'mapped_data',
        'status',
        'error',
        'processed_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'lead_payload' => 'array',
        'field_data' => 'array',
        'mapped_data' => 'array',
        'processed_at' => 'datetime',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(MetaOauthPage::class, 'meta_oauth_page_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'crm_lead_id');
    }
}
