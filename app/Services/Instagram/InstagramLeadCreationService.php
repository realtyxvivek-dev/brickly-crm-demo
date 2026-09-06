<?php

namespace App\Services\Instagram;

use App\Models\IgConversation;
use App\Models\Lead;
use App\Services\DuplicateDetectionService;
use App\Services\SourceAutomationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstagramLeadCreationService
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly InstagramAutomationSettings $settings,
    ) {
    }

    public function createOrLinkLead(IgConversation $conversation): array
    {
        return DB::transaction(function () use ($conversation) {
            $conversation->refresh();
            $fields = $conversation->collected_fields ?? [];
            $phone = $this->duplicateDetectionService->normalizeLeadPhone($fields['phone'] ?? null);

            if ($phone === '') {
                return [
                    'success' => false,
                    'error' => 'Phone number is missing.',
                ];
            }

            $duplicate = $this->settings->duplicateCheckEnabled()
                ? $this->findDuplicateLead($conversation, $phone)
                : null;

            if ($duplicate) {
                $conversation->update([
                    'lead_id' => $conversation->lead_id ?: $duplicate->id,
                    'duplicate_lead_id' => $duplicate->id,
                    'duplicate_checked_at' => now(),
                    'status' => 'duplicate_skipped',
                    'completed_at' => now(),
                ]);

                return [
                    'success' => true,
                    'lead' => $duplicate,
                    'was_created' => false,
                    'was_duplicate' => true,
                ];
            }

            if ($conversation->lead_id) {
                return [
                    'success' => true,
                    'lead' => $conversation->lead,
                    'was_created' => false,
                    'was_duplicate' => false,
                ];
            }

            $lead = Lead::query()->create([
                'name' => $this->leadName($conversation, $fields),
                'phone' => '+' . $phone,
                'city' => null,
                'requirements' => $conversation->original_comment,
                'notes' => $this->buildNotes($conversation, $fields),
                'source' => Lead::normalizeSource('instagram'),
                'status' => 'new',
                'created_by' => $conversation->instagramAccount?->connected_by ?: 1,
            ]);

            $conversation->update([
                'lead_id' => $lead->id,
                'duplicate_lead_id' => null,
                'duplicate_checked_at' => now(),
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            try {
                $this->sourceAutomationService->assignFromSource($lead, 'instagram');
            } catch (\Throwable $e) {
                Log::warning('Instagram lead source assignment failed', [
                    'lead_id' => $lead->id,
                    'conversation_id' => $conversation->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'lead' => $lead,
                'was_created' => true,
                'was_duplicate' => false,
            ];
        });
    }

    private function findDuplicateLead(IgConversation $conversation, string $phone): ?Lead
    {
        $since = now()->subDays($this->settings->duplicateDaysLimit());

        $phoneLead = Lead::query()
            ->where('created_at', '>=', $since)
            ->orderByDesc('id')
            ->get()
            ->first(fn (Lead $lead) => $this->duplicateDetectionService->normalizeLeadPhone($lead->phone) === $phone);

        if ($phoneLead) {
            return $phoneLead;
        }

        $conversationLeadId = IgConversation::query()
            ->where('instagram_account_id', $conversation->instagram_account_id)
            ->where('instagram_user_id', $conversation->instagram_user_id)
            ->whereNotNull('lead_id')
            ->where('created_at', '>=', $since)
            ->whereKeyNot($conversation->id)
            ->latest('id')
            ->value('lead_id');

        return $conversationLeadId ? Lead::query()->find($conversationLeadId) : null;
    }

    private function buildNotes(IgConversation $conversation, array $fields): string
    {
        return collect([
            'Instagram automation lead',
            'Customer name: ' . ($fields['name'] ?? 'N/A'),
            'Instagram account: ' . ($conversation->instagramAccount?->ig_username ?: $conversation->instagramAccount?->ig_user_id ?: 'Unknown'),
            'Instagram user: ' . ($conversation->instagram_username ?: $conversation->instagram_user_id ?: 'Unknown'),
            'Media ID: ' . ($conversation->media_id ?: 'N/A'),
            'Comment ID: ' . ($conversation->comment_id ?: 'N/A'),
            'Conversation ID: ' . $conversation->id,
            'Rule: ' . ($conversation->automationRule?->name ?: 'N/A'),
        ])->implode("\n");
    }

    private function leadName(IgConversation $conversation, array $fields): string
    {
        $name = trim((string) ($fields['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        if ($conversation->instagram_username) {
            return '@' . $conversation->instagram_username;
        }

        return 'Instagram Lead';
    }
}
