<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetaBulkRecoveryScanForm extends Model
{
    protected $fillable = [
        'scan_id',
        'fb_page_id',
        'fb_form_id',
        'status',
        'fetched_count',
        'importable_count',
        'already_present_count',
        'failed_count',
        'error',
    ];

    public function scan(): BelongsTo
    {
        return $this->belongsTo(MetaBulkRecoveryScan::class, 'scan_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(FbPage::class, 'fb_page_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(MetaBulkRecoveryScanLead::class, 'scan_form_id');
    }
}
