<?php

namespace App\Http\Controllers\AdManager;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class LeadMonitorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isAdManager(), 403);

        $sourceOptions = collect(Lead::sourceOptions())
            ->only($user->allowedLeadSources())
            ->all();

        $query = Lead::query()
            ->with(['activeAssignments.assignedTo:id,name'])
            ->latest('created_at');

        if (empty($sourceOptions)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('source', array_keys($sourceOptions));
        }

        if ($request->filled('source')) {
            $source = Lead::normalizeSource((string) $request->input('source'));
            abort_unless($user->canAccessLeadSource($source), 403, 'You do not have access to this lead source.');
            $query->where('source', $source);
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $assignedTo = (int) $request->input('user_id');
            $query->whereHas('activeAssignments', fn ($assignmentQuery) => $assignmentQuery->where('assigned_to', $assignedTo));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        if ($request->filled('search')) {
            $query->searchText((string) $request->input('search'));
        }

        $leads = $query->paginate(20)->withQueryString();
        $statuses = Lead::query()
            ->when(!empty($sourceOptions), fn ($statusQuery) => $statusQuery->whereIn('source', array_keys($sourceOptions)))
            ->when(empty($sourceOptions), fn ($statusQuery) => $statusQuery->whereRaw('1 = 0'))
            ->whereNotNull('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values();
        $filterUsers = $this->filterUsers(array_keys($sourceOptions));

        return view('ad-manager.leads.index', compact('leads', 'sourceOptions', 'statuses', 'filterUsers'));
    }

    public function show(Request $request, Lead $lead)
    {
        $user = $request->user();
        abort_unless($user?->isAdManager(), 403);
        abort_unless($user->canAccessLeadSource($lead->source), 403, 'You do not have access to this lead source.');

        $lead->load([
            'creator:id,name',
            'activeAssignments.assignedTo:id,name',
            'latestFbLead.form:id,form_id,form_name',
            'formFieldValues',
            'tasks' => fn ($query) => $query->latest('id')->limit(10),
            'managerTasks' => fn ($query) => $query->latest('id')->limit(10),
            'siteVisits:id,lead_id,status,scheduled_at,created_at',
            'meetings:id,lead_id,status,scheduled_at,created_at',
        ]);

        return view('ad-manager.leads.show', compact('lead'));
    }

    private function filterUsers(array $sources)
    {
        if (empty($sources)) {
            return collect();
        }

        $userIds = Lead::query()
            ->whereIn('source', $sources)
            ->whereHas('activeAssignments')
            ->with('activeAssignments:id,lead_id,assigned_to,is_active')
            ->get()
            ->flatMap(fn (Lead $lead) => $lead->activeAssignments->pluck('assigned_to'))
            ->filter()
            ->unique()
            ->values();

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
