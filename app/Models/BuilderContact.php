<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BuilderContact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'builder_id',
        'person_name',
        'mobile_number',
        'whatsapp_number',
        'whatsapp_same_as_mobile',
        'preferred_mode',
        'is_active',
    ];

    protected $casts = [
        'whatsapp_same_as_mobile' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the builder that owns the contact.
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }

    /**
     * Get the project contacts that use this builder contact.
     */
    public function projectContacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class, 'builder_contact_id');
    }

    /**
     * Get the WhatsApp number (use mobile if same_as_mobile is true).
     */
    public function getEffectiveWhatsAppNumber(): ?string
    {
        if ($this->whatsapp_same_as_mobile) {
            return $this->mobile_number;
        }

        return $this->whatsapp_number ?: $this->mobile_number;
    }

    /**
     * Get formatted mobile number.
     */
    public function getFormattedMobileAttribute(): string
    {
        return $this->mobile_number;
    }

    /**
     * Check if contact is active.
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
}
