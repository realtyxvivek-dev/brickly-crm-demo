<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorPublicGalleryItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'advisor_public_profile_id',
        'lead_id',
        'image_path',
        'caption',
        'category',
        'customer_consent_confirmed',
        'moderation_status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'customer_consent_confirmed' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(AdvisorPublicProfile::class, 'advisor_public_profile_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
