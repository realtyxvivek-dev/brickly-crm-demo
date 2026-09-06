<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetaBulkRecoveryScanLead extends Model
{
    protected $fillable = [
        'scan_id',
        'scan_form_id',
        'fb_form_id',
        'leadgen_id',
        'name',
        'phone',
        'email',
        'meta_created_time',
        'campaign_name',
        'ad_name',
        'raw_meta_json',
        'status',
        'error',
    ];

    protected $casts = [
        'meta_created_time' => 'datetime',
        'raw_meta_json' => 'array',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(MetaBulkRecoveryScan::class, 'scan_id');
    }

    public function scanForm(): BelongsTo
    {
        return $this->belongsTo(MetaBulkRecoveryScanForm::class, 'scan_form_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }
}
