<?php

namespace App\Services;

use App\Models\LeadAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeadsPendingResponseService
{
    /**
     * Get date range from request (same as CRM dashboard).
     */
    public function getDateRange(string $dateRange, ?Request $request = null): array
    {
        $today = Carbon::today();

        switch ($dateRange) {
            case 'today':
                return [$today->copy()->startOfDay(), $today->copy()->endOfDay()];
            case 'yesterday':
                $yesterday = $today->copy()->subDay();
                return [$yesterday->startOfDay(), $yesterday->endOfDay()];
            case 'this_week':
                return [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()];
            case 'this_month':
                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()];
            case 'this_year':
                return [$today->copy()->startOfYear(), $today->copy()->endOfYear()];
            case 'custom':
                if ($request && $request->has('start_date') && $request->has('end_date')) {
                    $start = Carbon::parse($request->get('start_date'))->startOfDay();
                    $end = Carbon::parse($request->get('end_date'))->endOfDay();
                    return [$start, $end];
                }
                return [null, null];
            case 'till_date':
            case 'all_time':
            default:
                return [null, null];
        }
    }

    /**
     * Get lead IDs where the user has already responded (completed task or CrmAssignment with outcome).
     */
    public function getLeadIdsWithResponse(int $userId): Collection
    {
        return DB::table('telecaller_tasks')
            ->where('assigned_to', $userId)
            ->where('status', 'completed')
            ->distinct()
            ->pluck('lead_id')
            ->merge(
                DB::table('crm_assignments')
                    ->where('assigned_to', $userId)
                    ->where(function ($q) {
                        $q->where('cnp_count', '>', 0)
                            ->orWhere('call_status', '!=', 'pending');
                    })
                    ->distinct()
                    ->pluck('lead_id')
            )
            ->unique()
            ->values();
    }

    /**
     * Get pending response data for a single user (count + leads list).
     * Same logic as CRM getLeadsPendingResponse but for one user.
     *
     * @return array{pending_count: int, leads: array, server_now: string}
     */
    public function getForUser(int $userId, Request $request): array
    {
        $dateRange = $request->get('date_range', 'this_month');
        [$startDate, $endDate] = $this->getDateRange($dateRange, $request);

        $leadIdsWithCalls = $this->getLeadIdsWithResponse($userId);

        $assignmentsQuery = LeadAssignment::query()
            ->activeWithLiveLead()
            ->where('assigned_to', $userId)
            ->with('lead:id,name,phone');

        if ($leadIdsWithCalls->isNotEmpty()) {
            $assignmentsQuery->whereNotIn('lead_id', $leadIdsWithCalls->toArray());
        }
        if ($startDate && $endDate) {
            $assignmentsQuery->whereBetween('assigned_at', [$startDate, $endDate]);
        }

        $assignments = $assignmentsQuery->orderBy('assigned_at', 'desc')->get();

        $leads = [];
        foreach ($assignments as $a) {
            $lead = $a->lead;
            if (!$lead) {
                continue;
            }
            $leads[] = [
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'assigned_at' => $a->assigned_at?->toIso8601String(),
            ];
        }

        return [
            'pending_count' => count($leads),
            'leads' => $leads,
            'server_now' => now()->toIso8601String(),
        ];
    }

    /**
     * Get only the count of leads pending response for a user (for dashboard card).
     */
    public function getCountForUser(int $userId, string $dateRange = 'this_month', ?Request $request = null): int
    {
        [$startDate, $endDate] = $this->getDateRange($dateRange, $request ?? new Request());

        $leadIdsWithCalls = $this->getLeadIdsWithResponse($userId);

        $query = LeadAssignment::query()
            ->activeWithLiveLead()
            ->where('assigned_to', $userId);

        if ($leadIdsWithCalls->isNotEmpty()) {
            $query->whereNotIn('lead_id', $leadIdsWithCalls->toArray());
        }
        if ($startDate && $endDate) {
            $query->whereBetween('assigned_at', [$startDate, $endDate]);
        }

        return $query->count();
    }
}
