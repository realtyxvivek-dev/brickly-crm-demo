<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\SiteVisit;
use App\Models\Lead;
use App\Models\Prospect;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Services\TelecallerTaskService;
use App\Services\AsmCnpAutomationService;
use App\Services\NotificationService;
use App\Services\MeetingService;
use App\Services\MetaReviewAutoStageService;
use App\Services\VerificationRoutingService;
use App\Models\User;
use App\Models\Role;
use App\Events\SiteVisitCreated;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MeetingController extends Controller
{
    protected $notificationService;
    protected $meetingService;
    protected $asmCnpAutomationService;

    public function __construct(NotificationService $notificationService, MeetingService $meetingService, AsmCnpAutomationService $asmCnpAutomationService)
    {
        $this->notificationService = $notificationService;
        $this->meetingService = $meetingService;
        $this->asmCnpAutomationService = $asmCnpAutomationService;
    }
    /**
     * List all meetings (accessible by Admin, CRM, Sales Head, Senior Manager)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Ensure role is loaded
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }
        
        $query = Meeting::with(['lead', 'prospect', 'creator', 'assignedTo', 'verifiedBy'])
            ->where('is_converted', false); // Exclude converted meetings

        // Role-based filtering
        if ($user->isAdmin() || $user->isCrm()) {
            // Admin and CRM can see all meetings
            // No additional filtering needed
        } elseif ($user->isSalesHead()) {
            // Sales Head can see all meetings from their entire team hierarchy
            $allTeamMemberIds = $user->getAllTeamMemberIds();
            if (!empty($allTeamMemberIds)) {
                $query->where(function($q) use ($allTeamMemberIds, $user) {
                    $q->whereIn('created_by', $allTeamMemberIds)
                      ->orWhere('created_by', $user->id)
                      ->orWhereIn('assigned_to', $allTeamMemberIds)
                      ->orWhere('assigned_to', $user->id);
                });
            } else {
                // No team members, show only their own
                $query->where('created_by', $user->id);
            }
            $query->where('is_dead', false);
        } elseif ($user->isSalesManager() || $user->isSeniorManager()) {
            // Sales Manager / Senior Manager see their own meetings and team meetings (excluding dead)
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->where(function($q) use ($teamMemberIds, $user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
                if ($teamMemberIds->isNotEmpty()) {
                    $q->orWhereIn('created_by', $teamMemberIds)
                      ->orWhereIn('assigned_to', $teamMemberIds);
                }
            })->where('is_dead', false);
        } elseif ($user->isAssistantSalesManager()) {
            // Assistant Sales Manager (ASM) sees their own meetings and their team's meetings (e.g. Sales Executives under them)
            $teamMemberIds = $user->teamMembers()->pluck('id');
            $query->where(function($q) use ($teamMemberIds, $user) {
                $q->where('created_by', $user->id)
                  ->orWhere('assigned_to', $user->id);
                if ($teamMemberIds->isNotEmpty()) {
                    $q->orWhereIn('created_by', $teamMemberIds)
                      ->orWhereIn('assigned_to', $teamMemberIds);
                }
            })->where('is_dead', false);
        } else {
            // Other roles - return empty
            return response()->json([
                'data' => [],
                'current_page' => 1,
                'per_page' => 15,
                'total' => 0,
                'last_page' => 1
            ]);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } elseif ($request->input('activity_state') === 'pending') {
            $query->where('status', '!=', 'completed')
                ->whereNull('completed_at');
        } elseif ($request->boolean('active_queue')) {
            $query->where(function ($activeQueueQuery) {
                $activeQueueQuery->where(function ($openQuery) {
                    $openQuery->where('status', 'scheduled')
                        ->whereNull('completed_at');
                })->orWhere(function ($completedQuery) {
                    $completedQuery->where('status', 'completed')
                        ->whereNotNull('completed_at');
                });
            });
        }

        // Filter by verification status
        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        // Filter by lead_id
        if ($request->filled('lead_id')) {
            $query->where('lead_id', $request->lead_id);
        }

        // Filter by prospect_id
        if ($request->filled('prospect_id')) {
            $query->where('prospect_id', $request->prospect_id);
        }

        // Search filter
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('property_type', 'like', "%{$search}%")
                  ->orWhereHas('lead', function($leadQuery) use ($search) {
                      $leadQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // Date filter
        if ($request->filled('date_filter')) {
            $dateFilter = $request->date_filter;
            $today = now()->startOfDay();
            
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('scheduled_at', $today);
                    break;
                case 'this_week':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfWeek(),
                        $today->copy()->endOfWeek()
                    ]);
                    break;
                case 'this_month':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfMonth(),
                        $today->copy()->endOfMonth()
                    ]);
                    break;
                case 'this_year':
                    $query->whereBetween('scheduled_at', [
                        $today->copy()->startOfYear(),
                        $today->copy()->endOfYear()
                    ]);
                    break;
                case 'custom':
                    if ($request->has('date_from') && $request->has('date_to')) {
                        $query->whereBetween('scheduled_at', [
                            $request->date_from . ' 00:00:00',
                            $request->date_to . ' 23:59:59'
                        ]);
                    }
                    break;
            }
        }

        $perPage = min(100, max(1, (int) $request->get('per_page', 15)));
        $meetings = $query->latest('scheduled_at')->paginate($perPage);

        // Add pending_verification_with (verifier names/level) for completed meetings awaiting verification
        $meetings->getCollection()->transform(function ($meeting) {
            $arr = $meeting->toArray();
            if ($meeting->verification_status === 'pending' && $meeting->status === 'completed') {
                $arr['pending_verification_with'] = $this->getPendingVerificationWith($meeting);
            } else {
                $arr['pending_verification_with'] = null;
            }
            return $arr;
        });

        return response()->json($meetings);
    }

    /**
     * Get who can verify this meeting (names with role) when status is pending verification.
     * Seniors of creator, or CRM when creator has no senior. Admin is not included.
     */
    private function getPendingVerificationWith(Meeting $meeting): string
    {
        $creator = $meeting->creator;
        $users = collect();

        if ($creator) {
            if ($creator->manager_id === null) {
                $crmUsers = User::whereHas('role', fn ($q) => $q->where('slug', Role::CRM))
                    ->where('is_active', true)->with('role')->get();
                $users = $users->merge($crmUsers);
            } else {
                $current = $creator->manager_id;
                $seen = [];
                while ($current && !isset($seen[$current])) {
                    $seen[$current] = true;
                    $manager = User::with('role')->find($current);
                    if ($manager && $manager->is_active) {
                        $users->push($manager);
                    }
                    $current = $manager ? $manager->manager_id : null;
                }
            }
        }

        $parts = $users->unique('id')->take(5)->map(function ($u) {
            $roleName = $u->getDisplayRoleName();
            return $u->name . ' (' . $roleName . ')';
        })->values()->all();

        return $parts ? implode(', ', $parts) : 'Senior or CRM';
    }

    /**
     * Create a new meeting
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'lead_id' => 'nullable|exists:leads,id',
            'prospect_id' => 'nullable|exists:prospects,id',
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:16',
            'employee' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'date_of_visit' => 'required|date',
            'project' => 'nullable|string|max:255',
            'budget_range' => 'required|in:Under 50 Lac,50 Lac – 1 Cr,1 Cr – 2 Cr,2 Cr – 3 Cr,Above 3 Cr',
            'team_leader' => 'nullable|string|max:255',
            'property_type' => 'required|in:Plot/Villa,Flat,Commercial,Just Exploring',
            'payment_mode' => 'required|in:Self Fund,Loan',
            'tentative_period' => 'required|in:Within 1 Month,Within 3 Months,Within 6 Months,More than 6 Months',
            'lead_type' => 'required|in:New Visit,Revisited,Meeting,Prospect',
            'scheduled_at' => 'required|date',
            'meeting_notes' => 'nullable|string',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB each
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['created_by'] = $user->id;
        $data['status'] = 'scheduled';
        $data['verification_status'] = 'pending';
        $data['reminder_enabled'] = $request->boolean('reminder_enabled', true);
        $data['reminder_minutes'] = (int) $request->input('reminder_minutes', 30);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $filename = 'meetings/' . time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $photo->storeAs('public', $filename);
                $photoPaths[] = $filename;
            }
            $data['photos'] = $photoPaths;
        }

        $meeting = $this->meetingService->createMeetingWithReminder($data, $user);

        if (!empty($meeting->lead_id) && $meeting->lead) {
            $this->asmCnpAutomationService->cancelLeadAutomation($meeting->lead, 'Lead moved to meeting flow.');
        }

        // Telecaller bot notification (if this lead still has an active telecaller task/assignment)
        try {
            if (!empty($meeting->lead_id)) {
                $teleTask = \App\Models\TelecallerTask::where('lead_id', $meeting->lead_id)
                    ->latest('created_at')
                    ->first();
                if ($teleTask) {
                    /** @var User|null $telecaller */
                    $telecaller = User::with('role')->find($teleTask->assigned_to);
                    if ($telecaller && method_exists($telecaller, 'isSalesExecutive') && $telecaller->isSalesExecutive()) {
                        $this->notificationService->notifyMeeting(
                            $telecaller,
                            $meeting->loadMissing('lead'),
                            url('/telecaller/tasks?status=pending')
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            // Don't fail meeting creation if notification fails
            Log::warning('Failed to send telecaller meeting notification', [
                'meeting_id' => $meeting->id ?? null,
                'lead_id' => $meeting->lead_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meeting scheduled successfully',
            'data' => $meeting->load(['lead', 'prospect', 'creator', 'assignedTo']),
        ], 201);
    }

    /**
     * Quick-create a meeting with minimal fields (lead_id, scheduled_at, optional title/notes)
     */
    public function quickStore(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'scheduled_at' => 'required|date',
            'title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $lead = $this->getLeadForManager($user, (int) $data['lead_id']);

        if (!$lead) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found or not accessible',
            ], 403);
        }

        $scheduledAt = Carbon::parse($data['scheduled_at']);

        $meetingNotes = $data['notes'] ?? null;
        if (!empty($data['title'])) {
            $meetingNotes = $data['title'] . ($meetingNotes ? (' - ' . $meetingNotes) : '');
        }

        $meeting = $this->meetingService->createMeetingWithReminder([
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'customer_name' => $lead->name ?? 'N/A',
            'phone' => $lead->phone ?? 'N/A',
            'employee' => $lead->creator?->name,
            'occupation' => $lead->getFormFieldValue('occupation') ?? null,
            'date_of_visit' => $scheduledAt->toDateString(),
            'project' => $lead->preferred_projects ?? null,
            'budget_range' => $this->mapBudgetRange($lead),
            'team_leader' => $user->name,
            'property_type' => $this->mapPropertyType($lead->property_type),
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 1 Month',
            'lead_type' => 'Meeting',
            'scheduled_at' => $scheduledAt,
            'meeting_notes' => $meetingNotes,
            'reminder_enabled' => true,
            'reminder_minutes' => 30,
        ], $user);

        $this->asmCnpAutomationService->cancelLeadAutomation($lead, 'Lead moved to meeting flow.');

        // Telecaller bot notification (if this lead still has an active telecaller task/assignment)
        try {
            $teleTask = \App\Models\TelecallerTask::where('lead_id', $lead->id)
                ->latest('created_at')
                ->first();
            if ($teleTask) {
                /** @var User|null $telecaller */
                $telecaller = User::with('role')->find($teleTask->assigned_to);
                if ($telecaller && method_exists($telecaller, 'isTelecaller') && $telecaller->isTelecaller()) {
                    $this->notificationService->notifyMeeting(
                        $telecaller,
                        $meeting->loadMissing('lead'),
                        url('/telecaller/tasks?status=pending')
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to send telecaller meeting notification (quickStore)', [
                'meeting_id' => $meeting->id ?? null,
                'lead_id' => $lead->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meeting scheduled successfully',
            'data' => $meeting->load(['lead', 'prospect', 'creator', 'assignedTo']),
        ], 201);
    }

    /**
     * Lead options limited to the current sales manager's accessible leads
     */
    public function leadOptions(Request $request)
    {
        $user = $request->user();
        $search = $request->get('search');

        $query = $this->buildLeadQueryForUser($user);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%');
            });
        }

        $leads = $query->latest('updated_at')
            ->limit((int) $request->get('limit', 100))
            ->get(['id', 'name', 'phone', 'status', 'preferred_location', 'budget_min', 'budget_max', 'property_type']);

        return response()->json([
            'success' => true,
            'data' => $leads,
        ]);
    }

    /**
     * Get a specific meeting
     */
    public function show(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $meeting->load([
            'lead.activeAssignments.assignedTo.role',
            'prospect',
            'creator',
            'assignedTo',
            'verifiedBy'
        ]);

        return response()->json($meeting);
    }

    /**
     * Update a meeting
     */
    public function update(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Can't update if already verified
        if ($meeting->verification_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update verified meeting',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:16',
            'employee' => 'nullable|string|max:255',
            'occupation' => 'nullable|string|max:255',
            'date_of_visit' => 'sometimes|required|date',
            'project' => 'nullable|string|max:255',
            'budget_range' => 'sometimes|required|in:Under 50 Lac,50 Lac – 1 Cr,1 Cr – 2 Cr,2 Cr – 3 Cr,Above 3 Cr',
            'team_leader' => 'nullable|string|max:255',
            'property_type' => 'sometimes|required|in:Plot/Villa,Flat,Commercial,Just Exploring',
            'payment_mode' => 'sometimes|required|in:Self Fund,Loan',
            'tentative_period' => 'sometimes|required|in:Within 1 Month,Within 3 Months,Within 6 Months,More than 6 Months',
            'lead_type' => 'sometimes|required|in:New Visit,Revisited,Meeting,Prospect',
            'scheduled_at' => 'sometimes|required|date',
            'meeting_notes' => 'nullable|string',
            'feedback' => 'nullable|string',
            'rating' => 'nullable|integer|min:1|max:5',
            'photos' => 'nullable|array',
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            // Delete old photos
            if ($meeting->photos) {
                foreach ($meeting->photos as $oldPhoto) {
                    Storage::disk('public')->delete($oldPhoto);
                }
            }

            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $filename = 'meetings/' . time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                $photo->storeAs('public', $filename);
                $photoPaths[] = $filename;
            }
            $data['photos'] = $photoPaths;
        }

        $meeting->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Meeting updated successfully',
            'data' => $meeting->fresh(['lead', 'prospect', 'creator', 'assignedTo']),
        ]);
    }

    /**
     * Mark meeting as completed
     */
    public function complete(Request $request, Meeting $meeting)
    {
        try {
            $user = $request->user();

            // Check access
            if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden'
                ], 403);
            }

            if ($meeting->status === 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Meeting already completed',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'feedback' => 'nullable|string',
                'rating' => 'nullable|integer|min:1|max:5',
                'meeting_notes' => 'nullable|string',
                'proof_photos' => 'required|array|min:1',
                'proof_photos.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120', // Max 5MB per image
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Handle proof photo uploads
            $proofPhotoPaths = [];
            if ($request->hasFile('proof_photos')) {
                foreach ($request->file('proof_photos') as $photo) {
                    try {
                        $filename = 'meetings/proof/' . time() . '_' . uniqid() . '.' . $photo->getClientOriginalExtension();
                        $photo->storeAs('public', $filename);
                        $proofPhotoPaths[] = $filename;
                    } catch (\Exception $e) {
                        \Log::error('Error storing proof photo: ' . $e->getMessage());
                        throw new \Exception('Failed to upload proof photos. Please try again.');
                    }
                }
            }

            $data = $validator->validated();
            unset($data['proof_photos']); // Remove from update data
            $data['completion_proof_photos'] = $proofPhotoPaths;
            
            // Update meeting status and data in one go to avoid double save
            $meeting->status = 'completed';
            $meeting->completed_at = now();
            $meeting->verification_status = 'pending';
            $meeting->fill($data);
            
            if (!$meeting->save()) {
                throw new \Exception('Failed to save meeting data.');
            }

            // Update lead status to meeting_completed
            try {
                if ($meeting->lead) {
                    $meeting->lead->updateStatusIfAllowed('meeting_completed');
                }
            } catch (\Exception $e) {
                // Log but don't fail the request
                \Log::warning('Error updating lead status: ' . $e->getMessage());
            }

            // Notify the same users who are allowed by Verification Routing.
            try {
                $meeting->load('creator');
                $toNotify = app(VerificationRoutingService::class)
                    ->eligibleVerifiers($meeting, VerificationRoutingService::WORKFLOW_MEETING);
                $actionUrl = url('/hr-manager/verifications');
                foreach ($toNotify->unique('id') as $verificationUser) {
                    $this->notificationService->notifyNewVerification(
                        $verificationUser,
                        'meeting',
                        'New Meeting Verification',
                        "Meeting '{$meeting->customer_name}' requires verification",
                        $actionUrl,
                        [
                            'meeting_id' => $meeting->id,
                            'customer_name' => $meeting->customer_name,
                        ]
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Error sending meeting verification notifications: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Meeting completed with proof photos. Awaiting verification.',
                'data' => $meeting->fresh(['lead', 'prospect', 'creator', 'assignedTo']),
            ])->header('Content-Type', 'application/json');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422)->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            \Log::error('Error completing meeting: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'meeting_id' => $meeting->id ?? null,
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while completing the meeting. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500)->header('Content-Type', 'application/json');
        }
    }

    /**
     * Cancel a meeting
     */
    public function cancel(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($meeting->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Meeting already cancelled',
            ], 422);
        }

        $meeting->update([
            'status' => 'cancelled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meeting cancelled successfully',
            'data' => $meeting->fresh(),
        ]);
    }

    /**
     * Reschedule a meeting
     */
    public function reschedule(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access - Senior Manager can reschedule their own meetings
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Can only reschedule scheduled meetings
        if ($meeting->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Can only reschedule meetings with status "scheduled"',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'scheduled_at' => 'required|date',
            'reason' => 'required|string|max:500',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update meeting with new scheduled time
        $oldScheduledAt = $meeting->scheduled_at;
        $meeting->scheduled_at = $request->scheduled_at;
        $meeting->status = 'scheduled'; // Keep as scheduled
        $meeting->is_rescheduled = true;
        $meeting->reschedule_count = ($meeting->reschedule_count ?? 0) + 1;
        $meeting->rescheduled_at = now();
        $meeting->rescheduled_by = $user->id;
        $meeting->reschedule_reason = $request->reason;
        // Reset verification status to pending (verification required after reschedule)
        $meeting->verification_status = 'pending';
        $meeting->verified_by = null;
        $meeting->verified_at = null;
        $meeting->rejection_reason = null;
        $meeting->save();

        $this->meetingService->syncPreMeetingReminderAfterReschedule(
            $meeting,
            $user,
            $oldScheduledAt ? Carbon::parse($oldScheduledAt) : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Meeting rescheduled successfully. Verification required.',
            'data' => $meeting->fresh(['lead', 'prospect', 'creator', 'assignedTo', 'rescheduledBy']),
        ]);
    }

    /**
     * Verify a meeting. Admin and CRM can verify any pending meeting; seniors keep existing access.
     */
    public function verify(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        $meeting->load('creator');
        $creator = $meeting->creator;
        if (!$creator) {
            return response()->json(['message' => 'Meeting creator not found'], 404);
        }

        $routing = app(VerificationRoutingService::class);
        if (!$routing->canVerify($user, $meeting, VerificationRoutingService::WORKFLOW_MEETING)) {
            return response()->json([
                'message' => 'Forbidden. You are not an eligible verifier for this meeting.',
            ], 403);
        }

        if ($meeting->verification_status === 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Meeting already verified',
            ], 422);
        }

        if ($meeting->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Meeting must be completed before verification',
            ], 422);
        }

        $notes = $request->input('notes');
        $meeting->verify($user->id, $notes);
        $routing->auditVerification('meeting_verified', $user, $meeting, VerificationRoutingService::WORKFLOW_MEETING);
        
        // Refresh the meeting to ensure latest data
        $meeting->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Meeting verified successfully',
            'data' => $meeting->fresh(['lead', 'prospect', 'creator', 'verifiedBy']),
        ]);
    }

    /**
     * Reject a meeting. Admin and CRM can reject any pending meeting; seniors keep existing access.
     */
    public function reject(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        $meeting->load('creator');
        $creator = $meeting->creator;
        if (!$creator) {
            return response()->json(['message' => 'Meeting creator not found'], 404);
        }

        $routing = app(VerificationRoutingService::class);
        if (!$routing->canVerify($user, $meeting, VerificationRoutingService::WORKFLOW_MEETING)) {
            return response()->json([
                'message' => 'Forbidden. You are not an eligible verifier for this meeting.',
            ], 403);
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

        $meeting->reject($user->id, $request->reason);
        $routing->auditVerification('meeting_rejected', $user, $meeting, VerificationRoutingService::WORKFLOW_MEETING);

        return response()->json([
            'success' => true,
            'message' => 'Meeting rejected',
            'data' => $meeting->fresh(['lead', 'prospect', 'creator', 'verifiedBy']),
        ]);
    }

    /**
     * Convert meeting to site visit with project and date selection
     */
    public function convertToSiteVisit(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Meeting should be completed before converting
        if ($meeting->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Meeting must be completed before converting to site visit',
            ], 422);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'project' => 'required|string|max:255',
            'scheduled_at' => 'required|date',
            'visit_type' => 'nullable|in:with_family,without_family',
            'visit_sequence' => 'nullable|in:fresh_visit,2nd_visit,3rd_visit',
            'reminder_enabled' => 'nullable|boolean',
            'reminder_minutes' => 'nullable|integer|min:1|max:60',
            'telecaller_id' => 'nullable|exists:users,id',
        ]);

        $validator->after(function ($validator) use ($request, $user) {
            if ($this->canScheduleInPast($user)) {
                return;
            }

            $scheduledAt = $request->input('scheduled_at');
            if ($scheduledAt && Carbon::parse($scheduledAt)->lte(now())) {
                $validator->errors()->add('scheduled_at', 'The scheduled at field must be a future date and time.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Prevent duplicate: same lead must not have another active site visit (scheduled/pending, not completed, not dead)
        if ($meeting->lead_id) {
            $hasActiveVisit = SiteVisit::where('lead_id', $meeting->lead_id)
                ->where('status', '!=', 'completed')
                ->where(function ($q) {
                    $q->whereNull('is_dead')->orWhere('is_dead', false);
                })
                ->exists();
            if ($hasActiveVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'This customer already has an active site visit (scheduled or pending). Complete, reschedule, or mark that visit as dead before creating a new one.',
                ], 422);
            }
        }

        // Create site visit from meeting data
        $siteVisitData = [
            'lead_id' => $meeting->lead_id,
            'prospect_id' => $meeting->prospect_id,
            'created_by' => $user->id,
            'assigned_to' => $meeting->assigned_to,
            'property_name' => $validated['project'], // Use provided project
            'property_address' => null,
            'scheduled_at' => Carbon::parse($validated['scheduled_at']), // Use provided scheduled_at
            'status' => 'scheduled',
            'verification_status' => 'pending',
            // Copy all form fields from meeting
            'customer_name' => $meeting->customer_name,
            'phone' => $meeting->phone,
            'employee' => $meeting->employee,
            'occupation' => $meeting->occupation,
            'date_of_visit' => $meeting->date_of_visit,
            'project' => $validated['project'], // Use provided project
            'budget_range' => $meeting->budget_range,
            'team_leader' => $meeting->team_leader,
            'property_type' => $meeting->property_type,
            'payment_mode' => $meeting->payment_mode,
            'tentative_period' => $meeting->tentative_period,
            'lead_type' => $meeting->lead_type,
            'photos' => $meeting->photos, // Copy photos
            'visit_notes' => 'Converted from Meeting #' . $meeting->id,
            // New fields
            'visit_type' => $validated['visit_type'] ?? null,
            'visit_sequence' => $validated['visit_sequence'] ?? null,
            'reminder_enabled' => $validated['reminder_enabled'] ?? false,
            'reminder_minutes' => $validated['reminder_minutes'] ?? 10,
        ];

        $siteVisit = SiteVisit::create($siteVisitData);
        app(\App\Services\LeadActiveWorkflowService::class)->moveLeadToWorkflow(
            $siteVisit->lead_id,
            'site_visit',
            $siteVisit->id
        );
        $leadSingleOpenTaskService = app(\App\Services\LeadSingleOpenTaskService::class);

        // Create reminder task if enabled
        if (($validated['reminder_enabled'] ?? false) && !$leadSingleOpenTaskService->hasOpenTaskForLead($siteVisit->lead_id)) {
            $reminderMinutes = $validated['reminder_minutes'] ?? 10;
            $taskScheduledAt = Carbon::parse($validated['scheduled_at'])->subMinutes($reminderMinutes);
            
            // If task scheduled time is in the past, set it to 10 minutes from now
            if ($taskScheduledAt->isPast()) {
                $taskScheduledAt = now()->addMinutes(10);
            }
            
            $assignedTo = $siteVisit->assigned_to ?? $user->id;
            $customerName = $siteVisit->customer_name ?? $siteVisit->lead->name ?? 'Customer';
            $phone = $siteVisit->phone ?? $siteVisit->lead->phone ?? '';
            
            $reminderTask = Task::create([
                'lead_id' => $siteVisit->lead_id,
                'site_visit_id' => $siteVisit->id,
                'assigned_to' => $assignedTo,
                'type' => 'phone_call',
                'title' => "Reminder: Site Visit for {$customerName}",
                'description' => "Reminder call {$reminderMinutes} minutes before scheduled Site Visit: {$customerName} ({$phone})",
                'status' => 'pending',
                'scheduled_at' => $taskScheduledAt,
                'notes' => "Site Visit Reminder - Site Visit ID: {$siteVisit->id}",
                'created_by' => $user->id,
            ]);
            
            // Link task to site visit
            $siteVisit->update(['reminder_task_id' => $reminderTask->id]);
        }

        // Create telecaller task if telecaller is selected
        if (!empty($validated['telecaller_id']) && !$leadSingleOpenTaskService->hasOpenTaskForLead($siteVisit->lead_id)) {
            $telecaller = User::with('role')->find($validated['telecaller_id']);
            if ($telecaller && $telecaller->role && $telecaller->role->slug === \App\Models\Role::SALES_EXECUTIVE) {
                $taskScheduledAt = Carbon::parse($validated['scheduled_at'])->subMinutes(30);
                
                // If task scheduled time is in the past, set it to 10 minutes from now
                if ($taskScheduledAt->isPast()) {
                    $taskScheduledAt = now()->addMinutes(10);
                }
                
                TelecallerTask::create([
                    'lead_id' => $siteVisit->lead_id,
                    'assigned_to' => $telecaller->id,
                    'task_type' => 'calling',
                    'status' => 'pending',
                    'scheduled_at' => $taskScheduledAt,
                    'notes' => "Reminder call 30 min before scheduled Site Visit",
                    'created_by' => $user->id,
                ]);
            }
        }

        // Mark meeting as converted and link to site visit
        $meeting->update([
            'converted_to_site_visit_id' => $siteVisit->id,
            'is_converted' => true,
        ]);

        // Update lead status if exists
        if ($meeting->lead_id) {
            $lead = Lead::find($meeting->lead_id);
            if ($lead) {
                $lead->updateStatusIfAllowed('visit_scheduled');
                app(MetaReviewAutoStageService::class)->applyVisitScheduled($lead);
            }
        }

        // Fire SiteVisitCreated event to trigger task creation
        try {
            event(new SiteVisitCreated($siteVisit));
        } catch (\Exception $e) {
            // Broadcasting errors (like Pusher) shouldn't stop site visit creation
            // Log but continue - the site visit is successfully created
            Log::warning("Broadcasting error in MeetingController convertToSiteVisit (non-critical): " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Meeting converted to Site Visit successfully! Site visit has been scheduled.',
            'data' => [
                'meeting' => $meeting->fresh(),
                'site_visit' => $siteVisit->load(['lead', 'creator']),
                'site_visit_id' => $siteVisit->id,
            ],
        ]);
    }

    /**
     * Mark meeting as dead
     */
    public function markDead(Request $request, Meeting $meeting)
    {
        $user = $request->user();

        // Check access
        if ($user->isSalesManager() && $meeting->created_by !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
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

        $meeting->markAsDead($user->id, $request->reason);

        return response()->json([
            'success' => true,
            'message' => 'Meeting marked as dead successfully',
            'data' => $meeting->fresh(['lead', 'creator', 'markedDeadBy']),
        ]);
    }

    /**
     * Build base lead query restricted to the current user's accessible leads.
     */
    private function buildLeadQueryForUser(User $user)
    {
        $query = Lead::query()->where('is_dead', false);

        if ($user->isAdmin() || $user->isCrm()) {
            return $query;
        }

        if ($user->isSalesHead()) {
            $teamIds = $user->getAllTeamMemberIds();
            $query->where(function ($q) use ($teamIds, $user) {
                $q->where('created_by', $user->id);

                if (!empty($teamIds)) {
                    $q->orWhereHas('activeAssignments', function ($assignmentQuery) use ($teamIds, $user) {
                        $assignmentQuery->whereIn('assigned_to', array_merge([$user->id], $teamIds));
                    });

                    $q->orWhereHas('prospects', function ($prospectQuery) use ($teamIds) {
                        $prospectQuery->whereIn('telecaller_id', $teamIds)
                                      ->whereIn('verification_status', ['verified', 'approved']);
                    });
                }
            });

            return $query;
        }

        if ($user->isSalesManager()) {
            $teamMemberIds = $user->teamMembers()->pluck('id')->toArray();

            $query->where(function ($q) use ($user, $teamMemberIds) {
                $q->where('created_by', $user->id)
                  ->orWhereHas('activeAssignments', function ($assignmentQuery) use ($user) {
                      $assignmentQuery->where('assigned_to', $user->id);
                  });

                if (!empty($teamMemberIds)) {
                    $q->orWhereHas('prospects', function ($prospectQuery) use ($teamMemberIds, $user) {
                        $prospectQuery->whereIn('telecaller_id', $teamMemberIds)
                                      ->whereIn('verification_status', ['verified', 'approved'])
                                      ->where('verified_by', $user->id);
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Fetch a single lead ensuring the current user is allowed to use it.
     */
    private function getLeadForManager(User $user, int $leadId): ?Lead
    {
        return $this->buildLeadQueryForUser($user)
            ->where('id', $leadId)
            ->first();
    }

    /**
     * Map lead budget to meeting budget range buckets.
     */
    private function mapBudgetRange(Lead $lead): string
    {
        $budgetValue = null;

        if (!is_null($lead->budget_max)) {
            $budgetValue = (float) $lead->budget_max;
        } elseif (!is_null($lead->budget_min)) {
            $budgetValue = (float) $lead->budget_min;
        }

        if (is_null($budgetValue)) {
            return 'Under 50 Lac';
        }

        if ($budgetValue < 5000000) {
            return 'Under 50 Lac';
        } elseif ($budgetValue < 10000000) {
            return '50 Lac – 1 Cr';
        } elseif ($budgetValue < 20000000) {
            return '1 Cr – 2 Cr';
        } elseif ($budgetValue < 30000000) {
            return '2 Cr – 3 Cr';
        }

        return 'Above 3 Cr';
    }

    /**
     * Normalize lead property type to meeting allowed values.
     */
    private function mapPropertyType(?string $propertyType): string
    {
        if (!$propertyType) {
            return 'Just Exploring';
        }

        $normalized = strtolower($propertyType);

        if (str_contains($normalized, 'plot') || str_contains($normalized, 'villa')) {
            return 'Plot/Villa';
        }

        if (str_contains($normalized, 'flat') || str_contains($normalized, 'apartment')) {
            return 'Flat';
        }

        if (str_contains($normalized, 'commercial')) {
            return 'Commercial';
        }

        return 'Just Exploring';
    }

    /**
     * Create simplified meeting with auto-reminder
     */
    public function quickScheduleWithReminder(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'lead_id' => 'nullable|exists:leads,id',
            'meeting_sequence' => 'required|integer|min:1',
            'scheduled_at' => 'required|date',
            'meeting_mode' => 'required|in:online,offline',
            'meeting_link' => 'nullable|url|max:500',
            'location' => 'nullable|string|max:255',
            'reminder_enabled' => 'boolean',
            'reminder_minutes' => 'integer|min:1',
            'meeting_notes' => 'nullable|string',
        ]);

        // Custom validation: location required for offline, meeting_link optional for online
        $validator->after(function ($validator) use ($request) {
            if ($request->meeting_mode === 'offline' && empty($request->location)) {
                $validator->errors()->add('location', 'Location is required for offline meetings.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $request->only([
                'lead_id',
                'meeting_sequence',
                'scheduled_at',
                'meeting_mode',
                'meeting_link',
                'location',
                'reminder_enabled',
                'reminder_minutes',
                'meeting_notes',
            ]);

            $meeting = $this->meetingService->createMeetingWithReminder($data, $user);

            return response()->json([
                'success' => true,
                'message' => 'Meeting scheduled successfully',
                'meeting' => $meeting
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create meeting with reminder', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Failed to schedule meeting',
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Complete pre-meeting call with action
     */
    public function completePreCall(Request $request, int $id)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:confirm,cancel,reschedule',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $meeting = Meeting::findOrFail($id);
            
            $result = $this->meetingService->handlePreCallComplete(
                $meeting,
                $request->action,
                $request->notes,
                $user->id
            );

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Failed to complete pre-call', [
                'error' => $e->getMessage(),
                'meeting_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to complete pre-call action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel meeting
     */
    public function cancelMeeting(Request $request, int $id)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $meeting = Meeting::findOrFail($id);
            
            $meeting->cancelMeeting($user->id, $request->reason ?? 'Cancelled by user');

            return response()->json([
                'message' => 'Meeting cancelled successfully',
                'meeting' => $meeting->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cancel meeting', [
                'error' => $e->getMessage(),
                'meeting_id' => $id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Failed to cancel meeting',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get meeting history for a lead
     */
    public function getMeetingHistory(Request $request, int $leadId)
    {
        try {
            $meetings = Meeting::where('lead_id', $leadId)
                ->orderBy('created_at', 'desc')
                ->get();

            $nextSequence = $this->meetingService->getLeadMeetingSequence($leadId);

            return response()->json([
                'meetings' => $meetings,
                'next_sequence' => $nextSequence,
                'total_meetings' => $meetings->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get meeting history', [
                'error' => $e->getMessage(),
                'lead_id' => $leadId,
            ]);

            return response()->json([
                'message' => 'Failed to get meeting history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function canScheduleInPast($user): bool
    {
        return (bool) ($user && ($user->isAdmin() || $user->isCrm()));
    }
}
