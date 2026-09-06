<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppAutomationJourney extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_automation_journeys';

    protected $fillable = [
        'key',
        'name',
        'slug',
        'category',
        'status',
        'is_active',
        'is_preset',
        'test_mode',
        'template_id',
        'description',
        'default_filters',
        'meta',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_preset' => 'boolean',
        'test_mode' => 'boolean',
        'default_filters' => 'array',
        'meta' => 'array',
    ];

    public function rules(): HasMany
    {
        return $this->hasMany(WhatsAppAutomationRule::class, 'journey_id')->orderBy('priority');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WhatsAppAutomationLog::class, 'journey_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }
}
