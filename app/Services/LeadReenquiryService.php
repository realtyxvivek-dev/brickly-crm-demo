<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FbForm;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;

class LeadReenquiryService
{
    public function __construct(
        private readonly LeadOwnerTaskService $leadOwnerTaskService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function markMetaReenquiry(Lead $lead, ?FbForm $fbForm = null, ?int $actorUserId = null): Lead
    {
        $oldStatus = $lead->status;
        $reopened = in_array($oldStatus, ['junk', 'not_interested', 'dead', 'closed'], true);
        $actorUserId = $actorUserId ?: ($lead->created_by ?: 1);

        $lead->forceFill([
            'is_reenquiry' => true,
            'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0) + 1,
            'last_reenquiry_at' => now(),
            'last_reenquiry_source' => 'meta',
            'last_reenquiry_fb_form_id' => $fbForm?->id,
        ]);

        if ($reopened) {
            $lead->status = 'new';
            $lead->status_auto_update_enabled = true;
            $lead->next_followup_at = null;

            if (in_array($oldStatus, ['junk', 'not_interested'], true)) {
                $lead->other_lead_marked_by = null;
                $lead->other_lead_marked_at = null;
                $lead->other_lead_reason = null;
            }

            if ($oldStatus === 'dead') {
                $lead->is_dead = false;
                $lead->dead_reason = null;
                $lead->dead_at_stage = null;
                $lead->marked_dead_at = null;
                $lead->marked_dead_by = null;
            }
        }

        $lead->save();

        ActivityLog::create([
            'user_id' => $actorUserId,
            'action' => 'lead_reenquiry',
            'model_type' => 'Lead',
            'model_id' => $lead->id,
            'description' => $reopened
                ? 'Lead re-enquired via Meta and was reopened.'
                : 'Lead re-enquired via Meta.',
            'old_values' => [
                'status' => $oldStatus,
                'reenquiry_count' => max(0, (int) $lead->reenquiry_count - 1),
            ],
            'new_values' => [
                'status' => $lead->status,
                'reenquiry_count' => $lead->reenquiry_count,
                'source' => 'meta',
                'fb_form_id' => $fbForm?->id,
                'fb_form_name' => $fbForm?->form_name,
                'reopened' => $reopened,
            ],
        ]);

        $activeAssignment = $lead->activeAssignments()->with('assignedTo.role')->first();
        $owner = $activeAssignment?->assignedTo;

        if ($owner) {
            $actionUrl = route('leads.show', $lead);

            if ($reopened) {
                $taskResult = $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                    $lead,
                    $owner,
                    $actorUserId,
                    'Lead re-enquired via Meta.'
                );
                $actionUrl = $taskResult['action_url'] ?: $actionUrl;
            }

            $this->notificationService->notifyLeadReenquiry(
                $owner,
                $lead,
                $actionUrl,
                'meta',
                $reopened
            );

            return $lead->fresh(['activeAssignments.assignedTo']);
        }

        if (!$reopened) {
            return $lead->fresh(['activeAssignments.assignedTo']);
        }

        if ($fbForm) {
            $this->sourceAutomationService->assignFromSource($lead, 'facebook_lead_ads', $fbForm->id);
        }

        return $lead->fresh(['activeAssignments.assignedTo']);
    }

    public function markWebsiteReenquiry(Lead $lead, int $integrationId, ?int $actorUserId = null, ?string $note = null): Lead
    {
        $oldStatus = $lead->status;
        $reopened = in_array($oldStatus, ['junk', 'not_interested', 'dead', 'closed'], true);
        $actorUserId = $actorUserId ?: ($lead->created_by ?: 1);

        $lead->forceFill([
            'is_reenquiry' => true,
            'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0) + 1,
            'last_reenquiry_at' => now(),
            'last_reenquiry_source' => 'website',
            'website_integration_id' => $integrationId,
        ]);

        if ($reopened) {
            $lead->status = 'new';
            $lead->status_auto_update_enabled = true;
            $lead->next_followup_at = null;

            if (in_array($oldStatus, ['junk', 'not_interested'], true)) {
                $lead->other_lead_marked_by = null;
                $lead->other_lead_marked_at = null;
                $lead->other_lead_reason = null;
            }

            if ($oldStatus === 'dead') {
                $lead->is_dead = false;
                $lead->dead_reason = null;
                $lead->dead_at_stage = null;
                $lead->marked_dead_at = null;
                $lead->marked_dead_by = null;
            }
        }

        $lead->save();

        ActivityLog::create([
            'user_id' => $actorUserId,
            'action' => 'lead_reenquiry',
            'model_type' => 'Lead',
            'model_id' => $lead->id,
            'description' => $reopened
                ? 'Lead re-enquired via Website and was reopened.'
                : 'Lead re-enquired via Website.',
            'old_values' => [
                'status' => $oldStatus,
                'reenquiry_count' => max(0, (int) $lead->reenquiry_count - 1),
            ],
            'new_values' => [
                'status' => $lead->status,
                'reenquiry_count' => $lead->reenquiry_count,
                'source' => 'website',
                'website_integration_id' => $integrationId,
                'reopened' => $reopened,
                'note' => $note,
            ],
        ]);

        $owner = $lead->activeAssignments()->with('assignedTo.role')->first()?->assignedTo;
        if ($owner) {
            $taskResult = $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                $lead,
                $owner,
                $actorUserId,
                $note ?: 'Lead re-enquired via Website.'
            );

            $this->notificationService->notifyLeadReenquiry(
                $owner,
                $lead,
                $taskResult['action_url'] ?: route('leads.show', $lead),
                'website',
                $reopened
            );
        }

        return $lead->fresh(['activeAssignments.assignedTo']);
    }

    public function markWhatsAppReenquiry(Lead $lead, ?int $actorUserId = null, ?string $note = null): Lead
    {
        $oldStatus = $lead->status;
        $reopened = in_array($oldStatus, ['junk', 'not_interested', 'dead', 'closed'], true);
        $actorUserId = $actorUserId ?: ($lead->created_by ?: 1);

        $lead->forceFill([
            'is_reenquiry' => true,
            'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0) + 1,
            'last_reenquiry_at' => now(),
            'last_reenquiry_source' => 'whatsapp',
        ]);

        if ($reopened) {
            $lead->status = 'new';
            $lead->status_auto_update_enabled = true;
            $lead->next_followup_at = null;

            if (in_array($oldStatus, ['junk', 'not_interested'], true)) {
                $lead->other_lead_marked_by = null;
                $lead->other_lead_marked_at = null;
                $lead->other_lead_reason = null;
            }

            if ($oldStatus === 'dead') {
                $lead->is_dead = false;
                $lead->dead_reason = null;
                $lead->dead_at_stage = null;
                $lead->marked_dead_at = null;
                $lead->marked_dead_by = null;
            }
        }

        if ($note) {
            $lead->notes = $this->appendLeadNote($lead->notes, $note);
        }

        $lead->save();

        ActivityLog::create([
            'user_id' => $actorUserId,
            'action' => 'lead_reenquiry',
            'model_type' => 'Lead',
            'model_id' => $lead->id,
            'description' => $reopened
                ? 'Lead re-enquired via WhatsApp Web and was reopened.'
                : 'Lead re-enquired via WhatsApp Web.',
            'old_values' => [
                'status' => $oldStatus,
                'reenquiry_count' => max(0, (int) $lead->reenquiry_count - 1),
            ],
            'new_values' => [
                'status' => $lead->status,
                'reenquiry_count' => $lead->reenquiry_count,
                'source' => 'whatsapp',
                'reopened' => $reopened,
                'note' => $note,
            ],
        ]);

        $owner = $lead->activeAssignments()->with('assignedTo.role')->first()?->assignedTo;
        if ($owner) {
            $taskResult = $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                $lead,
                $owner,
                $actorUserId,
                $note ?: 'Lead re-enquired via WhatsApp Web.'
            );

            $this->notificationService->notifyLeadReenquiry(
                $owner,
                $lead,
                $taskResult['action_url'] ?: route('leads.show', $lead),
                'whatsapp',
                $reopened
            );
        }

        return $lead->fresh(['activeAssignments.assignedTo']);
    }

    public function markGenericReenquiry(Lead $lead, string $source, ?int $actorUserId = null): Lead
    {
        $oldStatus = (string) $lead->status;
        $reopened = in_array($oldStatus, ['junk', 'not_interested', 'dead', 'closed'], true);
        $actorUserId = $actorUserId ?: ($lead->created_by ?: 1);

        $lead->forceFill([
            'is_reenquiry' => true,
            'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0) + 1,
            'last_reenquiry_at' => now(),
            'last_reenquiry_source' => Lead::normalizeSource($source),
        ]);

        if ($reopened && $oldStatus !== 'closed') {
            $lead->status = 'new';
            $lead->status_auto_update_enabled = true;
            $lead->next_followup_at = null;
            $lead->is_dead = false;
            $lead->dead_reason = null;
            $lead->dead_at_stage = null;
            $lead->marked_dead_at = null;
            $lead->marked_dead_by = null;
            $lead->other_lead_marked_by = null;
            $lead->other_lead_marked_at = null;
            $lead->other_lead_reason = null;
        }

        $lead->save();

        ActivityLog::create([
            'user_id' => $actorUserId,
            'action' => 'lead_reenquiry',
            'model_type' => 'Lead',
            'model_id' => $lead->id,
            'description' => "Lead re-enquired via {$source}." . ($reopened && $oldStatus !== 'closed' ? ' Lead reopened.' : ''),
            'old_values' => ['status' => $oldStatus, 'reenquiry_count' => max(0, (int) $lead->reenquiry_count - 1)],
            'new_values' => [
                'status' => $lead->status,
                'reenquiry_count' => $lead->reenquiry_count,
                'source' => Lead::normalizeSource($source),
                'reopened' => $reopened && $oldStatus !== 'closed',
            ],
        ]);

        return $lead->fresh(['activeAssignments.assignedTo']);
    }

    private function appendLeadNote(?string $existingNotes, string $note): string
    {
        $line = '[WhatsApp][' . now()->format('d M Y, h:i A') . '] ' . trim($note);
        $current = trim((string) $existingNotes);

        return $current !== '' ? $current . PHP_EOL . $line : $line;
    }
}
