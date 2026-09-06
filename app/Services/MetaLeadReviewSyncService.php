<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MetaReviewSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaLeadReviewSyncService
{
    public function __construct(
        private readonly MetaReviewAccessService $accessService,
        private readonly MetaIdentifierNormalizer $identifierNormalizer,
        private readonly MetaReviewStageService $stageService,
    ) {
    }

    public function configEnabled(): bool
    {
        return (bool) config('meta_review.meta_sync_enabled');
    }

    public function hasRequiredConfig(): bool
    {
        return filled(config('meta_review.meta_dataset_id')) && filled(config('meta_review.meta_system_user_token'));
    }

    public function shouldDispatch(Lead $lead, ?string $previousStage, ?string $currentStage): array
    {
        $currentStage = trim((string) $currentStage);
        if ((string) $previousStage === $currentStage) {
            return ['dispatch' => false, 'reason' => 'stage_unchanged'];
        }

        if (!$this->configEnabled()) {
            return ['dispatch' => false, 'reason' => 'sync_disabled'];
        }

        if (!$this->accessService->resolveMetaLinkage($lead)['is_linked']) {
            return ['dispatch' => false, 'reason' => 'not_meta_linked'];
        }

        if (!$this->stageService->isValid($currentStage)) {
            return ['dispatch' => false, 'reason' => 'invalid_stage'];
        }

        return ['dispatch' => true, 'reason' => null];
    }

    public function sync(Lead $lead): array
    {
        $lead->loadMissing('latestFbLead');

        $linkage = $this->accessService->resolveMetaLinkage($lead);
        if (!$this->configEnabled()) {
            return $this->skip($lead, 'sync_disabled');
        }

        if (!$this->hasRequiredConfig()) {
            return $this->skip($lead, 'missing_required_config');
        }

        if (!$linkage['is_linked']) {
            return $this->skip($lead, 'not_meta_linked');
        }

        $stage = trim((string) $lead->meta_stage);
        if ($stage === '' || !$this->stageService->isValid($stage)) {
            return $this->skip($lead, 'invalid_stage');
        }

        if ($lead->last_sent_meta_stage !== null && $lead->last_sent_meta_stage === $stage) {
            return $this->skip($lead, 'already_synced');
        }

        $email = $this->identifierNormalizer->normalizeEmail($lead->email);
        $phone = $this->identifierNormalizer->normalizePhone($lead->phone);

        if (!$email && !$phone) {
            return $this->fail($lead, 'No valid email or phone available for Meta matching.');
        }

        $payload = $this->buildPayload($lead, $stage, $email, $phone, $linkage['leadgen_id']);

        $response = Http::timeout(20)
            ->acceptJson()
            ->post(
                sprintf(
                    'https://graph.facebook.com/v22.0/%s/events',
                    config('meta_review.meta_dataset_id')
                ),
                $payload + [
                    'access_token' => config('meta_review.meta_system_user_token'),
                ]
            );

        if (!$response->successful()) {
            $message = $response->json('error.message') ?: $response->body();

            return $this->fail($lead, 'Meta sync failed: ' . trim((string) $message), $response->status(), $message);
        }

        $lead->forceFill([
            'meta_sync_status' => 'synced',
            'meta_last_synced_at' => now(),
            'meta_last_sync_error' => null,
            'last_sent_meta_stage' => $stage,
        ])->save();

        $this->log($lead, 'synced', null, [
            'meta_stage' => $stage,
            'previous_stage' => $lead->getOriginal('last_sent_meta_stage'),
            'dedupe_key' => $this->dedupeKey($lead, $stage),
            'event_name' => 'LeadStageUpdated',
            'meta_leadgen_id' => $linkage['leadgen_id'],
            'response_code' => $response->status(),
            'response_summary' => $response->body(),
            'synced_at' => now(),
        ]);

        return ['status' => 'synced'];
    }

    public function skip(Lead $lead, string $reason): array
    {
        $message = $reason === 'already_synced'
            ? 'Stage already synced to Meta, no update sent.'
            : null;

        $lead->forceFill([
            'meta_sync_status' => 'skipped',
            'meta_last_sync_error' => $message,
        ])->save();

        $this->log($lead, 'skipped', $reason, [
            'meta_stage' => $lead->meta_stage,
            'previous_stage' => $lead->last_sent_meta_stage,
            'dedupe_key' => $this->dedupeKey($lead, (string) $lead->meta_stage),
            'meta_leadgen_id' => $lead->latestFbLead?->leadgen_id,
            'response_summary' => $message,
        ]);

        return ['status' => 'skipped', 'reason' => $reason];
    }

    public function fail(Lead $lead, string $message, ?int $responseCode = null, ?string $responseSummary = null): array
    {
        $lead->forceFill([
            'meta_sync_status' => 'failed',
            'meta_last_sync_error' => $message,
        ])->save();

        $this->log($lead, 'failed', 'api_failed', [
            'meta_stage' => $lead->meta_stage,
            'previous_stage' => $lead->last_sent_meta_stage,
            'dedupe_key' => $this->dedupeKey($lead, (string) $lead->meta_stage),
            'meta_leadgen_id' => $lead->latestFbLead?->leadgen_id,
            'response_code' => $responseCode,
            'response_summary' => $responseSummary ?: $message,
        ]);

        Log::warning('Meta review sync failed', [
            'lead_id' => $lead->id,
            'message' => $message,
            'response_code' => $responseCode,
        ]);

        return ['status' => 'failed', 'message' => $message];
    }

    public function log(Lead $lead, string $status, ?string $reason, array $attributes = []): void
    {
        MetaReviewSyncLog::create(array_merge([
            'lead_id' => $lead->id,
            'user_id' => $lead->meta_stage_updated_by,
            'status' => $status,
            'reason' => $reason,
            'previous_stage' => $lead->getOriginal('meta_stage'),
            'meta_stage' => $lead->meta_stage,
            'note' => $lead->meta_review_note,
        ], $attributes));
    }

    private function buildPayload(Lead $lead, string $stage, ?string $email, ?string $phone, ?string $leadgenId): array
    {
        [$firstName, $lastName] = $this->splitName($lead->name);
        $meta = is_array($lead->website_payload_meta) ? $lead->website_payload_meta : [];

        $userData = array_filter([
            'em' => $this->identifierNormalizer->hash($email),
            'ph' => $this->identifierNormalizer->hash($phone),
            'fn' => $this->hashNormalized($firstName),
            'ln' => $this->hashNormalized($lastName),
            'ct' => $this->hashNormalized($lead->city),
            'st' => $this->hashNormalized($lead->state),
            'zp' => $this->hashNormalized($lead->pincode),
            'country' => $this->hashNormalized($this->countryCode($meta['country'] ?? null)),
            'external_id' => 'crm_lead_' . $lead->id,
            'lead_id' => $leadgenId,
            'fbc' => $this->firstMetaValue($meta, ['fbc', '_fbc', 'fb_click_id']),
            'fbp' => $this->firstMetaValue($meta, ['fbp', '_fbp', 'fb_browser_id']),
            'client_ip_address' => $this->firstMetaValue($meta, ['client_ip_address', 'ip_address', 'ip']),
            'client_user_agent' => $this->firstMetaValue($meta, ['client_user_agent', 'user_agent']),
        ]);

        $event = [
            'event_name' => 'LeadStageUpdated',
            'event_time' => now()->timestamp,
            'action_source' => 'system_generated',
            'user_data' => $userData,
            'custom_data' => [
                'meta_stage' => $stage,
                'lead_id' => (string) $lead->id,
                'lead_source' => Lead::normalizeSource($lead->source),
            ],
        ];

        if (filled(config('meta_review.test_event_code'))) {
            return [
                'data' => [$event],
                'test_event_code' => config('meta_review.test_event_code'),
            ];
        }

        return ['data' => [$event]];
    }

    private function dedupeKey(Lead $lead, string $stage): string
    {
        return sha1($lead->id . '|' . $stage);
    }

    private function splitName(?string $name): array
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return [null, null];
        }

        $firstName = array_shift($parts);
        $lastName = $parts ? implode(' ', $parts) : null;

        return [$firstName, $lastName];
    }

    private function hashNormalized(?string $value): ?string
    {
        $normalized = $this->normalizeForMetaHash($value);

        return $normalized !== null ? $this->identifierNormalizer->hash($normalized) : null;
    }

    private function normalizeForMetaHash(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return $normalized !== '' ? $normalized : null;
    }

    private function countryCode(?string $country): string
    {
        $country = $this->normalizeForMetaHash($country);

        return match ($country) {
            'in', 'ind', 'india', null => 'in',
            default => substr(preg_replace('/[^a-z]/', '', $country), 0, 2) ?: 'in',
        };
    }

    private function firstMetaValue(array $meta, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) data_get($meta, $key, ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
