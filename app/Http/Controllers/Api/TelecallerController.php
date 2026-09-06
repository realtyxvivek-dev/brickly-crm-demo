<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelecallerService;
use App\Models\User;
use App\Models\Task;
use App\Models\CrmAssignment;
use App\Models\TelecallerProfile;
use App\Models\UserAvailability;
use App\Models\ActivityLog;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\TelecallerTask;
use App\Models\Prospect;
use App\Models\LeadFormField;
use App\Models\AppNotification;
use App\Models\SiteVisit;
use App\Models\Incentive;
use App\Services\TelecallerTaskService;
use App\Services\UserStatusService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Services\LoginSecurityService;

class TelecallerController extends Controller
{
    protected $telecallerService;
    protected $taskService;
    protected $userStatusService;

    public function __construct(TelecallerService $telecallerService, TelecallerTaskService $taskService, UserStatusService $userStatusService, protected LoginSecurityService $loginSecurityService)
    {
        $this->telecallerService = $telecallerService;
        $this->taskService = $taskService;
        $this->userStatusService = $userStatusService;
    }

    /**
     * Login for telecaller
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $this->loginSecurityService->normalizeEmail((string) $request->email);
        $user = User::whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->with('role')
            ->first();
        $this->loginSecurityService->rememberAttempt($request, $email);

        if ($this->loginSecurityService->isLocked($user)) {
            return $this->loginSecurityService->jsonLockedResponse($user);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            $state = $this->loginSecurityService->recordFailure($request, $user, $email, 'api_telecaller_password');
            if ($state['locked']) {
                return $this->loginSecurityService->jsonLockedResponse($user?->fresh());
            }

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is sales executive
        if (!$user->isSalesExecutive()) {
            $this->loginSecurityService->recordFailure($request, $user, $email, 'api_telecaller_role');

            throw ValidationException::withMessages([
                'email' => ['This account is not authorized for sales executive access.'],
            ]);
        }

        $this->loginSecurityService->clearFailures($user);

        $token = $user->createToken('telecaller-token')->plainTextToken;

        // Mark attendance for telecaller
        $this->markTelecallerAttendance($user);

        return response()->json([
            'user' => $user->load('role', 'manager'),
            'token' => $token,
        ]);
    }

    /**
     * Mark telecaller attendance on login
     */
    private function markTelecallerAttendance(User $user): void
    {
        // Update UserAvailability - mark as online
        $availability = UserAvailability::firstOrCreate(
            ['user_id' => $user->id],
            [
                'is_online' => false,
                'timezone' => 'Asia/Kolkata',
                'current_day_leads' => 0,
                'is_available' => false,
            ]
        );
        
        $availability->update([
            'is_online' => true,
            'last_seen_at' => now(),
        ]);
        $availability->updateAvailability();

    }

    /**
     * Get current user (whoami)
     */
    public function whoami(Request $request)
    {
        return response()->json($request->user()->load('role', 'manager'));
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $request->validate([
            'fcm_token' => 'nullable|string',
            'push_endpoint' => 'nullable|string|max:500',
        ]);
        $user = $request->user();
        $devices = app(\App\Services\NotificationDeviceOwnershipService::class);
        $context = ['ip_address' => $request->ip(), 'user_agent' => $request->userAgent()];
        if ($request->filled('fcm_token')) {
            $devices->releaseFcm($user, (string) $request->fcm_token, $context);
        }
        if ($request->filled('push_endpoint')) {
            $devices->releasePush($user, (string) $request->push_endpoint, $context);
        }
        $user->currentAccessToken()?->delete();

        // Clear web session so login page does not redirect back to dashboard
        $request->session()->forget('telecaller_api_token');
        $request->session()->forget('user_password_for_change');
        $request->session()->forget('api_token');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(Request $request)
    {
        $telecallerId = $request->user()->id;
        $stats = $this->telecallerService->getStats($telecallerId);

        return response()->json($stats);
    }

    /**
     * Get top performers
     */
    public function getTopPerformers(Request $request)
    {
        $limit = $request->input('limit', 1);
        $performers = $this->telecallerService->getTopPerformers($limit);

        return response()->json([
            'top_performers' => $performers,
        ]);
    }

    /**
     * Get calling queue
     */
    public function getCallingQueue(Request $request)
    {
        $telecallerId = $request->user()->id;
        $queue = $this->telecallerService->getCallingQueue($telecallerId);

        return response()->json([
            'queue' => $queue,
        ]);
    }

    /**
     * Get completed calls
     */
    public function getCompletedCalls(Request $request)
    {
        $telecallerId = $request->user()->id;
        
        $filters = [
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
        ];

        $calls = $this->telecallerService->getCompletedCalls($telecallerId, $filters);

        return response()->json([
            'calls' => $calls,
        ]);
    }

    /**
     * Get follow-up calls
     */
    public function getFollowUpCalls(Request $request)
    {
        $telecallerId = $request->user()->id;
        $calls = $this->telecallerService->getFollowUpCalls($telecallerId);

        return response()->json([
            'calls' => $calls,
        ]);
    }

    /**
     * Get CNP calls
     */
    public function getCnpCalls(Request $request)
    {
        $telecallerId = $request->user()->id;
        $calls = $this->telecallerService->getCnpCalls($telecallerId);

        return response()->json([
            'calls' => $calls,
        ]);
    }

    /**
     * Get prospects
     */
    public function getProspects(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;
            $status = $request->input('status', 'pending'); // pending, approved, rejected, all
            $dateRange = $request->input('date_range', 'today');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            
            [$startDate, $endDate] = $this->getDateRange($dateRange, $startDate, $endDate);
            
            $query = Prospect::where('telecaller_id', $telecallerId)
                ->with(['lead', 'manager.role', 'verifiedBy', 'telecaller']);

            // Filter by verification status
            if ($status && $status !== 'all') {
                // Map frontend status to database values
                // Database uses: pending, pending_verification, approved, verified, rejected
                $statusMap = [
                    'pending' => ['pending', 'pending_verification'],
                    'approved' => ['approved', 'verified'],
                    'verified' => ['approved', 'verified'], // Alias
                    'rejected' => 'rejected',
                ];
                $dbStatus = $statusMap[$status] ?? $status;
                if (is_array($dbStatus)) {
                    $query->whereIn('verification_status', $dbStatus);
                } else {
                    $query->where('verification_status', $dbStatus);
                }
            }

            // Filter by date range
            if ($startDate && $endDate) {
                if ($status === 'pending') {
                    // Filter by created_at for pending prospects
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                } elseif (in_array($status, ['approved', 'rejected', 'verified'])) {
                    // Filter by verified_at for approved/rejected prospects
                    $query->whereBetween('verified_at', [$startDate, $endDate])
                          ->whereNotNull('verified_at');
                } elseif ($status === 'all') {
                    // Filter by either created_at or verified_at for all status
                    $query->where(function($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->orWhereBetween('verified_at', [$startDate, $endDate]);
                    });
                }
            }

            $query->latest('created_at');

            $perPage = min(100, max(1, (int) $request->get('per_page', 20)));
            $prospects = $query->paginate($perPage);

            $formattedProspects = $prospects->map(function($prospect) {
                $manager = $prospect->manager;
                $verifierLevel = $manager ? $manager->getDisplayRoleName() : 'Not Assigned';
                return [
                    'id' => $prospect->id,
                    'lead_id' => $prospect->lead_id,
                    'customer_name' => $prospect->customer_name,
                    'phone' => $prospect->phone,
                    'budget' => $prospect->budget,
                    'preferred_location' => $prospect->preferred_location,
                    'size' => $prospect->size,
                    'purpose' => $prospect->purpose,
                    'possession' => $prospect->possession,
                    'remark' => $prospect->remark,
                    'verification_status' => $prospect->verification_status,
                    'manager_name' => $manager->name ?? 'Not Assigned',
                    'manager_id' => $prospect->manager_id,
                    'verifier_level' => $verifierLevel,
                    'verified_by_name' => $prospect->verifiedBy->name ?? null,
                    'verified_at' => $prospect->verified_at ? $prospect->verified_at->format('Y-m-d H:i:s') : null,
                    'rejection_reason' => $prospect->rejection_reason,
                    'manager_remark' => $prospect->manager_remark,
                    'created_at' => $prospect->created_at ? $prospect->created_at->format('Y-m-d H:i:s') : null,
                    'lead_name' => $prospect->lead->name ?? '-',
                ];
            });

            $response = [
                'success' => true,
                'data' => $formattedProspects->values()->all(),
                'pagination' => [
                    'current_page' => $prospects->currentPage(),
                    'per_page' => $prospects->perPage(),
                    'total' => $prospects->total(),
                    'last_page' => $prospects->lastPage(),
                ],
            ];

            if (in_array($status, ['pending', 'all'])) {
                $summaryQuery = Prospect::where('telecaller_id', $telecallerId)
                    ->whereIn('verification_status', ['pending', 'pending_verification']);
                if ($startDate && $endDate) {
                    if ($status === 'pending') {
                        $summaryQuery->whereBetween('created_at', [$startDate, $endDate]);
                    } else {
                        $summaryQuery->where(function($q) use ($startDate, $endDate) {
                            $q->whereBetween('created_at', [$startDate, $endDate])
                              ->orWhereBetween('verified_at', [$startDate, $endDate]);
                        });
                    }
                }
                $verifierCounts = $summaryQuery
                    ->selectRaw('COALESCE(assigned_manager, manager_id) as verifier_id, count(*) as count')
                    ->groupBy('verifier_id')
                    ->get();
                $verifierIds = $verifierCounts->pluck('verifier_id')->filter()->unique()->values()->all();
                $users = $verifierIds ? User::with('role')->whereIn('id', $verifierIds)->get()->keyBy('id') : collect();
                $pendingByVerifier = [];
                foreach ($verifierCounts as $row) {
                    $userId = $row->verifier_id;
                    $user = $userId ? $users->get($userId) : null;
                    $pendingByVerifier[] = [
                        'user_id' => $userId,
                        'name' => $user ? $user->name : 'Not Assigned',
                        'role_display' => $user ? $user->getDisplayRoleName() : 'Not Assigned',
                        'count' => (int) $row->count,
                    ];
                }
                $response['pending_summary'] = $pendingByVerifier;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Get Prospects Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load prospects: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get date range based on filter type
     */
    private function getDateRange(string $dateRange, $startDate = null, $endDate = null): array
    {
        $today = Carbon::today();

        // If custom dates provided, use them
        if ($startDate && $endDate) {
            return [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ];
        }

        // Calculate based on filter type
        switch ($dateRange) {
            case 'all':
                return [
                    Carbon::create(2000, 1, 1)->startOfDay(),
                    $today->copy()->endOfDay(),
                ];
            case 'today':
                return [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                ];
            case 'this_week':
                return [
                    $today->copy()->startOfWeek(),
                    $today->copy()->endOfWeek(),
                ];
            case 'this_month':
                return [
                    $today->copy()->startOfMonth(),
                    $today->copy()->endOfMonth(),
                ];
            default:
                return [
                    $today->copy()->startOfDay(),
                    $today->copy()->endOfDay(),
                ];
        }
    }

    /**
     * Update call status (mark as interested/not interested)
     */
    public function updateCallStatus(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
            'status' => 'required|in:called_interested,called_not_interested',
            'remark' => 'nullable|string',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');
        $status = $request->input('status');
        $remark = $request->input('remark');

        if ($status === 'called_not_interested') {
            $result = $this->telecallerService->markNotInterested($assignmentId, $telecallerId, $remark);
        } else {
            // For interested, we create a prospect (handled in createProspect endpoint)
            return response()->json([
                'success' => false,
                'message' => 'Use create-prospect endpoint for interested leads',
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Mark as CNP
     */
    public function markCnp(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
            'remark' => 'nullable|string',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');
        $remark = $request->input('remark');

        $result = $this->telecallerService->markCnp($assignmentId, $telecallerId, $remark);

        return response()->json($result);
    }

    /**
     * Mark as Broker
     */
    public function markBroker(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
            'remark' => 'required|string',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');
        $remark = $request->input('remark');

        $result = $this->telecallerService->markAsBroker($assignmentId, $telecallerId, $remark);

        return response()->json($result);
    }

    /**
     * Schedule follow-up
     */
    public function scheduleFollowUp(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
            'follow_up_date' => 'required|date|after_or_equal:today',
            'follow_up_time' => 'required|string',
            'follow_up_notes' => 'nullable|string',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');
        $date = $request->input('follow_up_date');
        $time = $request->input('follow_up_time');
        $notes = $request->input('follow_up_notes');

        $result = $this->telecallerService->scheduleFollowUp($assignmentId, $telecallerId, $date, $time, $notes);

        return response()->json($result);
    }

    /**
     * Create prospect from assignment
     */
    public function createProspect(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
            'budget' => 'nullable|numeric',
            'preferred_location' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
            'purpose' => 'nullable|string|max:255',
            'possession' => 'nullable|string|max:255',
            'remark' => 'required|string',
            'assigned_manager' => 'nullable|exists:users,id',
            'lead_score' => 'required|integer|min:1|max:5',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');

        $data = [
            'budget' => $request->input('budget'),
            'preferred_location' => $request->input('preferred_location'),
            'size' => $request->input('size'),
            'purpose' => $request->input('purpose'),
            'possession' => $request->input('possession'),
            'remark' => $request->input('remark'),
            'assigned_manager' => $request->input('assigned_manager'),
            'lead_score' => $request->input('lead_score'),
        ];

        $result = $this->telecallerService->createProspect($assignmentId, $telecallerId, $data);

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Recall assignment (move back to queue)
     */
    public function recallAssignment(Request $request)
    {
        $request->validate([
            'assignment_id' => 'required|exists:crm_assignments,id',
        ]);

        $telecallerId = $request->user()->id;
        $assignmentId = $request->input('assignment_id');

        $result = $this->telecallerService->recallAssignment($assignmentId, $telecallerId);

        return response()->json($result);
    }

    /**
     * Blacklist number
     */
    public function blacklistNumber(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'reason' => 'nullable|string|max:255',
        ]);

        $telecallerId = $request->user()->id;
        $phone = $request->input('phone');
        $reason = $request->input('reason', 'Broker');

        $result = $this->telecallerService->blacklistNumber($phone, $telecallerId, $reason);

        return response()->json($result);
    }

    /**
     * Get users/managers list for assignment dropdown
     */
    public function getUsers(Request $request)
    {
        // Get managers and sales managers (for assignment dropdown)
        $users = User::whereHas('role', function($q) {
                $q->whereIn('slug', ['sales_manager', 'sales_executive']);
            })
            ->where('is_active', true)
            ->with('role')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->name,
                ];
            });

        return response()->json([
            'users' => $users,
        ]);
    }

    /**
     * Get telecallers list for dropdown
     */
    public function getTelecallers(Request $request)
    {
        $telecallers = User::whereHas('role', function($q) {
                $q->where('slug', \App\Models\Role::SALES_EXECUTIVE);
            })
            ->where('is_active', true)
            ->with('role')
            ->get()
            ->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role->name ?? 'Sales Executive',
                ];
            });

        return response()->json([
            'telecallers' => $telecallers,
        ]);
    }

    /**
     * Get tasks with filters (pending, completed, rescheduled, all).
     * Returns both TelecallerTask and Task (phone_call) for the user so sales_manager/assistant_sales_manager tasks also show.
     */
    public function getTasks(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;
            $status = $request->input('status', 'pending'); // pending, completed, rescheduled, all
            $taskType = $request->input('task_type', ''); // calling, follow_up, cnp_retry, all
            $dateRange = $request->input('date_range', 'today');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $page = max(1, (int) $request->get('page', 1));

            \Log::info('Telecaller getTasks - Starting', [
                'telecaller_id' => $telecallerId,
                'status_filter' => $status,
                'task_type_filter' => $taskType,
                'date_range' => $dateRange,
            ]);

            // ---- TelecallerTask query ----
            $ttQuery = TelecallerTask::where('assigned_to', $telecallerId)
                ->with(['lead' => function($q) {
                    $q->with(['activeAssignments' => function($q2) {
                        $q2->with(['assignedTo' => function($q3) {
                            $q3->with('manager');
                        }]);
                    }]);
                }, 'assignedTo', 'createdBy']);

            $this->applyTaskDateFilter($ttQuery, $dateRange, $startDate, $endDate, 'scheduled_at');
            if ($status && $status !== 'all') {
                $ttQuery->where('status', $status);
            }
            if ($taskType && $taskType !== 'all') {
                if ($taskType === 'call_again') {
                    $ttQuery->where('outcome', 'rescheduled');
                } else {
                    $ttQuery->where('task_type', $taskType);
                }
            }

            $telecallerTasks = $ttQuery->orderByRaw('CASE WHEN scheduled_at < NOW() THEN 0 ELSE 1 END')
                ->orderBy('scheduled_at', 'asc')
                ->limit(200)
                ->get();

            // ---- Task (phone_call) query for sales_manager / assistant_sales_manager ----
            $taskQuery = Task::where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->with(['lead' => function($q) {
                    $q->with(['activeAssignments' => function($q2) {
                        $q2->with(['assignedTo' => function($q3) {
                            $q3->with('manager');
                        }]);
                    }]);
                }, 'assignedTo', 'createdBy']);

            $this->applyTaskDateFilter($taskQuery, $dateRange, $startDate, $endDate, 'scheduled_at');
            if ($status && $status !== 'all') {
                $taskQuery->where('status', $status);
            }

            $managerTasks = $taskQuery->orderByRaw('CASE WHEN scheduled_at < NOW() THEN 0 ELSE 1 END')
                ->orderBy('scheduled_at', 'asc')
                ->limit(200)
                ->get();

            // Format TelecallerTask (id as-is)
            $ttFormatted = $telecallerTasks->map(function ($task) {
                $lead = $task->lead;
                $assignment = $lead ? $lead->activeAssignments->first() : null;
                $manager = $assignment?->assignedTo?->manager ?? null;
                return [
                    'id' => $task->id,
                    'lead_id' => $task->lead_id,
                    'lead_name' => $lead->name ?? '-',
                    'lead_phone' => $lead->phone ?? '-',
                    'lead_email' => $lead->email ?? null,
                    'manager_name' => $manager->name ?? 'Not Assigned',
                    'manager_id' => $manager->id ?? null,
                    'task_type' => $task->task_type,
                    'meeting_id' => $task->meeting_id ?? null,
                    'status' => $task->status,
                    'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $task->completed_at ? $task->completed_at->format('Y-m-d H:i:s') : null,
                    'outcome' => $task->outcome,
                    'notes' => $task->notes,
                ];
            });

            // Format Task (composite id mt_{id})
            $mtFormatted = $managerTasks->map(function ($task) {
                $lead = $task->lead;
                $assignment = $lead ? $lead->activeAssignments->first() : null;
                $manager = $assignment?->assignedTo?->manager ?? null;
                return [
                    'id' => 'mt_' . $task->id,
                    'lead_id' => $task->lead_id,
                    'lead_name' => $lead->name ?? '-',
                    'lead_phone' => $lead->phone ?? '-',
                    'lead_email' => $lead->email ?? null,
                    'manager_name' => $manager->name ?? 'Not Assigned',
                    'manager_id' => $manager->id ?? null,
                    'task_type' => 'calling',
                    'meeting_id' => null,
                    'status' => $task->status,
                    'scheduled_at' => $task->scheduled_at ? $task->scheduled_at->format('Y-m-d H:i:s') : null,
                    'completed_at' => $task->completed_at ? $task->completed_at->format('Y-m-d H:i:s') : null,
                    'outcome' => null,
                    'notes' => $task->notes,
                ];
            });

            $merged = $ttFormatted->concat($mtFormatted)->values();
            $sorted = $merged->sortBy(function ($t) {
                $s = $t['scheduled_at'] ?? '';
                return $s ? strtotime($s) : 0;
            })->values();
            $total = $sorted->count();
            $offset = ($page - 1) * $perPage;
            $paginated = $sorted->slice($offset, $perPage)->values()->all();
            $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

            \Log::info('Telecaller getTasks - Tasks found', [
                'telecaller_id' => $telecallerId,
                'total' => $total,
                'status_filter' => $status,
            ]);

            return response()->json([
                'success' => true,
                'data' => $paginated,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Tasks Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tasks: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Apply date range filter to a query (shared by TelecallerTask and Task).
     */
    private function applyTaskDateFilter($query, string $dateRange, $startDate, $endDate, string $column = 'scheduled_at'): void
    {
        if (!$dateRange || $dateRange === 'all') {
            return;
        }
        [$rangeStart, $rangeEnd] = $this->getDateRange($dateRange, $startDate, $endDate);
        if (in_array($dateRange, ['this_month', 'this_week', 'today'], true)) {
            $query->whereRaw("DATE({$column}) BETWEEN ? AND ?", [
                $rangeStart->format('Y-m-d'),
                $rangeEnd->format('Y-m-d'),
            ]);
        } else {
            $query->whereBetween($column, [$rangeStart, $rangeEnd]);
        }
    }

    /**
     * Resolve task id (numeric or composite mt_{id}) to TelecallerTask or Task for the given user.
     * Returns ['telecaller_task' => TelecallerTask|null, 'task' => Task|null] - exactly one non-null when found.
     */
    private function resolveTaskForUser($taskIdOrComposite, int $userId): array
    {
        // Admin/CRM can access any task
        $user = auth()->user();
        $isPrivileged = $user && ($user->isAdmin() || $user->isCrm());

        if (is_string($taskIdOrComposite) && str_starts_with($taskIdOrComposite, 'mt_')) {
            $id = (int) substr($taskIdOrComposite, 3);
            if ($id <= 0) {
                return [null, null];
            }
            $query = Task::with('lead')->where('id', $id);
            if (!$isPrivileged) {
                $query->where('assigned_to', $userId);
            }
            $task = $query->first();
            return [null, $task];
        }
        $id = (int) $taskIdOrComposite;
        if ($id <= 0) {
            return [null, null];
        }
        $query = TelecallerTask::with('lead')->where('id', $id);
        if (!$isPrivileged) {
            $query->where('assigned_to', $userId);
        }
        $telecallerTask = $query->first();
        if ($telecallerTask) {
            return [$telecallerTask, null];
        }

        $query = Task::with('lead')->where('id', $id);
        if (!$isPrivileged) {
            $query->where('assigned_to', $userId);
        }

        return [null, $query->first()];
    }

    /**
     * Get task statistics
     */
    public function getTaskStats(Request $request)
    {
        $telecallerId = $request->user()->id;

        $stats = [
            'pending' => Task::where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->where('status', 'pending')
                ->count(),
            'in_progress' => Task::where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->where('status', 'in_progress')
                ->count(),
            'completed' => Task::where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->where('status', 'completed')
                ->count(),
            'today_pending' => Task::where('assigned_to', $telecallerId)
                ->where('type', 'phone_call')
                ->where('status', 'pending')
                ->whereDate('created_at', Carbon::today())
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Initiate call from task (TelecallerTask or Task mt_)
     */
    public function initiateCall(Request $request, $task)
    {
        [$telecallerTask, $managerTask] = $this->resolveTaskForUser($task, $request->user()->id);
        
        if ($managerTask) {
            if ($managerTask->status !== 'pending') {
                return response()->json(['error' => 'Task is not in pending status'], 400);
            }
            $managerTask->update(['status' => 'in_progress']);
            $assignment = CrmAssignment::where('lead_id', $managerTask->lead_id)
                ->where('id', '>', app(\App\Services\LeadReopenService::class)->boundary($managerTask->lead_id)['crm_assignments'] ?? 0)
                ->where('assigned_to', $managerTask->assigned_to)
                ->first();
            return response()->json([
                'success' => true,
                'message' => 'Call initiated',
                'task' => $managerTask->fresh(['lead']),
                'assignment_id' => $assignment ? $assignment->id : null,
            ]);
        }

        if (!$telecallerTask) {
            return response()->json([
                'error' => 'Task not found',
                'message' => 'Resource not found.',
            ], 404);
        }

        if ($telecallerTask->status !== 'pending') {
            return response()->json([
                'error' => 'Task is not in pending status',
            ], 400);
        }

        $telecallerTask->update([
            'status' => 'in_progress',
        ]);

        $assignment = CrmAssignment::where('lead_id', $telecallerTask->lead_id)
            ->where('id', '>', app(\App\Services\LeadReopenService::class)->boundary($telecallerTask->lead_id)['crm_assignments'] ?? 0)
            ->where('assigned_to', $telecallerTask->assigned_to)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Call initiated',
            'task' => $telecallerTask->fresh(['lead']),
            'assignment_id' => $assignment ? $assignment->id : null,
        ]);
    }

    /**
     * Handle call outcome from task (TelecallerTask or Task mt_)
     */
    public function callOutcome(Request $request, $task)
    {
        [$telecallerTask, $managerTask] = $this->resolveTaskForUser($task, $request->user()->id);

        if ($managerTask) {
            $request->validate([
                'outcome' => 'required|in:interested,not_interested,cnp,call_later,broker',
            ]);
            if (!in_array($managerTask->status, ['pending', 'in_progress'])) {
                return response()->json(['error' => 'Task must be pending or in progress'], 400);
            }
            if ($managerTask->status === 'pending') {
                $managerTask->update(['status' => 'in_progress']);
            }
            $managerTask->update([
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => ($managerTask->notes ? $managerTask->notes . "\n" : '') . 'Outcome: ' . $request->outcome,
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Task completed',
                'task' => $managerTask->fresh(['lead']),
            ]);
        }

        if (!$telecallerTask) {
            return response()->json([
                'error' => 'Task not found',
                'message' => 'Resource not found.',
            ], 404);
        }
        
        if ($telecallerTask->assigned_to !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Allow both 'pending' and 'in_progress' status for call outcomes
        // If task is pending, auto-update to in_progress when marking outcome
        if (!in_array($telecallerTask->status, ['pending', 'in_progress'])) {
            return response()->json([
                'error' => 'Task must be pending or in progress',
                'current_status' => $telecallerTask->status,
            ], 400);
        }

        $request->validate([
            'outcome' => 'required|in:interested,not_interested,cnp,call_later,broker',
        ]);

        $telecallerId = $request->user()->id;
        $outcome = $request->input('outcome');
        
        // If task is pending, mark it as in_progress first (since telecaller is now working on it)
        if ($telecallerTask->status === 'pending') {
            $telecallerTask->update(['status' => 'in_progress']);
        }

        // Get or create CrmAssignment (e.g. when lead was assigned via CRM and only Task/TelecallerTask was created)
        $lead = Lead::find($telecallerTask->lead_id);
        $assignment = CrmAssignment::where('lead_id', $telecallerTask->lead_id)
            ->where('id', '>', app(\App\Services\LeadReopenService::class)->boundary($telecallerTask->lead_id)['crm_assignments'] ?? 0)
            ->where('assigned_to', $telecallerId)
            ->first();

        if (!$assignment && $lead) {
            $assignment = CrmAssignment::create([
                'lead_id' => $telecallerTask->lead_id,
                'customer_name' => $lead->name,
                'phone' => $lead->phone,
                'assigned_to' => $telecallerId,
                'assigned_by' => $telecallerTask->created_by ?? $telecallerId,
                'assigned_at' => $telecallerTask->created_at ?? now(),
                'call_status' => 'pending',
            ]);
        }

        if (!$assignment) {
            return response()->json([
                'error' => 'Assignment not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            switch ($outcome) {
                case 'interested':
                    // Don't complete the task yet - it will be completed when form is submitted
                    // Just mark as in_progress if it's pending, so user can fill the form
                    if ($telecallerTask->status === 'pending') {
                        $telecallerTask->update([
                            'status' => 'in_progress',
                            'outcome' => 'interested',
                            'notes' => $request->notes ?? 'Marked as interested - filling form',
                        ]);
                    } else {
                        $telecallerTask->update([
                            'outcome' => 'interested',
                            'notes' => $request->notes ?? 'Marked as interested - filling form',
                        ]);
                    }
                    
                    // Update lead status to connected
                    if (!$lead) {
                        $lead = Lead::find($telecallerTask->lead_id);
                    }
                    if ($lead) {
                        $lead->updateStatusIfAllowed('connected');
                    }
                    
                    DB::commit();
                    
                    // Return success with lead_id for modal (no redirect)
                    // Task will be completed when form is submitted in submitLeadFormForVerification
                    return response()->json([
                        'success' => true,
                        'outcome' => 'interested',
                        'message' => 'Lead marked as interested. Please fill the centralized form.',
                        'lead_id' => $telecallerTask->lead_id,
                        'lead_name' => $lead->name ?? '',
                        'lead_phone' => $lead->phone ?? '',
                        'task_id' => $telecallerTask->id,
                    ]);

                case 'not_interested':
                    $request->validate([
                        'remark' => 'nullable|string',
                    ]);
                    $this->telecallerService->markNotInterested($assignment->id, $telecallerId, $request->input('remark'));
                    $lead = Lead::find($telecallerTask->lead_id);
                    if ($lead) {
                        $lead->markAsOtherLead('not_interested', $telecallerId, $request->input('remark'));
                    }
                    $telecallerTask->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'outcome' => 'not_interested',
                        'notes' => $request->input('remark'),
                    ]);
                    break;

                case 'cnp':
                    $request->validate([
                        'remark' => 'nullable|string',
                    ]);
                    $this->telecallerService->markCnp($assignment->id, $telecallerId, $request->input('remark'));
                    // Don't complete task if CNP count < 2, task should remain pending for next call
                    if ($assignment->fresh()->cnp_count >= 2) {
                        $telecallerTask->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'outcome' => 'cnp',
                            'notes' => $request->input('remark'),
                        ]);
                    } else {
                        $telecallerTask->update([
                            'status' => 'pending',
                            'notes' => $request->input('remark'),
                        ]); // Reset to pending for next call
                    }
                    break;

                case 'call_later':
                    $request->validate([
                        'follow_up_date' => 'required|date|after_or_equal:today',
                        'follow_up_time' => 'required|string',
                        'follow_up_notes' => 'nullable|string',
                    ]);
                    $this->telecallerService->scheduleFollowUp(
                        $assignment->id,
                        $telecallerId,
                        $request->input('follow_up_date'),
                        $request->input('follow_up_time'),
                        $request->input('follow_up_notes')
                    );
                    // Update lead status to connected
                    $lead = Lead::find($telecallerTask->lead_id);
                    if ($lead) {
                        $lead->updateStatusIfAllowed('connected');
                    }
                    $telecallerTask->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'outcome' => 'call_later',
                        'notes' => $request->input('follow_up_notes'),
                    ]);
                    break;

                case 'broker':
                    $request->validate([
                        'remark' => 'required|string',
                    ]);
                    $this->telecallerService->markAsBroker($assignment->id, $telecallerId, $request->input('remark'));
                    // Update lead status to connected
                    $lead = Lead::find($telecallerTask->lead_id);
                    if ($lead) {
                        $lead->updateStatusIfAllowed('connected');
                    }
                    $telecallerTask->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'outcome' => 'broker',
                        'notes' => $request->input('remark'),
                    ]);
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Call outcome processed successfully',
                'task' => $telecallerTask->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Failed to process outcome: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get profile data
     */
    public function getProfile(Request $request)
    {
        $user = $request->user()->load('role', 'manager', 'telecallerProfile', 'userProfile');
        $leadOffProfile = $user->userProfile;
        
        // Get activity history (last 10 activities)
        $activityHistory = ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['action', 'ip_address', 'user_agent', 'created_at']);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture_url,
                'role' => $user->role->name ?? 'Sales Executive',
                'manager' => $user->manager ? $user->manager->name : null,
                'created_at' => $user->created_at ? $user->created_at->format('d M Y') : '-',
            ],
            'profile' => [
                'is_absent' => $leadOffProfile?->isCurrentlyAbsent() ?? false,
                'lead_off_enabled' => (bool) ($leadOffProfile?->is_absent ?? false),
                'has_scheduled_lead_off' => $leadOffProfile?->hasUpcomingLeadOffWindow() ?? false,
                'absent_reason' => $leadOffProfile?->absent_reason ?? null,
                'absent_until' => ($leadOffProfile?->lead_off_end_at ?? $leadOffProfile?->absent_until)?->format('Y-m-d H:i:s'),
                'lead_off_start_at' => $leadOffProfile?->lead_off_start_at?->format('Y-m-d H:i:s'),
                'lead_off_end_at' => ($leadOffProfile?->lead_off_end_at ?? $leadOffProfile?->absent_until)?->format('Y-m-d H:i:s'),
                'lead_off_source' => $leadOffProfile?->lead_off_source,
                'max_pending_leads' => $user->telecallerProfile?->max_pending_leads ?? null,
            ],
            'activity_history' => $activityHistory->map(function ($log) {
                return [
                    'action' => $log->action,
                    'ip' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                ];
            }),
        ]);
    }

    /**
     * Update profile (name, email and phone)
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        $user = $user->fresh(['role', 'manager']);
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role->name ?? 'Sales Executive',
                'manager' => $user->manager ? $user->manager->name : 'Not Assigned',
                'created_at' => $user->created_at ? $user->created_at->format('d M Y') : '-',
            ],
        ]);
    }

    /**
     * Upload profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        try {
            $request->validate([
                'profile_picture' => 'required|image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);

            $user = $request->user();

            // Delete old profile picture if exists
            if ($user->profile_picture) {
                $oldPath = $user->profile_picture;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Store new profile picture
            $file = $request->file('profile_picture');
            $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profiles', $filename, 'public');

            // Update user profile picture
            $user->update([
                'profile_picture' => $path,
            ]);

            // Refresh to get updated URL
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture uploaded successfully',
                'profile_picture' => $user->profile_picture_url,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
        $user->clearPasswordChangeRequirement();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Update availability status
     */
    public function updateAvailability(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_absent' => 'required|boolean',
            'absent_reason' => 'nullable|string|max:255',
            'mode' => 'nullable|in:normal,now,schedule,on',
            'absent_until' => 'nullable|date',
            'lead_off_start_at' => 'nullable|date',
            'lead_off_end_at' => 'nullable|date',
        ]);

        $validator->after(function ($validator) use ($request) {
            $isAbsent = $request->boolean('is_absent');
            $mode = $request->input('mode');
            $leadOffStartAt = $request->filled('lead_off_start_at') ? Carbon::parse($request->input('lead_off_start_at')) : null;
            $leadOffEndAt = $request->filled('lead_off_end_at') ? Carbon::parse($request->input('lead_off_end_at')) : null;
            $absentUntil = $request->filled('absent_until') ? Carbon::parse($request->input('absent_until')) : null;
            $now = now();

            if (!$isAbsent || $mode === 'on') {
                return;
            }

            if ($mode === 'schedule') {
                if (!$leadOffStartAt) {
                    $validator->errors()->add('lead_off_start_at', 'Lead Off From is required for a scheduled window.');
                }
                if (!$leadOffEndAt) {
                    $validator->errors()->add('lead_off_end_at', 'Lead Off Until is required for a scheduled window.');
                }
                if ($leadOffStartAt && $leadOffStartAt->lte($now)) {
                    $validator->errors()->add('lead_off_start_at', 'Lead Off From must be a future time for a scheduled window.');
                }
                if ($leadOffStartAt && $leadOffEndAt && $leadOffEndAt->lte($leadOffStartAt)) {
                    $validator->errors()->add('lead_off_end_at', 'Scheduled end time must be after start time.');
                }
                return;
            }

            $effectiveEndAt = $leadOffEndAt ?? $absentUntil;
            if ($effectiveEndAt && $effectiveEndAt->lte($now)) {
                $validator->errors()->add('absent_until', 'Lead Off Until must be after now.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all())->first() ?: 'Please correct the highlighted errors.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $leadOffStartAt = $request->lead_off_start_at ? Carbon::parse($request->lead_off_start_at) : null;
        $leadOffEndAt = $request->lead_off_end_at ? Carbon::parse($request->lead_off_end_at) : null;
        $absentUntil = $request->absent_until ? Carbon::parse($request->absent_until) : $leadOffEndAt;

        if ($request->boolean('is_absent') && !$leadOffStartAt) {
            $leadOffStartAt = now();
        }

        $profile = $this->userStatusService->toggleAbsentStatus(
            $user->id,
            $request->boolean('is_absent'),
            $request->absent_reason,
            $absentUntil,
            $leadOffStartAt,
            $leadOffEndAt,
            'self',
            $user->id
        )->fresh();

        return response()->json([
            'success' => true,
            'message' => $request->is_absent ? 'Lead off mode updated successfully' : 'Lead off mode disabled successfully',
            'profile' => [
                'is_absent' => $profile->isCurrentlyAbsent(),
                'lead_off_enabled' => (bool) $profile->is_absent,
                'has_scheduled_lead_off' => $profile->hasUpcomingLeadOffWindow(),
                'absent_reason' => $profile->absent_reason,
                'absent_until' => ($profile->lead_off_end_at ?? $profile->absent_until)?->format('Y-m-d H:i:s'),
                'lead_off_start_at' => $profile->lead_off_start_at?->format('Y-m-d H:i:s'),
                'lead_off_end_at' => ($profile->lead_off_end_at ?? $profile->absent_until)?->format('Y-m-d H:i:s'),
                'lead_off_source' => $profile->lead_off_source,
            ],
        ]);
    }

    /**
     * Get leads assigned to the telecaller
     */
    public function getLeads(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;
            $dateRange = $request->input('date_range', 'today');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');
            [$rangeStart, $rangeEnd] = $this->getDateRange($dateRange, $startDate, $endDate);

            \Log::info('Telecaller getLeads called', [
                'telecaller_id' => $telecallerId,
                'request_params' => $request->all(),
            ]);

            // First, let's check if there are any assignments for this telecaller
            $assignmentCount = DB::table('lead_assignments')
                ->where('assigned_to', $telecallerId)
                ->where('is_active', true)
                ->count();

            \Log::info('Assignment count for telecaller', [
                'telecaller_id' => $telecallerId,
                'assignment_count' => $assignmentCount,
            ]);

            // Query leads with active assignments to this telecaller within date range (assigned_at)
            $query = Lead::whereHas('assignments', function($q) use ($telecallerId, $rangeStart, $rangeEnd) {
                $q->where('assigned_to', $telecallerId)
                  ->where('is_active', true)
                  ->whereBetween('assigned_at', [$rangeStart, $rangeEnd]);
            })
            ->with(['assignments' => function($q) use ($telecallerId) {
                $q->where('assigned_to', $telecallerId)
                  ->where('is_active', true);
            }, 'assignments.assignedTo', 'creator'])
            ->latest();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('leads.name', 'like', "%{$search}%")
                      ->orWhere('leads.email', 'like', "%{$search}%")
                      ->orWhere('leads.phone', 'like', "%{$search}%")
                      ->orWhere('leads.city', 'like', "%{$search}%");
                });
            }

            // Filter by status
            if ($request->has('status') && $request->status) {
                $query->where('leads.status', $request->status);
            }

            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $leads = $query->paginate($perPage);

            // Log for debugging
            \Log::info('Telecaller Leads Query', [
                'telecaller_id' => $telecallerId,
                'total_leads' => $leads->total(),
                'current_page' => $leads->currentPage(),
                'leads_count' => $leads->count(),
            ]);

            // Format leads data
            $formattedLeads = $leads->map(function($lead) {
                $assignment = $lead->assignments->first();
                return [
                    'id' => $lead->id,
                    'name' => $lead->name ?? '-',
                    'phone' => $lead->phone ?? '-',
                    'email' => $lead->email ?? null,
                    'city' => $lead->city ?? null,
                    'state' => $lead->state ?? null,
                    'status' => $lead->status ?? 'new',
                    'last_contacted_at' => $lead->last_contacted_at ? $lead->last_contacted_at->format('Y-m-d H:i:s') : null,
                    'next_followup_at' => $lead->next_followup_at ? $lead->next_followup_at->format('Y-m-d H:i:s') : null,
                    'created_at' => $lead->created_at ? $lead->created_at->format('Y-m-d H:i:s') : null,
                    'assigned_at' => $assignment && $assignment->assigned_at ? $assignment->assigned_at->format('Y-m-d H:i:s') : ($lead->created_at ? $lead->created_at->format('Y-m-d H:i:s') : null),
                    'assigned_to_name' => $assignment && $assignment->assignedTo ? $assignment->assignedTo->name : 'Not Assigned',
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedLeads->values()->all(),
                'pagination' => [
                    'current_page' => $leads->currentPage(),
                    'per_page' => $leads->perPage(),
                    'total' => $leads->total(),
                    'last_page' => $leads->lastPage(),
                    'from' => $leads->firstItem(),
                    'to' => $leads->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Telecaller Leads Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load leads: ' . $e->getMessage(),
                'data' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 50,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ], 500);
        }
    }

    /**
     * Record call outcome for TelecallerTask or Task (mt_)
     */
    public function recordOutcome(Request $request, $taskId)
    {
        try {
            $telecallerId = $request->user()->id;
            
            $request->validate([
                'outcome' => 'required|in:interested,not_interested,cnp,call_again,block',
                'scheduled_at' => 'nullable|date',
                'retry_at' => 'nullable|date|after:now',
                'retry_minutes' => 'nullable|integer|min:1|max:10080', // Max 1 week (10080 minutes)
                'notes' => 'nullable|string',
            ], [
                'retry_at.after' => 'Retry time must be in the future',
                'retry_minutes.max' => 'Retry time cannot be more than 1 week in the future',
            ]);

            [$telecallerTask, $managerTask] = $this->resolveTaskForUser($taskId, $telecallerId);
            if ($managerTask) {
                $managerTask->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'notes' => ($managerTask->notes ? $managerTask->notes . "\n" : '') . 'Outcome: ' . $request->outcome . ($request->notes ? "\n" . $request->notes : ''),
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Task completed',
                    'task' => $managerTask->fresh(['lead']),
                ]);
            }

            $task = $telecallerTask;
            if (!$task) {
                return response()->json([
                    'success' => false,
                    'error' => 'Task not found',
                    'message' => 'Resource not found.',
                ], 404);
            }

            $task = $task->load(['lead', 'assignedTo']);
            $outcome = $request->outcome;
            $lead = $task->lead;

            DB::beginTransaction();

            switch ($outcome) {
                case 'not_interested':
                    $lead->markAsOtherLead('not_interested', $telecallerId, $request->notes);
                    $task->update([
                        'status' => 'completed',
                        'outcome' => 'not_interested',
                        'completed_at' => now(),
                        'notes' => $request->notes,
                    ]);
                    break;

                case 'cnp':
                    $lead->increment('cnp_count');
                    $cnpCount = $lead->fresh()->cnp_count;
                    
                    // If CNP count is less than 2, create a retry task at selected time
                    if ($cnpCount < 2) {
                        // Calculate scheduled time based on selection
                        $retryScheduledAt = null;
                        
                        if ($request->has('retry_at') && $request->retry_at) {
                            // Custom datetime provided
                            $retryScheduledAt = \Carbon\Carbon::parse($request->retry_at);
                        } elseif ($request->has('retry_minutes') && $request->retry_minutes) {
                            // Quick option (minutes) provided
                            $retryScheduledAt = now()->addMinutes($request->retry_minutes);
                        } else {
                            // Default: tomorrow (backward compatibility)
                            $retryScheduledAt = now()->addDay();
                        }
                        
                        // Get telecaller user (ensure relationship is loaded)
                        $telecallerUser = $task->assignedTo;
                        if (!$telecallerUser) {
                            $telecallerUser = User::find($telecallerId);
                        }
                        
                        // Ensure scheduled time is in future
                        if ($retryScheduledAt && $retryScheduledAt->isFuture()) {
                            if ($telecallerUser) {
                                $this->taskService->createCnpRetryTask($lead, $telecallerUser, $retryScheduledAt, $telecallerId);
                            }
                        } else {
                            // Fallback to default (tomorrow) if invalid time
                            if ($telecallerUser) {
                                $this->taskService->createCnpRetryTask($lead, $telecallerUser, now()->addDay(), $telecallerId);
                            }
                        }
                    }
                    // If CNP count reaches 2, task is completed automatically (no new task created)
                    
                    $task->update([
                        'status' => 'completed',
                        'outcome' => 'cnp',
                        'completed_at' => now(),
                        'notes' => $request->notes ?? "CNP count: {$cnpCount}",
                    ]);
                    break;

                case 'call_again':
                    if (!$request->scheduled_at) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Scheduled date and time is required for rescheduling.',
                        ], 400);
                    }
                    
                    // Check if original scheduled_at was within 10 minutes (notification would have been sent)
                    $originalScheduledAt = $task->scheduled_at;
                    $tenMinutesBeforeOriginal = $originalScheduledAt->copy()->subMinutes(10);
                    
                    // If notification was sent and we're rescheduling, cancel it
                    if ($task->notification_sent_at && now() >= $tenMinutesBeforeOriginal) {
                        // Delete related notifications
                        \App\Models\AppNotification::where('telecaller_task_id', $task->id)
                            ->where('user_id', $task->assigned_to)
                            ->where('type', 'call_reminder')
                            ->delete();
                        
                        // Reset notification_sent_at
                        $task->notification_sent_at = null;
                    }
                    
                    $task->update([
                        'status' => 'rescheduled',
                        'outcome' => 'rescheduled',
                        'scheduled_at' => $request->scheduled_at,
                        'notes' => $request->notes,
                        'notification_sent_at' => null, // Reset so new notification can be sent if needed
                    ]);
                    break;

                case 'block':
                    $lead->update([
                        'is_blocked' => true,
                        'blocked_at' => now(),
                        'blocked_reason' => $request->notes ?? 'Blocked by telecaller',
                    ]);
                    $task->update([
                        'status' => 'completed',
                        'outcome' => 'block',
                        'completed_at' => now(),
                        'notes' => $request->notes,
                    ]);
                    break;

                case 'interested':
                    // Update task status
                    $task->update([
                        'status' => 'completed',
                        'outcome' => 'interested',
                        'completed_at' => now(),
                        'notes' => $request->notes,
                    ]);
                    
                    // Update lead status to connected
                    $lead->updateStatusIfAllowed('connected');
                    
                    // Create a new task for filling the centralized form
                    try {
                        $newTask = $this->taskService->createCallingTask(
                            $lead,
                            $task->assignedTo,
                            $telecallerId
                        );
                        
                        // Mark this task with a special note for form filling
                        $newTask->update([
                            'notes' => 'Fill centralized lead requirement form',
                            'task_type' => 'calling',
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning("Failed to create form task for interested lead: " . $e->getMessage());
                    }
                    break;
            }

            DB::commit();

            // If interested, redirect to centralized form
            if ($outcome === 'interested') {
                return response()->json([
                    'success' => true,
                    'message' => 'Lead marked as interested. Please fill the centralized form.',
                    'redirect' => route('leads.edit', $lead->id),
                    'lead_id' => $lead->id,
                    'task' => $task->fresh(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Outcome recorded successfully',
                'task' => $task->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Record Outcome Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to record outcome: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create prospect from interested lead (for TelecallerTask flow)
     */
    public function createProspectFromTask(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;
            
            // Manually validate to ensure JSON response
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'lead_id' => 'required|exists:leads,id',
                'customer_name' => 'required|string|max:255',
                'phone' => 'required|string',
                'budget' => 'nullable|string',
                'preferred_location' => 'nullable|string',
                'size' => 'nullable|string',
                'purpose' => 'required|in:end_user,investment',
                'possession' => 'nullable|string',
                'remark' => 'required|string',
                'lead_score' => 'required|integer|min:1|max:5',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $lead = Lead::findOrFail($request->lead_id);
            $assignment = $lead->activeAssignments->first();
            $manager = $assignment?->assignedTo?->manager ?? $request->user()->manager;

            if (!$manager) {
                return response()->json([
                    'success' => false,
                    'message' => 'Manager not found for this lead.',
                ], 400);
            }

            // Check if a prospect already exists for this lead
            $existingProspect = Prospect::where('lead_id', $lead->id)
                ->where('id', '>', app(\App\Services\LeadReopenService::class)->boundary($lead->id)['prospects'] ?? 0)
                ->whereIn('verification_status', ['pending_verification', 'verified', 'approved', 'rejected'])
                ->latest()
                ->first();
            
            if ($existingProspect) {
                if (in_array($existingProspect->verification_status, ['verified', 'approved'], true)) {
                    $task = null;
                    if ($request->has('task_id')) {
                        $task = TelecallerTask::where('id', $request->task_id)
                            ->where('assigned_to', $telecallerId)
                            ->first();
                    }

                    $reopenResult = DB::transaction(function () use ($lead, $existingProspect, $request, $manager, $task) {
                        return $this->reopenVerifiedProspectForTelecallerManager(
                            $lead,
                            $existingProspect,
                            $request->user(),
                            $manager,
                            $task,
                            $request->input('remark')
                        );
                    });

                    return response()->json([
                        'success' => true,
                        'outcome' => 'duplicate_verified_reopened',
                        'message' => 'This customer already had a verified prospect. The lead has been assigned to your manager for follow-up.',
                        'lead_id' => $lead->id,
                        'manager_id' => $manager->id,
                        'manager_name' => $manager->name,
                        'existing_prospect' => [
                            'id' => $existingProspect->id,
                            'customer_name' => $existingProspect->customer_name,
                            'phone' => $existingProspect->phone,
                            'verification_status' => $existingProspect->verification_status,
                            'created_at' => $existingProspect->created_at ? $existingProspect->created_at->format('Y-m-d H:i:s') : null,
                        ],
                        'manager_task_id' => $reopenResult['manager_task_id'] ?? null,
                    ], 200);
                }

                \Log::warning('Duplicate prospect creation attempted in createProspectFromTask', [
                    'telecaller_id' => $telecallerId,
                    'lead_id' => $lead->id,
                    'existing_prospect_id' => $existingProspect->id,
                    'existing_prospect_status' => $existingProspect->verification_status,
                ]);
                
                $statusMessage = '';
                if ($existingProspect->verification_status === 'pending_verification') {
                    $statusMessage = 'This prospect is already pending verification';
                } elseif ($existingProspect->verification_status === 'verified') {
                    $statusMessage = 'This prospect has already been verified';
                } elseif ($existingProspect->verification_status === 'rejected') {
                    $statusMessage = 'This prospect was previously rejected';
                }
                
                return response()->json([
                    'success' => false,
                    'error' => 'duplicate',
                    'message' => 'A prospect already exists for this lead. ' . $statusMessage,
                    'existing_prospect' => [
                        'id' => $existingProspect->id,
                        'customer_name' => $existingProspect->customer_name,
                        'phone' => $existingProspect->phone,
                        'verification_status' => $existingProspect->verification_status,
                        'created_at' => $existingProspect->created_at ? $existingProspect->created_at->format('Y-m-d H:i:s') : null,
                    ],
                ], 409); // 409 Conflict status code
            }

            $prospect = Prospect::create([
                'lead_id' => $lead->id,
                'telecaller_id' => $telecallerId,
                'manager_id' => $manager->id,
                'customer_name' => $request->customer_name,
                'phone' => $request->phone,
                'budget' => $request->budget,
                'preferred_location' => $request->preferred_location,
                'size' => $request->size,
                'purpose' => $request->purpose,
                'possession' => $request->possession,
                'remark' => $request->remark,
                'lead_score' => $request->lead_score,
                'verification_status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $telecallerId,
                'created_by' => $telecallerId,
            ]);

            // Also update the task status if task_id is provided
            if ($request->has('task_id')) {
                $task = TelecallerTask::find($request->task_id);
                if ($task && $task->assigned_to === $telecallerId) {
                    $task->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'outcome' => 'interested',
                    ]);
                }
            }

            // Prospect is auto-verified, so move lead directly into verified prospect flow.
            $lead->updateStatusIfAllowed('verified_prospect');

            return response()->json([
                'success' => true,
                'message' => 'Prospect created successfully',
                'prospect' => $prospect->load('manager', 'telecaller'),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lead not found.',
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Create Prospect Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create prospect: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save call log from Flutter app
     */
    public function saveCallLog(Request $request)
    {
        try {
            $request->validate([
                'lead_id' => 'required|exists:leads,id',
                'phone_number' => 'required|string',
                'start_time' => 'required|date',
                'end_time' => 'nullable|date',
                'duration' => 'required|integer|min:0',
                'call_type' => 'required|in:incoming,outgoing',
                'task_id' => 'nullable|exists:telecaller_tasks,id',
                'status' => 'nullable|in:completed,missed,rejected,busy',
            ]);

            $telecallerId = $request->user()->id;

            $callLog = DB::table('call_logs')->insert([
                'telecaller_id' => $telecallerId,
                'lead_id' => $request->lead_id,
                'task_id' => $request->task_id,
                'phone_number' => $request->phone_number,
                'call_type' => $request->call_type,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'duration' => $request->duration,
                'status' => $request->status ?? 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Call log saved successfully',
            ]);
        } catch (\Exception $e) {
            \Log::error('Save Call Log Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save call log: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get call logs with filters
     */
    public function getCallLogs(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;

            $query = DB::table('call_logs')
                ->where('telecaller_id', $telecallerId)
                ->orderBy('start_time', 'desc');

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('start_time', '>=', $request->from_date);
            }
            if ($request->has('to_date')) {
                $query->where('start_time', '<=', $request->to_date);
            }

            // Filter by call type
            if ($request->has('call_type')) {
                $query->where('call_type', $request->call_type);
            }

            // Filter by lead_id
            if ($request->has('lead_id')) {
                $query->where('lead_id', $request->lead_id);
            }

            $perPage = min(100, max(1, (int) $request->get('per_page', 50)));
            $callLogs = $query->paginate($perPage);

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
        } catch (\Exception $e) {
            \Log::error('Get Call Logs Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load call logs: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get call statistics
     */
    public function getCallStatistics(Request $request)
    {
        try {
            $telecallerId = $request->user()->id;
            $today = Carbon::today();
            $startOfWeek = Carbon::now()->startOfWeek();

            // Today's statistics
            $todayStats = DB::table('call_logs')
                ->where('telecaller_id', $telecallerId)
                ->whereDate('start_time', $today)
                ->selectRaw('
                    COUNT(*) as total_calls,
                    SUM(CASE WHEN call_type = "incoming" THEN 1 ELSE 0 END) as incoming_calls,
                    SUM(CASE WHEN call_type = "outgoing" THEN 1 ELSE 0 END) as outgoing_calls,
                    SUM(duration) as total_talking_time,
                    AVG(duration) as average_duration
                ')
                ->first();

            // Calls per hour (today)
            $callsPerHour = DB::table('call_logs')
                ->where('telecaller_id', $telecallerId)
                ->whereDate('start_time', $today)
                ->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->pluck('count', 'hour')
                ->toArray();

            // This week's statistics
            $weekStats = DB::table('call_logs')
                ->where('telecaller_id', $telecallerId)
                ->where('start_time', '>=', $startOfWeek)
                ->selectRaw('
                    COUNT(*) as total_calls,
                    SUM(duration) as total_talking_time
                ')
                ->first();

            // Daily breakdown for this week
            $dailyBreakdown = DB::table('call_logs')
                ->where('telecaller_id', $telecallerId)
                ->where('start_time', '>=', $startOfWeek)
                ->selectRaw('DATE(start_time) as date, COUNT(*) as count, SUM(duration) as total_time')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'success' => true,
                'today' => [
                    'total_calls' => (int)($todayStats->total_calls ?? 0),
                    'incoming_calls' => (int)($todayStats->incoming_calls ?? 0),
                    'outgoing_calls' => (int)($todayStats->outgoing_calls ?? 0),
                    'total_talking_time' => (int)($todayStats->total_talking_time ?? 0),
                    'average_duration' => (int)($todayStats->average_duration ?? 0),
                    'calls_per_hour' => $callsPerHour,
                ],
                'this_week' => [
                    'total_calls' => (int)($weekStats->total_calls ?? 0),
                    'total_talking_time' => (int)($weekStats->total_talking_time ?? 0),
                    'daily_breakdown' => $dailyBreakdown,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Call Statistics Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get lead form data for modal when telecaller marks interested (TelecallerTask or Task mt_)
     */
    public function getLeadFormForModal(Request $request, $task)
    {
        try {
            $telecallerId = $request->user()->id;
            \Log::info('getLeadFormForModal called', ['task_id' => $task, 'telecaller_id' => $telecallerId]);
            
            [$telecallerTask, $managerTask] = $this->resolveTaskForUser($task, $telecallerId);
            $taskModel = $telecallerTask ?? $managerTask;
            
            if (!$taskModel) {
                \Log::warning('Task not found', ['task_id' => $task]);
                return response()->json([
                    'success' => false,
                    'error' => 'Task not found',
                    'message' => 'Resource not found.',
                ], 404);
            }
            
            $lead = $taskModel->lead;
            if (!$lead) {
                \Log::warning('Lead not found for task', ['task_id' => $task, 'lead_id' => $taskModel->lead_id]);
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found for this task',
                ], 404);
            }
            
            // Load form field values
            $lead->load('formFieldValues');
            
            // Get existing field values
            $existingValues = $lead->getFormFieldsArray();
            
            // Get visible fields for telecaller role (with full config)
            $visibleFields = LeadFormField::active()
                ->visibleToRole('sales_executive')
                ->orderBy('display_order')
                ->get()
                ->map(function($field) {
                    return [
                        'key' => $field->field_key,
                        'label' => $field->field_label,
                        'type' => $field->field_type,
                        'required' => $field->is_required,
                        'options' => $field->options ?? [],
                        'dependent_field' => $field->dependent_field,
                        'dependent_conditions' => $field->dependent_conditions,
                        'placeholder' => $field->placeholder,
                        'help_text' => $field->help_text,
                        'display_order' => $field->display_order,
                    ];
                })
                ->values() // Ensure indexed array
                ->all(); // Convert to plain array
            
            \Log::info('Lead form data retrieved successfully', [
                'lead_id' => $lead->id,
                'fields_count' => count($visibleFields),
                'form_values_count' => count($existingValues),
                'fields' => $visibleFields
            ]);
            
            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'lead_phone' => $lead->phone,
                'lead_email' => $lead->email,
                'task_id' => $taskModel instanceof Task ? 'mt_' . $taskModel->id : $taskModel->id,
                'form_values' => $existingValues,
                'form_fields' => $visibleFields, // Now guaranteed to be array
            ]);
        } catch (\Exception $e) {
            \Log::error('Get Lead Form Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'task_id' => $task ?? null,
                'telecaller_id' => $request->user()->id ?? null
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to load lead form: ' . $e->getMessage(),
                'message' => 'An error occurred while loading the form. Please try again.',
            ], 500);
        }
    }

    /**
     * Submit lead form for verification (create prospect and send to manager) (TelecallerTask or Task mt_)
     */
    public function submitLeadFormForVerification(Request $request, $taskId)
    {
        try {
            $telecallerId = $request->user()->id;
            
            [$telecallerTask, $managerTask] = $this->resolveTaskForUser($taskId, $telecallerId);
            $taskModel = $telecallerTask ?? $managerTask;
            
            if (!$taskModel) {
                return response()->json([
                    'success' => false,
                    'error' => 'Task not found',
                    'message' => 'Resource not found.',
                ], 404);
            }
            
            $lead = $taskModel->lead;
            if (!$lead) {
                return response()->json([
                    'success' => false,
                    'error' => 'Lead not found',
                ], 404);
            }
            
            // Validate basic fields (name and phone - always required)
            $validationRules = [
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
            ];
            
            // Get visible fields for telecaller role
            $visibleFields = LeadFormField::active()
                ->visibleToRole('sales_executive')
                ->orderBy('display_order')
                ->get();
            
            // Add dynamic field validation
            foreach ($visibleFields as $field) {
                if ($field->is_required) {
                    $rule = ['required'];
                } else {
                    $rule = ['nullable'];
                }
                
                // Add field type validation
                switch ($field->field_type) {
                    case 'email':
                        $rule[] = 'email';
                        break;
                    case 'number':
                        $rule[] = 'numeric';
                        break;
                    case 'date':
                        $rule[] = 'date';
                        break;
                    case 'time':
                        $rule[] = 'date_format:H:i';
                        break;
                }
                
                $validationRules[$field->field_key] = $rule;
            }
            
            // Special validation for conditional fields (follow-up date/time)
            if ($request->has('final_status') && $request->final_status === 'Follow Up') {
                // Note: final_status is sales_executive field, so won't be in telecaller fields
                // But handle it if it comes through
                if ($request->has('follow_up_date')) {
                    $validationRules['follow_up_date'] = ['required', 'date'];
                    $validationRules['follow_up_time'] = ['required', 'date_format:H:i'];
                }
            }
            
            $validated = $request->validate($validationRules);
            
            DB::beginTransaction();
            
            try {
                // Update basic lead fields (name and phone)
                $lead->name = $validated['name'];
                $lead->phone = $validated['phone'];
                $lead->save();
                
                // Save dynamic form field values
                foreach ($visibleFields as $field) {
                    if ($request->has($field->field_key)) {
                        $value = $request->input($field->field_key);
                        // Only save if value is not empty or if it's a required field
                        if (!empty($value) || $field->is_required) {
                            $lead->setFormFieldValue($field->field_key, $value ?? '', $telecallerId);
                        }
                    }
                }
                
                // Mark form as filled by telecaller
                $lead->form_filled_by_telecaller = true;
                $lead->save();
                
                // Get manager for prospect assignment
                // Priority: assignment's assigned user's manager > telecaller's manager
                $assignment = $lead->activeAssignments->first();
                $telecaller = $request->user();
                
                // Load manager relationship if not loaded
                if (!$telecaller->relationLoaded('manager')) {
                    $telecaller->load('manager');
                }
                
                $manager = null;
                if ($assignment && $assignment->assignedTo) {
                    $assignedUser = $assignment->assignedTo;
                    if (!$assignedUser->relationLoaded('manager')) {
                        $assignedUser->load('manager');
                    }
                    $manager = $assignedUser->manager;
                }
                
                // Fallback to telecaller's manager
                if (!$manager) {
                    $manager = $telecaller->manager;
                }
                
                if (!$manager) {
                    \Log::error('Manager not found for prospect creation', [
                        'telecaller_id' => $telecallerId,
                        'lead_id' => $lead->id,
                        'assignment_id' => $assignment?->id,
                    ]);
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'error' => 'Manager not found for this lead. Please ensure the telecaller has a manager assigned.',
                    ], 400);
                }
                
                \Log::info('Prospect creation - Manager assigned', [
                    'telecaller_id' => $telecallerId,
                    'manager_id' => $manager->id,
                    'manager_name' => $manager->name,
                    'lead_id' => $lead->id,
                ]);
                
                // Check if a prospect already exists for this lead
                $existingProspect = Prospect::where('lead_id', $lead->id)
                    ->where('id', '>', app(\App\Services\LeadReopenService::class)->boundary($lead->id)['prospects'] ?? 0)
                    ->whereIn('verification_status', ['pending_verification', 'verified', 'approved', 'rejected'])
                    ->latest()
                    ->first();
                
                if ($existingProspect) {
                    if (in_array($existingProspect->verification_status, ['verified', 'approved'], true)) {
                        $reopenResult = $this->reopenVerifiedProspectForTelecallerManager(
                            $lead,
                            $existingProspect,
                            $telecaller,
                            $manager,
                            $taskModel,
                            $request->input('remark')
                        );

                        DB::commit();

                        return response()->json([
                            'success' => true,
                            'outcome' => 'duplicate_verified_reopened',
                            'message' => 'This customer already had a verified prospect. The lead has been assigned to your manager for follow-up.',
                            'lead_id' => $lead->id,
                            'manager_id' => $manager->id,
                            'manager_name' => $manager->name,
                            'existing_prospect' => [
                                'id' => $existingProspect->id,
                                'customer_name' => $existingProspect->customer_name,
                                'phone' => $existingProspect->phone,
                                'verification_status' => $existingProspect->verification_status,
                                'created_at' => $existingProspect->created_at ? $existingProspect->created_at->format('Y-m-d H:i:s') : null,
                            ],
                            'manager_task_id' => $reopenResult['manager_task_id'] ?? null,
                        ], 200);
                    }

                    DB::rollBack();
                    \Log::warning('Duplicate prospect creation attempted', [
                        'telecaller_id' => $telecallerId,
                        'lead_id' => $lead->id,
                        'existing_prospect_id' => $existingProspect->id,
                        'existing_prospect_status' => $existingProspect->verification_status,
                    ]);
                    
                    $statusMessage = '';
                    if ($existingProspect->verification_status === 'pending_verification') {
                        $statusMessage = 'This prospect is already pending verification';
                    } elseif ($existingProspect->verification_status === 'verified') {
                        $statusMessage = 'This prospect has already been verified';
                    } elseif ($existingProspect->verification_status === 'rejected') {
                        $statusMessage = 'This prospect was previously rejected';
                    }
                    
                    return response()->json([
                        'success' => false,
                        'error' => 'duplicate',
                        'message' => 'A prospect already exists for this lead. ' . $statusMessage,
                        'existing_prospect' => [
                            'id' => $existingProspect->id,
                            'customer_name' => $existingProspect->customer_name,
                            'phone' => $existingProspect->phone,
                            'verification_status' => $existingProspect->verification_status,
                            'created_at' => $existingProspect->created_at ? $existingProspect->created_at->format('Y-m-d H:i:s') : null,
                        ],
                    ], 409); // 409 Conflict status code
                }
                
                // Map form field values to prospect fields
                $category = $request->input('category');
                $preferredLocation = $request->input('preferred_location');
                $type = $request->input('type');
                $purposeRaw = $request->input('purpose');
                $possession = $request->input('possession');
                $budget = $request->input('budget');
                
                // Map purpose form values to prospect format
                // Form: 'End Use', 'Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use', 'N.A'
                // Prospect: stores as string (no strict enum, but prefer 'end_user' or 'investment' format for consistency)
                $purpose = $purposeRaw;
                if ($purposeRaw === 'End Use') {
                    $purpose = 'end_user';
                } elseif (in_array($purposeRaw, ['Short Term Investment', 'Long Term Investment', 'Rental Income', 'Investment + End Use'])) {
                    $purpose = 'investment';
                } elseif ($purposeRaw === 'N.A' || empty($purposeRaw)) {
                    $purpose = null;
                }
                
                // Create prospect with mapped values
                $prospect = Prospect::create([
                    'lead_id' => $lead->id,
                    'telecaller_id' => $telecallerId,
                    'manager_id' => $manager->id,
                    'assigned_manager' => $manager->id,
                    'customer_name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'budget' => $budget,
                    'preferred_location' => $preferredLocation,
                    'purpose' => $purpose,
                    'possession' => $possession,
                    'remark' => $request->input('remark') ?? 'Sent for verification via centralized form',
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                    'verified_by' => $telecallerId,
                    'created_by' => $telecallerId,
                ]);
                
                \Log::info('Prospect created successfully', [
                    'prospect_id' => $prospect->id,
                    'telecaller_id' => $telecallerId,
                    'manager_id' => $manager->id,
                    'assigned_manager' => $manager->id,
                    'verification_status' => $prospect->verification_status,
                    'lead_id' => $lead->id,
                ]);
                
                // Complete the task (TelecallerTask or Task) - remove from pending/in_progress lists
                $updateData = [
                    'status' => 'completed',
                    'completed_at' => now(),
                    'notes' => ($taskModel->notes ? $taskModel->notes . "\n" : '') . 'Sent for verification via centralized form',
                ];
                if ($taskModel instanceof TelecallerTask) {
                    $updateData['outcome'] = 'interested';
                }
                $taskModel->update($updateData);
                
                \Log::info('Task completed after form submission', [
                    'task_id' => $taskModel instanceof Task ? 'mt_' . $taskModel->id : $taskModel->id,
                    'lead_id' => $lead->id,
                    'telecaller_id' => $telecallerId,
                    'prospect_id' => $prospect->id,
                ]);
                
                // Prospect is auto-verified, so move lead directly into verified prospect flow.
                $lead->updateStatusIfAllowed('verified_prospect');
                
                DB::commit();
                
                return response()->json([
                    'success' => true,
                    'message' => 'Lead requirements saved successfully',
                    'prospect' => $prospect->load('manager', 'telecaller'),
                    'lead_id' => $lead->id,
                    'task_id' => $taskModel instanceof Task ? 'mt_' . $taskModel->id : $taskModel->id,
                    'task_status' => 'completed', // Indicate task is now completed
                    'task_completed' => true, // Flag for frontend to refresh list
                ], 200);
                
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Submit Lead Form for Verification Error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Submit Lead Form for Verification Error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to submit form: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get site visits eligible for incentive (Telecaller's prospect's site visits)
     */
    public function getEligibleSiteVisitsForIncentive(Request $request)
    {
        $user = $request->user();

        if (!$user->isTelecaller()) {
            return response()->json(['message' => 'Forbidden. Only Sales Executives can access this.'], 403);
        }

        try {
            // Get all prospects created by this telecaller
            $prospectIds = Prospect::where('telecaller_id', $user->id)
                ->pluck('lead_id')
                ->filter()
                ->unique()
                ->toArray();

            if (empty($prospectIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            // Get site visits for these leads that are verified and don't have incentive yet
            $siteVisits = SiteVisit::whereIn('lead_id', $prospectIds)
                ->where('verification_status', 'verified')
                ->where('status', 'completed')
                ->with(['lead', 'creator'])
                ->get()
                ->filter(function ($siteVisit) use ($user) {
                    // Check if incentive already requested
                    $existingIncentive = Incentive::where('site_visit_id', $siteVisit->id)
                        ->where('type', 'site_visit')
                        ->where('user_id', $user->id)
                        ->first();
                    
                    return !$existingIncentive || $existingIncentive->status === 'rejected';
                })
                ->map(function ($siteVisit) {
                    return [
                        'id' => $siteVisit->id,
                        'customer_name' => $siteVisit->customer_name ?? ($siteVisit->lead->name ?? 'N/A'),
                        'phone' => $siteVisit->phone ?? ($siteVisit->lead->phone ?? 'N/A'),
                        'completed_at' => $siteVisit->completed_at ? $siteVisit->completed_at->toIso8601String() : null,
                        'verified_at' => $siteVisit->verified_at ? $siteVisit->verified_at->toIso8601String() : null,
                        'project' => $siteVisit->project ?? 'N/A',
                        'budget_range' => $siteVisit->budget_range ?? 'N/A',
                        'lead' => $siteVisit->lead ? [
                            'id' => $siteVisit->lead->id,
                            'name' => $siteVisit->lead->name,
                        ] : null,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $siteVisits,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting eligible site visits for incentive: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to get eligible site visits.',
            ], 500);
        }
    }

    /**
     * Request site visit incentive (Telecaller)
     */
    public function requestSiteVisitIncentive(Request $request, SiteVisit $siteVisit)
    {
        $user = $request->user();

        if (!$user->isTelecaller()) {
            return response()->json(['message' => 'Forbidden. Only Sales Executives can request site visit incentives.'], 403);
        }

        // Check if site visit is verified
        if ($siteVisit->verification_status !== 'verified') {
            return response()->json([
                'success' => false,
                'message' => 'Site visit must be verified before requesting incentive.',
            ], 422);
        }

        // Check if this is Telecaller's prospect's site visit
        $prospect = Prospect::where('lead_id', $siteVisit->lead_id)
            ->where('telecaller_id', $user->id)
            ->latest('created_at')
            ->first();

        if (!$prospect) {
            return response()->json([
                'success' => false,
                'message' => 'This site visit is not eligible for incentive. Only your prospect\'s site visits are eligible.',
            ], 403);
        }

        // Check if incentive already requested
        $existingIncentive = Incentive::where('site_visit_id', $siteVisit->id)
            ->where('type', 'site_visit')
            ->where('user_id', $user->id)
            ->first();

        $isResubmission = $existingIncentive && $existingIncentive->status === 'rejected';

        if ($existingIncentive && !$isResubmission) {
            return response()->json([
                'success' => false,
                'message' => 'Incentive already requested for this site visit.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($isResubmission) {
                $existingIncentive->fill([
                    'amount' => $request->input('amount'),
                    'status' => 'pending_sales_head',
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
                $incentive = $existingIncentive->fresh(['siteVisit.lead', 'user']);
            } else {
                // Create incentive record
                $incentive = Incentive::create([
                    'site_visit_id' => $siteVisit->id,
                    'user_id' => $user->id,
                    'type' => 'site_visit',
                    'amount' => $request->input('amount'),
                    'status' => 'pending_sales_head',
                ]);
            }

            // Update site visit incentive_amount
            $siteVisit->incentive_amount = $request->input('amount');
            $siteVisit->save();

            return response()->json([
                'success' => true,
                'message' => $isResubmission
                    ? 'Site visit incentive resubmitted successfully. Awaiting verification.'
                    : 'Site visit incentive requested successfully. Awaiting verification.',
                'data' => $incentive->fresh(['siteVisit.lead', 'user']),
            ]);
        } catch (\Exception $e) {
            Log::error('Error requesting site visit incentive: ' . $e->getMessage(), [
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

    private function reopenVerifiedProspectForTelecallerManager(
        Lead $lead,
        Prospect $existingProspect,
        User $telecaller,
        User $manager,
        TelecallerTask|Task|null $taskModel = null,
        ?string $remark = null
    ): array {
        $previousOwnerId = $lead->activeAssignments()
            ->where('assigned_to', '!=', $manager->id)
            ->value('assigned_to');

        $note = trim(sprintf(
            'Re-opened from telecaller interest. Existing verified prospect #%s was found. Telecaller: %s.%s',
            $existingProspect->id,
            $telecaller->name,
            $remark ? ' Remark: ' . $remark : ''
        ));

        if ($taskModel) {
            $taskNotes = trim(($taskModel->notes ? $taskModel->notes . "\n" : '') . $note);
            $taskUpdate = [
                'status' => 'completed',
                'completed_at' => now(),
                'notes' => $taskNotes,
            ];

            if ($taskModel instanceof TelecallerTask) {
                $taskUpdate['outcome'] = 'interested';
            }

            $taskModel->update($taskUpdate);
        }

        $lead->assignments()->where('is_active', true)->update([
            'is_active' => false,
            'unassigned_at' => now(),
        ]);

        $assignment = LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $manager->id,
            'assigned_by' => $telecaller->id,
            'assignment_type' => 'primary',
            'assignment_method' => 'manual',
            'notes' => $note,
            'assigned_at' => now(),
            'is_active' => true,
        ]);

        $lead->markAsFreshTransfer(
            $previousOwnerId ? (int) $previousOwnerId : $telecaller->id,
            $manager->id,
            $telecaller->id,
            $note
        );

        $managerTask = Task::where('lead_id', $lead->id)
            ->where('assigned_to', $manager->id)
            ->where('type', 'phone_call')
            ->whereIn('status', Task::OPEN_STATUSES)
            ->latest('id')
            ->first();

        if (!$managerTask) {
            $managerTask = Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $manager->id,
                'type' => 'phone_call',
                'title' => 'Re-opened lead: ' . ($lead->name ?: $lead->phone),
                'description' => "{$telecaller->name} marked this customer as interested. An existing verified prospect was found, so the lead has been assigned to you for follow-up.",
                'status' => 'pending',
                'scheduled_at' => now()->addMinutes(10),
                'created_by' => $telecaller->id,
                'notes' => $note,
            ]);
        }

        $actionUrl = route('leads.show', [
            'lead' => $lead->id,
            'back' => route('sales-manager.tasks'),
            'open_task' => $managerTask->id,
            'task_category' => 'fresh_lead',
        ]);

        AppNotification::create([
            'user_id' => $manager->id,
            'type' => AppNotification::TYPE_NEW_LEAD,
            'title' => 'Re-opened Lead Assigned',
            'message' => "{$telecaller->name} marked {$lead->name} as interested. This customer already had a verified prospect, so the lead has been assigned to you for follow-up.",
            'action_type' => AppNotification::ACTION_LEAD,
            'action_url' => $actionUrl,
            'data' => [
                'kind' => 'duplicate_verified_reopened',
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'telecaller_id' => $telecaller->id,
                'telecaller_name' => $telecaller->name,
                'manager_id' => $manager->id,
                'existing_prospect_id' => $existingProspect->id,
                'manager_task_id' => $managerTask->id,
            ],
        ]);

        ActivityLog::create([
            'user_id' => $telecaller->id,
            'action' => 'duplicate_verified_prospect_reopened',
            'model_type' => Lead::class,
            'model_id' => $lead->id,
            'description' => "Existing verified prospect lead reopened and assigned to {$manager->name}.",
            'old_values' => [
                'previous_owner_id' => $previousOwnerId,
                'existing_prospect_id' => $existingProspect->id,
                'existing_prospect_status' => $existingProspect->verification_status,
            ],
            'new_values' => [
                'assigned_to' => $manager->id,
                'assignment_id' => $assignment->id,
                'manager_task_id' => $managerTask->id,
                'status' => Lead::STATUS_FRESH_TRANSFER,
            ],
        ]);

        return [
            'assignment_id' => $assignment->id,
            'manager_task_id' => $managerTask->id,
            'action_url' => $actionUrl,
        ];
    }
}
