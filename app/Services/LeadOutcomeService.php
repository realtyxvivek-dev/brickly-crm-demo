<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Prospect;
use App\Models\Task;
use App\Models\User;
use App\Services\MetaReviewAutoStageService;

class LeadOutcomeService
{
    public function __construct(
        private readonly AsmCnpAutomationService $asmCnpAutomationService
    ) {
    }

    public function markAsOtherLead(Task $task, Lead $lead, Prospect $prospect, User $user, string $status, ?string $remark = null): void
    {
        $reason = trim((string) $remark) ?: ($status === 'junk' ? 'Junk' : 'Not Interested');

        $lead->notes = $this->appendNote(
            $lead->notes,
            '[' . now()->format('Y-m-d H:i:s') . '] ASM outcome: ' . $reason
        );
        $lead->markAsOtherLead($status, $user->id, $reason);

        $prospect->update([
            'verification_status' => 'verified',
            'lead_status' => $status === 'junk' ? 'junk' : 'cold',
            'manager_remark' => $reason,
            'rejection_reason' => null,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $task->markAsCompleted();
        $task->update([
            'outcome' => $status,
            'outcome_remark' => $remark,
            'outcome_recorded_at' => now(),
            'next_action_at' => null,
        ]);

        $this->asmCnpAutomationService->cancelLeadAutomation(
            $lead,
            $status === 'junk'
                ? 'Lead marked as junk.'
                : 'Lead marked as not interested.'
        );

        app(MetaReviewAutoStageService::class)->applyForOutcome($lead, $status);
    }

    private function appendNote(?string $existing, string $entry): string
    {
        $existing = trim((string) $existing);

        return $existing !== '' ? $existing . "\n\n" . $entry : $entry;
    }
}
