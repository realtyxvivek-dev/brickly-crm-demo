<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorPublicReview extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const SOURCE_PUBLIC_FORM = 'public_form';
    public const SOURCE_ADVISOR_PANEL = 'advisor_panel';
    public const CONTENT_TEXT = 'text';
    public const CONTENT_VIDEO = 'video';

    protected $fillable = [
        'advisor_public_profile_id',
        'lead_id',
        'customer_name',
        'customer_phone',
        'customer_phone_masked',
        'rating',
        'review_text',
        'project_name',
        'is_verified_customer',
        'submission_source',
        'content_type',
        'video_url',
        'video_platform',
        'video_thumbnail_url',
        'submitted_by_user_id',
        'customer_consent_confirmed',
        'moderation_status',
        'approved_by',
        'approved_at',
        'rejected_reason',
    ];

    protected $casts = [
        'is_verified_customer' => 'boolean',
        'customer_consent_confirmed' => 'boolean',
        'approved_at' => 'datetime',
        'rating' => 'integer',
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }
}
