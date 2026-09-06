<?php

namespace App\Models;

use App\Events\LeadStatusUpdated;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\FbForm;
use App\Models\WebsiteIntegration;
use App\Models\ActivityLog;
use App\Services\WhatsAppAutomationTriggerService;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    private const SOURCE_MASTER_LOCATION_PATH = 'leads.create';
    public const STATUS_FRESH_TRANSFER = 'fresh_transfer';

    private static ?array $resolvedSourceOptions = null;
    public function currentCycleProspects()
    {
        $boundary = app(\App\Services\LeadReopenService::class)->boundary((int) $this->id);
        return $this->prospects()->where('prospects.id', '>', $boundary['prospects'] ?? 0);
    }

    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            if (!self::hasNormalizedPhoneColumn()) {
                return;
            }

            // Legacy blank identities must not be backfilled during unrelated workflow saves.
            if ($lead->exists && !$lead->isDirty(['phone', 'phone_country_iso'])) {
                return;
            }

            $rawPhone = (string) ($lead->getAttributes()['phone'] ?? '');
            $parsed = app(\App\Services\DuplicateDetectionService::class)
                ->parsedLeadPhone($rawPhone, $lead->getAttributes()['phone_country_iso'] ?? null);

            $lead->normalized_phone = $parsed['normalized'] ?? null;
            if ($parsed) {
                $lead->phone = $parsed['e164'];
                if (self::hasPhoneCountryColumn()) {
                    $lead->phone_country_iso = $parsed['country_iso'];
                }
            }
        });

        static::created(function (Lead $lead) {
            try {
                app(WhatsAppAutomationTriggerService::class)->leadCreated($lead->fresh(['activeAssignments.assignedTo.role']), $lead->created_by);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp lead-created automation dispatch failed', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::updated(function (Lead $lead) {
            if (
                !$lead->wasChanged('status')
                && !$lead->wasChanged('is_dead')
            ) {
                return;
            }

            if (!in_array((string) $lead->status, ['closed', 'dead', 'junk', 'not_interested'], true) && !$lead->is_dead) {
                return;
            }

            try {
                app(\App\Services\LeadSingleOpenTaskService::class)->cancelOpenTasksAfterLeadClosure(
                    (int) $lead->id,
                    auth()->id(),
                    'Auto closed because lead is no longer active.'
                );
            } catch (\Throwable $e) {
                Log::warning('Lead terminal task cleanup failed', [
                    'lead_id' => $lead->id,
                    'status' => $lead->status,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public const HIRING_STATUS_OPTIONS = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'interview_scheduled' => 'Interview Scheduled',
        'interview_done' => 'Interview Done',
        'shortlisted' => 'Shortlisted',
        'offer_sent' => 'Offer Sent',
        'hired' => 'Hired',
        'rejected' => 'Rejected',
        'not_interested' => 'Not Interested',
        'not_reachable' => 'Not Reachable',
        'wrong_number' => 'Wrong Number',
        'duplicate' => 'Duplicate',
        'on_hold' => 'On Hold',
    ];

    public const SOURCE_OPTIONS = [
        'meta' => 'Meta',
        'meta_awareness' => 'Meta Awareness',
        'ivr' => 'Ivr',
        'sheet' => 'Sheet',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website',
        'organic' => 'Organic',
        'google' => 'Google',
        '99acres' => '99acres',
        'housing' => 'Housing',
        'reference' => 'Reference',
        'other' => 'Other',
    ];

    public const LEGACY_SOURCE_MAP = [
        'facebook_lead_ads' => 'meta',
        'pabbly' => 'meta',
        'social_media' => 'meta',
        'google_sheets' => 'sheet',
        'csv' => 'sheet',
        'mcube' => 'ivr',
        'call' => 'ivr',
        'referral' => 'reference',
        'website' => 'website',
        'walk_in' => 'other',
        'crm_manual' => 'other',
        'manual' => 'other',
        'other' => 'other',
        '' => 'other',
    ];

    private const SOURCE_ALIAS_MAP = [
        'meta_awareness' => ['meta awareness', 'meta_awareness', 'facebook awareness', 'fb awareness'],
        'meta' => ['meta', 'facebook', 'fb', 'instagram', 'insta', 'social', 'pabbly'],
        'ivr' => ['ivr', 'call', 'calling', 'telecalling', 'telecaller', 'mcube', 'telephony'],
        'sheet' => ['sheet', 'sheets', 'excel', 'csv', 'import'],
        'whatsapp' => ['whatsapp', 'wa', 'waba'],
        'website' => ['website', 'web', 'landing', 'landingpage', 'site'],
        'organic' => ['organic', 'seo', 'direct'],
        'google' => ['google', 'adwords', 'gads', 'sem'],
        '99acres' => ['99acres', '99 acres'],
        'housing' => ['housing', 'housing.com'],
        'reference' => ['reference', 'referral', 'ref', 'broker', 'channel partner', 'cp'],
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'normalized_phone',
        'phone_country_iso',
        'merged_into_lead_id',
        'merged_at',
        'merge_reason',
        'whatsapp_opted_out_at',
        'whatsapp_opt_out_reason',
        'address',
        'city',
        'state',
        'pincode',
        'source',
        'status',
        'property_type',
        'budget_min',
        'budget_max',
        'budget',
        'requirements',
        'notes',
        'created_by',
        'last_contacted_at',
        'next_followup_at',
        'preferred_location',
        'preferred_size',
        'preferred_projects',
        'use_end_use',
        'possession_status',
        'cnp_count',
        'is_blocked',
        'blocked_reason',
        'blocked_at',
        'is_dead',
        'dead_reason',
        'dead_at_stage',
        'marked_dead_at',
        'marked_dead_by',
        'needs_verification',
        'verification_requested_by',
        'verification_requested_at',
        'verified_by',
        'verified_at',
        'verification_notes',
        'pending_manager_id',
        'other_lead_marked_by',
        'other_lead_marked_at',
        'other_lead_reason',
        'status_auto_update_enabled',
        'form_filled_by_telecaller',
        'form_filled_by_executive',
        'form_filled_by_manager',
        'is_hiring_candidate',
        'hiring_status',
        'hr_remark',
        'is_reenquiry',
        'reenquiry_count',
        'last_reenquiry_at',
        'last_reenquiry_source',
        'last_reenquiry_fb_form_id',
        'meta_stage',
        'meta_stage_updated_at',
        'meta_stage_updated_by',
        'meta_review_note',
        'meta_sync_status',
        'meta_last_synced_at',
        'meta_last_sync_error',
        'last_sent_meta_stage',
        'website_integration_id',
        'website_queue_status',
        'website_payload_meta',
        'pre_transfer_status',
        'transferred_from_user_id',
        'transferred_to_user_id',
        'transferred_at',
        'transfer_note',
        'cnp_quarantined_at',
        'cnp_quarantined_by',
        'cnp_quarantine_reason',
        'cnp_quarantine_cleared_at',
        'cnp_quarantine_cleared_by',
    ];

    protected $hidden = [
        'normalized_phone',
    ];

    private static function hasNormalizedPhoneColumn(): bool
    {
        try {
            return Schema::hasColumn('leads', 'normalized_phone');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function hasPhoneCountryColumn(): bool
    {
        try {
            return Schema::hasColumn('leads', 'phone_country_iso');
        } catch (\Throwable) {
            return false;
        }
    }

    protected $casts = [
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'investment' => 'decimal:2',
        'last_contacted_at' => 'datetime',
        'whatsapp_opted_out_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'marked_dead_at' => 'datetime',
        'is_dead' => 'boolean',
        'needs_verification' => 'boolean',
        'verification_requested_at' => 'datetime',
        'verified_at' => 'datetime',
        'other_lead_marked_at' => 'datetime',
        'status_auto_update_enabled' => 'boolean',
        'form_filled_by_telecaller' => 'boolean',
        'form_filled_by_executive' => 'boolean',
        'form_filled_by_manager' => 'boolean',
        'is_hiring_candidate' => 'boolean',
        'is_reenquiry' => 'boolean',
        'last_reenquiry_at' => 'datetime',
        'meta_stage_updated_at' => 'datetime',
        'meta_last_synced_at' => 'datetime',
        'website_payload_meta' => 'array',
        'transferred_at' => 'datetime',
        'cnp_quarantined_at' => 'datetime',
        'cnp_quarantine_cleared_at' => 'datetime',
        'merged_at' => 'datetime',
    ];

    public static function hiringStatusOptions(): array
    {
        return self::HIRING_STATUS_OPTIONS;
    }

    public static function sourceOptions(): array
    {
        if (self::$resolvedSourceOptions !== null) {
            return self::$resolvedSourceOptions;
        }

        $masterOptions = LeadSource::activeOptions();
        if (!empty($masterOptions)) {
            return self::$resolvedSourceOptions = $masterOptions;
        }

        if (!Schema::hasTable('dynamic_forms') || !Schema::hasTable('dynamic_form_fields')) {
            return self::$resolvedSourceOptions = self::SOURCE_OPTIONS;
        }

        $dynamicOptions = DynamicForm::query()
            ->where('location_path', self::SOURCE_MASTER_LOCATION_PATH)
            ->where('status', 'published')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->with(['fields' => function ($query) {
                $query->where('field_key', 'source')->orderBy('order');
            }])
            ->first();

        $field = $dynamicOptions?->fields->first();
        $options = collect($field?->options ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->values();

        if ($options->isEmpty()) {
            return self::$resolvedSourceOptions = self::SOURCE_OPTIONS;
        }

        $resolved = [];
        foreach ($options as $label) {
            $key = self::sourceKeyFromLabel($label);
            if ($key === '') {
                continue;
            }

            $resolved[$key] = $label;
        }

        if (!isset($resolved['meta_awareness'])) {
            $resolved['meta_awareness'] = self::SOURCE_OPTIONS['meta_awareness'];
        }

        return self::$resolvedSourceOptions = !empty($resolved)
            ? $resolved
            : self::SOURCE_OPTIONS;
    }

    public static function normalizeSource(?string $source): string
    {
        $value = trim((string) $source);
        if ($value === '') {
            return 'other';
        }

        $normalized = strtolower($value);
        $canonicalOptions = self::SOURCE_OPTIONS;

        if (array_key_exists($normalized, $canonicalOptions)) {
            return $normalized;
        }

        if (isset(self::LEGACY_SOURCE_MAP[$normalized])) {
            return self::LEGACY_SOURCE_MAP[$normalized];
        }

        if (LeadSource::activeKeyExists($normalized)) {
            return $normalized;
        }

        $sourceOptions = self::sourceOptions();

        if (array_key_exists($normalized, $sourceOptions)) {
            return self::mapSourceToCanonical($sourceOptions[$normalized], $normalized);
        }

        foreach ($sourceOptions as $key => $label) {
            if ($normalized === strtolower(trim((string) $label))) {
                if (LeadSource::activeKeyExists($key)) {
                    return $key;
                }

                return self::mapSourceToCanonical((string) $label, $key);
            }
        }

        return self::mapSourceToCanonical($value, $normalized);
    }

    public static function displaySourceLabel(?string $source): string
    {
        $raw = trim((string) $source);
        if ($raw === '') {
            return 'Other';
        }

        $rawKey = strtolower($raw);
        $sourceOptions = self::sourceOptions();
        if (isset($sourceOptions[$rawKey])) {
            return $sourceOptions[$rawKey];
        }

        if (isset(self::SOURCE_OPTIONS[$rawKey])) {
            return self::SOURCE_OPTIONS[$rawKey];
        }

        $normalized = self::normalizeSource($raw);

        if ($normalized === 'other' && !in_array($rawKey, ['other', ''], true)) {
            return self::humanizeSourceLabel($raw);
        }

        if (isset($sourceOptions[$normalized])) {
            return $sourceOptions[$normalized];
        }

        if (isset(self::SOURCE_OPTIONS[$normalized])) {
            return self::SOURCE_OPTIONS[$normalized];
        }

        return self::humanizeSourceLabel($raw);
    }

    public function getSourceLabelAttribute(): string
    {
        if ($this->relationLoaded('formFieldValues')) {
            $storedSourceLabel = trim((string) optional(
                $this->formFieldValues->firstWhere('field_key', 'source')
            )->field_value);

            if ($storedSourceLabel !== '') {
                return $storedSourceLabel;
            }
        }

        $structuredSourceLabel = self::extractStructuredSourceLabel($this->notes);
        if ($structuredSourceLabel !== null) {
            return $structuredSourceLabel;
        }

        return self::displaySourceLabel($this->source);
    }

    public static function forgetResolvedSourceOptions(): void
    {
        self::$resolvedSourceOptions = null;
    }

    public function setSourceAttribute($value): void
    {
        $this->attributes['source'] = self::normalizeSource(is_string($value) ? $value : (string) $value);
    }

    private static function sourceKeyFromLabel(string $label): string
    {
        $slug = Str::slug($label, '_');

        return trim((string) $slug, '_');
    }

    private static function mapSourceToCanonical(string $label, ?string $rawKey = null): string
    {
        $candidates = array_filter([
            strtolower(trim((string) $label)),
            strtolower(trim((string) $rawKey)),
            self::sourceKeyFromLabel($label),
        ]);

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && isset(self::LEGACY_SOURCE_MAP[$candidate])) {
                return self::LEGACY_SOURCE_MAP[$candidate];
            }

            if ($candidate !== '' && array_key_exists($candidate, self::SOURCE_OPTIONS)) {
                return $candidate;
            }
        }

        $haystack = collect($candidates)
            ->map(function (string $candidate) {
                return Str::of($candidate)
                    ->replace(['-', '_'], ' ')
                    ->squish()
                    ->lower()
                    ->value();
            })
            ->filter()
            ->implode(' ');

        foreach (self::SOURCE_ALIAS_MAP as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (Str::contains($haystack, strtolower($alias))) {
                    return $canonical;
                }
            }
        }

        return 'other';
    }

    private static function humanizeSourceLabel(string $source): string
    {
        return Str::of($source)
            ->replace(['-', '_'], ' ')
            ->squish()
            ->title()
            ->value();
    }

    private static function extractStructuredSourceLabel(?string $notes): ?string
    {
        $notes = trim((string) $notes);
        if ($notes === '') {
            return null;
        }

        if (preg_match('/^Lead\s+Source\s*:\s*(.+)$/mi', $notes, $matches) !== 1) {
            return null;
        }

        $sourceLabel = trim((string) ($matches[1] ?? ''));

        return $sourceLabel !== '' ? $sourceLabel : null;
    }

    public function isSimpleUploadLead(): bool
    {
        /** @var \App\Models\ImportedLead|null $importedLead */
        $importedLead = $this->relationLoaded('latestImportedLead')
            ? $this->getRelation('latestImportedLead')
            : $this->latestImportedLead()->with('importBatch')->first();

        if (!$importedLead) {
            return false;
        }

        $importKind = (string) data_get($importedLead->import_data, 'kind', '');
        if ($importKind === 'simple_import') {
            return true;
        }

        $importBatch = $importedLead->relationLoaded('importBatch')
            ? $importedLead->getRelation('importBatch')
            : $importedLead->importBatch;

        return (string) ($importBatch->import_kind ?? '') === 'simple_import';
    }

    public function getImportChannelTagAttribute(): ?string
    {
        return $this->isSimpleUploadLead() ? 'LSQ' : null;
    }

    public function getDisplayCreatedAtAttribute(): ?Carbon
    {
        return $this->resolveImportedCreatedAt() ?? $this->created_at;
    }

    public function getCrmCreatedAtAttribute(): ?Carbon
    {
        return $this->created_at;
    }

    public function resolveImportedCreatedAt(): ?Carbon
    {
        /** @var \App\Models\ImportedLead|null $importedLead */
        $importedLead = $this->relationLoaded('latestImportedLead')
            ? $this->getRelation('latestImportedLead')
            : $this->latestImportedLead()->with('importBatch')->first();

        if (!$importedLead) {
            return null;
        }

        $candidates = [
            data_get($importedLead->import_data, 'metadata.created_on'),
            data_get($importedLead->import_data, 'metadata.old_created_at'),
            data_get($importedLead->import_data, 'created_on'),
            data_get($importedLead->import_data, 'old_created_at'),
        ];

        foreach ($candidates as $candidate) {
            $parsed = $this->parseImportedCreatedAtCandidate($candidate);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    private function parseImportedCreatedAtCandidate($value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function usesSystemCreatedByLabel(): bool
    {
        if ($this->latestFbLead()->exists() || $this->latestImportedLead()->exists()) {
            return true;
        }

        return in_array(self::normalizeSource($this->source), [
            'meta',
            'ivr',
            'sheet',
            'whatsapp',
            'website',
            'google',
            '99acres',
            'housing',
        ], true);
    }

    public function getCreatorDisplayNameAttribute(): string
    {
        if ($this->usesSystemCreatedByLabel()) {
            return 'System';
        }

        return trim((string) ($this->creator?->name ?? '')) !== ''
            ? (string) $this->creator->name
            : 'System';
    }

    public function getCreatorDisplayContextAttribute(): ?string
    {
        if ($this->usesSystemCreatedByLabel()) {
            return $this->source_label !== 'Other'
                ? 'Source: ' . $this->source_label
                : null;
        }

        return null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markedDeadBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_dead_by');
    }

    public function verificationRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verification_requested_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function pendingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_manager_id');
    }

    public function otherLeadMarkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'other_lead_marked_by');
    }

    public function lastReenquiryForm(): BelongsTo
    {
        return $this->belongsTo(FbForm::class, 'last_reenquiry_fb_form_id');
    }

    public function metaStageUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'meta_stage_updated_by');
    }

    public function websiteIntegration(): BelongsTo
    {
        return $this->belongsTo(WebsiteIntegration::class, 'website_integration_id');
    }

    /**
     * Mark lead as dead
     */
    public function markAsDead(int $userId, string $reason, ?string $stage = null): void
    {
        $this->is_dead = true;
        $this->dead_reason = $reason;
        $this->dead_at_stage = $stage;
        $this->marked_dead_at = now();
        $this->marked_dead_by = $userId;
        $this->status = 'dead';
        $this->next_followup_at = null;
        $this->other_lead_marked_by = $userId;
        $this->other_lead_marked_at = now();
        $this->other_lead_reason = trim($reason) !== '' ? trim($reason) : 'Dead lead';
        $this->disableAutoUpdate();
        $this->save();

        $this->assignments()
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);
    }

    /**
     * Disable auto-update for status
     */
    public function disableAutoUpdate(): void
    {
        $this->status_auto_update_enabled = false;
    }

    /**
     * Enable auto-update for status
     */
    public function enableAutoUpdate(): void
    {
        $this->status_auto_update_enabled = true;
    }

    public function markAsOtherLead(string $status, int $userId, ?string $reason = null): void
    {
        if (!in_array($status, ['junk', 'not_interested'], true)) {
            throw new \InvalidArgumentException("Invalid other lead status '{$status}'.");
        }

        $this->status = $status;
        $this->next_followup_at = null;
        $this->other_lead_marked_by = $userId;
        $this->other_lead_marked_at = now();
        $this->other_lead_reason = $reason ? trim($reason) : null;
        $this->disableAutoUpdate();
        $this->save();
    }

    public function wasTerminalBeforeReenquiry(): bool
    {
        return in_array($this->status, ['junk', 'not_interested', 'dead', 'closed'], true);
    }

    /**
     * Check if auto-update is allowed
     */
    public function canAutoUpdate(): bool
    {
        return $this->status_auto_update_enabled === true;
    }

    /**
     * Update status only if auto-update is enabled
     */
    public function updateStatusIfAllowed(string $newStatus): bool
    {
        if (!$this->canAutoUpdate()) {
            return false;
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;
        $this->save();

        if ($oldStatus === self::STATUS_FRESH_TRANSFER && $newStatus !== self::STATUS_FRESH_TRANSFER) {
            $this->logFreshTransferAcknowledgement(auth()->id(), 'status_update', $oldStatus, $newStatus);
        }

        // Fire event if status changed
        // Wrap in try-catch to handle broadcasting errors (Pusher may not be configured)
        if ($oldStatus !== $newStatus) {
            try {
                event(new LeadStatusUpdated($this, $oldStatus, $newStatus));
            } catch (\Exception $e) {
                // Broadcasting errors (like Pusher) shouldn't stop the status update
                // Log but continue - the status update is successful even if broadcast fails
                Log::warning("Broadcasting error in LeadStatusUpdated (non-critical): " . $e->getMessage());
            }
        }

        return true;
    }

    public function isFreshTransfer(): bool
    {
        return (string) $this->status === self::STATUS_FRESH_TRANSFER;
    }

    public function markAsFreshTransfer(?int $fromUserId, int $toUserId, ?int $changedBy = null, ?string $note = null): bool
    {
        $oldStatus = (string) ($this->status ?? 'new');
        $reason = trim((string) $note);
        $fromUserName = $fromUserId ? (User::query()->whereKey($fromUserId)->value('name') ?: "user #{$fromUserId}") : 'Unassigned';
        $toUserName = User::query()->whereKey($toUserId)->value('name') ?: "user #{$toUserId}";
        $changedByName = $changedBy ? (User::query()->whereKey($changedBy)->value('name') ?: "user #{$changedBy}") : 'System';
        $normalizedNote = strtolower((string) $note);
        $isAsmCnpTransfer = str_contains($normalizedNote, 'auto-transferred')
            && str_contains($normalizedNote, 'cnp');

        $this->forceFill([
            'pre_transfer_status' => $oldStatus,
            'transferred_from_user_id' => $fromUserId,
            'transferred_to_user_id' => $toUserId,
            'transferred_at' => now(),
            'transfer_note' => $note,
            'status' => self::STATUS_FRESH_TRANSFER,
            'status_auto_update_enabled' => true,
        ])->save();

        $newValues = [
            'status' => self::STATUS_FRESH_TRANSFER,
            'assigned_to' => $toUserId,
            'pre_transfer_status' => $oldStatus,
            'transfer_note' => $note,
            'transfer_reason' => $reason !== '' ? $reason : null,
            'from_user_name' => $fromUserName,
            'to_user_name' => $toUserName,
            'changed_by_name' => $changedByName,
        ];

        if ($isAsmCnpTransfer) {
            $newValues += [
                'automation_type' => 'asm_fresh_lead_cnp',
                'automation_label' => 'ASM Fresh Lead CNP Automation',
                'automation_details' => [
                    'mode' => 'auto_transfer',
                    'trigger' => 'Fresh lead reached configured CNP limit',
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $toUserId,
                    'note' => $note,
                ],
            ];
        }

        ActivityLog::create([
            'user_id' => $changedBy,
            'action' => 'lead_transferred',
            'model_type' => 'Lead',
            'model_id' => $this->id,
            'description' => "Lead transferred from {$fromUserName} to {$toUserName} by {$changedByName}."
                . ($reason !== '' ? " Reason: {$reason}" : ''),
            'old_values' => [
                'status' => $oldStatus,
                'assigned_to' => $fromUserId,
                'from_user_name' => $fromUserName,
            ],
            'new_values' => $newValues,
        ]);

        if ($oldStatus !== self::STATUS_FRESH_TRANSFER) {
            try {
                event(new LeadStatusUpdated($this, $oldStatus, self::STATUS_FRESH_TRANSFER));
            } catch (\Exception $e) {
                Log::warning("Broadcasting error in LeadStatusUpdated (non-critical): " . $e->getMessage());
            }
        }

        return true;
    }

    public function acknowledgeFreshTransfer(?int $userId, string $reason = 'first_action', string $targetStatus = 'connected'): bool
    {
        if (!$this->isFreshTransfer()) {
            return false;
        }

        $oldStatus = (string) $this->status;
        $this->status = $targetStatus;
        $this->save();

        $this->logFreshTransferAcknowledgement($userId, $reason, $oldStatus, $targetStatus);

        try {
            event(new LeadStatusUpdated($this, $oldStatus, $targetStatus));
        } catch (\Exception $e) {
            Log::warning("Broadcasting error in LeadStatusUpdated (non-critical): " . $e->getMessage());
        }

        return true;
    }

    private function logFreshTransferAcknowledgement(?int $userId, string $reason, string $oldStatus, string $newStatus): void
    {
        ActivityLog::create([
            'user_id' => $userId,
            'action' => 'fresh_transfer_acknowledged',
            'model_type' => 'Lead',
            'model_id' => $this->id,
            'description' => "Fresh transfer acknowledged via {$reason}",
            'old_values' => [
                'status' => $oldStatus,
                'transferred_from_user_id' => $this->transferred_from_user_id,
                'transferred_to_user_id' => $this->transferred_to_user_id,
            ],
            'new_values' => [
                'status' => $newStatus,
                'reason' => $reason,
                'pre_transfer_status' => $this->pre_transfer_status,
            ],
        ]);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function latestAssignment(): HasOne
    {
        return $this->hasOne(LeadAssignment::class)->latestOfMany('assigned_at');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(LeadAssignment::class)
            ->ofMany(['assigned_at' => 'max', 'id' => 'max'], function ($query) {
                $query->where('is_active', true)->where('assignment_type', 'primary');
            });
    }

    public function leadTags(): BelongsToMany
    {
        return $this->belongsToMany(LeadTag::class, 'lead_tag_assignments')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }

    public function leadBankCooldown(): HasOne
    {
        return $this->hasOne(LeadBankCooldown::class);
    }

    public function leadBankAllocations(): HasMany
    {
        return $this->hasMany(LeadBankAllocation::class);
    }

    public function asmCnpHistories(): HasMany
    {
        return $this->hasMany(AsmCnpAutomationLeadHistory::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)
            ->where('is_active', true)
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function latestSiteVisit(): HasOne
    {
        return $this->hasOne(SiteVisit::class)->latestOfMany('updated_at');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function latestFollowUpRemark(): HasOne
    {
        return $this->hasOne(FollowUp::class)
            ->ofMany(['updated_at' => 'MAX'], function (Builder $query) {
                $query->whereNotNull('notes')->where('notes', '!=', '');
            });
    }

    public function getAssignedUsersAttribute()
    {
        return $this->activeAssignments->pluck('assignedTo');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TelecallerTask::class);
    }

    public function managerTasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function latestPhoneCallTask(): HasOne
    {
        return $this->hasOne(Task::class)
            ->where('type', 'phone_call')
            ->latestOfMany('updated_at');
    }

    public function latestManagerTaskRemark(): HasOne
    {
        return $this->hasOne(Task::class)
            ->ofMany(['updated_at' => 'MAX'], function (Builder $query) {
                $query->whereNotNull('outcome_remark')->where('outcome_remark', '!=', '');
            });
    }

    public function pendingTasks(): HasMany
    {
        return $this->hasMany(TelecallerTask::class)->where('status', 'pending');
    }

    public function prospects(): HasMany
    {
        return $this->hasMany(Prospect::class);
    }

    public function latestProspect(): HasOne
    {
        return $this->hasOne(Prospect::class)->latestOfMany('updated_at');
    }

    public function latestProspectRemark(): HasOne
    {
        return $this->hasOne(Prospect::class)
            ->ofMany(['updated_at' => 'MAX'], function (Builder $query) {
                $query->where(function (Builder $remarkQuery) {
                    foreach (['manager_remark', 'employee_remark', 'remark', 'notes'] as $column) {
                        $remarkQuery->orWhere(function (Builder $columnQuery) use ($column) {
                            $columnQuery->whereNotNull($column)->where($column, '!=', '');
                        });
                    }
                });
            });
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function latestMeeting(): HasOne
    {
        return $this->hasOne(Meeting::class)->latestOfMany('updated_at');
    }

    public function callLogs(): HasMany
    {
        return $this->hasMany(CallLog::class);
    }

    public function scopeSearchText(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $searchQuery) use ($search) {
            $like = "%{$search}%";

            $searchQuery->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('managerTasks', function (Builder $taskQuery) use ($like) {
                    $taskQuery->where('outcome_remark', 'like', $like);
                })
                ->orWhereHas('followUps', function (Builder $followUpQuery) use ($like) {
                    $followUpQuery->where('notes', 'like', $like);
                })
                ->orWhereHas('prospects', function (Builder $prospectQuery) use ($like) {
                    $prospectQuery->where('remark', 'like', $like)
                        ->orWhere('employee_remark', 'like', $like)
                        ->orWhere('manager_remark', 'like', $like)
                        ->orWhere('notes', 'like', $like);
                });
        });
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(LeadFavorite::class);
    }

    public function importedLeads(): HasMany
    {
        return $this->hasMany(ImportedLead::class);
    }

    public function latestImportedLead(): HasOne
    {
        return $this->hasOne(ImportedLead::class)->latestOfMany();
    }

    public function latestFbLead(): HasOne
    {
        return $this->hasOne(FbLead::class, 'crm_lead_id')->latestOfMany();
    }

    /**
     * Scope to get leads for a specific telecaller
     */
    public function scopeForTelecaller($query, $userId)
    {
        return $query->whereHas('activeAssignments', function ($q) use ($userId) {
            $q->where('assigned_to', $userId);
        });
    }

    public function scopeWhereAssignedToUsers(Builder $query, $userIds): Builder
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('activeAssignments', function ($assignmentQuery) use ($ids) {
            $assignmentQuery->where('is_active', true)
                ->whereIn('assigned_to', $ids->all());
        });
    }

    public function scopeVisibleInAllLeadsInventory(Builder $query): Builder
    {
        return $query->where(function (Builder $visibilityQuery) {
            $visibilityQuery
                ->whereHas('activeAssignments')
                ->orWhereDoesntHave('importedLeads', function (Builder $importQuery) {
                    $importQuery
                        ->where('import_data->action', 'create')
                        ->whereHas('importBatch', function (Builder $batchQuery) {
                            $batchQuery->where('import_kind', 'lead_bank');
                        });
                });
        });
    }

    public function scopeWhereVisibleViaProspectFallback(
        Builder $query,
        $telecallerIds,
        ?Closure $prospectConstraint = null
    ): Builder {
        $ids = collect($telecallerIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereDoesntHave('activeAssignments')
            ->whereHas('prospects', function ($prospectQuery) use ($ids, $prospectConstraint) {
                $prospectQuery->whereIn('telecaller_id', $ids->all());

                if ($prospectConstraint) {
                    $prospectConstraint($prospectQuery);
                }
            });
    }

    public function isAssignedToUser(int $userId): bool
    {
        return $this->activeAssignments()
            ->where('assigned_to', $userId)
            ->exists();
    }

    public function isAssignedToAnyUser($userIds): bool
    {
        $ids = collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return false;
        }

        return $this->activeAssignments()
            ->where('is_active', true)
            ->whereIn('assigned_to', $ids->all())
            ->exists();
    }

    public function isVisibleViaProspectFallback($telecallerIds, ?Closure $prospectConstraint = null): bool
    {
        $ids = collect($telecallerIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty() || $this->activeAssignments()->exists()) {
            return false;
        }

        $prospects = $this->prospects()->whereIn('telecaller_id', $ids->all());

        if ($prospectConstraint) {
            $prospectConstraint($prospects);
        }

        return $prospects->exists();
    }

    /**
     * Scope to get hot leads (high CNP count or recently contacted)
     */
    public function scopeHotLeads($query)
    {
        return $query->where(function ($q) {
            $q->where('cnp_count', '>=', 3)
              ->orWhere(function ($subQ) {
                  $subQ->whereNotNull('last_contacted_at')
                       ->whereDate('last_contacted_at', today());
              });
        });
    }

    /**
     * Scope to get leads pending contact
     */
    public function scopePendingContact($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_contacted_at')
              ->orWhere('status', 'new');
        });
    }

    /**
     * Get WhatsApp conversations for this lead
     */
    public function whatsappConversations(): HasMany
    {
        return $this->hasMany(WhatsAppConversation::class);
    }

    public function wabaCallEvents(): HasMany
    {
        return $this->hasMany(WabaCallEvent::class);
    }

    /**
     * Get all form field values for this lead
     */
    public function formFieldValues(): HasMany
    {
        return $this->hasMany(LeadFormFieldValue::class);
    }

    /**
     * Get value for a specific form field
     */
    public function getFormFieldValue(string $fieldKey): ?string
    {
        $fieldValue = $this->formFieldValues()->where('field_key', $fieldKey)->first();
        return $fieldValue ? $fieldValue->field_value : null;
    }

    /**
     * Set value for a specific form field
     */
    public function setFormFieldValue(string $fieldKey, $value, ?int $userId = null): LeadFormFieldValue
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return LeadFormFieldValue::updateOrCreate(
            [
                'lead_id' => $this->id,
                'field_key' => $fieldKey,
            ],
            [
                'field_value' => $value,
                'filled_by_user_id' => $userId ?? auth()->id(),
                'filled_at' => now(),
            ]
        );
    }

    /**
     * Get all form field values as key-value array
     */
    public function getFormFieldsArray(): array
    {
        return $this->formFieldValues()->pluck('field_value', 'field_key')->toArray();
    }

    public function getHiringStatusLabelAttribute(): ?string
    {
        if (!$this->hiring_status) {
            return null;
        }

        return self::HIRING_STATUS_OPTIONS[$this->hiring_status] ?? ucfirst(str_replace('_', ' ', $this->hiring_status));
    }

    public function getPhoneAttribute($value): ?string
    {
        return app(\App\Services\PhonePrivacyService::class)->display($value, auth()->user());
    }
}
