<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\SystemSettings;
use App\Models\User;
use App\Services\CallLogService;
use App\Events\CallLogCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CallLogController extends Controller
{
    protected $callLogService;

    public function __construct(CallLogService $callLogService)
    {
        $this->callLogService = $callLogService;
    }

    /**
     * Get call logs list
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = CallLog::with(['lead:id,name,phone', 'user:id,name', 'telecaller:id,name']);

        // Role-based filtering
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $query->forUser($user->id);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            $query->forTeam($teamMemberIds);
        }

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

        $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
        $callLogs = $query->orderBy('start_time', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $callLogs->items(),
            'pagination' => [
                'current_page' => $callLogs->currentPage(),
                'per_page' => $callLogs->perPage(),
                'total' => $callLogs->total(),
                'last_page' => $callLogs->lastPage(),
            ],
        ]);
    }

    /**
     * Store a new call log (from mobile app)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
            'phone_number' => 'required|string|max:20',
            'call_type' => 'required|in:incoming,outgoing',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
            'duration' => 'required|integer|min:0',
            'status' => 'nullable|in:completed,missed,rejected,busy',
            'call_outcome' => 'nullable|in:interested,not_interested,callback,no_answer,busy,other',
            'notes' => 'nullable|string',
            'task_id' => 'nullable|integer',
            'synced_from_mobile' => 'boolean',
        ]);

        $user = $request->user();
        $validated['lead_id'] = $validated['lead_id'] ?? $this->resolveLeadIdForPhone($validated['phone_number'], $user);

        if (!empty($validated['task_id']) && !DB::table('telecaller_tasks')->where('id', $validated['task_id'])->exists()) {
            unset($validated['task_id']);
        }

        // Set user_id
        $validated['user_id'] = $user->id;
        $validated['telecaller_id'] = $user->id; // For backward compatibility
        $validated['synced_from_mobile'] = $request->get('synced_from_mobile', true);
        $validated['status'] = $validated['status'] ?? 'completed';

        $callLog = $this->findDuplicateMobileCall($user->id, $validated);
        if ($callLog) {
            $updates = [];
            if (!empty($validated['lead_id']) && (int) $callLog->lead_id !== (int) $validated['lead_id']) {
                $updates['lead_id'] = $validated['lead_id'];
            }
            if (!empty($validated['task_id']) && (int) $callLog->task_id !== (int) $validated['task_id']) {
                $updates['task_id'] = $validated['task_id'];
            }
            if ($updates !== []) {
                $callLog->fill($updates)->save();
            }
        } else {
            $callLog = CallLog::create($validated);
        }

        $lead = Lead::find($validated['lead_id']);
        if ($lead && $lead->isFreshTransfer()) {
            $lead->acknowledgeFreshTransfer($user->id, 'call_logged', 'connected');
        }

        // Suggest next followup
        if ($callLog->call_outcome) {
            $suggestedDate = $this->callLogService->suggestNextFollowup($callLog);
            if ($suggestedDate) {
                $callLog->next_followup_date = $suggestedDate;
                $callLog->save();
            }
        }

        // Broadcast event for real-time updates
        event(new CallLogCreated($callLog));

        return response()->json([
            'success' => true,
            'message' => 'Call log saved successfully',
            'data' => $callLog->load(['lead:id,name,phone', 'user:id,name']),
        ], 201);
    }

    private function resolveLeadIdForPhone(string $phoneNumber, ?User $user = null): ?int
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber);
        $lastTen = substr($digits, -10);
        if ($lastTen === '') {
            return null;
        }

        if ($user) {
            $assignedLeadId = Lead::query()
                ->where(function ($query) use ($lastTen) {
                    $query->where('phone', 'like', '%' . $lastTen)
                        ->orWhere('phone', 'like', '%' . $lastTen . '%');
                })
                ->whereHas('activeAssignments', function ($query) use ($user) {
                    $query->where('assigned_to', $user->id)
                        ->where('is_active', true);
                })
                ->latest('updated_at')
                ->latest('id')
                ->value('id');

            if ($assignedLeadId) {
                return (int) $assignedLeadId;
            }
        }

        return Lead::query()
            ->where(function ($query) use ($lastTen) {
                $query->where('phone', 'like', '%' . $lastTen)
                    ->orWhere('phone', 'like', '%' . $lastTen . '%');
            })
            ->latest('id')
            ->value('id');
    }

    private function findDuplicateMobileCall(int $userId, array $data): ?CallLog
    {
        if (empty($data['synced_from_mobile'])) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $data['phone_number'] ?? '');
        $lastTen = substr($digits, -10);
        if ($lastTen === '') {
            return null;
        }

        $start = Carbon::parse($data['start_time']);

        return CallLog::query()
            ->where('user_id', $userId)
            ->where('synced_from_mobile', true)
            ->whereBetween('start_time', [
                $start->copy()->subSeconds(5),
                $start->copy()->addSeconds(5),
            ])
            ->where('duration', (int) ($data['duration'] ?? 0))
            ->where('phone_number', 'like', '%' . $lastTen)
            ->first();
    }

    public function uploadRecording(Request $request, $id)
    {
        $user = $request->user();
        $callLog = CallLog::findOrFail($id);

        if (!$this->userCanUploadRecording($user, $callLog)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$this->callRecordingEnabledForEmail((string) $user->email)) {
            return response()->json(['message' => 'Call recording is not enabled for this user.'], 403);
        }

        $maxSizeMb = max(1, (int) SystemSettings::get('mobile_call_recording_max_size_mb', '25'));
        $validated = $request->validate([
            'recording' => ['required', 'file', 'mimes:m4a,mp4,aac,mp3,wav', 'max:' . ($maxSizeMb * 1024)],
        ]);

        $file = $validated['recording'];
        if ($file->getSize() < 2048) {
            $callLog->notes = trim(($callLog->notes ? $callLog->notes . "\n" : '') . 'Recording unavailable from Android app.');
            $callLog->save();

            return response()->json([
                'success' => false,
                'message' => 'Recording file was too small to save.',
            ], 422);
        }

        $path = $file->store('call-recordings/' . now()->format('Y/m'), 'public');
        $callLog->recording_url = url(Storage::url($path));
        $callLog->notes = trim(($callLog->notes ? $callLog->notes . "\n" : '') . 'Recording uploaded from Android app.');
        $callLog->save();

        return response()->json([
            'success' => true,
            'message' => 'Recording uploaded successfully.',
            'recording_url' => $callLog->recording_url,
        ]);
    }

    private function userCanUploadRecording($user, CallLog $callLog): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->isAdmin() || $user->isCrm()) {
            return true;
        }

        return (int) $callLog->user_id === (int) $user->id
            || (int) $callLog->telecaller_id === (int) $user->id;
    }

    private function callRecordingEnabledForEmail(string $email): bool
    {
        $configured = (string) SystemSettings::get('mobile_call_recording_user_emails', 'test@gmail.com');
        $allowedEmails = collect(explode(',', $configured))
            ->map(fn ($item) => strtolower(trim($item)))
            ->filter()
            ->values();

        if ($allowedEmails->isEmpty()) {
            $allowedEmails = collect(['test@gmail.com']);
        }

        return $allowedEmails->contains(strtolower(trim($email)));
    }

    /**
     * Bulk sync call logs from mobile app
     */
    public function bulkSync(Request $request)
    {
        $validated = $request->validate([
            'call_logs' => 'required|array',
            'call_logs.*.lead_id' => 'required|exists:leads,id',
            'call_logs.*.phone_number' => 'required|string|max:20',
            'call_logs.*.call_type' => 'required|in:incoming,outgoing',
            'call_logs.*.start_time' => 'required|date',
            'call_logs.*.end_time' => 'nullable|date',
            'call_logs.*.duration' => 'required|integer|min:0',
            'call_logs.*.status' => 'nullable|in:completed,missed,rejected,busy',
            'call_logs.*.call_outcome' => 'nullable|in:interested,not_interested,callback,no_answer,busy,other',
            'call_logs.*.notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $synced = 0;
        $errors = [];

        foreach ($validated['call_logs'] as $index => $callData) {
            try {
                $callData['user_id'] = $user->id;
                $callData['telecaller_id'] = $user->id;
                $callData['synced_from_mobile'] = true;
                $callData['status'] = $callData['status'] ?? 'completed';

                $callLog = CallLog::create($callData);

                $lead = Lead::find($callData['lead_id']);
                if ($lead && $lead->isFreshTransfer()) {
                    $lead->acknowledgeFreshTransfer($user->id, 'call_logged', 'connected');
                }

                // Suggest next followup
                if ($callLog->call_outcome) {
                    $suggestedDate = $this->callLogService->suggestNextFollowup($callLog);
                    if ($suggestedDate) {
                        $callLog->next_followup_date = $suggestedDate;
                        $callLog->save();
                    }
                }

                $synced++;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced {$synced} call logs",
            'synced' => $synced,
            'total' => count($validated['call_logs']),
            'errors' => $errors,
        ]);
    }

    /**
     * Get single call log
     */
    public function show($id)
    {
        $user = request()->user();
        $callLog = CallLog::with(['lead', 'user', 'telecaller', 'task'])->findOrFail($id);

        // Check access
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            if ($callLog->user_id !== $user->id && $callLog->telecaller_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } elseif ($user->isSalesManager()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            if (!in_array($callLog->user_id ?? $callLog->telecaller_id, $teamMemberIds)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $callLog,
        ]);
    }

    /**
     * Update call log
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $callLog = CallLog::findOrFail($id);

        // Check access
        if (!$user->isAdmin() && !$user->isCrm()) {
            if ($callLog->user_id !== $user->id && $callLog->telecaller_id !== $user->id) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $validated = $request->validate([
            'call_outcome' => 'nullable|in:interested,not_interested,callback,no_answer,busy,other',
            'notes' => 'nullable|string',
            'next_followup_date' => 'nullable|date',
        ]);

        $callLog->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Call log updated successfully',
            'data' => $callLog->load(['lead:id,name,phone', 'user:id,name']),
        ]);
    }

    /**
     * Get call statistics
     */
    public function getStatistics(Request $request)
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $stats = $this->callLogService->getCallStatistics($user->id, $dateRange, $startDate, $endDate);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $stats = $this->callLogService->getTeamCallStatistics($user->id, $dateRange, $startDate, $endDate);
        } else {
            $stats = $this->callLogService->getSystemCallStatistics($dateRange, $startDate, $endDate);
        }

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get team statistics (for managers)
     */
    public function getTeamStatistics(Request $request)
    {
        $user = $request->user();

        if (!$user->isSalesManager() && !$user->isSalesHead() && !$user->isAdmin() && !$user->isAssistantSalesManager()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $dateRange = $request->get('date_range', 'today');
        $stats = $this->callLogService->getTeamCallStatistics(
            $user->id,
            $dateRange,
            $request->get('start_date'),
            $request->get('end_date')
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get dashboard quick stats
     */
    public function getDashboardStats(Request $request)
    {
        $user = $request->user();
        $dateRange = $request->get('date_range', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $stats = $this->callLogService->getCallStatistics($user->id, $dateRange, $startDate, $endDate);
            // Add calls per hour
            $stats['calls_per_hour'] = $this->callLogService->getCallsPerHour($user->id, Carbon::today());
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $stats = $this->callLogService->getTeamCallStatistics($user->id, $dateRange, $startDate, $endDate);
        } else {
            $stats = $this->callLogService->getSystemCallStatistics($dateRange, $startDate, $endDate);
        }

        // Get recent calls (last 5)
        $recentCallsQuery = CallLog::with(['lead:id,name,phone', 'user:id,name']);
        
        if ($user->isSalesExecutive() || $user->isAssistantSalesManager()) {
            $recentCallsQuery->forUser($user->id);
        } elseif ($user->isSalesManager() || $user->isSalesHead()) {
            $teamMemberIds = $user->getAllTeamMemberIds();
            $teamMemberIds[] = $user->id;
            $recentCallsQuery->forTeam($teamMemberIds);
        }

        $recentCalls = $recentCallsQuery->orderBy('start_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function($call) {
                return [
                    'id' => $call->id,
                    'phone_number' => $call->phone_number,
                    'lead_name' => $call->lead->name ?? 'N/A',
                    'user_name' => $call->callerUser->name ?? 'N/A',
                    'duration' => $call->formatted_duration,
                    'call_type' => $call->call_type_label,
                    'status' => $call->status_label,
                    'start_time' => $call->start_time->format('Y-m-d H:i:s'),
                ];
            });

        $stats['recent_calls'] = $recentCalls;

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
