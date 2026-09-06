<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CrmAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function transfer(Request $request)
    {
        $validated = $request->validate([
            'from_telecaller_id' => 'required|exists:users,id',
            'to_telecaller_id' => 'required|exists:users,id|different:from_telecaller_id',
            'transfer_not_interested' => 'boolean',
            'transfer_cnp' => 'boolean',
            'transfer_reason' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
        ]);

        $fromUserId = $validated['from_telecaller_id'];
        $toUserId = $validated['to_telecaller_id'];
        $reason = trim((string) $validated['transfer_reason']);
        $actor = $request->user();
        $fromUserName = User::query()->whereKey($fromUserId)->value('name') ?: "user #{$fromUserId}";
        $toUserName = User::query()->whereKey($toUserId)->value('name') ?: "user #{$toUserId}";

        $query = CrmAssignment::where('assigned_to', $fromUserId);

        $conditions = [];
        
        if ($validated['transfer_not_interested'] ?? false) {
            $conditions[] = "call_status = 'called_not_interested'";
        }
        
        if ($validated['transfer_cnp'] ?? false) {
            $conditions[] = "(call_status = 'pending' AND cnp_count > 0)";
        }

        if (empty($conditions)) {
            return response()->json(['message' => 'Please select at least one lead type to transfer'], 400);
        }

        $whereClause = '(' . implode(' OR ', $conditions) . ')';
        
        $transferred = DB::transaction(function () use ($query, $whereClause, $toUserId, $reason, $actor, $fromUserId, $fromUserName, $toUserName) {
            $assignments = (clone $query)->whereRaw($whereClause)->get();
            $count = 0;

            foreach ($assignments as $assignment) {
                $assignment->update([
                    'assigned_to' => $toUserId,
                    'assigned_by' => $actor?->id,
                    'notes' => trim((string) $assignment->notes) !== ''
                        ? trim((string) $assignment->notes) . "\nTransfer reason: " . $reason
                        : 'Transfer reason: ' . $reason,
                ]);
                if ($assignment->lead_id) {
                    ActivityLog::create([
                        'user_id' => $actor?->id,
                        'action' => 'lead_transferred',
                        'model_type' => 'Lead',
                        'model_id' => $assignment->lead_id,
                        'description' => "Lead transferred from {$fromUserName} to {$toUserName} by " . ($actor?->name ?? 'System') . ". Reason: {$reason}",
                        'old_values' => [
                            'assigned_to' => $fromUserId,
                            'from_user_name' => $fromUserName,
                        ],
                        'new_values' => [
                            'assigned_to' => $toUserId,
                            'to_user_name' => $toUserName,
                            'changed_by_name' => $actor?->name ?? 'System',
                            'transfer_reason' => $reason,
                            'crm_assignment_id' => $assignment->id,
                        ],
                    ]);
                }
                $count++;
            }

            return $count;
        });

        return response()->json([
            'message' => "Successfully transferred {$transferred} leads",
            'transferred_count' => $transferred,
        ]);
    }
}
