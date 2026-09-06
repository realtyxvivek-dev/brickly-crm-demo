<?php

namespace App\Services;

use App\Events\LeadCreated;
use App\Models\GoogleSheetsConfig;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class GoogleSheetsLeadIntakeService
{
    public function __construct(
        private readonly FieldMappingService $fieldMappingService,
        private readonly LeadAssignmentService $leadAssignmentService,
        private readonly DuplicateDetectionService $duplicateDetectionService,
    ) {
    }

    public function handle(GoogleSheetsConfig $config, array $payload): array
    {
        try {
            if (!$config->is_active) {
                return [
                    'status' => 'error',
                    'message' => 'This Google Sheets integration is inactive.',
                    'http_status' => 410,
                    'lead_id' => null,
                    'duplicate' => false,
                    'assigned_to' => null,
                ];
            }

            $validator = Validator::make($payload, [
                'sheet_id' => 'required|string',
                'sheet_row_number' => 'required|integer|min:1',
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return [
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()->toArray(),
                    'http_status' => 422,
                    'lead_id' => null,
                    'duplicate' => false,
                    'assigned_to' => null,
                ];
            }

            $payloadSheetId = GoogleSheetsConfig::extractSheetId($payload['sheet_id'] ?? null);
            if (!$payloadSheetId || $payloadSheetId !== $config->sheet_id) {
                return [
                    'status' => 'error',
                    'message' => 'Sheet ID mismatch.',
                    'http_status' => 422,
                    'lead_id' => null,
                    'duplicate' => false,
                    'assigned_to' => null,
                ];
            }

            $mappedData = $this->fieldMappingService->mapFieldsFromPayload($payload, $config);
            $mappedData['name'] = $mappedData['name'] ?? $payload['name'];
            $mappedData['phone'] = $mappedData['phone'] ?? $payload['phone'];
            $mappedData['source'] = Lead::normalizeSource($mappedData['source'] ?? 'google_sheets');
            $mappedData['status'] = 'new';

            $sanitizedPhone = $this->duplicateDetectionService->sanitizePhone((string) $mappedData['phone']);
            $phoneDigits = preg_replace('/[^0-9]/', '', $sanitizedPhone);
            if (!$this->duplicateDetectionService->isValidPhone($phoneDigits)) {
                return [
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => [
                        'phone' => ['Invalid phone number'],
                    ],
                    'http_status' => 422,
                    'lead_id' => null,
                    'duplicate' => false,
                    'assigned_to' => null,
                ];
            }
            $mappedData['phone'] = $phoneDigits;

            $sheetRowNumber = (int) $payload['sheet_row_number'];
            $existingLead = $this->duplicateDetectionService->findExistingLeadByPhone($mappedData['phone']);
            if ($existingLead) {
                app(LeadReenquiryService::class)->markGenericReenquiry($existingLead, 'sheet', $config->created_by);
                $assignment = LeadAssignment::where('lead_id', $existingLead->id)
                    ->where('sheet_config_id', $config->id)
                    ->where('sheet_row_number', $sheetRowNumber)
                    ->first();

                if (!$assignment) {
                    $fallbackAssignedTo = optional($existingLead->activeAssignments()->first())->assigned_to
                        ?? $config->linked_telecaller_id
                        ?? $config->created_by;

                    LeadAssignment::create([
                        'lead_id' => $existingLead->id,
                        'sheet_config_id' => $config->id,
                        'sheet_row_number' => $sheetRowNumber,
                        'assigned_to' => $fallbackAssignedTo,
                        'assigned_by' => $config->created_by,
                        'assignment_type' => 'secondary',
                        'assigned_at' => now(),
                        'is_active' => false,
                    ]);
                }

                return [
                    'status' => 'ok',
                    'message' => 'Lead already exists',
                    'http_status' => 200,
                    'lead_id' => $existingLead->id,
                    'duplicate' => true,
                    'assigned_to' => null,
                ];
            }

            $lead = Lead::create([
                'name' => $mappedData['name'],
                'phone' => $mappedData['phone'],
                'email' => $mappedData['email'] ?? null,
                'city' => $mappedData['city'] ?? null,
                'state' => $mappedData['state'] ?? null,
                'property_type' => $mappedData['property_type'] ?? null,
                'budget' => $mappedData['budget'] ?? null,
                'requirements' => $mappedData['requirements'] ?? null,
                'notes' => $mappedData['notes'] ?? null,
                'source' => Lead::normalizeSource($mappedData['source'] ?? 'google_sheets'),
                'status' => $mappedData['status'] ?? 'new',
                'created_by' => $config->created_by,
            ]);

            event(new LeadCreated($lead));

            $assignedUser = null;
            try {
                $assignedUserId = $this->leadAssignmentService->assignLead($lead, $config->id, $config->created_by);
                if (!$assignedUserId) {
                    $assignedUserId = $config->linked_telecaller_id ?? $config->created_by;

                    LeadAssignment::create([
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedUserId,
                        'assigned_by' => $config->created_by,
                        'assignment_type' => 'primary',
                        'assignment_method' => 'manual',
                        'assigned_at' => now(),
                        'is_active' => true,
                        'sheet_config_id' => $config->id,
                        'sheet_row_number' => $sheetRowNumber,
                    ]);
                } else {
                    $lead->refresh();
                    $active = $lead->activeAssignments()->first();
                    if ($active) {
                        $active->update([
                            'sheet_row_number' => $sheetRowNumber,
                            'sheet_config_id' => $config->id,
                        ]);
                    }
                }

                $assignedUser = User::find($assignedUserId);
            } catch (\Throwable $e) {
                Log::error('Failed to auto-assign lead from Google Sheet', [
                    'lead_id' => $lead->id,
                    'config_id' => $config->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'status' => 'ok',
                'message' => 'Lead created successfully',
                'http_status' => 200,
                'lead_id' => $lead->id,
                'duplicate' => false,
                'assigned_to' => $assignedUser ? [
                    'id' => $assignedUser->id,
                    'name' => $assignedUser->name,
                ] : null,
            ];
        } catch (\Throwable $e) {
            Log::error('Google Sheets lead intake failed', [
                'config_id' => $config->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to process Google Sheets lead.',
                'http_status' => 500,
                'lead_id' => null,
                'duplicate' => false,
                'assigned_to' => null,
            ];
        }
    }
}
