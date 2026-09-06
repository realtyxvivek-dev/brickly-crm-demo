<?php

namespace App\Services;

use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserDeletionTransferService
{
    public function __construct(
        protected LeadAssignmentService $leadAssignmentService
    ) {
    }

    public function getActiveLeadIds(User $user): array
    {
        return LeadAssignment::query()
            ->activeWithLiveLead()
            ->where('assigned_to', $user->id)
            ->pluck('lead_id')
            ->unique()
            ->values()
            ->all();
    }

    public function getEligibleReplacementUsers(User $user): Collection
    {
        $eligibleRoleIds = Role::whereIn('slug', [
            Role::SALES_EXECUTIVE,
            Role::SALES_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
        ])->pluck('id');

        return User::with('role')
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->whereIn('role_id', $eligibleRoleIds)
            ->orderBy('name')
            ->get();
    }

    public function getTransferPreview(User $user): array
    {
        $leadIds = $this->getActiveLeadIds($user);

        return [
            'active_lead_ids' => $leadIds,
            'active_lead_count' => count($leadIds),
            'open_task_count' => Task::where('assigned_to', $user->id)
                ->whereIn('lead_id', $leadIds)
                ->where('status', '!=', 'completed')
                ->count(),
            'open_telecaller_task_count' => TelecallerTask::where('assigned_to', $user->id)
                ->whereIn('lead_id', $leadIds)
                ->where('status', '!=', 'completed')
                ->count(),
        ];
    }

    public function transferAndDelete(User $user, User $replacementUser, int $performedBy): array
    {
        $leadIds = $this->getActiveLeadIds($user);

        return DB::transaction(function () use ($user, $replacementUser, $performedBy, $leadIds) {
            $transferResults = $this->leadAssignmentService->transferAssignedLeads(
                $leadIds,
                $replacementUser->id,
                $performedBy
            );

            if ($transferResults['failed'] > 0) {
                throw new \RuntimeException($transferResults['errors'][0] ?? 'Lead transfer failed.');
            }

            Task::where('assigned_to', $user->id)
                ->whereIn('lead_id', $leadIds)
                ->where('status', '!=', 'completed')
                ->update(['assigned_to' => $replacementUser->id]);

            TelecallerTask::where('assigned_to', $user->id)
                ->whereIn('lead_id', $leadIds)
                ->where('status', '!=', 'completed')
                ->update(['assigned_to' => $replacementUser->id]);

            $user->delete();

            return [
                'transferred_leads' => $transferResults['success'],
                'open_task_count' => Task::where('assigned_to', $replacementUser->id)
                    ->whereIn('lead_id', $leadIds)
                    ->where('status', '!=', 'completed')
                    ->count(),
                'open_telecaller_task_count' => TelecallerTask::where('assigned_to', $replacementUser->id)
                    ->whereIn('lead_id', $leadIds)
                    ->where('status', '!=', 'completed')
                    ->count(),
            ];
        });
    }
}
