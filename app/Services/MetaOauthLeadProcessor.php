<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\MetaOauthEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaOauthLeadProcessor
{
    public function __construct(
        private readonly MetaOAuthService $metaOAuthService,
        private readonly FacebookLeadMappingService $mappingService,
        private readonly DuplicateDetectionService $duplicateDetectionService,
    ) {
    }

    public function process(MetaOauthEvent $event): MetaOauthEvent
    {
        $event->loadMissing('page.connection');
        $page = $event->page;

        if (!$page) {
            return $this->fail($event, 'Connected OAuth Page not found for webhook event.');
        }

        if ($page->lead_mode !== 'create_leads') {
            $event->update([
                'status' => 'received',
                'error' => null,
            ]);

            return $event;
        }

        if (!$page->page_access_token) {
            return $this->fail($event, 'Page access token missing. Reconnect Facebook and select this Page again.');
        }

        if (!$event->leadgen_id || $this->isMetaSampleLeadId((string) $event->leadgen_id)) {
            $event->update([
                'status' => 'received',
                'error' => null,
            ]);

            return $event;
        }

        try {
            $leadPayload = $this->metaOAuthService->getLeadDetails((string) $event->leadgen_id, $page->page_access_token);
            $fieldData = $leadPayload['field_data'] ?? [];
            $flatFieldData = $this->mappingService->fieldDataToFlat($fieldData);
            $mapped = $this->mappingService->applyMapping(
                $fieldData,
                FacebookLeadMappingService::fallbackMappingForFieldData($fieldData)
            );

            $event->update([
                'lead_payload' => $leadPayload,
                'field_data' => $flatFieldData,
                'mapped_data' => $mapped,
                'status' => 'fetched',
                'error' => null,
            ]);

            return DB::transaction(function () use ($event, $mapped, $leadPayload) {
                $existingLead = $this->findDuplicateLead($mapped);
                if ($existingLead) {
                    $createdBy = $event->page?->connection?->user_id ?: $existingLead->created_by;
                    $existingLead = app(LeadReenquiryService::class)->markGenericReenquiry($existingLead, 'meta', $createdBy);
                    $event->update([
                        'crm_lead_id' => $existingLead->id,
                        'status' => 'duplicate',
                        'processed_at' => now(),
                    ]);

                    return $event->fresh(['crmLead', 'page']);
                }

                $lead = $this->createLead($event, $mapped, $leadPayload);

                $event->update([
                    'crm_lead_id' => $lead->id,
                    'status' => 'lead_created',
                    'processed_at' => now(),
                ]);

                $this->autoAssign($event, $lead);

                return $event->fresh(['crmLead', 'page']);
            });
        } catch (Throwable $e) {
            Log::warning('Meta OAuth lead processing failed', [
                'event_id' => $event->id,
                'leadgen_id' => $event->leadgen_id,
                'error' => $e->getMessage(),
            ]);

            return $this->fail($event, $e->getMessage());
        }
    }

    private function createLead(MetaOauthEvent $event, array $mapped, array $leadPayload): Lead
    {
        $createdBy = $event->page?->connection?->user_id
            ?: User::orderBy('id')->value('id')
            ?: 1;

        return Lead::create([
            'name' => $this->stringValue($mapped['name'] ?? null) ?: 'Facebook Lead',
            'phone' => $this->resolvePhone($mapped) ?: 'N/A',
            'email' => $this->stringValue($mapped['email'] ?? null) ?: null,
            'address' => $this->stringValue($mapped['address'] ?? null) ?: null,
            'city' => $this->stringValue($mapped['city'] ?? null) ?: null,
            'state' => $this->stringValue($mapped['state'] ?? null) ?: null,
            'pincode' => $this->stringValue($mapped['pincode'] ?? null) ?: null,
            'requirements' => $this->stringValue($mapped['requirements'] ?? null) ?: null,
            'notes' => $this->buildNotes($event, $mapped, $leadPayload),
            'source' => Lead::normalizeSource('facebook_lead_ads'),
            'status' => 'new',
            'created_by' => $createdBy,
        ]);
    }

    private function findDuplicateLead(array $mapped): ?Lead
    {
        $phone = $this->resolvePhone($mapped);
        if ($phone !== '') {
            $lead = $this->duplicateDetectionService->findExistingLeadByPhone($phone);
            if ($lead) {
                return $lead;
            }
        }

        $email = $this->stringValue($mapped['email'] ?? null);
        if ($email !== '') {
            return Lead::where('email', $email)->latest()->first();
        }

        return null;
    }

    private function autoAssign(MetaOauthEvent $event, Lead $lead): void
    {
        if (!$event->page?->auto_assign_leads) {
            return;
        }

        try {
            app(SourceAutomationService::class)->assignFromSource($lead, 'facebook_lead_ads');
        } catch (Throwable $e) {
            Log::warning('Meta OAuth lead auto assignment failed', [
                'lead_id' => $lead->id,
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolvePhone(array $mapped): string
    {
        $candidates = [
            $mapped['phone'] ?? null,
            $mapped['phone_number'] ?? null,
            $mapped['mobile'] ?? null,
            $mapped['mobile_number'] ?? null,
            $mapped['whatsapp_number'] ?? null,
        ];

        if (!empty($mapped['meta']) && is_array($mapped['meta'])) {
            $meta = $mapped['meta'];
            $candidates = array_merge($candidates, [
                $meta['phone'] ?? null,
                $meta['phone_number'] ?? null,
                $meta['mobile'] ?? null,
                $meta['mobile_number'] ?? null,
                $meta['whatsapp_number'] ?? null,
                $meta['contact_number'] ?? null,
            ]);
        }

        foreach ($candidates as $candidate) {
            $normalized = $this->duplicateDetectionService->normalizeLeadPhone($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function buildNotes(MetaOauthEvent $event, array $mapped, array $leadPayload): string
    {
        $lines = [
            'Facebook OAuth Connector lead',
            'Page: ' . ($event->page?->page_name ?: $event->page_id ?: 'N/A'),
            'Form ID: ' . ($event->form_id ?: ($leadPayload['form_id'] ?? 'N/A')),
            'Leadgen ID: ' . ($event->leadgen_id ?: 'N/A'),
        ];

        if (!empty($mapped['meta']) && is_array($mapped['meta'])) {
            $lines[] = '';
            $lines[] = 'Additional fields:';
            foreach ($mapped['meta'] as $key => $value) {
                $lines[] = $key . ': ' . $this->stringValue($value);
            }
        }

        return trim(implode("\n", $lines));
    }

    private function fail(MetaOauthEvent $event, string $error): MetaOauthEvent
    {
        $event->update([
            'status' => 'failed',
            'error' => $error,
            'processed_at' => now(),
        ]);

        return $event;
    }

    private function isMetaSampleLeadId(string $leadgenId): bool
    {
        return preg_match('/^4+$/', $leadgenId) === 1;
    }

    private function stringValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_filter(array_map([$this, 'stringValue'], $value)));
        }

        return trim((string) $value);
    }
}
