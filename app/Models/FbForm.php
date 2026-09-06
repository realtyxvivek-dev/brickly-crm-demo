<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FbForm extends Model
{
    protected $table = 'fb_forms';

    protected $fillable = [
        'fb_page_id',
        'form_id',
        'form_name',
        'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(FbPage::class, 'fb_page_id');
    }

    public function mapping(): HasOne
    {
        return $this->hasOne(FbFormMapping::class, 'fb_form_id')->latestOfMany();
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(FbFormMapping::class, 'fb_form_id');
    }

    public function fbLeads(): HasMany
    {
        return $this->hasMany(FbLead::class, 'fb_form_id');
    }
}
