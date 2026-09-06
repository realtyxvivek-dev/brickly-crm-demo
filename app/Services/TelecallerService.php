<?php

namespace App\Services;

use App\Models\CrmAssignment;
use App\Models\Prospect;
use App\Models\BlacklistedNumber;
use App\Models\TelecallerDailyLimit;
use App\Models\User;
use App\Models\GoogleSheetsConfig;
use App\Models\Task;
use App\Models\Lead;
use App\Services\DuplicateDetectionService;
use App\Services\GoogleSheetsService;
use App\Notifications\ProspectCreatedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TelecallerService
{
    protected $duplicateService;
    protected $sheetsService;

    public function __construct(
        DuplicateDetectionService $duplicateService,
        GoogleSheetsService $sheetsService
    ) {
        $this->duplicateService = $duplicateService;
        $this->sheetsService = $sheetsService;
    }

    /**
     * Get dashboard statistics for telecaller
     */
    public function getStats(int $telecallerId): array
    {
        $remainingCalls = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('call_status', 'pending')
            ->count();

        $completedCalls = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereIn('call_status', ['called_interested', 'called_not_interested', 'completed'])
            ->count();

        $notInterested = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('call_status', 'called_not_interested')
            ->count();

        $prospects = Prospect::where('created_by', $telecallerId)->count();
        $prospectsVerified = Prospect::where('created_by', $telecallerId)
            ->where('verification_status', 'verified')
            ->count();
        $prospectsPending = 0;

        // Get daily limit
        $dailyLimit = TelecallerDailyLimit::where('user_id', $telecallerId)->first();
        $dailyLimitValue = $dailyLimit ? $dailyLimit->overall_daily_limit : 0;

        // Calculate called today with CNP logic
        $calledToday = $this->calculateDailyCallCount($telecallerId);
        $remainingToday = $dailyLimitValue > 0 ? max(0, $dailyLimitValue - $calledToday) : null;

        // Task stats
        $pendingTasks = Task::where('assigned_to', $telecallerId)
            ->where('type', 'phone_call')
            ->where('status', 'pending')
            ->count();
        
        $completedTasks = Task::where('assigned_to', $telecallerId)
            ->where('type', 'phone_call')
            ->where('status', 'completed')
            ->count();

        // Today Productivity Stats
        $today = Carbon::today();
        
        // Total calls today (all call outcomes)
        $totalCallsToday = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereDate('called_at', $today)
            ->whereIn('call_status', ['called_interested', 'called_not_interested', 'completed', 'broker'])
            ->count();
        
        // Customers (interested) today
        $customersToday = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereDate('called_at', $today)
            ->where('call_status', 'called_interested')
            ->count();
        
        // Not interested today
        $notInterestedToday = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereDate('called_at', $today)
            ->where('call_status', 'called_not_interested')
            ->where('cnp_count', 0) // Only direct not interested, not from CNP
            ->count();
        
        // Call later today
        $callLaterToday = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereDate('follow_up_scheduled_at', $today)
            ->where('follow_up_completed', false)
            ->count();
        
        // CNP today
        $cnpToday = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('cnp_count', '>', 0)
            ->where(function($q) use ($today) {
                $q->whereDate('updated_at', $today)
                  ->orWhereDate('called_at', $today);
            })
            ->where('call_status', 'pending')
            ->count();
        
        // Verification flow removed.
        $pendingInterested = 0;
        
        // Verified interested (prospects verified)
        $verifiedInterested = Prospect::where('created_by', $telecallerId)
            ->where('verification_status', 'verified')
            ->count();
        
        $rejectedInterested = 0;

        return [
            'remaining_calls' => $remainingCalls,
            'completed_calls' => $completedCalls,
            'not_interested' => $notInterested,
            'prospects' => $prospects,
            'prospects_verified' => $prospectsVerified,
            'prospects_pending' => $prospectsPending,
            'daily_limit' => $dailyLimitValue,
            'called_today' => $calledToday,
            'remaining_today' => $remainingToday,
            // Task stats
            'pending_tasks' => $pendingTasks,
            'completed_tasks' => $completedTasks,
            // Today Productivity
            'today_productivity' => [
                'total_calls' => $totalCallsToday,
                'customers' => $customersToday,
                'not_interested' => $notInterestedToday,
                'call_later' => $callLaterToday,
                'cnp' => $cnpToday,
                'pending_interested' => $pendingInterested,
                'verified_interested' => $verifiedInterested,
                'rejected_interested' => $rejectedInterested,
            ],
        ];
    }

    /**
     * Get calling queue with auto-queue logic and daily limit check
     */
    public function getCallingQueue(int $telecallerId): array
    {
        // Auto-queue follow-ups
        $this->autoQueueFollowUps($telecallerId);

        // Get daily limit
        $dailyLimit = TelecallerDailyLimit::where('user_id', $telecallerId)->first();
        $dailyLimitValue = $dailyLimit ? $dailyLimit->overall_daily_limit : 0;

        // Calculate remaining calls
        $calledToday = $this->calculateDailyCallCount($telecallerId);
        $remainingCalls = $dailyLimitValue > 0 ? max(0, $dailyLimitValue - $calledToday) : null;

        // Get pending assignments
        $query = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('call_status', 'pending')
            ->whereNotIn('phone', function($q) {
                $q->select('phone')->from('blacklisted_numbers');
            })
            ->orderBy('assigned_at', 'asc')
            ->with(['assignedTo', 'lead']);

        // Apply daily limit if set
        if ($remainingCalls !== null && $remainingCalls > 0) {
            $query->limit($remainingCalls);
        }

        return $query->get()->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'customer_name' => $assignment->customer_name,
                'phone' => $assignment->phone,
                'assigned_date' => $assignment->assigned_at->format('Y-m-d'),
                'notes' => $assignment->notes,
                'cnp_count' => $assignment->cnp_count,
            ];
        })->toArray();
    }

    /**
     * Get completed calls with filters
     */
    public function getCompletedCalls(int $telecallerId, array $filters = []): array
    {
        $query = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereIn('call_status', ['called_interested', 'called_not_interested', 'completed'])
            ->orderBy('called_at', 'desc');

        // Apply date filters
        if (isset($filters['from_date'])) {
            $query->whereDate('called_at', '>=', $filters['from_date']);
        }
        if (isset($filters['to_date'])) {
            $query->whereDate('called_at', '<=', $filters['to_date']);
        }

        return $query->get()->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'customer_name' => $assignment->customer_name,
                'phone' => $assignment->phone,
                'call_status' => $assignment->call_status,
                'called_at' => $assignment->called_at ? $assignment->called_at->format('Y-m-d H:i:s') : null,
                'notes' => $assignment->notes,
            ];
        })->toArray();
    }

    /**
     * Get follow-up calls
     */
    public function getFollowUpCalls(int $telecallerId): array
    {
        $query = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereNotNull('follow_up_scheduled_at')
            ->where('follow_up_completed', false)
            ->orderBy('follow_up_scheduled_at', 'asc');

        return $query->get()->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'customer_name' => $assignment->customer_name,
                'phone' => $assignment->phone,
                'follow_up_scheduled_at' => $assignment->follow_up_scheduled_at->format('Y-m-d H:i:s'),
                'notes' => $assignment->notes,
            ];
        })->toArray();
    }

    /**
     * Get CNP calls (cnp_count >= 1 and < 2)
     */
    public function getCnpCalls(int $telecallerId): array
    {
        $query = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('cnp_count', '>=', 1)
            ->where('cnp_count', '<', 2)
            ->where('call_status', 'pending')
            ->orderBy('assigned_at', 'asc');

        return $query->get()->map(function($assignment) {
            return [
                'id' => $assignment->id,
                'customer_name' => $assignment->customer_name,
                'phone' => $assignment->phone,
                'cnp_count' => $assignment->cnp_count,
                'assigned_at' => $assignment->assigned_at->format('Y-m-d H:i:s'),
                'notes' => $assignment->notes,
            ];
        })->toArray();
    }

    /**
     * Get prospects created by telecaller
     */
    public function getProspects(int $telecallerId, array $filters = []): array
    {
        $query = Prospect::where('created_by', $telecallerId)
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if (isset($filters['from_date'])) {
            $query->whereDate('created_at', '>=', $filters['from_date']);
        }
        if (isset($filters['to_date'])) {
            $query->whereDate('created_at', '<=', $filters['to_date']);
        }

        // Apply verification status filter
        if (isset($filters['verification_status']) && $filters['verification_status'] !== 'all' && $filters['verification_status'] !== null) {
            $query->where('verification_status', $filters['verification_status']);
        }

        return $query->get()->map(function($prospect) {
            return [
                'id' => $prospect->id,
                'customer_name' => $prospect->customer_name,
                'phone' => $prospect->phone,
                'email' => $prospect->email,
                'budget' => $prospect->budget,
                'preferred_location' => $prospect->preferred_location,
                'size' => $prospect->size,
                'purpose' => $prospect->purpose,
                'possession' => $prospect->possession,
                'remark' => $prospect->remark,
                'verification_status' => $prospect->verification_status,
                'created_at' => $prospect->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }

    /**
     * Mark assignment as not interested
     */
    public function markNotInterested(int $assignmentId, int $telecallerId, ?string $remark): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            $remark = trim((string) $remark);
            $noteSuffix = $remark !== '' ? ' - ' . $remark : '';

            // Update assignment
            $assignment->call_status = 'called_not_interested';
            $assignment->called_at = now();
            $assignment->not_interested_date = now();
            $assignment->shuffle_after_date = now()->addDays(rand(3, 6));
            $assignment->notes = ($assignment->notes ? $assignment->notes . "\n" : '') . 
                "[" . now()->format('Y-m-d H:i:s') . "] Not Interested" . $noteSuffix;
            $assignment->save();

            // Complete related task if exists
            $task = Task::where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            
            if ($task) {
                $task->markAsCompleted();
            }

            // Sync to Google Sheet if configured
            if ($assignment->sheet_config_id && $assignment->sheet_row_number) {
                $this->sheetsService->updateGoogleSheetStatus(
                    $assignment->sheet_config_id,
                    $assignment->sheet_row_number,
                    'called_not_interested',
                    $assignment->notes,
                    $assignment->assignedTo->name ?? null
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Marked as not interested successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error marking not interested: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark assignment as CNP (Call Not Picked)
     */
    public function markCnp(int $assignmentId, int $telecallerId, ?string $remark): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            $remark = trim((string) $remark);
            $noteSuffix = $remark !== '' ? ' - ' . $remark : '';

            // Increment CNP count (handles auto-marking logic)
            $oldCnpCount = $assignment->cnp_count;
            $assignment->incrementCnp();
            $assignment->refresh();

            // Update notes
            $assignment->notes = ($assignment->notes ? $assignment->notes . "\n" : '') . 
                "[" . now()->format('Y-m-d H:i:s') . "] CNP" . $noteSuffix;
            $assignment->save();

            // Handle task status based on CNP count
            $task = Task::where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            
            if ($task) {
                // If CNP count >= 2, complete the task, otherwise reset to pending for next call
                if ($assignment->cnp_count >= 2) {
                    $task->markAsCompleted();
                } else {
                    $task->update(['status' => 'pending']);
                }
            }

            // Sync to Google Sheet if configured and auto-marked as not interested
            if ($assignment->sheet_config_id && $assignment->sheet_row_number && $assignment->cnp_count >= 2) {
                $this->sheetsService->updateGoogleSheetStatus(
                    $assignment->sheet_config_id,
                    $assignment->sheet_row_number,
                    'called_not_interested',
                    $assignment->notes,
                    $assignment->assignedTo->name ?? null
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Marked as CNP successfully',
                'cnp_count' => $assignment->cnp_count,
                'auto_marked_not_interested' => $assignment->cnp_count >= 2,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error marking CNP: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Schedule follow-up
     */
    public function scheduleFollowUp(int $assignmentId, int $telecallerId, string $date, string $time, ?string $notes = null): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            $followUpDate = Carbon::parse($date);
            $assignment->scheduleFollowUp($followUpDate, $time, $notes);

            // Complete related task if exists
            $task = Task::where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            
            if ($task) {
                $task->markAsCompleted();
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Follow-up scheduled successfully',
                'follow_up_scheduled_at' => $assignment->follow_up_scheduled_at->format('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error scheduling follow-up: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create prospect from assignment
     */
    public function createProspect(int $assignmentId, int $telecallerId, array $data): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            // Validate required fields
            if (empty($data['remark'])) {
                throw new \Exception('Remark is required');
            }
            
            if (empty($data['lead_score']) || !is_numeric($data['lead_score']) || $data['lead_score'] < 1 || $data['lead_score'] > 5) {
                throw new \Exception('Lead score is required and must be between 1 and 5');
            }

            // Check duplicate
            $sanitizedPhone = $this->duplicateService->sanitizePhone($assignment->phone);
            $existingProspect = Prospect::where('phone', $sanitizedPhone)->first();
            
            if ($existingProspect) {
                DB::rollBack();
                return [
                    'success' => false,
                    'error' => 'duplicate',
                    'message' => 'Prospect with this phone number already exists',
                    'existing_prospect' => [
                        'id' => $existingProspect->id,
                        'customer_name' => $existingProspect->customer_name,
                        'phone' => $existingProspect->phone,
                        'created_at' => $existingProspect->created_at->format('Y-m-d H:i:s'),
                    ],
                ];
            }

            // Get manager
            $telecaller = User::findOrFail($telecallerId);
            $managerId = $data['assigned_manager'] ?? $telecaller->manager_id;

            // Create prospect
            $prospect = Prospect::create([
                'assignment_id' => $assignment->id,
                'lead_id' => $assignment->lead_id,
                'customer_name' => $assignment->customer_name,
                'phone' => $sanitizedPhone,
                'budget' => $data['budget'] ?? null,
                'preferred_location' => $data['preferred_location'] ?? null,
                'size' => $data['size'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'possession' => $data['possession'] ?? null,
                'assigned_manager' => $managerId,
                'created_by' => $telecallerId,
                'notes' => $assignment->notes,
                'employee_remark' => $data['remark'],
                'lead_score' => $data['lead_score'],
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $telecallerId,
            ]);

            // Update assignment
            $assignment->call_status = 'completed';
            $assignment->called_at = now();
            $assignment->notes = ($assignment->notes ? $assignment->notes . "\n" : '') . $data['remark'];
            $assignment->save();

            // Complete related task if exists
            $task = Task::where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            
            if ($task) {
                $task->markAsCompleted();
            }

            if ($assignment->lead_id) {
                $lead = Lead::find($assignment->lead_id);
                if ($lead) {
                    $lead->updateStatusIfAllowed('verified_prospect');
                }
            }

            // Sync to Google Sheet if configured
            if ($assignment->sheet_config_id && $assignment->sheet_row_number) {
                $this->sheetsService->updateGoogleSheetStatus(
                    $assignment->sheet_config_id,
                    $assignment->sheet_row_number,
                    'called_interested',
                    "Remark by {$telecaller->name}: {$data['remark']}",
                    $telecaller->name
                );
            }

            // Send notification to manager
            if ($managerId) {
                $manager = User::find($managerId);
                if ($manager) {
                    $manager->notify(new ProspectCreatedNotification($prospect, $telecaller->name));
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Prospect created successfully',
                'prospect' => [
                    'id' => $prospect->id,
                    'customer_name' => $prospect->customer_name,
                    'phone' => $prospect->phone,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error creating prospect: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate daily call count with CNP logic
     * CNP Logic: 1st CNP = 0.5, 2nd CNP = 0.5 (total 1 call for 2 CNP attempts)
     */
    public function calculateDailyCallCount(int $telecallerId): float
    {
        $today = Carbon::today();

        // Regular calls (interested/completed) = 1 each
        $regularCalls = CrmAssignment::where('assigned_to', $telecallerId)
            ->whereIn('call_status', ['called_interested', 'completed'])
            ->whereDate('called_at', $today)
            ->count();

        // Not interested calls that are NOT from CNP (cnp_count = 0) = 1 each
        $notInterestedRegular = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('call_status', 'called_not_interested')
            ->where('cnp_count', 0)
            ->whereDate('called_at', $today)
            ->count();

        // CNP calls: count based on cnp_count
        // cnp_count = 1 means 1 CNP attempt = 0.5
        // cnp_count = 2 means 2 CNP attempts = 1.0 total (both counted together)
        $cnpWithCount1 = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('cnp_count', 1)
            ->where('call_status', 'pending')
            ->whereDate('updated_at', $today)
            ->count() * 0.5;

        $cnpWithCount2 = CrmAssignment::where('assigned_to', $telecallerId)
            ->where('cnp_count', 2)
            ->where('call_status', 'called_not_interested')
            ->whereDate('called_at', $today)
            ->count() * 1.0; // Already counted both attempts together

        return $regularCalls + $notInterestedRegular + $cnpWithCount1 + $cnpWithCount2;
    }

    /**
     * Auto-queue follow-ups when scheduled time arrives
     */
    public function autoQueueFollowUps(int $telecallerId): void
    {
        $now = now();

        CrmAssignment::where('assigned_to', $telecallerId)
            ->whereNotNull('follow_up_scheduled_at')
            ->where('follow_up_completed', false)
            ->where('follow_up_scheduled_at', '<=', $now)
            ->update([
                'call_status' => 'pending',
                'follow_up_completed' => true,
                'follow_up_scheduled_at' => null,
            ]);
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(int $limit = 1): array
    {
        $today = Carbon::today();

        $topPerformers = User::whereHas('role', function($q) {
                $q->where('slug', \App\Models\Role::SALES_EXECUTIVE);
            })
            ->withCount(['crmAssignments as completed_calls' => function($q) use ($today) {
                $q->whereIn('call_status', ['called_interested', 'called_not_interested', 'completed'])
                  ->whereDate('called_at', $today);
            }])
            ->having('completed_calls', '>', 0)
            ->orderBy('completed_calls', 'desc')
            ->limit($limit)
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'completed_calls' => $user->completed_calls ?? 0,
                ];
            })
            ->toArray();

        return $topPerformers;
    }

    /**
     * Mark assignment as broker and auto-blacklist
     */
    public function markAsBroker(int $assignmentId, int $telecallerId, string $remark): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            // Update assignment status
            $assignment->call_status = 'broker';
            $assignment->called_at = now();
            $assignment->notes = ($assignment->notes ? $assignment->notes . "\n" : '') . 
                "[" . now()->format('Y-m-d H:i:s') . "] Marked as Broker - " . $remark;
            $assignment->save();

            // Complete related task if exists
            $task = Task::where('lead_id', $assignment->lead_id)
                ->where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->whereIn('status', ['pending', 'in_progress'])
                ->first();
            
            if ($task) {
                $task->markAsCompleted();
            }

            // Auto-blacklist the phone number
            $this->blacklistNumber($assignment->phone, $telecallerId, 'Broker');

            // Sync to Google Sheet if configured
            if ($assignment->sheet_config_id && $assignment->sheet_row_number) {
                $this->sheetsService->updateGoogleSheetStatus(
                    $assignment->sheet_config_id,
                    $assignment->sheet_row_number,
                    'broker',
                    $assignment->notes,
                    $assignment->assignedTo->name ?? null
                );
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Marked as broker and blacklisted successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error marking as broker: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Blacklist a number
     */
    public function blacklistNumber(string $phone, int $telecallerId, string $reason = 'Broker'): array
    {
        DB::beginTransaction();
        try {
            $sanitizedPhone = $this->duplicateService->sanitizePhone($phone);

            BlacklistedNumber::updateOrCreate(
                ['phone' => $sanitizedPhone],
                [
                    'reason' => $reason,
                    'blacklisted_by' => $telecallerId,
                    'blacklisted_at' => now(),
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'message' => 'Number blacklisted successfully',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error blacklisting number: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Recall assignment (move back to queue)
     */
    public function recallAssignment(int $assignmentId, int $telecallerId): array
    {
        DB::beginTransaction();
        try {
            $assignment = CrmAssignment::where('id', $assignmentId)
                ->where('assigned_to', $telecallerId)
                ->firstOrFail();

            $assignment->call_status = 'pending';
            $assignment->save();

            DB::commit();

            return [
                'success' => true,
                'message' => 'Assignment moved back to queue',
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error recalling assignment: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get lead data for task form
     */
    public function getLeadDataForTask(\App\Models\Task $task): array
    {
        $lead = $task->lead;
        
        return [
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'address' => $lead->address,
            'city' => $lead->city,
            'state' => $lead->state,
            'pincode' => $lead->pincode,
            'preferred_location' => $lead->preferred_location,
            'preferred_size' => $lead->preferred_size,
            'property_type' => $lead->property_type,
            'budget_min' => $lead->budget_min,
            'budget_max' => $lead->budget_max,
            'investment' => $lead->investment ?? null,
            'source' => $lead->source,
            'use_end_use' => $lead->use_end_use,
            'requirements' => $lead->requirements,
            'notes' => $lead->notes,
        ];
    }
}
