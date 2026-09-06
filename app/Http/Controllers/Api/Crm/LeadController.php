<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Events\LeadAssigned;
use App\Models\CrmAssignment;
use App\Models\Lead;
use App\Models\ImportedLead;
use App\Services\LeadDuplicateGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends Controller
{
    public function addLead(Request $request, LeadDuplicateGuardService $leadDuplicateGuardService)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'phone_country_iso' => 'nullable|string|size:2',
            'source' => 'nullable|in:' . implode(',', array_keys(Lead::sourceOptions())),
            'notes' => 'nullable|string',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $parsedPhone = app(\App\Services\DuplicateDetectionService::class)
            ->parsedLeadPhone($validated['phone'], $validated['phone_country_iso'] ?? null);
        if (!$parsedPhone) {
            return response()->json(['message' => 'Enter a valid phone number. Use + country code for non-Indian numbers.'], 422);
        }

        return $leadDuplicateGuardService->withPhoneLock($parsedPhone['normalized'], function (string $normalizedPhone) use ($validated, $leadDuplicateGuardService, $parsedPhone) {
            if ($normalizedPhone !== '') {
                $existingLead = $leadDuplicateGuardService->findExistingLeadByPhone($normalizedPhone);
                if ($existingLead) {
                    return response()->json([
                        'message' => 'Lead already exists for this phone number.',
                        ...$leadDuplicateGuardService->buildDuplicatePayload($existingLead),
                    ], 409);
                }

                $validated['phone'] = $parsedPhone['e164'];
                $validated['phone_country_iso'] = $parsedPhone['country_iso'];
            }

        DB::beginTransaction();
        try {
            // Create lead
            $lead = Lead::create([
                'name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'phone_country_iso' => $validated['phone_country_iso'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
                'source' => Lead::normalizeSource($validated['source'] ?? null),
            ]);

            // Create CRM assignment
            $assignment = CrmAssignment::create([
                'lead_id' => $lead->id,
                'customer_name' => $validated['customer_name'],
                'phone' => $validated['phone'],
                'assigned_to' => $validated['assigned_to'],
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
                'call_status' => 'pending',
            ]);

            // Fire LeadAssigned event to trigger task creation
            event(new LeadAssigned($lead, $validated['assigned_to'], auth()->id()));

            DB::commit();

            return response()->json([
                'message' => 'Lead added successfully',
                'lead' => $lead,
                'assignment' => $assignment->load('assignedTo'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add lead: ' . $e->getMessage()], 500);
        }
        });
    }

    public function getImportedLeads(Request $request)
    {
        $query = ImportedLead::with(['lead', 'importBatch', 'assignedTo'])
            ->whereNull('assigned_to');

        if ($request->has('from_date')) {
            $query->whereHas('importBatch', function($q) use ($request) {
                $q->where('created_at', '>=', $request->from_date);
            });
        }

        if ($request->has('to_date')) {
            $query->whereHas('importBatch', function($q) use ($request) {
                $q->where('created_at', '<=', $request->to_date);
            });
        }

        if ($request->has('source')) {
            $source = $request->source;
            if ($source === 'google_sheets') {
                $query->whereHas('importBatch', function($q) {
                    $q->whereNotNull('google_sheets_config_id');
                });
            } elseif ($source === 'csv_upload') {
                $query->whereHas('importBatch', function($q) {
                    $q->whereNull('google_sheets_config_id');
                });
            } elseif ($source === 'manual') {
                // Manual leads - this would need to be determined based on your logic
            }
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $leads = $query->latest()->paginate($perPage);

        return response()->json($leads);
    }

    public function assignLeads(Request $request)
    {
        $validated = $request->validate([
            'lead_ids' => 'required|array',
            'lead_ids.*' => 'exists:imported_leads,id',
            'assigned_to' => 'required|exists:users,id',
        ]);

        $assigned = 0;

        DB::transaction(function () use ($validated, &$assigned) {
            foreach ($validated['lead_ids'] as $importedLeadId) {
                $importedLead = ImportedLead::findOrFail($importedLeadId);
                
                if ($importedLead->assigned_to) {
                    continue; // Skip already assigned
                }

                if ($importedLead->lead_id) {
                    $lead = $importedLead->lead;
                    // Create CRM assignment
                    CrmAssignment::create([
                        'lead_id' => $lead->id,
                        'customer_name' => $lead->name ?? 'Unknown',
                        'phone' => $lead->phone ?? '',
                        'assigned_to' => $validated['assigned_to'],
                        'assigned_by' => auth()->id(),
                        'assigned_at' => now(),
                        'call_status' => 'pending',
                    ]);

                    // Fire LeadAssigned event to trigger task creation
                    event(new LeadAssigned($lead, $validated['assigned_to'], auth()->id()));
                }

                $importedLead->update([
                    'assigned_to' => $validated['assigned_to'],
                    'assigned_at' => now(),
                ]);

                $assigned++;
            }
        });

        return response()->json([
            'message' => "Successfully assigned {$assigned} leads",
            'assigned_count' => $assigned,
        ]);
    }
}
