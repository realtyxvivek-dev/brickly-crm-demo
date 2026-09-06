<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteIntegrationFieldMapping extends Model
{
    protected $fillable = [
        'website_integration_id',
        'incoming_field',
        'crm_field',
        'is_required',
        'default_value',
        'is_ignored',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_ignored' => 'boolean',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(WebsiteIntegration::class, 'website_integration_id');
    }
}
