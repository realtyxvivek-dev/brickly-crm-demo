<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookLeadCenterAuditRow extends Model
{
    protected $fillable = [
        'facebook_lead_center_audit_id',
        'row_hash',
        'leadgen_id',
        'page_id',
        'form_id',
        'lead_name',
        'phone',
        'email',
        'submitted_at_text',
        'raw_text',
        'raw_payload',
        'status',
        'match_source',
        'match_reason',
        'crm_lead_id',
        'fb_lead_id',
        'fb_webhook_event_id',
        'meta_oauth_event_id',
        'imported_at',
        'imported_by_user_id',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'imported_at' => 'datetime',
    ];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(FacebookLeadCenterAudit::class, 'facebook_lead_center_audit_id');
    }

    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'crm_lead_id');
    }
}
