<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Services\LeadSingleOpenTaskService;
use App\Services\WhatsAppAutomationTriggerService;
use App\Services\KycFormSchemaService;

class SiteVisit extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope('visible_in_queue', function (Builder $builder) {
            if (!static::supportsQueueArchiving()) {
                return;
            }

            $builder->whereNull($builder->getModel()->qualifyColumn('queue_hidden_at'));
        });

        static::saving(function (SiteVisit $siteVisit) {
            $scheduledChanged = $siteVisit->isDirty('scheduled_at');
            $statusChanged = $siteVisit->isDirty('status');
            $completedChanged = $siteVisit->isDirty('completed_at');

            if (!$scheduledChanged && !$statusChanged && !$completedChanged) {
                return;
            }

            if (
                ($siteVisit->status === 'completed' || $siteVisit->completed_at !== null)
                && $siteVisit->scheduled_at
                && $siteVisit->completed_at
                && $siteVisit->completed_at->lt($siteVisit->scheduled_at)
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'completed_at' => ['Site visit scheduled time se pehle complete nahi ho sakti.'],
                ]);
            }

            $isScheduledOpen = $siteVisit->status === 'scheduled' && $siteVisit->completed_at === null;
            $wasScheduledOpen = $siteVisit->getOriginal('status') === 'scheduled'
                && $siteVisit->getOriginal('completed_at') === null;

            if ($isScheduledOpen && ($scheduledChanged || !$wasScheduledOpen)) {
                $siteVisit->first_reminder_sent_at = null;
                $siteVisit->final_reminder_sent_at = null;
            }

            if (!$isScheduledOpen) {
                $siteVisit->first_reminder_sent_at = null;
                $siteVisit->final_reminder_sent_at = null;
            }
        });

        static::updated(function (SiteVisit $siteVisit) {
            if (!$siteVisit->wasChanged('verification_status') || $siteVisit->verification_status !== 'verified') {
                return;
            }

            try {
                $siteVisit->loadMissing('lead.activeAssignments.assignedTo.role');
                app(WhatsAppAutomationTriggerService::class)->siteVisitVerified($siteVisit, $siteVisit->verified_by ?: $siteVisit->created_by);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp site-visit-verified automation dispatch failed', [
                    'site_visit_id' => $siteVisit->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public static function supportsQueueArchiving(): bool
    {
        static $supportsQueueArchiving = null;

        if ($supportsQueueArchiving !== null) {
            return $supportsQueueArchiving;
        }

        try {
            $supportsQueueArchiving = Schema::hasColumn('site_visits', 'queue_hidden_at');
        } catch (\Throwable $e) {
            $supportsQueueArchiving = false;
        }

        return $supportsQueueArchiving;
    }

    protected $fillable = [
        'lead_id',
        'created_by',
        'assigned_to',
        'property_name',
        'property_address',
        'scheduled_at',
        'first_reminder_sent_at',
        'final_reminder_sent_at',
        'completed_at',
        'status',
        'visit_notes',
        'feedback',
        'rating',
        // Verification fields
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'resubmission_count',
        'resubmitted_at',
        'latest_rejected_at',
        'lead_status',
        // Closer fields
        'closer_status',
        'converted_to_closer_at',
        'closer_verified_by',
        'closer_verified_at',
        'closer_rejection_reason',
        'closer_review_remark',
        'closer_submitted_at',
        'closer_submitted_by',
        'actual_closer_date',
        'actual_closer_backdate_reason',
        'actual_closer_date_approved_at',
        'actual_closer_date_approved_by',
        'closer_reviewed_at',
        'closer_reviewed_by',
        'closer_resubmission_count',
        'kyc_submitted_at',
        'kyc_last_corrected_at',
        'finance_handover_status',
        'finance_transferred_at',
        'finance_transferred_by',
        'finance_reviewed_at',
        'finance_reviewed_by',
        'revenue_value',
        'revenue_note',
        'revenue_entered_by',
        'revenue_entered_at',
        'revenue_updated_by',
        'revenue_updated_at',
        // Form fields
        'customer_name',
        'phone',
        'employee',
        'occupation',
        'date_of_visit',
        'project',
        'budget_range',
        'team_leader',
        'property_type',
        'payment_mode',
        'tentative_period',
        'lead_type',
        'photos',
        'visited_projects',
        'visited_property_types',
        'tentative_closing_time',
        'completion_proof_photos',
        'closer_request_proof_photos',
        'is_dead',
        'dead_reason',
        'marked_dead_at',
        'marked_dead_by',
        // Reschedule fields
        'rescheduled_at',
        'rescheduled_by',
        'reschedule_reason',
        // Visit type and reminder fields
        'visit_type',
        'visit_sequence',
        'reminder_enabled',
        'reminder_minutes',
        'reminder_task_id',
        'reschedule_count',
        'is_rescheduled',
        'incentive_amount',
        // KYC fields for closing
        'nominee_name',
        'second_customer_name',
        'customer_dob',
        'pan_card',
        'aadhaar_card_no',
        'kyc_documents',
        'primary_applicant_details',
        'joint_applicant_details',
        'unit_details',
        'kyc_dynamic_form_id',
        'booking_form_version',
        'booking_lifecycle_status',
        'booking_payment_proofs',
        'booking_activity_log',
        'booking_document_reviews',
        'kyc_custom_fields',
        'kyc_section_remarks',
        // Closing verification fields
        'closing_verification_status',
        'closing_verified_by',
        'closing_verified_at',
        'closing_rejection_reason',
        'queue_hidden_at',
        'queue_hidden_reason',
        'is_finance_direct_closer',
        'approval_admin_id',
        'rescheduled_from_visit_id',
        'rescheduled_to_visit_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'first_reminder_sent_at' => 'datetime',
        'final_reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'verified_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'latest_rejected_at' => 'datetime',
        'converted_to_closer_at' => 'datetime',
        'closer_verified_at' => 'datetime',
        'closer_submitted_at' => 'datetime',
        'actual_closer_date' => 'date',
        'actual_closer_date_approved_at' => 'datetime',
        'closer_reviewed_at' => 'datetime',
        'marked_dead_at' => 'datetime',
        'date_of_visit' => 'date',
        'rating' => 'integer',
        'photos' => 'array',
        'visited_property_types' => 'array',
        'completion_proof_photos' => 'array',
        'closer_request_proof_photos' => 'array',
        'kyc_documents' => 'array',
        'primary_applicant_details' => 'array',
        'joint_applicant_details' => 'array',
        'unit_details' => 'array',
        'booking_payment_proofs' => 'array',
        'booking_activity_log' => 'array',
        'booking_document_reviews' => 'array',
        'kyc_custom_fields' => 'array',
        'kyc_section_remarks' => 'array',
        'pan_card' => 'encrypted',
        'aadhaar_card_no' => 'encrypted',
        'customer_dob' => 'date',
        'closing_verified_at' => 'datetime',
        'is_dead' => 'boolean',
        'rescheduled_at' => 'datetime',
        'is_rescheduled' => 'boolean',
        'queue_hidden_at' => 'datetime',
        'is_finance_direct_closer' => 'boolean',
        'kyc_submitted_at' => 'datetime',
        'kyc_last_corrected_at' => 'datetime',
        'finance_transferred_at' => 'datetime',
        'finance_reviewed_at' => 'datetime',
        'revenue_value' => 'decimal:2',
        'revenue_entered_at' => 'datetime',
        'revenue_updated_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function markedDeadBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_dead_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function closerVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_verified_by');
    }

    public function closingVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closing_verified_by');
    }

    public function closerSubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_submitted_by');
    }

    public function closerReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closer_reviewed_by');
    }

    public function financeTransferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_transferred_by');
    }

    public function financeReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_reviewed_by');
    }

    public function revenueEnteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revenue_entered_by');
    }

    public function revenueUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revenue_updated_by');
    }

    public function approvalAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_admin_id');
    }

    public function revenueAudits(): HasMany
    {
        return $this->hasMany(SiteVisitRevenueAudit::class);
    }

    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function rescheduledFromVisit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_visit_id');
    }

    public function rescheduledToVisit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_to_visit_id');
    }

    public function incentives(): HasMany
    {
        return $this->hasMany(Incentive::class);
    }

    /**
     * Check if site visit is rescheduled
     */
    public function isRescheduled(): bool
    {
        return $this->is_rescheduled === true;
    }

    /**
     * Mark site visit as verified
     */
    public function verify(int $userId, ?string $notes = null, ?string $leadStatus = null): void
    {
        $this->verification_status = 'verified';
        $this->verified_at = now();
        $this->verified_by = $userId;
        $this->rejection_reason = null;
        
        if ($notes) {
            $this->visit_notes = ($this->visit_notes ? $this->visit_notes . "\n" : '') . $notes;
        }
        
        if ($leadStatus) {
            $this->lead_status = $leadStatus;
        }
        
        $this->save();
    }

    /**
     * Mark site visit as rejected
     */
    public function reject(int $userId, string $reason): void
    {
        $this->verification_status = 'rejected';
        $this->verified_at = now();
        $this->verified_by = $userId;
        $this->rejection_reason = $reason;
        $this->latest_rejected_at = now();
        $this->save();
    }

    /**
     * Check if site visit is verified
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if site visit is pending verification
     */
    public function isPendingVerification(): bool
    {
        return $this->status === 'completed' && $this->verification_status === 'pending';
    }

    /**
     * Mark site visit as completed
     */
    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->verification_status = 'pending'; // Goes for verification
        $this->save();
    }

    /**
     * Convert site visit to closer
     */
    public function convertToCloser(): void
    {
        if ($this->verification_status !== 'verified') {
            throw new \Exception('Site visit must be verified before converting to closer.');
        }

        $this->closer_status = 'draft';
        $this->converted_to_closer_at = now();
        $this->save();
    }

    /**
     * Verify closer
     */
    public function verifyCloser(int $userId, ?string $notes = null, ?string $leadStatus = null): void
    {
        if (!in_array($this->closer_status, ['pending_crm', 'approved'], true)) {
            throw new \Exception('Closer must be pending CRM approval before verification.');
        }

        $this->closer_status = 'approved';
        $this->closer_verified_at = now();
        $this->closer_verified_by = $userId;
        $this->closer_reviewed_at = now();
        $this->closer_reviewed_by = $userId;

        // Update lead status to closed when closer is verified
        if ($this->lead_id) {
            $lead = Lead::find($this->lead_id);
            if ($lead) {
                $lead->update(['status' => 'closed']);
                app(LeadSingleOpenTaskService::class)->cancelOpenTasksAfterLeadClosure($lead->id, $userId);
            }
        }

        if ($notes) {
            $this->visit_notes = ($this->visit_notes ? $this->visit_notes . "\n" : '') . "Closer: " . $notes;
        }

        if ($leadStatus) {
            $this->lead_status = $leadStatus;
        }

        $this->save();
    }

    /**
     * Reject closer
     */
    public function rejectCloser(int $userId, string $reason): void
    {
        if (!in_array($this->closer_status, ['pending_crm', 'correction_required', 'rejected'], true)) {
            throw new \Exception('Closer must be in review before rejection.');
        }

        $this->closer_status = 'rejected';
        $this->closer_verified_at = now();
        $this->closer_verified_by = $userId;
        $this->closer_rejection_reason = $reason;
        $this->closer_reviewed_at = now();
        $this->closer_reviewed_by = $userId;
        $this->save();
    }

    /**
     * Check if closer is verified
     */
    public function isCloserVerified(): bool
    {
        return $this->closer_status === 'approved';
    }

    /**
     * Check if closing is verified by CRM
     */
    public function isClosingVerified(): bool
    {
        return $this->closer_status === 'approved' || $this->closing_verification_status === 'verified';
    }

    /**
     * Verify closing (CRM only)
     */
    public function verifyClosing(int $userId, ?string $notes = null): void
    {
        if (!in_array($this->closer_status, ['pending_crm', 'approved'], true)) {
            throw new \Exception('Closing must be pending before verification.');
        }

        $this->closing_verification_status = 'verified';
        $this->closing_verified_at = now();
        $this->closing_verified_by = $userId;
        
        // After closing verification, set closer_status to approved so incentive can be requested
        $this->closer_status = 'approved';
        $this->closer_verified_at = now();
        $this->closer_verified_by = $userId;
        $this->closer_reviewed_at = now();
        $this->closer_reviewed_by = $userId;
        
        if ($notes) {
            $this->visit_notes = ($this->visit_notes ? $this->visit_notes . "\n" : '') . "Closing Verification: " . $notes;
        }
        
        $this->save();
    }

    /**
     * Reject closing (CRM only)
     */
    public function rejectClosing(int $userId, string $reason): void
    {
        if (!in_array($this->closer_status, ['pending_crm', 'correction_required'], true)) {
            throw new \Exception('Closing must be pending before rejection.');
        }

        $this->closing_verification_status = 'rejected';
        $this->closing_verified_at = now();
        $this->closing_verified_by = $userId;
        $this->closing_rejection_reason = $reason;
        $this->closer_status = 'rejected';
        $this->closer_reviewed_at = now();
        $this->closer_reviewed_by = $userId;
        $this->save();
    }

    public function hasCompleteKyc(): bool
    {
        $kycDocuments = array_values(array_filter((array) $this->kyc_documents));
        $proofPhotos = array_values(array_filter((array) $this->closer_request_proof_photos));

        if (count($kycDocuments) === 0 || count($proofPhotos) === 0) {
            return false;
        }

        $schemaService = app(KycFormSchemaService::class);
        $schema = $schemaService->getResolvedSchema($this->kyc_dynamic_form_id);

        foreach ((array) ($schema['fields'] ?? []) as $field) {
            if (empty($field['required']) || empty($field['is_visible'])) {
                continue;
            }

            if (($field['requires_form_version'] ?? null) === 'v2' && $this->booking_form_version !== 'v2') {
                continue;
            }

            if (($field['field_type'] ?? '') === 'section_heading') {
                continue;
            }

            $value = $schemaService->getFieldValue($this, $field);

            if (!$this->kycFieldHasValue((array) $field, $value)) {
                return false;
            }
        }

        return true;
    }

    private function kycFieldHasValue(array $field, mixed $value): bool
    {
        return match ($field['field_type'] ?? 'text') {
            'checkbox' => (bool) $value,
            'file' => is_array($value) ? count(array_filter($value)) > 0 : filled($value),
            default => is_string($value) ? trim($value) !== '' : filled($value),
        };
    }

    /**
     * Mark site visit as dead
     */
    public function markAsDead(int $userId, string $reason): void
    {
        $this->is_dead = true;
        $this->dead_reason = $reason;
        $this->marked_dead_at = now();
        $this->marked_dead_by = $userId;
        $this->save();

        // Also mark associated lead as dead if exists
        if ($this->lead_id) {
            $lead = Lead::find($this->lead_id);
            if ($lead) {
                $lead->markAsDead($userId, $reason, 'site_visit');
            }
        }
    }

    /**
     * Get photos URLs
     */
    public function getPhotosUrlsAttribute(): array
    {
        if (!$this->photos || !is_array($this->photos)) {
            return [];
        }

        return array_map(function ($photo) {
            if (filter_var($photo, FILTER_VALIDATE_URL)) {
                return $photo;
            }
            return asset('storage/' . $photo);
        }, $this->photos);
    }

    /**
     * Get the reminder task for this site visit
     */
    public function reminderTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'reminder_task_id');
    }

    public function scopeWithQueueHidden(Builder $query): Builder
    {
        if (!static::supportsQueueArchiving()) {
            return $query;
        }

        return $query->withoutGlobalScope('visible_in_queue');
    }

    public function archiveForQueue(string $reason): void
    {
        if (!static::supportsQueueArchiving()) {
            return;
        }

        $this->update([
            'queue_hidden_at' => now(),
            'queue_hidden_reason' => $reason,
        ]);
    }

    public function restoreToQueue(): void
    {
        if (!static::supportsQueueArchiving()) {
            return;
        }

        $this->update([
            'queue_hidden_at' => null,
            'queue_hidden_reason' => null,
        ]);
    }
}
