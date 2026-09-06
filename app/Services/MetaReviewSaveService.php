<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class MetaReviewSaveService
{
    public function __construct(
        private readonly MetaReviewAccessService $accessService,
        private readonly MetaLeadReviewSyncService $syncService,
        private readonly MetaReviewStageService $stageService,
    ) {
    }

    public function saveFromLeadRequirements(Lead $lead, User $user, ?string $requestedStage, ?string $requestedNote): void
    {
        $requestedStage = trim((string) $requestedStage);
        $requestedNote = $requestedNote !== null ? trim((string) $requestedNote) : null;

        $currentStage = trim((string) $lead->meta_stage);
        $currentNote = $lead->meta_review_note !== null ? trim((string) $lead->meta_review_note) : null;

        $stageChanged = $requestedStage !== $currentStage;
        $noteChanged = $requestedNote !== $currentNote;

        if (!$stageChanged && !$noteChanged) {
            return;
        }

        if (!$this->accessService->resolveMetaLinkage($lead)['is_linked']) {
            throw new \InvalidArgumentException('Meta Review is not available for this lead.');
        }

        if (!$this->accessService->canEdit($user, $lead)) {
            throw new AuthorizationException('You are not allowed to update Meta Review for this lead.');
        }

        if ($requestedStage !== '' && !$this->stageService->isValid($requestedStage)) {
            throw new \InvalidArgumentException('Selected Meta stage is invalid or inactive.');
        }

        $previousStage = $lead->meta_stage;

        $lead->forceFill([
            'meta_stage' => $requestedStage !== '' ? $requestedStage : null,
            'meta_review_note' => $requestedNote !== '' ? $requestedNote : null,
            'meta_stage_updated_at' => now(),
            'meta_stage_updated_by' => $user->id,
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
}
