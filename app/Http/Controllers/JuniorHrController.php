<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\View\View;

class JuniorHrController extends Controller
{
    public function dashboard(): View
    {
        $user = request()->user()->loadMissing('role');
        $baseQuery = $this->hiringCandidateQuery($user->id);
        $statusOptions = Lead::hiringStatusOptions();

        $counts = [];
        foreach ($statusOptions as $key => $label) {
            $counts[$key] = (clone $baseQuery)->where('hiring_status', $key)->count();
        }

        $summary = [
            'total' => array_sum($counts),
            'pending' => ($counts['new'] ?? 0) + ($counts['contacted'] ?? 0) + ($counts['not_reachable'] ?? 0),
            'interviews' => ($counts['interview_scheduled'] ?? 0) + ($counts['interview_done'] ?? 0),
            'selected' => $counts['selected'] ?? 0,
            'rejected' => ($counts['rejected'] ?? 0) + ($counts['not_interested'] ?? 0) + ($counts['wrong_number'] ?? 0) + ($counts['duplicate'] ?? 0),
        ];

        $recentCandidates = (clone $baseQuery)
            ->select(['id', 'name', 'phone', 'hiring_status', 'next_followup_at', 'updated_at'])
            ->latest('updated_at')
            ->limit(5)
            ->get();

        return view('junior-hr.dashboard', [
            'user' => $user,
            'summary' => $summary,
            'counts' => $counts,
            'statusOptions' => $statusOptions,
            'recentCandidates' => $recentCandidates,
        ]);
    }

    public function profile(): View
    {
        return view('junior-hr.profile', [
            'user' => request()->user()->loadMissing('role'),
        ]);
    }

    protected function hiringCandidateQuery(int $userId): Builder
    {
        return Lead::query()
            ->where('is_hiring_candidate', true)
            ->whereHas('activeAssignments', function (Builder $query) use ($userId) {
                $query->where('assigned_to', $userId)
                    ->where('is_active', true);
            });
    }
}
