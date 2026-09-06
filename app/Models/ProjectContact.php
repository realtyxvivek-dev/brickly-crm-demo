<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'builder_contact_id',
        'contact_role',
    ];

    /**
     * Get the project that owns the contact.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the builder contact.
     */
    public function builderContact(): BelongsTo
    {
        return $this->belongsTo(BuilderContact::class);
    }

    /**
     * Get the contact person name.
     */
    public function getPersonNameAttribute(): string
    {
        return $this->builderContact->person_name;
    }

    /**
     * Get the mobile number.
     */
    public function getMobileNumberAttribute(): string
    {
        return $this->builderContact->mobile_number;
    }

    /**
     * Get the WhatsApp number.
     */
    public function getWhatsAppNumber(): ?string
    {
        return $this->builderContact->getEffectiveWhatsAppNumber();
    }
}
