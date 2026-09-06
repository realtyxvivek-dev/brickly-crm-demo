<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\GoogleSheetsConfig;
use App\Models\SheetAssignmentConfig;
use App\Models\SheetPercentageConfig;
use App\Models\User;
use App\Models\Role;
use App\Events\LeadAssigned;
use App\Services\NotificationService;
use App\Services\LeadTaskCleanupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class LeadAssignmentService
{
    protected $limitService;
    protected $statusService;
    protected $userStatusService;
    protected $notificationService;
    protected $leadTaskCleanupService;
    protected static $roundRobinCounters = [];

    public function __construct(
        TelecallerLimitService $limitService,
        TelecallerStatusService $statusService,
        UserStatusService $userStatusService,
        NotificationService $notificationService,
        LeadTaskCleanupService $leadTaskCleanupService
    ) {
        $this->limitService = $limitService;
        $this->statusService = $statusService;
        $this->userStatusService = $userStatusService;
        $this->notificationService = $notificationService;
        $this->leadTaskCleanupService = $leadTaskCleanupService;
    }

    /**
     * Main assignment method with priority logic
     */
    public function assignLead(Lead $lead, ?int $sheetConfigId = null, int $assignedBy, ?string $method = null): ?int
    {
        DB::beginTransaction();
        try {
            $assignedUserId = null;
            $assignmentMethod = null;

            // Priority 1: Linked Telecaller (if sheet has linked_telecaller_id)
            if ($sheetConfigId) {
                $sheetConfig = GoogleSheetsConfig::find($sheetConfigId);
                if ($sheetConfig && $sheetConfig->linked_telecaller_id) {
                    $assignedUserId = $this->tryAssignToLinkedTelecaller($lead, $sheetConfig, $assignedBy);
                    if ($assignedUserId) {
                        $assignmentMethod = 'linked_telecaller';
                    }
                }
            }

            // Priority 2: Assigned User (if lead already has assigned_to)
            if (!$assignedUserId && $lead->activeAssignments()->exists()) {
                $existingAssignment = $lead->activeAssignments()->first();
                $assignedUserId = $existingAssignment->assigned_to;
                $assignmentMethod = 'existing_assignment';
            }

            // Priority 3: Auto-Assignment Config (if sheet has config)
            if (!$assignedUserId && $sheetConfigId) {
                // First check if ANY config exists (for debugging)
                $anyConfig = SheetAssignmentConfig::where('google_sheets_config_id', $sheetConfigId)->first();
                if ($anyConfig) {
                    Log::info("SheetAssignmentConfig exists but checking auto_assign_enabled", [
                        'sheet_config_id' => $sheetConfigId,
                        'assignment_config_id' => $anyConfig->id,
                        'auto_assign_enabled' => $anyConfig->auto_assign_enabled ? 'true' : 'false',
                        'assignment_method' => $anyConfig->assignment_method,
                    ]);
                } else {
                    Log::warning("No SheetAssignmentConfig found for sheet", [
                        'sheet_config_id' => $sheetConfigId,
                        'lead_id' => $lead->id,
                    ]);
                }
                
                $sheetAssignmentConfig = SheetAssignmentConfig::where('google_sheets_config_id', $sheetConfigId)
                    ->where('auto_assign_enabled', true)
                    ->first();

                if ($sheetAssignmentConfig) {
                    Log::info("Found auto-assignment config for sheet", [
                        'sheet_config_id' => $sheetConfigId,
                        'assignment_config_id' => $sheetAssignmentConfig->id,
                        'assignment_method' => $sheetAssignmentConfig->assignment_method,
                        'lead_id' => $lead->id,
                    ]);
                    
                    $assignedUserId = $this->assignByConfig($lead, $sheetAssignmentConfig, $assignedBy);
                    if ($assignedUserId) {
                        $assignmentMethod = $sheetAssignmentConfig->assignment_method;
                        Log::info("Lead assigned via auto-assignment config", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedUserId,
                            'assignment_method' => $assignmentMethod,
                        ]);
                    } else {
                        Log::warning("Auto-assignment config found but assignment failed", [
                            'lead_id' => $lead->id,
                            'sheet_config_id' => $sheetConfigId,
                            'assignment_config_id' => $sheetAssignmentConfig->id,
                            'assignment_method' => $sheetAssignmentConfig->assignment_method,
                        ]);
                    }
                } else {
                    Log::warning("No auto-assignment config found for sheet (or auto_assign_enabled is false)", [
                        'sheet_config_id' => $sheetConfigId,
                        'lead_id' => $lead->id,
                        'any_config_exists' => $anyConfig ? 'yes' : 'no',
                        'any_config_auto_assign' => $anyConfig ? ($anyConfig->auto_assign_enabled ? 'true' : 'false') : 'N/A',
                    ]);
                }
            }

            // Priority 4: Manual (if method specified or telecaller_id provided)
            if (!$assignedUserId && ($method === 'manual' || request()->has('telecaller_id'))) {
                $telecallerId = request()->input('telecaller_id');
                if ($telecallerId) {
                    $assignedUserId = $this->assignManually($lead, $telecallerId, $assignedBy);
                    if ($assignedUserId) {
                        $assignmentMethod = 'manual';
                    }
                }
            }

            if ($assignedUserId && $assignmentMethod) {
                // Create assignment record
                $assignment = $this->createAssignmentRecord($lead, $assignedUserId, $assignedBy, $assignmentMethod, $sheetConfigId, $sheetAssignmentConfig->id ?? null);
                
                // Increment daily counts
                $this->limitService->incrementAssignedCount($assignedUserId, $sheetConfigId, $sheetAssignmentConfig->id ?? null);

                // Fire LeadAssigned event - listener will auto-create calling task
                // CRITICAL: Fire event synchronously to ensure listeners execute immediately
                // Broadcast errors should not prevent task creation
                if (!$lead->is_blocked) {
                    // Create event instance
                    $leadAssignedEvent = new LeadAssigned($lead, $assignedUserId, $assignedBy);
                    
                    // Fire event synchronously - listeners execute immediately
                    // Use Event::dispatch() to ensure synchronous execution
                    try {
                        \Illuminate\Support\Facades\Event::dispatch($leadAssignedEvent);
                        
                        Log::info("LeadAssigned event fired successfully", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedUserId,
                            'assigned_by' => $assignedBy,
                        ]);
                        
                        // Verify task was created (for sales managers/executives)
                        $assignedUser = User::with('role')->find($assignedUserId);
                        if ($assignedUser && in_array($assignedUser->role->slug ?? '', [\App\Models\Role::SALES_MANAGER, \App\Models\Role::SENIOR_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                            $taskCreated = \App\Models\Task::where('lead_id', $lead->id)
                                ->where('assigned_to', $assignedUserId)
                                ->where('type', 'phone_call')
                                ->where('status', 'pending')
                                ->exists();
                            
                            if ($taskCreated) {
                                Log::info("Task creation verified for sales manager/executive", [
                                    'lead_id' => $lead->id,
                                    'assigned_to' => $assignedUserId,
                                ]);
                            } else {
                                Log::warning("Task creation verification failed - task not found", [
                                    'lead_id' => $lead->id,
                                    'assigned_to' => $assignedUserId,
                                    'role' => $assignedUser->role->slug ?? 'unknown',
                                ]);
                            }
                        } elseif ($assignedUser && $assignedUser->isSalesExecutive()) {
                            $taskCreated = \App\Models\TelecallerTask::where('lead_id', $lead->id)
                                ->where('assigned_to', $assignedUserId)
                                ->where('task_type', 'calling')
                                ->where('status', 'pending')
                                ->exists();
                            
                            if ($taskCreated) {
                                Log::info("Task creation verified for telecaller", [
                                    'lead_id' => $lead->id,
                                    'assigned_to' => $assignedUserId,
                                ]);
                            } else {
                                Log::warning("Task creation verification failed - task not found", [
                                    'lead_id' => $lead->id,
                                    'assigned_to' => $assignedUserId,
                                ]);
                            }
                        }
                        
                    } catch (\Exception $e) {
                        // Log error but don't fail assignment
                        // Broadcast errors should not prevent listeners from executing
                        Log::error("LeadAssigned event error (non-critical for assignment)", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedUserId,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                    }
                    
                    // Send chatbot notification separately (non-blocking)
                    try {
                        $assignedUser = User::with('role')->find($assignedUserId);
                        if ($assignedUser instanceof User) {
                            $leadUrl = $this->getLeadDetailUrl($assignedUser, $lead->id);
                            $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                        }
                    } catch (\Exception $notifError) {
                        Log::warning("Failed to send notification for lead assignment", [
                            'lead_id' => $lead->id,
                            'assigned_to' => $assignedUserId,
                            'error' => $notifError->getMessage(),
                        ]);
                    }
                }

                DB::commit();
                return $assignedUserId;
            }

            // Log why assignment failed
            if (!$assignedUserId) {
                Log::warning("Lead assignment failed - no user assigned", [
                    'lead_id' => $lead->id,
                    'sheet_config_id' => $sheetConfigId,
                    'assigned_by' => $assignedBy,
                    'has_existing_assignment' => $lead->activeAssignments()->exists(),
                ]);
            }
            
            DB::rollBack();
            return null;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lead assignment error for lead {$lead->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Try to assign to linked telecaller
     */
    protected function tryAssignToLinkedTelecaller(Lead $lead, GoogleSheetsConfig $sheetConfig, int $assignedBy): ?int
    {
        $telecallerId = $sheetConfig->linked_telecaller_id;
        $user = User::find($telecallerId);
        
        if (!$user) {
            return null;
        }

        // Check if user is absent (for all users)
        if ($this->userStatusService->isUserAbsent($telecallerId)) {
            return null;
        }

        // For sales executives, check sales executive-specific limits
        if ($user->isSalesExecutive()) {
            $canReceive = $this->statusService->canReceiveAssignment($telecallerId);
            if (!$canReceive['can_receive']) {
                return null;
            }

            // Check daily limits
            $limitCheck = $this->limitService->checkDailyLimits($telecallerId, $sheetConfig->id);
            if (!$limitCheck['is_allowed']) {
                return null;
            }
        }

        return $telecallerId;
    }

    /**
     * Assign by sheet assignment config
     */
    protected function assignByConfig(Lead $lead, SheetAssignmentConfig $config, int $assignedBy): ?int
    {
        switch ($config->assignment_method) {
            case 'round_robin':
                return $this->assignByRoundRobin($lead, $config, $assignedBy);
            case 'first_available':
                return $this->assignByFirstAvailable($lead, $config, $assignedBy);
            case 'percentage':
                return $this->assignByPercentage($lead, $config, $assignedBy);
            default:
                return null;
        }
    }

    /**
     * Manual assignment
     */
    public function assignManually(Lead $lead, int $userId, int $assignedBy, bool $ignoreAvailability = false): ?int
    {
        $user = User::with('role')->find($userId);
        
        if (!$user) {
            return null;
        }

        // Check if user is absent (for all users)
        if (!$ignoreAvailability && $this->userStatusService->isUserAbsent($userId)) {
            return null;
        }

        // For sales executives, check sales executive-specific limits
        if ($user->isSalesExecutive()) {
            if (!$ignoreAvailability) {
                $canReceive = $this->statusService->canReceiveAssignment($userId);
                if (!$canReceive['can_receive']) {
                    return null;
                }

                // Check daily limits
                $limitCheck = $this->limitService->checkDailyLimits($userId);
                if (!$limitCheck['is_allowed']) {
                    return null;
                }
            }
        }

        return $userId;
    }

    /**
     * Round Robin assignment
     */
    protected function assignByRoundRobin(Lead $lead, SheetAssignmentConfig $config, int $assignedBy): ?int
    {
        // Get configured users from SheetPercentageConfig (for round robin, percentage = 0)
        $configuredUsers = SheetPercentageConfig::where('sheet_assignment_config_id', $config->id)
            ->with('user')
            ->get()
            ->filter(function ($pc) {
                return $pc->user && $pc->user->is_active;
            });

        // If no users configured, fall back to all available telecallers
        if ($configuredUsers->isEmpty()) {
            $telecallers = $this->getAvailableTelecallersForConfig($config);
            if ($telecallers->isEmpty()) {
                return null;
            }
            $telecallerIds = $telecallers->pluck('id')->toArray();
        } else {
            $telecallerIds = $configuredUsers->pluck('user_id')->toArray();
        }

        $configKey = $config->id;

        // Initialize counter if not exists
        if (!isset(self::$roundRobinCounters[$configKey])) {
            self::$roundRobinCounters[$configKey] = 0;
        }

        // Get next telecaller in rotation
        $index = self::$roundRobinCounters[$configKey] % count($telecallerIds);
        $telecallerId = $telecallerIds[$index];

        // Check if this telecaller can receive assignment
        $canReceive = $this->statusService->canReceiveAssignment($telecallerId);
        $limitCheck = $this->limitService->checkDailyLimits($telecallerId, $config->google_sheets_config_id, $config->id);

        if ($canReceive['can_receive'] && $limitCheck['is_allowed']) {
            self::$roundRobinCounters[$configKey]++;
            return $telecallerId;
        }

        // Try next telecaller
        for ($i = 1; $i < count($telecallerIds); $i++) {
            $nextIndex = ($index + $i) % count($telecallerIds);
            $nextTelecallerId = $telecallerIds[$nextIndex];

            $canReceive = $this->statusService->canReceiveAssignment($nextTelecallerId);
            $limitCheck = $this->limitService->checkDailyLimits($nextTelecallerId, $config->google_sheets_config_id, $config->id);

            if ($canReceive['can_receive'] && $limitCheck['is_allowed']) {
                self::$roundRobinCounters[$configKey] = $nextIndex + 1;
                return $nextTelecallerId;
            }
        }

        return null;
    }

    /**
     * First Available assignment (minimum pending calls)
     */
    protected function assignByFirstAvailable(Lead $lead, SheetAssignmentConfig $config, int $assignedBy): ?int
    {
        $telecallers = $this->getAvailableTelecallersForConfig($config);
        
        if ($telecallers->isEmpty()) {
            return null;
        }

        $bestTelecaller = null;
        $minPending = PHP_INT_MAX;

        foreach ($telecallers as $telecaller) {
            // Check if user is absent (for all users)
            if ($this->userStatusService->isUserAbsent($telecaller->id)) {
                continue;
            }

            // For sales executives, check sales executive-specific limits
            if ($telecaller->isSalesExecutive()) {
                $canReceive = $this->statusService->canReceiveAssignment($telecaller->id);
                $limitCheck = $this->limitService->checkDailyLimits($telecaller->id, $config->google_sheets_config_id, $config->id);

                if ($canReceive['can_receive'] && $limitCheck['is_allowed']) {
                    $pendingCount = $canReceive['pending_count'];
                    if ($pendingCount < $minPending) {
                        $minPending = $pendingCount;
                        $bestTelecaller = $telecaller->id;
                    }
                }
            } else {
                // Non-telecaller users - only check absent status (already checked above)
                $bestTelecaller = $telecaller->id;
                $minPending = 0; // No pending count for non-telecallers
                break; // First available non-telecaller
            }
        }

        return $bestTelecaller;
    }

    /**
     * Percentage-based assignment
     */
    protected function assignByPercentage(Lead $lead, SheetAssignmentConfig $config, int $assignedBy): ?int
    {
        $percentageConfigs = SheetPercentageConfig::where('sheet_assignment_config_id', $config->id)
            ->with('user')
            ->get()
            ->filter(function ($pc) {
                return $pc->user && $pc->user->is_active;
            });

        if ($percentageConfigs->isEmpty()) {
            return null;
        }

        // Build weighted array
        $weightedArray = [];
        foreach ($percentageConfigs as $pc) {
            $user = User::find($pc->user_id);
            
            // Check if user is absent (for all users)
            if (!$user || $this->userStatusService->isUserAbsent($pc->user_id)) {
                continue;
            }

            // For telecallers, check telecaller-specific limits
            if ($user->isTelecaller()) {
                $canReceive = $this->statusService->canReceiveAssignment($pc->user_id);
                $limitCheck = $this->limitService->checkDailyLimits($pc->user_id, $config->google_sheets_config_id, $config->id);

                if ($canReceive['can_receive'] && $limitCheck['is_allowed']) {
                    // Add user ID multiple times based on percentage
                    $weight = (int) ($pc->percentage * 100);
                    for ($i = 0; $i < $weight; $i++) {
                        $weightedArray[] = $pc->user_id;
                    }
                } else {
                    Log::info("User excluded from percentage assignment", [
                        'user_id' => $pc->user_id,
                        'can_receive' => $canReceive['can_receive'] ?? false,
                        'limit_allowed' => $limitCheck['is_allowed'] ?? false,
                        'reason' => $canReceive['reason'] ?? ($limitCheck['reason'] ?? 'unknown'),
                    ]);
                }
            } else {
                // Non-telecaller users (Senior Manager, Sales Executive, etc.) - only check absent status
                // Add user ID multiple times based on percentage
                $weight = (int) ($pc->percentage * 100);
                for ($i = 0; $i < $weight; $i++) {
                    $weightedArray[] = $pc->user_id;
                }
                
                Log::info("Non-telecaller user added to percentage assignment", [
                    'user_id' => $pc->user_id,
                    'user_role' => $user->role->name ?? 'unknown',
                    'percentage' => $pc->percentage,
                    'weight' => $weight,
                ]);
            }
        }

        if (empty($weightedArray)) {
            Log::warning("Percentage assignment: No available users in weighted array", [
                'lead_id' => $lead->id,
                'config_id' => $config->id,
                'assignment_config_id' => $config->id,
                'percentage_configs_count' => $percentageConfigs->count(),
                'weighted_array_size' => 0,
            ]);
            return null;
        }

        // Random selection from weighted array
        $selectedUserId = $weightedArray[array_rand($weightedArray)];
        
        Log::info("Percentage assignment: User selected", [
            'lead_id' => $lead->id,
            'config_id' => $config->id,
            'selected_user_id' => $selectedUserId,
            'weighted_array_size' => count($weightedArray),
        ]);
        
        return $selectedUserId;
    }

    /**
     * Get available telecallers for config
     */
    protected function getAvailableTelecallersForConfig(SheetAssignmentConfig $config): \Illuminate\Support\Collection
    {
        $telecallers = $this->statusService->getAvailableTelecallers();

        // If linked telecaller is set, prioritize them
        if ($config->linked_telecaller_id) {
            $linked = $telecallers->firstWhere('id', $config->linked_telecaller_id);
            if ($linked) {
                return collect([$linked]);
            }
        }

        return $telecallers;
    }

    /**
     * Get lead detail URL based on user role
     */
    protected function getLeadDetailUrl(User $user, int $leadId): string
    {
        if ($user->isHrManager()) {
            $lead = Lead::find($leadId);
            if ($lead && $lead->is_hiring_candidate) {
                return route('hr-manager.hiring.show', $lead);
            }
        }

        return route('leads.show', $leadId);
    }

    public function assignToSpecificUser(
        Lead $lead,
        int $assignedTo,
        int $assignedBy,
        string $method,
        bool $ignoreAvailability = true,
        bool $sendAssignmentNotification = true
    ): ?LeadAssignment {
        DB::beginTransaction();

        try {
            $assignedUser = User::with('role')->find($assignedTo);
            if (!$assignedUser) {
                DB::rollBack();
                return null;
            }

            if (!$ignoreAvailability) {
                $validatedUserId = $this->assignManually($lead, $assignedTo, $assignedBy, false);
                if (!$validatedUserId) {
                    DB::rollBack();
                    return null;
                }
            }

            $existingActiveAssignment = $lead->activeAssignments()
                ->where('assigned_to', $assignedTo)
                ->first();

            if ($existingActiveAssignment) {
                DB::commit();
                return $existingActiveAssignment;
            }

            $assignment = $this->createAssignmentRecord($lead, $assignedTo, $assignedBy, $method);

            if ($assignedUser->isSalesExecutive()) {
                try {
                    $this->limitService->incrementAssignedCount($assignedTo);
                } catch (\Exception $e) {
                    Log::warning("Failed to increment assigned count for direct assignment", [
                        'lead_id' => $lead->id,
                        'assigned_to' => $assignedTo,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->dispatchLeadAssignedSideEffects($lead, $assignedTo, $assignedBy, $sendAssignmentNotification);

            DB::commit();

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("assignToSpecificUser failed", [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedTo,
                'assigned_by' => $assignedBy,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function dispatchLeadAssignedSideEffects(Lead $lead, int $assignedUserId, int $assignedBy, bool $sendAssignmentNotification = true): void
    {
        if ($lead->is_blocked) {
            return;
        }

        try {
            $leadAssignedEvent = new LeadAssigned($lead, $assignedUserId, $assignedBy, !$sendAssignmentNotification);
            Event::dispatch($leadAssignedEvent);

            Log::info("LeadAssigned event fired successfully", [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUserId,
                'assigned_by' => $assignedBy,
            ]);
        } catch (\Exception $e) {
            Log::error("LeadAssigned event error (non-critical for assignment)", [
                'lead_id' => $lead->id,
                'assigned_to' => $assignedUserId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        if ($sendAssignmentNotification) {
            try {
                $assignedUser = User::with('role')->find($assignedUserId);
                if ($assignedUser instanceof User) {
                    $leadUrl = $this->getLeadDetailUrl($assignedUser, $lead->id);
                    $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                }
            } catch (\Exception $notifError) {
                Log::warning("Failed to send notification for lead assignment", [
                    'lead_id' => $lead->id,
                    'assigned_to' => $assignedUserId,
                    'error' => $notifError->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create assignment record
     */
    protected function createAssignmentRecord(Lead $lead, int $assignedTo, int $assignedBy, string $method, ?int $sheetConfigId = null, ?int $sheetAssignmentConfigId = null, ?string $notes = null): LeadAssignment
    {
        $oldOwnerIds = $lead->assignments()
            ->where('is_active', true)
            ->pluck('assigned_to')
            ->filter(fn ($ownerId) => (int) $ownerId !== (int) $assignedTo)
            ->unique()
            ->values();

        foreach ($oldOwnerIds as $oldOwnerId) {
            $this->leadTaskCleanupService->deleteTasksForLeadAndOwner(
                $lead->id,
                (int) $oldOwnerId,
                $assignedBy,
                'lead_transferred'
            );
        }

        // Deactivate existing assignments
        $lead->assignments()->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        // Create new assignment
        $assignment = LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $assignedTo,
            'assigned_by' => $assignedBy,
            'assignment_type' => 'primary',
            'assignment_method' => $method,
            'notes' => $notes,
            'assigned_at' => now(),
            'is_active' => true,
            'sheet_config_id' => $sheetConfigId,
            'sheet_assignment_config_id' => $sheetAssignmentConfigId,
        ]);

        $this->syncHiringCandidateForHrAssignment($lead, $assignedTo);

        app(\App\Services\NewLeadSlaAutomationService::class)->syncForAssignment($lead, $assignment);

        if ($oldOwnerIds->isNotEmpty()) {
            $lead->markAsFreshTransfer((int) $oldOwnerIds->first(), $assignedTo, $assignedBy, $notes);
        }

        return $assignment;
    }

    protected function syncHiringCandidateForHrAssignment(Lead $lead, int $assignedTo): void
    {
        $assignedUser = User::with('role')->find($assignedTo);

        if (!$assignedUser?->isHrUser()) {
            return;
        }

        $updates = [];

        if (!$lead->is_hiring_candidate) {
            $updates['is_hiring_candidate'] = true;
        }

        if (blank($lead->hiring_status)) {
            $updates['hiring_status'] = 'new';
        }

        if ($updates !== []) {
            $lead->forceFill($updates)->save();
        }
    }

    /**
     * Bulk assign leads
     */
    public function bulkAssignLeads(array $leadIds, int $telecallerId, int $assignedBy, bool $ignoreAvailability = false, ?string $notes = null): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($leadIds as $leadId) {
            try {
                DB::beginTransaction();
                
                $lead = Lead::find($leadId);
                if (!$lead) {
                    $results['failed']++;
                    $results['errors'][] = "Lead {$leadId} not found";
                    DB::rollBack();
                    continue;
                }

                $assigned = $this->assignManually($lead, $telecallerId, $assignedBy, $ignoreAvailability);
                if ($assigned) {
                    $this->createAssignmentRecord($lead, $assigned, $assignedBy, 'manual', null, null, $notes);
                    
                    // Only increment count for telecallers
                    $assignedUser = User::with('role')->find($assigned);
                    if ($assignedUser && $assignedUser->isSalesExecutive()) {
                        try {
                            $this->limitService->incrementAssignedCount($assigned);
                        } catch (\Exception $e) {
                            Log::warning("Failed to increment assigned count for user {$assigned}: " . $e->getMessage());
                            // Continue with assignment even if limit increment fails
                        }
                    }
                    
                    // Fire LeadAssigned event - listener will auto-create calling task
                    // Use same synchronous event firing logic as assignLead method
                    if (!$lead->is_blocked) {
                        $leadAssignedEvent = new LeadAssigned($lead, $assigned, $assignedBy);
                        
                        try {
                            Event::dispatch($leadAssignedEvent);
                            
                            Log::info("LeadAssigned event fired in bulkAssignLeads", [
                                'lead_id' => $lead->id,
                                'assigned_to' => $assigned,
                                'assigned_by' => $assignedBy,
                            ]);
                            
                            // Verify task creation
                            $assignedUser = User::with('role')->find($assigned);
                            if ($assignedUser) {
                                if (in_array($assignedUser->role->slug ?? '', [\App\Models\Role::SALES_MANAGER, \App\Models\Role::SENIOR_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                                    $taskCreated = \App\Models\Task::where('lead_id', $lead->id)
                                        ->where('assigned_to', $assigned)
                                        ->where('type', 'phone_call')
                                        ->where('status', 'pending')
                                        ->exists();
                                    
                                    if ($taskCreated) {
                                        Log::info("Task creation verified in bulkAssignLeads", [
                                            'lead_id' => $lead->id,
                                            'assigned_to' => $assigned,
                                        ]);
                                    }
                                } elseif ($assignedUser->isTelecaller()) {
                                    $taskCreated = \App\Models\TelecallerTask::where('lead_id', $lead->id)
                                        ->where('assigned_to', $assigned)
                                        ->where('task_type', 'calling')
                                        ->where('status', 'pending')
                                        ->exists();
                                    
                                    if ($taskCreated) {
                                        Log::info("Task creation verified in bulkAssignLeads", [
                                            'lead_id' => $lead->id,
                                            'assigned_to' => $assigned,
                                        ]);
                                    }
                                }
                                
                                // Send chatbot notification
                                try {
                                    $leadUrl = $this->getLeadDetailUrl($assignedUser, $lead->id);
                                    $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                                } catch (\Exception $notifError) {
                                    Log::warning("Failed to send notification in bulkAssignLeads", [
                                        'lead_id' => $lead->id,
                                        'assigned_to' => $assigned,
                                        'error' => $notifError->getMessage(),
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Failed to fire LeadAssigned event in bulkAssignLeads", [
                                'lead_id' => $leadId,
                                'assigned_to' => $assigned,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                            // Continue with assignment even if event fails
                        }
                    }
                    
                    DB::commit();
                    $results['success']++;
                } else {
                    DB::rollBack();
                    $results['failed']++;
                    $results['errors'][] = "Failed to assign lead {$leadId} - User may be absent or limit reached";
                }
            } catch (\Exception $e) {
                DB::rollBack();
                $results['failed']++;
                $results['errors'][] = "Error assigning lead {$leadId}: " . $e->getMessage();
                Log::error("Error in bulkAssignLeads for lead {$leadId}: " . $e->getMessage(), [
                    'lead_id' => $leadId,
                    'assigned_to' => $telecallerId,
                    'assigned_by' => $assignedBy,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return $results;
    }

    /**
     * Transfer active lead ownership without creating new auto tasks/notifications.
     */
    public function transferAssignedLeads(array $leadIds, int $assignedTo, int $assignedBy): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($leadIds as $leadId) {
            try {
                DB::beginTransaction();

                $lead = Lead::find($leadId);
                if (!$lead) {
                    $results['failed']++;
                    $results['errors'][] = "Lead {$leadId} not found";
                    DB::rollBack();
                    continue;
                }

                $validatedUserId = $this->assignManually($lead, $assignedTo, $assignedBy);
                if (!$validatedUserId) {
                    $results['failed']++;
                    $results['errors'][] = "Failed to transfer lead {$leadId} to the selected user";
                    DB::rollBack();
                    continue;
                }

                $this->createAssignmentRecord($lead, $validatedUserId, $assignedBy, 'manual');

                DB::commit();
                $results['success']++;
            } catch (\Exception $e) {
                DB::rollBack();
                $results['failed']++;
                $results['errors'][] = "Error transferring lead {$leadId}: " . $e->getMessage();
                Log::error("Error in transferAssignedLeads for lead {$leadId}: " . $e->getMessage(), [
                    'lead_id' => $leadId,
                    'assigned_to' => $assignedTo,
                    'assigned_by' => $assignedBy,
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Auto-assign unassigned leads (called by scheduled job)
     */
    public function autoAssignUnassignedLeads(): array
    {
        $results = [
            'assigned' => 0,
            'failed' => 0,
        ];

        // Get all sheets with auto-assign enabled
        $configs = SheetAssignmentConfig::where('auto_assign_enabled', true)
            ->with('googleSheetsConfig')
            ->get();

        foreach ($configs as $config) {
            // Get unassigned leads from this sheet
            $unassignedLeads = Lead::whereDoesntHave('activeAssignments')
                ->whereHas('assignments', function ($q) use ($config) {
                    $q->where('sheet_config_id', $config->google_sheets_config_id);
                })
                ->limit(100) // Process in batches
                ->get();

            foreach ($unassignedLeads as $lead) {
                $assigned = $this->assignByConfig($lead, $config, 1); // System user
                if ($assigned) {
                    $this->createAssignmentRecord($lead, $assigned, 1, $config->assignment_method, $config->google_sheets_config_id, $config->id);
                    $this->limitService->incrementAssignedCount($assigned, $config->google_sheets_config_id, $config->id);
                    
                    // Fire LeadAssigned event - listener will auto-create calling task
                    // Use same synchronous event firing logic
                    if (!$lead->is_blocked) {
                        $leadAssignedEvent = new LeadAssigned($lead, $assigned, 1);
                        
                        try {
                            Event::dispatch($leadAssignedEvent);
                            
                            Log::info("LeadAssigned event fired in autoAssignUnassignedLeads", [
                                'lead_id' => $lead->id,
                                'assigned_to' => $assigned,
                            ]);
                            
                            // Verify task creation
                            $assignedUser = User::with('role')->find($assigned);
                            if ($assignedUser) {
                                if (in_array($assignedUser->role->slug ?? '', [\App\Models\Role::SALES_MANAGER, \App\Models\Role::SENIOR_MANAGER, \App\Models\Role::ASSISTANT_SALES_MANAGER])) {
                                    $taskCreated = \App\Models\Task::where('lead_id', $lead->id)
                                        ->where('assigned_to', $assigned)
                                        ->where('type', 'phone_call')
                                        ->where('status', 'pending')
                                        ->exists();
                                    
                                    if ($taskCreated) {
                                        Log::info("Task creation verified in autoAssignUnassignedLeads", [
                                            'lead_id' => $lead->id,
                                            'assigned_to' => $assigned,
                                        ]);
                                    }
                                } elseif ($assignedUser->isTelecaller()) {
                                    $taskCreated = \App\Models\TelecallerTask::where('lead_id', $lead->id)
                                        ->where('assigned_to', $assigned)
                                        ->where('task_type', 'calling')
                                        ->where('status', 'pending')
                                        ->exists();
                                    
                                    if ($taskCreated) {
                                        Log::info("Task creation verified in autoAssignUnassignedLeads", [
                                            'lead_id' => $lead->id,
                                            'assigned_to' => $assigned,
                                        ]);
                                    }
                                }
                                
                                // Send chatbot notification
                                try {
                                    $leadUrl = $this->getLeadDetailUrl($assignedUser, $lead->id);
                                    $this->notificationService->notifyNewLead($assignedUser, $lead, $leadUrl);
                                } catch (\Exception $notifError) {
                                    Log::warning("Failed to send notification in autoAssignUnassignedLeads", [
                                        'lead_id' => $lead->id,
                                        'assigned_to' => $assigned,
                                        'error' => $notifError->getMessage(),
                                    ]);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Failed to fire LeadAssigned event in autoAssignUnassignedLeads", [
                                'lead_id' => $lead->id,
                                'assigned_to' => $assigned,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ]);
                        }
                    }
                    
                    $results['assigned']++;
                } else {
                    $results['failed']++;
                }
            }
        }

        return $results;
    }
}
