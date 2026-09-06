<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadFormFieldValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'field_key',
        'field_value',
        'filled_by_user_id',
        'filled_at',
    ];

    protected $casts = [
        'filled_at' => 'datetime',
    ];

    /**
     * Get the lead that owns this field value
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the user who filled this field
     */
    public function filledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filled_by_user_id');
    }

    /**
     * Get the field configuration
     */
    public function fieldConfig(): ?LeadFormField
    {
        return LeadFormField::where('field_key', $this->field_key)->first();
    }

    public function getFieldValueAttribute($value)
    {
        $phoneKeys = ['alternate_phone', 'secondary_phone', 'alternate_number'];
        if (!in_array((string) ($this->attributes['field_key'] ?? ''), $phoneKeys, true)) {
            return $value;
        }

        return app(\App\Services\PhonePrivacyService::class)->display($value, auth()->user());
    }
}
