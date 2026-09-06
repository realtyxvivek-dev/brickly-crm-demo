<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function getPending(Request $request)
    {
        $user = $request->user();
        
        // Optimize: Select only needed columns and eager load relationships
        $relations = [
            'createdBy:id,name',
            'assignedManager:id,name',
            'telecaller:id,name',
            'manager:id,name',
            'verifiedBy:id,name'
        ];
        
        // Verification flow removed: CRM/Admin only need active verified prospects.
        if ($user->isCrm() || $user->isAdmin()) {
            $query = Prospect::with($relations)
                ->select([
                    'id', 'customer_name', 'phone', 'budget', 'preferred_location', 'size',
                    'purpose', 'possession', 'remark', 'employee_remark', 'manager_remark', 'lead_status',
                    'lead_score', 'rejection_reason', 'notes', 'verification_status', 'verified_at', 'verified_by',
                    'telecaller_id', 'manager_id', 'assigned_manager', 'created_by', 'lead_id',
                    'created_at', 'updated_at'
                ])
                ->whereIn('verification_status', ['verified', 'approved']);
        } 
        // For Managers: Show only their team's pending prospects
        elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id');
            if ($teamMemberIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                ]);
            }
            $query = Prospect::with($relations)
                ->select([
                    'id', 'customer_name', 'phone', 'budget', 'preferred_location', 'size',
                    'purpose', 'possession', 'remark', 'employee_remark', 'manager_remark', 'lead_status',
                    'lead_score', 'rejection_reason', 'notes', 'verification_status', 'verified_at', 'verified_by',
                    'telecaller_id', 'manager_id', 'assigned_manager', 'created_by', 'lead_id',
                    'created_at', 'updated_at'
                ])
                ->whereIn('telecaller_id', $teamMemberIds)
                ->whereIn('verification_status', ['verified', 'approved']);
        } 
        // For others: Show only their own prospects
        else {
            $query = Prospect::with($relations)
                ->select([
                    'id', 'customer_name', 'phone', 'budget', 'preferred_location', 'size',
                    'purpose', 'possession', 'remark', 'employee_remark', 'manager_remark', 'lead_status',
                    'lead_score', 'rejection_reason', 'notes', 'verification_status', 'verified_at', 'verified_by',
                    'telecaller_id', 'manager_id', 'assigned_manager', 'created_by', 'lead_id',
                    'created_at', 'updated_at'
                ])
                ->where('created_by', $user->id)
                ->whereIn('verification_status', ['verified', 'approved']);
        }

        // Filter by verification status if specified
        if ($request->has('verification_status') && $request->verification_status !== 'all') {
            $status = $request->verification_status;
            if (in_array($status, ['pending', 'pending_verification', 'rejected'], true)) {
                $status = 'verified';
            }

            if ($status === 'verified') {
                $query->whereIn('verification_status', ['verified', 'approved']);
            } else {
                $query->where('verification_status', $status);
            }
        }

        // Use pagination for better performance (default 50 per page, max 100)
        $perPage = min($request->get('per_page', 50), 100);
        $prospects = $query->latest('created_at')->paginate($perPage);

        return response()->json([
            'data' => $prospects->items(),
            'total' => $prospects->total(),
            'per_page' => $prospects->perPage(),
            'current_page' => $prospects->currentPage(),
            'last_page' => $prospects->lastPage(),
        ]);
    }

    /**
     * Verify a prospect - Only the manager of the telecaller can verify
     */
    public function verify(Request $request, Prospect $prospect)
    {
        $request->validate([
            'manager_remark' => 'nullable|string',
            'lead_status' => 'required|in:hot,warm,cold,junk',
        ]);

        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin cannot verify prospects.'], 403);
        }

        if (!$user->isSalesManager() && !$user->isAssistantSalesManager()) {
            return response()->json(['message' => 'Forbidden. Only the telecaller\'s manager (ASM or Sales Manager) can verify prospects.'], 403);
        }

        $telecaller = $prospect->telecaller;
        if (!$telecaller || $telecaller->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Forbidden. You can only verify prospects created by your team members.',
            ], 403);
        }

        // Check if prospect is already verified or approved
        if (in_array($prospect->verification_status, ['verified', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Prospect is already verified.'
            ], 422);
        }

        // Verify the prospect
        $managerRemark = $request->input('manager_remark');
        $leadStatus = $request->input('lead_status');
        $prospect->verify($user->id, $managerRemark, $leadStatus);

        return response()->json([
            'success' => true,
            'message' => 'Prospect verified successfully',
            'prospect' => $prospect->fresh(['createdBy', 'assignedManager', 'telecaller']),
        ]);
    }

    /**
     * Reject a prospect - Only the manager of the telecaller can reject
     */
    public function reject(Request $request, Prospect $prospect)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        $user = $request->user();

        if ($user->isAdmin()) {
            return response()->json(['message' => 'Forbidden. Admin cannot reject prospects.'], 403);
        }

        if (!$user->isSalesManager() && !$user->isAssistantSalesManager()) {
            return response()->json(['message' => 'Forbidden. Only the telecaller\'s manager (ASM or Sales Manager) can reject prospects.'], 403);
        }

        $telecaller = $prospect->telecaller;
        if (!$telecaller || $telecaller->manager_id !== $user->id) {
            return response()->json([
                'message' => 'Forbidden. You can only reject prospects created by your team members.',
            ], 403);
        }

        // Check if prospect is already verified or approved
        if (in_array($prospect->verification_status, ['verified', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot reject an already verified prospect.'
            ], 422);
        }

        // Verification flow removed; keep prospect verified.
        $prospect->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $user->id,
            'rejection_reason' => null,
            'manager_remark' => trim((string) $request->input('reason')) ?: $prospect->manager_remark,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Prospect marked as verified successfully',
            'prospect' => $prospect->fresh(['createdBy', 'assignedManager', 'telecaller']),
        ]);
    }
}
