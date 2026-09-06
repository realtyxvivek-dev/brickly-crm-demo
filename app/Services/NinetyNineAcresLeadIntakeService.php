<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\ActivityLog;
use App\Models\NinetyNineAcresRequestLog;
use App\Models\NinetyNineAcresSetting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NinetyNineAcresLeadIntakeService
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly LeadAssignmentWorkflowService $leadAssignmentWorkflowService,
    ) {
    }

    public function handle(NinetyNineAcresSetting $settings, array $payload, bool $isTest = false, ?string $requestIp = null): array
    {
        $requestId = (string) Str::uuid();
        $startedAt = microtime(true);
        $mapped = $this->mapPayload($payload);
        $phone = (string) ($mapped['phone'] ?? '');
        $externalLeadId = $mapped['external_lead_id'] ?? null;

        if (!$settings->is_enabled && !$isTest) {
            return $this->finalize($settings, $requestId, $startedAt, $payload, $mapped, [
                'errors' => ['integration' => '99acres integration is disabled.'],
            ], [], null, null, false, $isTest, $requestIp, 'inactive', '99acres integration is disabled.', 403);
        }

        $validationErrors = $this->validateMappedPayload($mapped);
        if (!empty($validationErrors)) {
            return $this->finalize($settings, $requestId, $startedAt, $payload, $mapped, [
                'errors' => $validationErrors,
            ], [], null, null, false, $isTest, $requestIp, 'validation_failed', 'Validation failed.', 422);
        }

        $existingExternalLog = $externalLeadId
            ? NinetyNineAcresRequestLog::where('external_lead_id', $externalLeadId)->whereNotNull('lead_id')->first()
            : null;

        if ($existingExternalLog?->lead) {
            return $this->finalize($settings, $requestId, $startedAt, $payload, $mapped, [
                'errors' => [],
                'duplicate_reason' => 'external_lead_id',
            ], [], null, $existingExternalLog->lead, true, $isTest, $requestIp, 'duplicate', null, 200);
        }

        return $this->leadDuplicateGuardService->withPhoneLock($phone, function (string $lockedPhone) use ($settings, $requestId, $startedAt, $payload, $mapped, $isTest, $requestIp) {
            $mapped['phone'] = '+' . $lockedPhone;
            $lead = null;
            $duplicate = false;
            $assignmentResult = [
                'strategy' => 'automation',
                'matched' => false,
                'assigned_to' => null,
            ];
            $fallbackResult = null;
            $status = 'success';

            try {
                DB::beginTransaction();

                $existingLead = $this->duplicateDetectionService->findExistingLeadByPhone($lockedPhone);
                if ($existingLead) {
                    $duplicate = true;
                    $lead = $this->markReenquiry($existingLead, $mapped);
                    $this->mergeLeadDetails($lead, $mapped);
                    $status = 'duplicate';
                } else {
                    $lead = Lead::create($this->buildLeadPayload($mapped, $settings));
                    $lead = $lead->fresh(['activeAssignments.assignedTo']);
                }

                if (!$lead->activeAssignments()->exists()) {
                    $automationAssigned = $this->sourceAutomationService->assignFromSource($lead, '99acres');
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
                    } elseif (!$duplicate) {
                        $fallbackResult = $this->applyFallback($lead, $settings);
                    }
                } else {
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
                    $settings->forceFill(['last_tested_at' => now()])->save();
                }

                DB::commit();

                return $this->finalize($settings, $requestId, $startedAt, $payload, $mapped, [
                    'errors' => [],
                ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, $status, null, 200);
            } catch (\Throwable $e) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                return $this->finalize($settings, $requestId, $startedAt, $payload, $mapped, [
                    'errors' => ['exception' => $e->getMessage()],
                ], $assignmentResult, $fallbackResult, $lead, $duplicate, $isTest, $requestIp, 'error', $e->getMessage(), 500);
            }
        });
    }

    private function mapPayload(array $payload): array
    {
        $name = $this->firstValue($payload, ['name', 'customer_name', 'full_name']);
        $phone = $this->firstValue($payload, ['phone', 'mobile', 'contact_number']);
        $email = $this->firstValue($payload, ['email']);
        $project = $this->firstValue($payload, ['project', 'property', 'project_name']);
        $location = $this->firstValue($payload, ['city', 'location', 'locality']);
        $budget = $this->firstValue($payload, ['budget', 'budget_range']);
        $propertyType = $this->firstValue($payload, ['property_type', 'configuration']);
        $message = $this->firstValue($payload, ['message', 'requirement', 'remarks']);
        $externalLeadId = $this->firstValue($payload, ['lead_id', 'enquiry_id']);

        return [
            'external_lead_id' => $externalLeadId,
            'name' => $name ?: '99acres Lead',
            'phone' => $this->duplicateDetectionService->parsedLeadPhone((string) $phone)['e164'] ?? '',
            'email' => $email,
            'project_name' => $project,
            'preferred_location' => $location,
            'budget' => $budget,
            'property_type' => $propertyType,
            'requirements' => $message,
            'created_at' => $this->firstValue($payload, ['created_at', 'created_time', 'lead_date']),
            'meta' => array_filter([
                'external_lead_id' => $externalLeadId,
                'project_name' => $project,
                'raw_location' => $location,
                'provider_created_at' => $this->firstValue($payload, ['created_at', 'created_time', 'lead_date']),
            ]),
        ];
    }

    private function validateMappedPayload(array $mapped): array
    {
        $errors = [];

        if (blank($mapped['phone'] ?? null)) {
            $errors['phone'] = 'Phone is required.';
        }

        return $errors;
    }

    private function buildLeadPayload(array $mapped, NinetyNineAcresSetting $settings): array
    {
        return [
            'name' => trim((string) ($mapped['name'] ?: '99acres Lead')),
            'phone' => $mapped['phone'],
            'phone_country_iso' => $this->duplicateDetectionService->parsedLeadPhone($mapped['phone'])['country_iso'] ?? null,
            'email' => $mapped['email'] ?: null,
            'source' => Lead::normalizeSource('99acres'),
            'status' => $settings->default_status ?: 'new',
            'property_type' => $mapped['property_type'] ?: null,
            'budget' => $mapped['budget'] ?: null,
            'requirements' => $mapped['requirements'] ?: null,
            'preferred_location' => $mapped['preferred_location'] ?: null,
            'notes' => $this->buildLeadNote($mapped),
            'created_by' => 1,
        ];
    }

    private function mergeLeadDetails(Lead $lead, array $mapped): void
    {
        $lead->forceFill([
            'email' => $lead->email ?: ($mapped['email'] ?: null),
            'property_type' => $lead->property_type ?: ($mapped['property_type'] ?: null),
            'budget' => $lead->budget ?: ($mapped['budget'] ?: null),
            'requirements' => $lead->requirements ?: ($mapped['requirements'] ?: null),
            'preferred_location' => $lead->preferred_location ?: ($mapped['preferred_location'] ?: null),
        ]);

        $note = $this->buildReenquiryNote($mapped);
        if ($note !== '') {
            $lead->notes = trim(trim((string) $lead->notes) . PHP_EOL . $note);
        }

        $lead->save();
    }

    private function markReenquiry(Lead $lead, array $mapped): Lead
    {
        $oldStatus = $lead->status;
        $reopened = in_array($oldStatus, ['junk', 'not_interested', 'dead', 'closed'], true);
        $actorUserId = $lead->created_by ?: 1;

        $lead->forceFill([
            'is_reenquiry' => true,
            'reenquiry_count' => (int) ($lead->reenquiry_count ?? 0) + 1,
            'last_reenquiry_at' => now(),
            'last_reenquiry_source' => '99acres',
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
                ? 'Lead re-enquired via 99acres and was reopened.'
                : 'Lead re-enquired via 99acres.',
            'old_values' => [
                'status' => $oldStatus,
                'reenquiry_count' => max(0, (int) $lead->reenquiry_count - 1),
            ],
            'new_values' => [
                'status' => $lead->status,
                'reenquiry_count' => $lead->reenquiry_count,
                'source' => '99acres',
                'external_lead_id' => $mapped['external_lead_id'] ?? null,
                'reopened' => $reopened,
            ],
        ]);

        return $lead->fresh(['activeAssignments.assignedTo']);
    }

    private function applyFallback(Lead $lead, NinetyNineAcresSetting $settings): ?array
    {
        if ($settings->fallback_type === NinetyNineAcresSetting::FALLBACK_DEFAULT_USER && $settings->fallback_user_id) {
            $assignee = User::find($settings->fallback_user_id);

            if ($assignee) {
                $workflow = $this->leadAssignmentWorkflowService->assignLead(
                    $lead,
                    (int) $assignee->id,
                    1,
                    '99acres integration fallback assignment',
                );

                return [
                    'type' => 'default_user',
                    'applied' => true,
                    'assigned_to' => Arr::only($assignee->toArray(), ['id', 'name']),
                    'workflow' => $workflow,
                ];
            }
        }

        return [
            'type' => 'unassigned_crm_queue',
            'applied' => true,
            'assigned_to' => null,
        ];
    }

    private function finalize(
        NinetyNineAcresSetting $settings,
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

        NinetyNineAcresRequestLog::create([
            'ninety_nine_acres_setting_id' => $settings->id,
            'request_id' => $requestId,
            'request_ip' => $requestIp,
            'external_lead_id' => $mappedPayload['external_lead_id'] ?? null,
            'phone' => $mappedPayload['phone'] ?? null,
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
            'inactive' => '99acres integration is inactive.',
            'error' => 'Failed to process 99acres lead.',
            'auth_failed' => 'Invalid API key.',
            default => 'Lead created successfully.',
        };

        return [
            'http_status' => $httpStatus,
            'request_id' => $requestId,
            'status' => in_array($status, ['validation_failed', 'inactive', 'error', 'auth_failed'], true) ? 'error' : 'ok',
            'message' => $message,
            'lead_id' => $lead?->id,
            'duplicate' => $duplicate,
            'assignment_result' => $assignmentResult,
            'fallback_result' => $fallbackResult,
        ];
    }

    private function firstValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);
            if (!blank($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function buildLeadNote(array $mapped): string
    {
        $lines = ['Imported from 99acres'];

        foreach ([
            'external_lead_id' => '99acres Lead ID',
            'project_name' => 'Project',
            'preferred_location' => 'Location',
            'budget' => 'Budget',
            'property_type' => 'Property Type',
            'created_at' => 'Provider Created At',
        ] as $key => $label) {
            if (!blank($mapped[$key] ?? null)) {
                $lines[] = $label . ': ' . trim((string) $mapped[$key]);
            }
        }

        if (!blank($mapped['requirements'] ?? null)) {
            $lines[] = 'Requirement: ' . trim((string) $mapped['requirements']);
        }

        return implode(PHP_EOL, $lines);
    }

    private function buildReenquiryNote(array $mapped): string
    {
        return '[99acres][' . now()->format('d M Y, h:i A') . '] ' . $this->buildLeadNote($mapped);
    }
}
