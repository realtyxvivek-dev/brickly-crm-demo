<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\Prospect;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Incentive;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FlowTestService
{
    /**
     * Get all flow stages configuration
     */
    public function getAllStages(): array
    {
        return config('flow-test.stages', []);
    }

    /**
     * Get stage status (pending, in_progress, completed, error)
     */
    public function getStageStatus($stageId): string
    {
        // This can be enhanced to check actual database state
        // For now, return pending
        return 'pending';
    }

    /**
     * Check if user can test a stage
     */
    public function canTestStage($stageId, User $user): bool
    {
        $stages = $this->getAllStages();
        $stage = collect($stages)->firstWhere('id', $stageId);
        
        if (!$stage) {
            return false;
        }

        $requiredRoles = $stage['requiredRoles'] ?? [];
        
        // Admin and CRM can test all stages
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        // Check if user's role matches required roles
        $userRoleSlug = $user->role->slug ?? '';
        return in_array($userRoleSlug, $requiredRoles);
    }

    /**
     * Test a specific stage
     */
    public function testStage($stageId, User $user): array
    {
        $stages = $this->getAllStages();
        $stage = collect($stages)->firstWhere('id', $stageId);
        
        if (!$stage) {
            throw new \Exception("Stage {$stageId} not found");
        }

        $method = 'validate' . str_replace(' ', '', ucwords(str_replace('_', ' ', $stageId))) . 'Stage';
        
        if (method_exists($this, $method)) {
            return $this->$method($user);
        }

        // Fallback to generic validation
        return $this->validateStage($stageId, $user);
    }

    /**
     * Validate stage conditions
     */
    public function validateStage($stageId, User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        switch ($stageId) {
            case 'telecaller_lead_creation':
                return $this->validateTelecallerLeadCreationStage($user);
            
            case 'prospect_creation':
                return $this->validateProspectCreationStage($user);
            
            case 'manager_verification':
                return $this->validateManagerVerificationStage($user);
            
            case 'meeting_creation':
                return $this->validateMeetingCreationStage($user);
            
            case 'meeting_verification':
                return $this->validateMeetingVerificationStage($user);
            
            case 'site_visit_creation':
                return $this->validateSiteVisitCreationStage($user);
            
            case 'site_visit_verification':
                return $this->validateSiteVisitVerificationStage($user);
            
            case 'closer_conversion':
                return $this->validateCloserConversionStage($user);
            
            case 'closer_verification':
                return $this->validateCloserVerificationStage($user);
            
            case 'incentive_request':
                return $this->validateIncentiveRequestStage($user);
            
            case 'incentive_approval':
                return $this->validateIncentiveApprovalStage($user);
            
            default:
                return [
                    'valid' => false,
                    'errors' => ["Unknown stage: {$stageId}"],
                    'warnings' => [],
                    'info' => [],
                    'data' => []
                ];
        }
    }

    /**
     * Get stage data
     */
    public function getStageData($stageId, User $user): array
    {
        switch ($stageId) {
            case 'telecaller_lead_creation':
                return $this->getTelecallerLeadData($user);
            
            case 'prospect_creation':
                return $this->getProspectData($user);
            
            case 'manager_verification':
                return $this->getManagerVerificationData($user);
            
            default:
                return [];
        }
    }

    /**
     * Fix errors for a stage
     */
    public function fixErrors($stageId, array $errors, User $user): array
    {
        $results = [];
        
        foreach ($errors as $error) {
            $errorId = $error['id'] ?? null;
            $errorType = $error['type'] ?? null;
            $fixAction = $error['fixAction'] ?? null;

            try {
                $fixResult = $this->executeFix($errorId, $errorType, $fixAction, $user);
                $results[] = [
                    'errorId' => $errorId,
                    'success' => $fixResult['success'],
                    'message' => $fixResult['message'] ?? 'Fix applied'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'errorId' => $errorId,
                    'success' => false,
                    'message' => 'Fix failed: ' . $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Execute a fix action
     */
    protected function executeFix($errorId, $errorType, $fixAction, User $user): array
    {
        // Implement fix logic based on error type
        // This is a placeholder - implement specific fixes as needed
        return [
            'success' => true,
            'message' => 'Fix executed'
        ];
    }

    // ========== Stage Validation Methods ==========

    /**
     * Stage 1: Telecaller Lead Creation
     */
    protected function validateTelecallerLeadCreationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user is telecaller/sales executive
        if (!$user->isSalesExecutive() && !$user->isTelecaller()) {
            $errors[] = "User must be Telecaller/Sales Executive to test this stage";
        }

        // Check for assigned leads
        $assignments = LeadAssignment::where('assigned_to', $user->id)
            ->where('is_active', true)
            ->with('lead')
            ->get();

        if ($assignments->isEmpty()) {
            $errors[] = "No leads assigned to telecaller";
            $info[] = "Create a lead and assign it to this telecaller";
        } else {
            $data['assignments'] = $assignments->count();
            $info[] = "Found {$assignments->count()} assigned leads";
        }

        // Check for tasks
        $tasks = Task::where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        if ($tasks->isEmpty()) {
            $warnings[] = "No pending tasks found for telecaller";
        } else {
            $data['tasks'] = $tasks->count();
            $info[] = "Found {$tasks->count()} pending tasks";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 2: Prospect Creation
     */
    protected function validateProspectCreationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check for prospects created by this user
        $prospects = Prospect::where('created_by', $user->id)
            ->with(['lead', 'assignedManager'])
            ->get();

        if ($prospects->isEmpty()) {
            $errors[] = "No prospects created by this telecaller";
            $info[] = "Mark a lead as interested to create a prospect";
        } else {
            $data['prospects'] = $prospects->count();
            
            // Check verification status
            $pending = $prospects->where('verification_status', 'pending_verification')->count();
            $verified = $prospects->where('verification_status', 'verified')->count();
            $rejected = $prospects->where('verification_status', 'rejected')->count();
            
            $data['pending'] = $pending;
            $data['verified'] = $verified;
            $data['rejected'] = $rejected;
            
            $info[] = "Total prospects: {$prospects->count()} (Pending: {$pending}, Verified: {$verified}, Rejected: {$rejected})";
        }

        // Check for verification tasks created
        $verificationTasks = Task::where('type', 'phone_call')
            ->whereHas('prospect', function($q) use ($user) {
                $q->where('created_by', $user->id);
            })
            ->get();

        if ($verificationTasks->isEmpty() && $prospects->isNotEmpty()) {
            $warnings[] = "No verification tasks found for prospects";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 3: Manager Verification
     */
    protected function validateManagerVerificationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user is sales manager
        if (!$user->isSalesManager() && !$user->isSalesHead()) {
            $errors[] = "User must be Senior Manager or Sales Head to test this stage";
        }

        // Check for verification tasks
        $verificationTasks = Task::where('assigned_to', $user->id)
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with('prospect')
            ->get();

        if ($verificationTasks->isEmpty()) {
            $errors[] = "No verification tasks assigned to manager";
            $info[] = "Prospects need to be created by telecallers first";
        } else {
            $data['tasks'] = $verificationTasks->count();
            $info[] = "Found {$verificationTasks->count()} verification tasks";
        }

        // Check for pending prospects
        $pendingProspects = Prospect::where('assigned_manager', $user->id)
            ->where('verification_status', 'pending_verification')
            ->get();

        $data['pendingProspects'] = $pendingProspects->count();
        $info[] = "Found {$pendingProspects->count()} pending prospects";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 4: Meeting Creation
     */
    protected function validateMeetingCreationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user is sales manager
        if (!$user->isSalesManager() && !$user->isSalesHead()) {
            $errors[] = "User must be Senior Manager to test this stage";
        }

        // Check for meetings
        $meetings = Meeting::where('created_by', $user->id)
            ->get();

        if ($meetings->isEmpty()) {
            $warnings[] = "No meetings created by this manager";
        } else {
            $data['meetings'] = $meetings->count();
            
            $scheduled = $meetings->where('status', 'scheduled')->count();
            $completed = $meetings->where('status', 'completed')->count();
            $pendingVerification = $meetings->where('verification_status', 'pending')->count();
            
            $data['scheduled'] = $scheduled;
            $data['completed'] = $completed;
            $data['pendingVerification'] = $pendingVerification;
            
            $info[] = "Total meetings: {$meetings->count()} (Scheduled: {$scheduled}, Completed: {$completed}, Pending Verification: {$pendingVerification})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 5: Meeting Verification
     */
    protected function validateMeetingVerificationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user can verify (CRM, Admin, Sales Head)
        if (!$user->isAdmin() && !$user->isCrm() && !$user->isSalesHead()) {
            $errors[] = "User must be Admin, CRM, or Sales Head to verify meetings";
        }

        // Check for pending meetings
        $pendingMeetings = Meeting::where('status', 'completed')
            ->where('verification_status', 'pending')
            ->get();

        if ($pendingMeetings->isEmpty()) {
            $warnings[] = "No meetings pending verification";
        } else {
            $data['pendingMeetings'] = $pendingMeetings->count();
            $info[] = "Found {$pendingMeetings->count()} meetings pending verification";
        }

        // Check verified meetings
        $verifiedMeetings = Meeting::where('verification_status', 'verified')
            ->get();

        $data['verifiedMeetings'] = $verifiedMeetings->count();
        $info[] = "Total verified meetings: {$verifiedMeetings->count()}";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 6: Site Visit Creation
     */
    protected function validateSiteVisitCreationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user is sales manager
        if (!$user->isSalesManager() && !$user->isSalesHead()) {
            $errors[] = "User must be Senior Manager to test this stage";
        }

        // Check for site visits
        $siteVisits = SiteVisit::where('created_by', $user->id)
            ->get();

        if ($siteVisits->isEmpty()) {
            $warnings[] = "No site visits created by this manager";
        } else {
            $data['siteVisits'] = $siteVisits->count();
            
            $scheduled = $siteVisits->where('status', 'scheduled')->count();
            $completed = $siteVisits->where('status', 'completed')->count();
            $pendingVerification = $siteVisits->where('verification_status', 'pending')->count();
            
            $data['scheduled'] = $scheduled;
            $data['completed'] = $completed;
            $data['pendingVerification'] = $pendingVerification;
            
            $info[] = "Total site visits: {$siteVisits->count()} (Scheduled: {$scheduled}, Completed: {$completed}, Pending Verification: {$pendingVerification})";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 7: Site Visit Verification
     */
    protected function validateSiteVisitVerificationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user can verify
        if (!$user->isAdmin() && !$user->isCrm() && !$user->isSalesHead()) {
            $errors[] = "User must be Admin, CRM, or Sales Head to verify site visits";
        }

        // Check for pending site visits
        $pendingSiteVisits = SiteVisit::where('status', 'completed')
            ->where('verification_status', 'pending')
            ->get();

        if ($pendingSiteVisits->isEmpty()) {
            $warnings[] = "No site visits pending verification";
        } else {
            $data['pendingSiteVisits'] = $pendingSiteVisits->count();
            $info[] = "Found {$pendingSiteVisits->count()} site visits pending verification";
        }

        // Check verified site visits
        $verifiedSiteVisits = SiteVisit::where('verification_status', 'verified')
            ->get();

        $data['verifiedSiteVisits'] = $verifiedSiteVisits->count();
        $info[] = "Total verified site visits: {$verifiedSiteVisits->count()}";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 8: Closer Conversion
     */
    protected function validateCloserConversionStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check for verified site visits that can be converted
        $verifiedSiteVisits = SiteVisit::where('verification_status', 'verified')
            ->whereNull('closer_status')
            ->get();

        if ($verifiedSiteVisits->isEmpty()) {
            $warnings[] = "No verified site visits available for closer conversion";
        } else {
            $data['availableForConversion'] = $verifiedSiteVisits->count();
            $info[] = "Found {$verifiedSiteVisits->count()} verified site visits available for conversion";
        }

        // Check existing closers
        $closers = SiteVisit::whereNotNull('closer_status')
            ->get();

        $data['totalClosers'] = $closers->count();
        $pendingClosers = $closers->where('closer_status', 'pending')->count();
        $verifiedClosers = $closers->whereIn('closer_status', ['approved', 'verified'])->count();
        
        $data['pendingClosers'] = $pendingClosers;
        $data['verifiedClosers'] = $verifiedClosers;
        
        $info[] = "Total closers: {$closers->count()} (Pending: {$pendingClosers}, Verified: {$verifiedClosers})";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 9: Closer Verification
     */
    protected function validateCloserVerificationStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user can verify closers (Admin, CRM)
        if (!$user->isAdmin() && !$user->isCrm()) {
            $errors[] = "User must be Admin or CRM to verify closers";
        }

        // Check for pending closers
        $pendingClosers = SiteVisit::where('closer_status', 'pending')
            ->get();

        if ($pendingClosers->isEmpty()) {
            $warnings[] = "No closers pending verification";
        } else {
            $data['pendingClosers'] = $pendingClosers->count();
            $info[] = "Found {$pendingClosers->count()} closers pending verification";
        }

        // Check verified closers and lead status
        $verifiedClosers = SiteVisit::whereIn('closer_status', ['approved', 'verified'])
            ->with('lead')
            ->get();

        $data['verifiedClosers'] = $verifiedClosers->count();
        
        $closedLeads = $verifiedClosers->filter(function($closer) {
            return $closer->lead && $closer->lead->status === 'closed';
        })->count();
        
        $data['closedLeads'] = $closedLeads;
        $info[] = "Total verified closers: {$verifiedClosers->count()} (Leads closed: {$closedLeads})";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 10: Incentive Request
     */
    protected function validateIncentiveRequestStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user can request incentive
        if (!$user->isHrManager() && !$user->isSalesExecutive() && !$user->isSalesManager() && !$user->isAssistantSalesManager()) {
            $errors[] = "User must be HR Manager, Sales Executive, or Senior Manager to request incentives";
        }

        // Check for verified closers
        $verifiedClosers = SiteVisit::whereIn('closer_status', ['approved', 'verified'])
            ->where('closing_verification_status', 'verified')
            ->get();

        if ($verifiedClosers->isEmpty()) {
            $warnings[] = "No verified closers available for incentive request";
        } else {
            $data['availableClosers'] = $verifiedClosers->count();
            $info[] = "Found {$verifiedClosers->count()} verified closers available for incentive";
        }

        // Check existing incentives
        $incentives = Incentive::where('user_id', $user->id)
            ->get();

        $data['totalIncentives'] = $incentives->count();
        $pendingIncentives = $incentives->where('status', 'pending')->count();
        $approvedIncentives = $incentives->where('status', 'approved')->count();
        
        $data['pendingIncentives'] = $pendingIncentives;
        $data['approvedIncentives'] = $approvedIncentives;
        
        $info[] = "Total incentives: {$incentives->count()} (Pending: {$pendingIncentives}, Approved: {$approvedIncentives})";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    /**
     * Stage 11: Incentive Approval
     */
    protected function validateIncentiveApprovalStage(User $user): array
    {
        $errors = [];
        $warnings = [];
        $info = [];
        $data = [];

        // Check if user is finance manager
        if (!$user->isFinanceManager()) {
            $errors[] = "User must be Finance Manager to approve incentives";
        }

        // Check for pending incentives
        $pendingIncentives = Incentive::where('status', 'pending')
            ->with(['user', 'siteVisit'])
            ->get();

        if ($pendingIncentives->isEmpty()) {
            $warnings[] = "No incentives pending approval";
        } else {
            $data['pendingIncentives'] = $pendingIncentives->count();
            $info[] = "Found {$pendingIncentives->count()} incentives pending approval";
        }

        // Check approved incentives
        $approvedIncentives = Incentive::where('status', 'approved')
            ->get();

        $data['approvedIncentives'] = $approvedIncentives->count();
        $info[] = "Total approved incentives: {$approvedIncentives->count()}";

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
            'data' => $data
        ];
    }

    // ========== Data Getter Methods ==========

    protected function getTelecallerLeadData(User $user): array
    {
        $assignments = LeadAssignment::where('assigned_to', $user->id)
            ->where('is_active', true)
            ->with('lead')
            ->limit(10)
            ->get();

        return [
            'assignments' => $assignments->map(function($assignment) {
                return [
                    'id' => $assignment->id,
                    'lead_id' => $assignment->lead_id,
                    'lead_name' => $assignment->lead->name ?? 'N/A',
                    'lead_phone' => $assignment->lead->phone ?? 'N/A',
                    'status' => $assignment->call_status ?? 'pending',
                ];
            })
        ];
    }

    protected function getProspectData(User $user): array
    {
        $prospects = Prospect::where('created_by', $user->id)
            ->with(['lead', 'assignedManager'])
            ->limit(10)
            ->get();

        return [
            'prospects' => $prospects->map(function($prospect) {
                return [
                    'id' => $prospect->id,
                    'customer_name' => $prospect->customer_name,
                    'phone' => $prospect->phone,
                    'verification_status' => $prospect->verification_status,
                    'assigned_manager' => $prospect->assignedManager->name ?? 'N/A',
                ];
            })
        ];
    }

    protected function getManagerVerificationData(User $user): array
    {
        $tasks = Task::where('assigned_to', $user->id)
            ->where('type', 'phone_call')
            ->whereIn('status', ['pending', 'in_progress'])
            ->with('prospect')
            ->limit(10)
            ->get();

        return [
            'tasks' => $tasks->map(function($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'prospect_name' => $task->prospect->customer_name ?? 'N/A',
                ];
            })
        ];
    }
}
