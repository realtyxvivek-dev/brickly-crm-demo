<?php

namespace App\Http\Controllers;

use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use App\Services\CallLogService;
use App\Events\CallLogCreated;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CallLogController extends Controller
{
    protected $callLogService;

    public function __construct(CallLogService $callLogService)
    {
        $this->middleware('auth');
        $this->callLogService = $callLogService;
    }

    /**
     * Display a listing of call logs
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $ivrSource = Lead::normalizeSource('ivr');
        $query = CallLog::with(['lead', 'user', 'telecaller'])
            ->whereHas('lead', function ($q) use ($ivrSource) {
                $q->where('source', $ivrSource);
            });

        // Role-based filtering
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            // Own calls only
            $query->forUser($user->id);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            // Team calls + own calls
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            $query->forTeam($teamMemberIds);
        }
        // Admin/CRM can see all calls (no filter)

        // Filters
        if ($request->has('from_date')) {
            $query->whereDate('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('start_time', '<=', $request->to_date);
        }
        if ($request->has('user_id')) {
            $query->forUser($request->user_id);
        }
        if ($request->has('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }
        if ($request->has('call_type')) {
            $query->where('call_type', $request->call_type);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('call_outcome')) {
            $query->where('call_outcome', $request->call_outcome);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Quick stats
        $quickStats = [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->today()->count(),
            'this_week' => (clone $query)->thisWeek()->count(),
            'this_month' => (clone $query)->thisMonth()->count(),
        ];

        $callLogs = $query->orderBy('start_time', 'desc')->paginate(50);

        // Get users for filter (if admin/manager)
        $users = [];
        if ($user->isAdmin() || $user->isCrm() || $user->isSalesManager()) {
            if ($user->isSalesManager()) {
                $teamMemberIds = $user->getAllTeamMemberIds();
                $teamMemberIds[] = $user->id;
                $users = User::whereIn('id', $teamMemberIds)->get();
            } else {
                $users = User::where('is_active', true)->get();
            }
        }

        return view('calls.index', compact('callLogs', 'quickStats', 'users'));
    }

    /**
     * Show the form for creating a new call log
     */
    public function create()
    {
        $user = auth()->user();
        
        // Get leads for dropdown
        $leadsQuery = Lead::query();
        
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            // Only assigned leads
            $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            })->pluck('id');
            $leadsQuery->whereIn('id', $leadIds);
        } elseif ($user->isSalesManager()) {
            // Team leads
            $teamMemberIds = $user->getAllTeamMemberIds();
            $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($teamMemberIds) {
                $q->whereIn('assigned_to', $teamMemberIds);
            })->pluck('id');
            $leadsQuery->whereIn('id', $leadIds);
        }
        // Admin/CRM can see all leads

        $leads = $leadsQuery->orderBy('name')->get();

        return view('calls.create', compact('leads'));
    }

    /**
     * Store a newly created call log
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'phone_number' => 'required|string|max:20',
            'call_type' => 'required|in:incoming,outgoing',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration' => 'nullable|integer|min:0',
            'status' => 'required|in:completed,missed,rejected,busy',
            'call_outcome' => 'nullable|in:interested,not_interested,callback,no_answer,busy,other',
            'notes' => 'nullable|string',
            'next_followup_date' => 'nullable|date',
        ]);

        $user = auth()->user();

        // Auto-calculate duration if end_time provided
        if ($request->end_time && !$request->duration) {
            $start = Carbon::parse($request->start_time);
            $end = Carbon::parse($request->end_time);
            $validated['duration'] = $end->diffInSeconds($start);
        }

        // Set user_id
        $validated['user_id'] = $user->id;
        $validated['telecaller_id'] = $user->id; // For backward compatibility

        $callLog = CallLog::create($validated);

        // Suggest next followup if not provided
        if (!$request->next_followup_date && $request->call_outcome) {
            $suggestedDate = $this->callLogService->suggestNextFollowup($callLog);
            if ($suggestedDate) {
                $callLog->next_followup_date = $suggestedDate;
                $callLog->save();
            }
        }

        // Broadcast event for real-time updates
        event(new CallLogCreated($callLog));

        return redirect()->route('calls.index')
            ->with('success', 'Call log created successfully.');
    }

    /**
     * Display the specified call log
     */
    public function show($id)
    {
        $user = auth()->user();
        $callLog = CallLog::with(['lead', 'user', 'telecaller', 'task'])->findOrFail($id);

        // Check access
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            if ($callLog->user_id !== $user->id && $callLog->telecaller_id !== $user->id) {
                abort(403, 'Unauthorized');
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            if (!in_array($callLog->user_id ?? $callLog->telecaller_id, $teamMemberIds)) {
                abort(403, 'Unauthorized');
            }
        }

        // Get previous calls to same lead
        $previousCalls = CallLog::where('lead_id', $callLog->lead_id)
            ->where('id', '!=', $callLog->id)
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get();

        // Get related tasks
        $relatedTasks = [];
        if ($callLog->lead_id) {
            $relatedTasks = \App\Models\TelecallerTask::where('lead_id', $callLog->lead_id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();
        }

        return view('calls.show', compact('callLog', 'previousCalls', 'relatedTasks'));
    }

    public function streamRecording(Request $request, CallLog $callLog): Response
    {
        $user = auth()->user();

        if (!$user || !$this->userCanAccessRecording($user, $callLog)) {
            abort(403, 'Unauthorized');
        }

        if (blank($callLog->recording_url)) {
            abort(404, 'Recording not available');
        }

        $remoteResponse = Http::timeout(45)->withOptions([
            'verify' => false,
        ])->get($callLog->recording_url);

        if (!$remoteResponse->successful()) {
            abort(404, 'Unable to fetch recording');
        }

        $contentType = $remoteResponse->header('Content-Type') ?: 'audio/mpeg';
        $extension = str_contains($contentType, 'wav') ? 'wav' : (str_contains($contentType, 'ogg') ? 'ogg' : 'mp3');
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';
        $filename = 'call-recording-' . $callLog->id . '.' . $extension;

        return response($remoteResponse->body(), 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Show the form for editing the specified call log
     */
    public function edit($id)
    {
        $user = auth()->user();
        $callLog = CallLog::with(['lead'])->findOrFail($id);

        // Check access - only own calls or admin
        if (!$user->isAdmin() && !$user->isCrm()) {
            if ($callLog->user_id !== $user->id && $callLog->telecaller_id !== $user->id) {
                abort(403, 'Unauthorized');
            }
        }

        // Get leads for dropdown
        $leadsQuery = Lead::query();
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $leadIds = Lead::whereHas('activeAssignments', function ($q) use ($user) {
                $q->where('assigned_to', $user->id);
            })->pluck('id');
            $leadsQuery->whereIn('id', $leadIds);
        }
        $leads = $leadsQuery->orderBy('name')->get();

        return view('calls.edit', compact('callLog', 'leads'));
    }

    /**
     * Update the specified call log
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $callLog = CallLog::findOrFail($id);

        // Check access
        if (!$user->isAdmin() && !$user->isCrm()) {
            if ($callLog->user_id !== $user->id && $callLog->telecaller_id !== $user->id) {
                abort(403, 'Unauthorized');
            }
        }

        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'phone_number' => 'required|string|max:20',
            'call_type' => 'required|in:incoming,outgoing',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration' => 'nullable|integer|min:0',
            'status' => 'required|in:completed,missed,rejected,busy',
            'call_outcome' => 'nullable|in:interested,not_interested,callback,no_answer,busy,other',
            'notes' => 'nullable|string',
            'next_followup_date' => 'nullable|date',
        ]);

        // Auto-calculate duration if end_time provided
        if ($request->end_time && !$request->duration) {
            $start = Carbon::parse($request->start_time);
            $end = Carbon::parse($request->end_time);
            $validated['duration'] = $end->diffInSeconds($start);
        }

        $callLog->update($validated);

        return redirect()->route('calls.show', $callLog->id)
            ->with('success', 'Call log updated successfully.');
    }

    /**
     * Remove the specified call log
     */
    public function destroy($id)
    {
        $user = auth()->user();
        $callLog = CallLog::findOrFail($id);

        // Only admin/CRM can delete
        if (!$user->isAdmin() && !$user->isCrm()) {
            abort(403, 'Unauthorized');
        }

        $callLog->delete();

        return redirect()->route('calls.index')
            ->with('success', 'Call log deleted successfully.');
    }

    /**
     * Get call statistics (AJAX endpoint)
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', 'today');

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $stats = $this->callLogService->getCallStatistics($user->id, $dateRange);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $stats = $this->callLogService->getTeamCallStatistics($user->id, $dateRange);
        } else {
            $stats = $this->callLogService->getSystemCallStatistics($dateRange);
        }

        return response()->json($stats);
    }

    /**
     * Show statistics page
     */
    public function statistics(Request $request)
    {
        $user = $request->user();
        
        // Get users for filter (if admin/manager)
        $users = [];
        if ($user->isAdmin() || $user->isCrm() || $user->isSalesManager()) {
            if ($user->isSalesManager()) {
                $teamMemberIds = $user->getAllTeamMemberIds();
                $teamMemberIds[] = $user->id;
                $users = User::whereIn('id', $teamMemberIds)->get();
            } else {
                $users = User::where('is_active', true)->get();
            }
        }

        return view('calls.statistics', compact('users'));
    }

    /**
     * Export call logs to CSV
     */
    public function exportCsv(Request $request)
    {
        $user = $request->user();
        $ivrSource = Lead::normalizeSource('ivr');
        $query = CallLog::with(['lead', 'user', 'telecaller'])
            ->whereHas('lead', function ($q) use ($ivrSource) {
                $q->where('source', $ivrSource);
            });

        // Apply same filters as index
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->forUser($user->id);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            $query->forTeam($teamMemberIds);
        }

        // Apply filters
        if ($request->has('from_date')) {
            $query->whereDate('start_time', '>=', $request->from_date);
        }
        if ($request->has('to_date')) {
            $query->whereDate('start_time', '<=', $request->to_date);
        }

        $callLogs = $query->orderBy('start_time', 'desc')->get();

        $filename = 'call_logs_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($callLogs) {
            $file = fopen('php://output', 'w');
            
            // Headers
            fputcsv($file, [
                'Phone Number',
                'Lead Name',
                'User',
                'Date & Time',
                'Duration',
                'Call Type',
                'Status',
                'Outcome',
                'Notes'
            ]);

            // Data
            foreach ($callLogs as $callLog) {
                fputcsv($file, [
                    $callLog->phone_number,
                    $callLog->lead->name ?? 'N/A',
                    $callLog->callerUser->name ?? 'N/A',
                    $callLog->start_time->format('Y-m-d H:i:s'),
                    $callLog->formatted_duration,
                    $callLog->call_type_label,
                    $callLog->status_label,
                    $callLog->call_outcome_label,
                    strip_tags($callLog->notes ?? '')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function userCanAccessRecording(User $user, CallLog $callLog): bool
    {
        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        $lead = $callLog->lead;
        if (!$lead) {
            return false;
        }

        return $lead->activeAssignments()
            ->where('assigned_to', $user->id)
            ->exists();
    }
}
