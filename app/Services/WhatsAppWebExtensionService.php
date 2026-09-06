<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsAppWebEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppWebExtensionService
{
    public function __construct(
        private readonly LeadReenquiryService $leadReenquiryService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService,
    ) {
    }

    public function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $digits = ltrim($digits, '0');

        if (strlen($digits) > 10) {
            return substr($digits, -10);
        }

        return $digits;
    }

    public function lookupLeadByPhone(string $phone): ?Lead
    {
        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return null;
        }

        $candidates = Lead::query()
            ->with(['activeAssignments.assignedTo.role'])
            ->where('phone', 'like', '%' . $normalized . '%')
            ->latest('id')
            ->limit(25)
            ->get();

        return $candidates->first(function (Lead $lead) use ($normalized) {
            $leadPhone = $this->normalizePhone((string) $lead->phone);

            return $leadPhone !== '' && (
                $leadPhone === $normalized
                || str_ends_with($leadPhone, $normalized)
                || str_ends_with($normalized, $leadPhone)
            );
        });
    }

    public function buildLookupPayload(string $phone): array
    {
        $lead = $this->lookupLeadByPhone($phone);
        $normalized = $this->normalizePhone($phone);

        if (!$lead) {
            return [
                'decision' => 'no_lead_found',
                'normalized_phone' => $normalized,
                'suggested_action' => 'create_lead',
                'lead' => null,
            ];
        }

        $owner = $lead->activeAssignments->first()?->assignedTo;

        return [
            'decision' => 'existing_lead',
            'normalized_phone' => $normalized,
            'suggested_action' => 'reenquiry',
            'lead' => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'status' => $lead->status,
                'is_reenquiry' => (bool) $lead->is_reenquiry,
                'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0),
                'owner_name' => $owner?->name,
                'owner_role' => $owner?->role?->name,
                'show_url' => route('leads.show', $lead),
            ],
        ];
    }

    public function processInboundMessage(User $actor, array $payload): array
    {
        $normalizedPhone = $this->normalizePhone((string) ($payload['phone'] ?? ''));
        if ($normalizedPhone === '') {
            return [
                'status' => 'invalid',
                'message' => 'Phone number is required.',
            ];
        }

        $eventHash = trim((string) ($payload['event_hash'] ?? ''));
        if ($eventHash === '') {
            $eventHash = sha1(implode('|', [
                trim((string) ($payload['session_key'] ?? 'default')),
                $normalizedPhone,
                trim((string) ($payload['message_timestamp'] ?? '')),
                trim((string) ($payload['message_preview'] ?? '')),
            ]));
        }

        $event = WhatsAppWebEvent::query()->firstOrCreate(
            ['event_hash' => $eventHash],
            [
                'session_key' => trim((string) ($payload['session_key'] ?? '')),
                'phone' => $normalizedPhone,
                'contact_name' => $this->trimNullable($payload['contact_name'] ?? null),
                'message_preview' => $this->trimNullable($payload['message_preview'] ?? null),
                'message_timestamp' => $payload['message_timestamp'] ?? null,
                'decision' => 'ignored',
                'processed_by_mode' => $payload['mode'] === 'auto' ? 'auto' : 'assist',
                'created_by_user_id' => $actor->id,
                'payload_meta' => [
                    'raw_phone' => $payload['phone'] ?? null,
                ],
            ]
        );

        if ($event->processed_at) {
            return [
                'status' => 'duplicate_event',
                'message' => 'This WhatsApp message was already processed.',
                'decision' => 'duplicate',
                'lead_id' => $event->lead_id,
                'lead_url' => $event->lead_id ? route('leads.show', $event->lead_id) : null,
            ];
        }

        return $this->leadDuplicateGuardService->withPhoneLock($normalizedPhone, function (string $lockedPhone) use ($normalizedPhone, $payload, $actor, $event, $eventHash) {
        $normalizedPhone = $lockedPhone !== '' ? $lockedPhone : $normalizedPhone;
        $lead = $this->lookupLeadByPhone($normalizedPhone);
        $messagePreview = trim((string) ($payload['message_preview'] ?? ''));
        $contactName = trim((string) ($payload['contact_name'] ?? ''));

        try {
            if ($lead) {
                $lead = $this->leadReenquiryService->markWhatsAppReenquiry(
                    $lead,
                    $actor->id,
                    $messagePreview !== '' ? "WhatsApp Web incoming message: {$messagePreview}" : 'WhatsApp Web incoming message.'
                );

                $event->forceFill([
                    'decision' => 'reenquiry',
                    'lead_id' => $lead->id,
                    'processed_at' => now(),
                    'payload_meta' => array_merge($event->payload_meta ?? [], [
                        'lead_status_after' => $lead->status,
                    ]),
                ])->save();

                return [
                    'status' => 'success',
                    'decision' => 'reenquiry',
                    'message' => 'Existing lead marked as re-enquiry.',
                    'lead_id' => $lead->id,
                    'lead_url' => route('leads.show', $lead),
                    'lead_name' => $lead->name,
                ];
            }

            $leadName = $contactName !== '' ? $contactName : 'WhatsApp Lead ' . $normalizedPhone;
            $notes = "Created from WhatsApp Web extension.";
            if ($messagePreview !== '') {
                $notes .= "\nInitial message: " . $messagePreview;
            }

            $lead = Lead::create([
                'name' => $leadName,
                'phone' => $normalizedPhone,
                'source' => Lead::normalizeSource('whatsapp'),
                'status' => 'new',
                'created_by' => $actor->id,
                'notes' => $notes,
            ]);

            $this->sourceAutomationService->assignFromSource($lead, 'whatsapp');

            $event->forceFill([
                'decision' => 'new_lead',
                'lead_id' => $lead->id,
                'processed_at' => now(),
                'payload_meta' => array_merge($event->payload_meta ?? [], [
                    'lead_status_after' => $lead->status,
                ]),
            ])->save();

            return [
                'status' => 'success',
                'decision' => 'new_lead',
                'message' => 'New lead created from WhatsApp Web message.',
                'lead_id' => $lead->id,
                'lead_url' => route('leads.show', $lead),
                'lead_name' => $lead->name,
            ];
        } catch (\Throwable $e) {
            $event->forceFill([
                'decision' => 'error',
                'processed_at' => now(),
                'payload_meta' => array_merge($event->payload_meta ?? [], [
                    'error' => $e->getMessage(),
                ]),
            ])->save();

            Log::error('WhatsApp Web extension processing failed', [
                'user_id' => $actor->id,
                'phone' => $normalizedPhone,
                'event_hash' => $eventHash,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
        });
    }

    private function trimNullable(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
