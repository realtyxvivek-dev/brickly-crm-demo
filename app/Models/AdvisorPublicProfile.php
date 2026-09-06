<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvisorPublicProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'public_slug',
        'designation',
        'bio',
        'experience_years',
        'languages',
        'service_areas',
        'specialization_tags',
        'why_choose_me',
        'successful_closures',
        'site_visits_handled',
        'sqft_sold',
        'happy_families_served',
        'investor_portfolio_value',
        'active_investors',
        'nri_investors_assisted',
        'bookings_this_quarter',
        'is_public',
        'is_approved',
        'approved_by',
        'approved_at',
        'completion_percentage',
    ];

    protected $casts = [
        'languages' => 'array',
        'service_areas' => 'array',
        'specialization_tags' => 'array',
        'is_public' => 'boolean',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'completion_percentage' => 'integer',
        'experience_years' => 'integer',
        'successful_closures' => 'integer',
        'site_visits_handled' => 'integer',
        'sqft_sold' => 'integer',
        'happy_families_served' => 'integer',
        'investor_portfolio_value' => 'integer',
        'active_investors' => 'integer',
        'nri_investors_assisted' => 'integer',
        'bookings_this_quarter' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AdvisorPublicReview::class, 'advisor_public_profile_id');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(AdvisorPublicGalleryItem::class, 'advisor_public_profile_id');
    }

    /**
     * Builders this advisor is partnered with (assigned by admin).
     */
    /**
     * Pivot rows for this advisor (builders that are hidden and/or featured).
     * By default, ALL active builders are shown on every advisor's public
     * profile; a pivot row only exists when the admin marks a builder as
     * hidden (is_hidden = 1) and/or featured (is_featured = 1) for THIS
     * advisor.
     */
    public function builders(): BelongsToMany
    {
        return $this->belongsToMany(
            Builder::class,
            'advisor_public_profile_builder'
        )
            ->withPivot(['is_hidden', 'is_featured', 'display_order'])
            ->withTimestamps()
            ->orderByPivot('display_order');
    }

    public function calculateCompletionPercentage(): int
    {
        $score = 0;
        $total = 8;

        if ($this->user?->profile_picture) {
            $score++;
        }

        if (!empty($this->bio)) {
            $score++;
        }

        if (!empty($this->experience_years)) {
            $score++;
        }

        if (!empty($this->service_areas)) {
            $score++;
        }

        if (!empty($this->why_choose_me)) {
            $score++;
        }

        if (
            (int) $this->successful_closures > 0 ||
            (int) $this->site_visits_handled > 0 ||
            (int) $this->sqft_sold > 0 ||
            (int) $this->happy_families_served > 0
        ) {
            $score++;
        }

        if ($this->galleryItems()->count() > 0) {
            $score++;
        }

        if ($this->reviews()->where('moderation_status', AdvisorPublicReview::STATUS_APPROVED)->exists()) {
            $score++;
        }

        return (int) round(($score / $total) * 100);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->is_approved
            && $this->user
            && $this->user->canUseAdvisorPublicProfile();
    }

    public function getCompanyTenureLabelAttribute(): string
    {
        $joinedAt = $this->user?->created_at;

        if (!$joinedAt) {
            return 'Not available';
        }

        $joinedAt = Carbon::parse($joinedAt);
        $now = now();

        if ($joinedAt->greaterThan($now)) {
            return 'Less than 1 month';
        }

        $months = max(0, $joinedAt->diffInMonths($now));
        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;

        if ($years > 0 && $remainingMonths > 0) {
            return $years . ' year' . ($years === 1 ? '' : 's') . ' ' . $remainingMonths . ' month' . ($remainingMonths === 1 ? '' : 's');
        }

        if ($years > 0) {
            return $years . ' year' . ($years === 1 ? '' : 's');
        }

        if ($remainingMonths > 0) {
            return $remainingMonths . ' month' . ($remainingMonths === 1 ? '' : 's');
        }

        return 'Less than 1 month';
    }

    /**
     * Whether CRM approval should be applied automatically (no moderation queue).
     * Defaults to true when APP_ENV is local-like; override with ADVISOR_PUBLIC_AUTO_APPROVE=true|false in .env.
     */
    public static function shouldAutoCrmApproveInEnvironment(): bool
    {
        $configured = config('app.advisor_public_auto_approve');
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }

        return app()->isLocal()
            || app()->environment(['development', 'dev']);
    }
}
