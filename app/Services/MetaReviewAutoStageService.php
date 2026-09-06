<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SystemSettings;
use Illuminate\Support\Facades\DB;

class MetaReviewAutoStageService
{
    private const DEFAULT_MAPPING = [
        'cnp_stage' => 'intake',
        'not_interested_stage' => 'not_qualified',
        'interested_stage' => 'qualified',
        'fresh_follow_up_stage' => 'intake',
        'follow_up_after_interested_stage' => 'in_progress',
        'visit_scheduled_stage' => 'converted',
    ];

    private ?array $cachedMapping = null;

    public function __construct(
        private readonly MetaReviewAccessService $accessService,
        private readonly MetaLeadReviewSyncService $syncService,
        private readonly MetaReviewStageService $stageService,
    ) {
    }

    public function applyForOutcome(Lead $lead, string $outcome): void
    {
        $stage = $this->mapOutcomeToStage($lead, $outcome);
        if ($stage === null) {
            return;
        }

        $this->applyStage($lead, $stage);
    }

    public function applyStage(Lead $lead, string $stage): void
    {
        $stage = trim((string) $stage);
        if ($stage === '') {
            return;
        }

        if (!$this->accessService->resolveMetaLinkage($lead)['is_linked']) {
            return;
        }

        if (!$this->stageService->isValid($stage)) {
            return;
        }

        // Manual override: once a human has updated the stage, do not auto-change.
        if (!is_null($lead->meta_stage_updated_by)) {
            return;
        }

        if ((string) $lead->meta_stage === $stage) {
            return;
        }

        $previousStage = $lead->meta_stage;

        $lead->forceFill([
            'meta_stage' => $stage,
            'meta_stage_updated_at' => now(),
            'meta_stage_updated_by' => null,
        ])->save();

        $decision = $this->syncService->shouldDispatch($lead, $previousStage, $lead->meta_stage);

        if ($decision['dispatch']) {
            $lead->forceFill([
                'meta_sync_status' => 'pending',
                'meta_last_sync_error' => null,
            ])->save();

            DB::afterCommit(function () use ($lead) {
                $freshLead = Lead::with('latestFbLead')->find($lead->id);
                if ($freshLead) {
                    $this->syncService->sync($freshLead);
                }
            });

            return;
        }

        $this->syncService->skip($lead, $decision['reason']);
    }

    public function applyVisitScheduled(Lead $lead): void
    {
        $mapping = $this->getMapping();
        $stage = $mapping['visit_scheduled_stage'] ?? self::DEFAULT_MAPPING['visit_scheduled_stage'];
        $this->applyStage($lead, $stage);
    }

    public function getMapping(): array
    {
        if ($this->cachedMapping !== null) {
            return $this->cachedMapping;
        }

        $raw = SystemSettings::get('meta_auto_mapping');
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        $payload = is_array($payload) ? $payload : [];

        $mapping = array_merge(self::DEFAULT_MAPPING, array_intersect_key($payload, self::DEFAULT_MAPPING));

        foreach ($mapping as $key => $value) {
            if (!$this->stageService->isValid($value)) {
                $mapping[$key] = self::DEFAULT_MAPPING[$key];
            }
        }

        $this->cachedMapping = $mapping;

        return $mapping;
    }

    private function mapOutcomeToStage(Lead $lead, string $outcome): ?string
    {
        $outcome = trim(strtolower($outcome));
        $mapping = $this->getMapping();

        if (in_array($outcome, ['not_interested', 'junk'], true)) {
            return $mapping['not_interested_stage'];
        }

        if ($outcome === 'cnp') {
            return $mapping['cnp_stage'];
        }

        if ($outcome === 'interested') {
            return $mapping['interested_stage'];
        }

        if ($outcome === 'follow_up') {
            $isInterestedPipeline = in_array(($lead->status ?? ''), [
                'connected',
                'verified_prospect',
                'meeting_scheduled',
                'meeting_completed',
            ], true);

            if ($isInterestedPipeline || (string) $lead->meta_stage === 'qualified') {
                return $mapping['follow_up_after_interested_stage'];
            }

            return $mapping['fresh_follow_up_stage'];
        }

        return null;
    }
}
