<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\WebsiteIntegration;
use App\Models\WebsiteIntegrationRequestLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteLeadIntakeService
{
    public function __construct(
        private readonly FieldMappingService $fieldMappingService,
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService,
        private readonly LeadReenquiryService $leadReenquiryService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly WebsiteLeadAssignmentFallbackService $fallbackService,
    ) {
    }

    public function preview(WebsiteIntegration $integration, array $payload, ?array $mappings = null): array
    {
        $effectiveMappings = $mappings ?: $integration->fieldMappings
            ->map(fn ($mapping) => Arr::only($mapping->toArray(), [
                'incoming_field',
                'crm_field',
                'is_required',
                'default_value',
                'is_ignored',
            ]))
            ->values()
            ->all();

        return $this->fieldMappingService->previewWebsiteMappings($payload, $effectiveMappings);
    }

    public function handle(WebsiteIntegration $integration, array $payload, bool $isTest = false, ?string $requestIp = null, ?array $mappings = null): array
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        $actorUserId = (int) ($integration->created_by ?: 1);
        $status = 'success';
        $lead = null;
        $duplicate = false;
        $assignmentResult = [
            'strategy' => 'automation',
            'matched' => false,
            'assigned_to' => null,
        ];
        $fallbackResult = null;
        $errorMessage = null;

        try {
            if (!$integration->is_active) {
                $status = 'inactive';
                return $this->finalize($integration, $requestId, $startedAt, $payload, [], [
                    'errors' => ['integration' => 'Integration is inactive.'],
                ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, 'Integration is inactive.', 403);
            }

            if ($this->isRateLimited($integration)) {
                $status = 'rate_limited';
                return $this->finalize($integration, $requestId, $startedAt, $payload, [], [
                    'errors' => ['rate_limit' => 'Rate limit exceeded for this integration.'],
                ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, 'Rate limit exceeded.', 429);
            }

            $preview = $this->preview($integration, $payload, $mappings);
            $mappedPayload = $preview['mapped_payload'];
            $metaPayload = $preview['meta_payload'];
            $validationErrors = [];

            if (!empty($preview['missing_required'])) {
                foreach ($preview['missing_required'] as $field) {
                    $validationErrors[$field] = "Required field '{$field}' is missing.";
                }
            }

            foreach ($preview['field_errors'] as $field => $message) {
                $validationErrors[$field] = $message;
            }

            if (!empty($validationErrors)) {
                $status = 'validation_failed';
                return $this->finalize($integration, $requestId, $startedAt, $payload, [
                    'lead' => $mappedPayload,
                    'meta' => $metaPayload,
                    'ignored_fields' => $preview['ignored_fields'],
                    'unmapped_incoming' => $preview['unmapped_incoming'],
                ], [
                    'errors' => $validationErrors,
                    'missing_required' => $preview['missing_required'],
                    'duplicate_warnings' => $preview['duplicate_warnings'],
                ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, 'Validation failed.', 422);
            }

            $parsedPhone = $this->duplicateDetectionService->parsedLeadPhone((string) ($mappedPayload['phone'] ?? ''));
            $normalizedPhone = $parsedPhone['normalized'] ?? '';
            $mappedPayload['phone'] = $parsedPhone['e164'] ?? '';
            $mappedPayload['phone_country_iso'] = $parsedPhone['country_iso'] ?? null;

            return $this->leadDuplicateGuardService->withPhoneLock($normalizedPhone, function (string $lockedPhone) use ($integration, $requestId, $startedAt, $payload, $mappedPayload, $metaPayload, $preview, $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, $actorUserId) {
            $normalizedPhone = $lockedPhone;
            $mappedPayload['phone'] = '+' . $normalizedPhone;

            try {
            DB::beginTransaction();

            $existingLead = $this->duplicateDetectionService->findExistingLeadByPhone($normalizedPhone);
            $integrationNote = $this->buildIntegrationNote($integration, $mappedPayload, $metaPayload);

            if ($existingLead) {
                $duplicate = true;
                $lead = $this->leadReenquiryService->markWebsiteReenquiry(
                    $existingLead,
                    $integration->id,
                    $actorUserId,
                    $integrationNote
                );

                $this->updateLeadFromMappedPayload($lead, $mappedPayload, $metaPayload, $integration, true);
                $status = 'duplicate';
            } else {
                $leadData = $this->buildLeadPayload($mappedPayload, $metaPayload, $integration, $actorUserId);
                $extraLeadData = Arr::only($leadData, ['website_integration_id', 'website_queue_status', 'website_payload_meta']);
                unset($leadData['website_integration_id'], $leadData['website_queue_status'], $leadData['website_payload_meta']);

                $lead = Lead::create($leadData);
                if (!empty($extraLeadData)) {
                    $lead->forceFill($extraLeadData)->save();
                }
                $status = 'success';
            }

            $lead = $lead->fresh(['activeAssignments.assignedTo']);

            if (!$lead->activeAssignments()->exists()) {
                $automationAssigned = $this->sourceAutomationService->assignFromSource($lead, 'website', null, null, (string) $integration->id);
                $lead = $lead->fresh(['activeAssignments.assignedTo']);

                if ($automationAssigned && $lead->activeAssignments->isNotEmpty()) {
                    $assignee = $lead->activeAssignments->first()->assignedTo;
                    $assignmentResult = [
                        'strategy' => 'automation',
                        'matched' => true,
                        'assigned_to' => $assignee ? [
                            'id' => $assignee->id,
                            'name' => $assignee->name,
                        ] : null,
                    ];
                    $lead->update(['website_queue_status' => null]);
                } else {
                    $assignmentResult = [
                        'strategy' => 'automation',
                        'matched' => false,
                        'assigned_to' => null,
                    ];
                    $fallbackResult = $this->fallbackService->apply($lead, $integration, $actorUserId);
                    $lead = $lead->fresh(['activeAssignments.assignedTo']);
                }
            } else {
                $lead->forceFill(['website_queue_status' => null])->save();
                $assignee = $lead->activeAssignments->first()->assignedTo;
                $assignmentResult = [
                    'strategy' => 'existing_owner_retained',
                    'matched' => true,
                    'assigned_to' => $assignee ? [
                        'id' => $assignee->id,
                        'name' => $assignee->name,
                    ] : null,
                ];
            }

            if ($isTest) {
                $integration->forceFill(['last_tested_at' => now()])->save();
            }

            DB::commit();

            return $this->finalize($integration, $requestId, $startedAt, $payload, [
                'lead' => $mappedPayload,
                'meta' => $metaPayload,
                'ignored_fields' => $preview['ignored_fields'],
                'unmapped_incoming' => $preview['unmapped_incoming'],
            ], [
                'errors' => [],
                'missing_required' => [],
                'duplicate_warnings' => $preview['duplicate_warnings'],
            ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, null, 200);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $status = 'error';
            $errorMessage = $e->getMessage();

            return $this->finalize($integration, $requestId, $startedAt, $payload, [], [
                'errors' => ['exception' => $errorMessage],
            ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, $errorMessage, 500);
        }
        });
        } catch (\Throwable $e) {
            $status = 'error';
            $errorMessage = $e->getMessage();

            return $this->finalize($integration, $requestId, $startedAt, $payload, [], [
                'errors' => ['exception' => $errorMessage],
            ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, $errorMessage, 500);
        }
    }

    private function finalize(
        WebsiteIntegration $integration,
        string $requestId,
        float $startedAt,
        array $rawPayload,
        array $mappedPayload,
        array $validationResult,
        array $assignmentResult,
        ?array $fallbackResult,
        ?Lead $lead,
        bool $duplicate,
        bool $isTest,
        ?string $requestIp,
        string $status,
        ?string $errorMessage,
        int $httpStatus
    ): array {
        $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);

        WebsiteIntegrationRequestLog::create([
            'website_integration_id' => $integration->id,
            'request_id' => $requestId,
            'request_ip' => $requestIp,
            'raw_payload' => $rawPayload,
            'mapped_payload' => $mappedPayload,
            'validation_result' => $validationResult,
            'assignment_result' => $assignmentResult,
            'fallback_result' => $fallbackResult,
            'status' => $status,
            'lead_id' => $lead?->id,
            'duplicate' => $duplicate,
            'is_test' => $isTest,
            'response_time_ms' => $responseTimeMs,
            'error_message' => $errorMessage,
        ]);

        $message = match ($status) {
            'duplicate' => 'Lead already exists and was marked as re-enquiry.',
            'validation_failed' => 'Validation failed.',
            'rate_limited' => 'Rate limit exceeded.',
            'inactive' => 'Integration is inactive.',
            'error' => 'Failed to process website lead.',
            default => 'Lead created successfully.',
        };

        return [
            'http_status' => $httpStatus,
            'request_id' => $requestId,
            'status' => in_array($status, ['validation_failed', 'rate_limited', 'inactive', 'error'], true) ? 'error' : 'ok',
            'message' => $message,
            'lead_id' => $lead?->id,
            'duplicate' => $duplicate,
            'is_test' => $isTest,
            'response_time_ms' => $responseTimeMs,
            'parsed_output' => $mappedPayload,
            'validation_result' => $validationResult,
            'assignment_result' => $assignmentResult,
            'fallback_result' => $fallbackResult,
        ];
    }

    private function isRateLimited(WebsiteIntegration $integration): bool
    {
        if ((int) $integration->rate_limit <= 0) {
            return false;
        }

        return $integration->requestLogs()
            ->where('created_at', '>=', now()->subMinute())
            ->count() >= (int) $integration->rate_limit;
    }

    private function buildLeadPayload(array $mappedPayload, array $metaPayload, WebsiteIntegration $integration, int $actorUserId): array
    {
        return [
            'name' => trim((string) ($mappedPayload['name'] ?? 'Website Lead')),
            'phone' => trim((string) ($mappedPayload['phone'] ?? '')),
            'phone_country_iso' => $mappedPayload['phone_country_iso'] ?? null,
            'email' => $mappedPayload['email'] ?? null,
            'address' => $mappedPayload['address'] ?? null,
            'city' => $mappedPayload['city'] ?? null,
            'state' => $mappedPayload['state'] ?? null,
            'pincode' => $mappedPayload['pincode'] ?? null,
            'property_type' => $mappedPayload['property_type'] ?? null,
            'budget' => $mappedPayload['budget'] ?? null,
            'budget_min' => $mappedPayload['budget_min'] ?? null,
            'budget_max' => $mappedPayload['budget_max'] ?? null,
            'requirements' => $mappedPayload['requirements'] ?? null,
            'notes' => $mappedPayload['notes'] ?? null,
            'preferred_location' => $mappedPayload['preferred_location'] ?? null,
            'preferred_size' => $mappedPayload['preferred_size'] ?? null,
            'use_end_use' => $mappedPayload['use_end_use'] ?? null,
            'possession_status' => $mappedPayload['possession_status'] ?? null,
            'source' => Lead::normalizeSource($integration->source ?: 'website'),
            'status' => $integration->default_status ?: 'new',
            'created_by' => $actorUserId,
            'website_integration_id' => $integration->id,
            'website_queue_status' => null,
            'website_payload_meta' => $metaPayload ?: null,
        ];
    }

    private function updateLeadFromMappedPayload(Lead $lead, array $mappedPayload, array $metaPayload, WebsiteIntegration $integration, bool $appendNotes): void
    {
        $note = trim((string) ($mappedPayload['notes'] ?? ''));
        $requirements = trim((string) ($mappedPayload['requirements'] ?? ''));

        $lead->forceFill([
            'email' => $lead->email ?: ($mappedPayload['email'] ?? null),
            'address' => $lead->address ?: ($mappedPayload['address'] ?? null),
            'city' => $lead->city ?: ($mappedPayload['city'] ?? null),
            'state' => $lead->state ?: ($mappedPayload['state'] ?? null),
            'pincode' => $lead->pincode ?: ($mappedPayload['pincode'] ?? null),
            'property_type' => $lead->property_type ?: ($mappedPayload['property_type'] ?? null),
            'budget' => $lead->budget ?: ($mappedPayload['budget'] ?? null),
            'preferred_location' => $lead->preferred_location ?: ($mappedPayload['preferred_location'] ?? null),
            'preferred_size' => $lead->preferred_size ?: ($mappedPayload['preferred_size'] ?? null),
            'use_end_use' => $lead->use_end_use ?: ($mappedPayload['use_end_use'] ?? null),
            'possession_status' => $lead->possession_status ?: ($mappedPayload['possession_status'] ?? null),
            'website_integration_id' => $integration->id,
            'website_payload_meta' => array_filter(array_merge($lead->website_payload_meta ?? [], $metaPayload)),
        ]);

        if ($appendNotes && $note !== '') {
            $lead->notes = trim((string) $lead->notes);
            $lead->notes = trim($lead->notes . "\n" . $note);
        }

        if ($appendNotes && $requirements !== '' && blank($lead->requirements)) {
            $lead->requirements = $requirements;
        }

        $lead->save();
    }

    private function buildIntegrationNote(WebsiteIntegration $integration, array $mappedPayload, array $metaPayload): string
    {
        $parts = [];

        if (!blank($mappedPayload['notes'] ?? null)) {
            $parts[] = trim((string) $mappedPayload['notes']);
        }

        if (!empty($metaPayload)) {
            foreach ($metaPayload as $key => $value) {
                $parts[] = $key . ': ' . $value;
            }
        }

        array_unshift($parts, 'Website integration: ' . $integration->name);

        return implode("\n", array_filter($parts));
    }
}
