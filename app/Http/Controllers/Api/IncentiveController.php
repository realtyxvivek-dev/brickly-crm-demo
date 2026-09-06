<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incentive;
use App\Models\SiteVisit;
use App\Models\Target;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IncentiveController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Request incentive after closing is verified by CRM
     * HR, Sales Executive, and Manager can request incentives for closed leads
     */
    public function requestIncentive(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        // Check if closer is approved by CRM/Admin
        if ($siteVisit->closer_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Closer must be approved before requesting incentive.',
            ], 422);
        }

        // Keep compatibility with older verified-closing state
        if ($siteVisit->closing_verification_status && $siteVisit->closing_verification_status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Closing must be approved before requesting incentive.',
            ], 422);
        }

        if (!$siteVisit->hasCompleteKyc()) {
            return response()->json([
                'success' => false,
                'message' => 'KYC must be submitted before requesting incentive.',
            ], 422);
        }

        // Check if user has access to this lead
        $hasAccess = false;
        if ($siteVisit->lead) {
            $lead = $siteVisit->lead;
            
            // Check if user is assigned to this lead
            $assignment = \App\Models\LeadAssignment::where('lead_id', $lead->id)
                ->where('assigned_to', $user->id)
                ->where('is_active', true)
                ->first();
            
            if ($assignment) {
                $hasAccess = true;
            }
            
            // Check if user is HR Manager, Sales Executive, or Manager
            if ($user->isAdmin() || $user->isCrm() || $user->isHrManager() || $user->isSalesExecutive() || $user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager()) {
                // Check if lead was created by user or assigned to user
                if ($lead->created_by === $user->id || $assignment) {
                    $hasAccess = true;
                }
            }

            if (!$hasAccess && $siteVisit->assignedTo && $user->isSeniorOf($siteVisit->assignedTo)) {
                $hasAccess = true;
            }
        }

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to request incentive for this lead.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:closer,site_visit',
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if incentive already exists for this user and site visit
        $existingIncentive = Incentive::where('site_visit_id', $siteVisit->id)
            ->where('user_id', $user->id)
            ->where('type', $request->input('type'))
            ->first();

        $isResubmission = $existingIncentive && $existingIncentive->status === 'rejected';

        if ($existingIncentive && !$isResubmission) {
            return response()->json([
                'success' => false,
                'message' => 'Incentive request already exists for this site visit.',
            ], 422);
        }

        try {
            if ($isResubmission) {
                $existingIncentive->fill([
                    'amount' => $request->input('amount'),
                    'status' => 'pending_finance_manager',
                    'rejected_by' => null,
                    'rejection_reason' => null,
                    'sales_head_verified_by' => null,
                    'sales_head_verified_at' => null,
                    'crm_verified_by' => null,
                    'crm_verified_at' => null,
                    'finance_manager_verified_by' => null,
                    'finance_manager_verified_at' => null,
                ]);
                $existingIncentive->save();
                $incentive = $existingIncentive->fresh(['user', 'siteVisit', 'siteVisit.lead']);
            } else {
                $incentive = Incentive::create([
                    'site_visit_id' => $siteVisit->id,
                    'user_id' => $user->id,
                    'type' => $request->input('type'),
                    'amount' => $request->input('amount'),
                    'status' => 'pending_finance_manager',
                ]);
            }

            // Send notification to Finance Manager
            try {
                $this->notificationService->notifyIncentiveRequestPending($incentive);
            } catch (\Exception $e) {
                Log::warning('Error sending incentive request notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => $isResubmission
                    ? 'Incentive request resubmitted. Awaiting Finance Manager approval.'
                    : 'Incentive request submitted. Awaiting Finance Manager approval.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'siteVisit.lead']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting incentive: ' . $e->getMessage(), [
                'site_visit_id' => $siteVisit->id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to request incentive.',
            ], 500);
        }
    }

    /**
     * Sales Head verifies incentive (DEPRECATED - kept for backward compatibility)
     */
    public function verifyBySalesHead(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isSalesHead()) {
            return response()->json(['message' => 'Forbidden. Only Sales Head can verify incentives.'], 403);
        }

        if ($incentive->status !== 'pending_sales_head') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending Sales Head verification.',
            ], 422);
        }

        try {
            $incentive->status = 'pending_crm';
            $incentive->sales_head_verified_by = $user->id;
            $incentive->sales_head_verified_at = now();
            $incentive->save();

            return response()->json([
                'success' => true,
                'message' => 'Incentive verified by Sales Head. Awaiting CRM verification.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'salesHeadVerifiedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying incentive by Sales Head: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify incentive.',
            ], 500);
        }
    }

    /**
     * Sales Head rejects incentive
     */
    public function rejectBySalesHead(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isSalesHead()) {
            return response()->json(['message' => 'Forbidden. Only Sales Head can reject incentives.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($incentive->status !== 'pending_sales_head') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending Sales Head verification.',
            ], 422);
        }

        try {
            $incentive->status = 'rejected';
            $incentive->rejected_by = $user->id;
            $incentive->rejection_reason = $request->input('reason');
            $incentive->save();

            return response()->json([
                'success' => true,
                'message' => 'Incentive rejected by Sales Head.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'rejectedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting incentive by Sales Head: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject incentive.',
            ], 500);
        }
    }

    /**
     * Finance Manager verifies incentive (approves incentive request)
     */
    public function verifyByFinanceManager(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isFinanceManager()) {
            return response()->json(['message' => 'Forbidden. Only Finance Manager can verify incentives.'], 403);
        }

        if ($incentive->status !== 'pending_finance_manager') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending Finance Manager verification.',
            ], 422);
        }

        try {
            $incentive->status = 'verified';
            $incentive->finance_manager_verified_by = $user->id;
            $incentive->finance_manager_verified_at = now();
            $incentive->save();

            // If this is a closer incentive, complete the closer target
            if ($incentive->type === 'closer') {
                $this->completeCloserTarget($incentive);
            }

            // Send notification to user who requested incentive
            try {
                $this->notificationService->notifyIncentiveApproved($incentive);
            } catch (\Exception $e) {
                Log::warning('Error sending incentive approval notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Incentive approved by Finance Manager. ' . ($incentive->type === 'closer' ? 'Closer target completed.' : ''),
                'data' => $incentive->fresh(['user', 'siteVisit', 'financeManagerVerifiedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying incentive by Finance Manager: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify incentive.',
            ], 500);
        }
    }

    /**
     * Finance Manager rejects incentive
     */
    public function rejectByFinanceManager(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isFinanceManager()) {
            return response()->json(['message' => 'Forbidden. Only Finance Manager can reject incentives.'], 403);
        }

        $payload = $request->all();
        $payload['reason'] = trim((string) ($payload['reason'] ?? $payload['rejection_reason'] ?? ''));

        $validator = Validator::make($payload, [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($incentive->status !== 'pending_finance_manager') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending Finance Manager verification.',
            ], 422);
        }

        try {
            $incentive->status = 'rejected';
            $incentive->rejected_by = $user->id;
            $incentive->rejection_reason = $payload['reason'];
            $incentive->save();

            // Send notification to user who requested incentive
            try {
                $this->notificationService->notifyIncentiveRejected($incentive, $payload['reason']);
            } catch (\Exception $e) {
                Log::warning('Error sending incentive rejection notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Incentive rejected by Finance Manager.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'rejectedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting incentive by Finance Manager: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject incentive.',
            ], 500);
        }
    }

    /**
     * CRM/Admin verifies incentive
     */
    public function verifyByCrm(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isCrm() && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Only CRM or Admin can verify incentives.'], 403);
        }

        if ($incentive->status !== 'pending_finance_manager') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending verification.',
            ], 422);
        }

        try {
            $incentive->status = 'verified';
            $incentive->crm_verified_by = $user->id;
            $incentive->crm_verified_at = now();
            if ($user->isAdmin()) {
                $incentive->finance_manager_verified_by = $user->id;
                $incentive->finance_manager_verified_at = now();
            }
            $incentive->save();

            if ($incentive->type === 'closer') {
                $this->completeCloserTarget($incentive);
            }

            try {
                $this->notificationService->notifyIncentiveApproved($incentive);
            } catch (\Exception $e) {
                Log::warning('Error sending incentive approval notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Incentive verified successfully.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'crmVerifiedBy', 'financeManagerVerifiedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error verifying incentive by CRM/Admin: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify incentive.',
            ], 500);
        }
    }

    /**
     * CRM/Admin rejects incentive
     */
    public function rejectByCrm(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        if (!$user->isCrm() && !$user->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Only CRM or Admin can reject incentives.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($incentive->status !== 'pending_finance_manager') {
            return response()->json([
                'success' => false,
                'message' => 'Incentive is not pending verification.',
            ], 422);
        }

        try {
            $incentive->status = 'rejected';
            $incentive->rejected_by = $user->id;
            $incentive->rejection_reason = $request->input('reason');
            $incentive->save();

            try {
                $this->notificationService->notifyIncentiveRejected($incentive, $request->input('reason'));
            } catch (\Exception $e) {
                Log::warning('Error sending incentive rejection notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Incentive rejected successfully.',
                'data' => $incentive->fresh(['user', 'siteVisit', 'rejectedBy']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting incentive by CRM/Admin: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject incentive.',
            ], 500);
        }
    }

    /**
     * Complete closer target for the manager after both verifications
     */
    private function completeCloserTarget(Incentive $incentive)
    {
        try {
            $user = $incentive->user;
            $currentMonth = Carbon::now()->startOfMonth();

            // Keep the approved closer state intact after finance approval.
            $siteVisit = $incentive->siteVisit;
            if ($siteVisit) {
                if (empty($siteVisit->closer_verified_by) && $incentive->finance_manager_verified_by) {
                    $siteVisit->closer_verified_by = $incentive->finance_manager_verified_by;
                }
                if (empty($siteVisit->closer_verified_at)) {
                    $siteVisit->closer_verified_at = now();
                }
                $siteVisit->save();
            }

            // Note: Closer target completion is automatically calculated by Target model's getClosersCount() method
            // which counts site visits with closer_status = 'verified' and closer_verified_at in the target month
        } catch (\Exception $e) {
            Log::error('Error completing closer target: ' . $e->getMessage(), [
                'incentive_id' => $incentive->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Get incentives for a user (Manager/Sales Executive for closer, Telecaller for site visit)
     * Finance Manager can see all incentives
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $type = $request->input('type'); // 'closer' or 'site_visit'
        $status = $request->input('status'); // Filter by status

        // Finance Manager can see all incentives
        if ($user->isFinanceManager()) {
            $query = Incentive::with(['siteVisit.lead', 'user', 'salesHeadVerifiedBy', 'crmVerifiedBy', 'financeManagerVerifiedBy', 'rejectedBy']);
        } else {
            // Other users see only their own incentives
            $query = Incentive::where('user_id', $user->id)
                ->with(['siteVisit.lead', 'salesHeadVerifiedBy', 'crmVerifiedBy', 'financeManagerVerifiedBy', 'rejectedBy']);
        }

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $incentives = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $incentives,
        ]);
    }

    /**
     * Get single incentive details
     */
    public function show(Request $request, Incentive $incentive)
    {
        $user = $request->user();

        // Check access - user can only view their own incentives unless they are Sales Head/CRM/Finance Manager
        if (!$user->isSalesHead() && !$user->isAdmin() && !$user->isCrm() && !$user->isFinanceManager()) {
            if ($incentive->user_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $incentive->load(['siteVisit.lead', 'user', 'salesHeadVerifiedBy', 'crmVerifiedBy', 'financeManagerVerifiedBy', 'rejectedBy']);

        return response()->json([
            'success' => true,
            'data' => $incentive,
        ]);
    }
}
