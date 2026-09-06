<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Models\FcmToken;
use App\Models\Lead;
use App\Models\PushSubscription;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Services\LeadAssignmentWorkflowService;
use App\Services\NotificationService;
use App\Support\TestLeadNameGenerator;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class TestLeadDeliveryController extends Controller
{
    public function __construct(
        private readonly LeadAssignmentWorkflowService $leadAssignmentWorkflowService,
        private readonly NotificationService $notificationService
    ) {
    }

    public function index()
    {
        $users = User::with('role:id,name,slug')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'is_active']);

        $fcmCounts = FcmToken::selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $pushCounts = PushSubscription::selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        foreach ($users as $user) {
            $user->fcm_tokens_count = (int) ($fcmCounts[$user->id] ?? 0);
            $user->push_subscriptions_count = (int) ($pushCounts[$user->id] ?? 0);
        }

        return view('test.lead-delivery', [
            'users' => $users,
            'result' => session('leadDeliveryResult'),
            'notificationTypes' => $this->notificationTypes(),
        ]);
    }

    public function indexReminder()
    {
        $users = User::with('role:id,name,slug')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'is_active']);

        $fcmCounts = FcmToken::selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $pushCounts = PushSubscription::selectRaw('user_id, COUNT(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        foreach ($users as $user) {
            $user->fcm_tokens_count = (int) ($fcmCounts[$user->id] ?? 0);
            $user->push_subscriptions_count = (int) ($pushCounts[$user->id] ?? 0);
        }

        return view('test.reminder-delivery', [
            'users' => $users,
            'result' => session('reminderDeliveryResult'),
            'reminderTypes' => $this->reminderTypes(),
        ]);
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'test_note' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUser = User::with('role:id,name,slug')->findOrFail($validated['user_id']);
        abort_unless($targetUser->is_active, 422, 'Selected user is inactive.');

        $actor = $request->user();
        $lead = null;
        $workflow = null;
        $error = null;

        try {
            $lead = $this->createDummyLead($actor->id, $validated['test_note'] ?? null);

            $workflow = $this->leadAssignmentWorkflowService->assignLead(
                $lead,
                $targetUser->id,
                $actor->id,
                $validated['test_note'] ?? 'Notification delivery test',
                true,
                true
            );
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        $result = $this->buildResultPayload(
            $actor,
            $targetUser->fresh('role'),
            $lead?->fresh(['activeAssignments.assignedTo', 'creator']),
            $workflow,
            $validated['test_note'] ?? null,
            $error
        );

        return redirect()
            ->route('test.lead-delivery')
            ->withInput()
            ->with($error ? 'error' : 'success', $error ? 'Lead delivery test failed.' : 'Lead delivery test executed.')
            ->with('leadDeliveryResult', $result);
    }

    public function sendNotification(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'notification_type' => ['required', 'string'],
            'test_note' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUser = User::with('role:id,name,slug')->findOrFail($validated['user_id']);
        abort_unless($targetUser->is_active, 422, 'Selected user is inactive.');

        $types = $this->notificationTypes();
        $notificationType = $validated['notification_type'];
        abort_unless(isset($types[$notificationType]), 422, 'Unsupported notification type selected.');

        $definition = $types[$notificationType];
        $timestamp = now()->format('d M Y, h:i A');
        $note = trim((string) ($validated['test_note'] ?? ''));

        $title = $definition['title'];
        $message = $definition['message_prefix'] . ' at ' . $timestamp;
        if ($note !== '') {
            $message .= ' | Note: ' . $note;
        }

        $actionUrl = $this->resolveTestActionUrl($targetUser, $notificationType);

        $this->notificationService->sendTestNotification(
            $targetUser,
            $notificationType,
            $title,
            $message,
            $actionUrl,
            [
                'test_note' => $note,
                'target_user_id' => $targetUser->id,
                'target_user_name' => $targetUser->name,
            ]
        );

        return redirect()
            ->route('test.lead-delivery')
            ->withInput()
            ->with('success', $title . ' test notification sent to ' . $targetUser->name . '.');
    }

    public function sendReminder(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'reminder_type' => ['required', 'string'],
            'stage_minutes' => ['required', 'integer', 'in:15,5'],
            'test_note' => ['nullable', 'string', 'max:500'],
        ]);

        $targetUser = User::with('role:id,name,slug')->findOrFail($validated['user_id']);
        abort_unless($targetUser->is_active, 422, 'Selected user is inactive.');

        $types = $this->reminderTypes();
        $reminderType = $validated['reminder_type'];
        abort_unless(isset($types[$reminderType]), 422, 'Unsupported reminder type selected.');

        $stageMinutes = (int) $validated['stage_minutes'];
        $definition = $types[$reminderType];
        $timestamp = now()->format('d M Y, h:i A');
        $note = trim((string) ($validated['test_note'] ?? ''));

        $title = $definition['title'];
        $message = "{$definition['message_prefix']} for lead \"TEST REMINDER\" at {$timestamp}. Stage: {$stageMinutes} min before.";
        if ($note !== '') {
            $message .= ' | Note: ' . $note;
        }

        $actionUrl = $this->resolveReminderTestActionUrl($targetUser, $reminderType);

        $notification = $this->notificationService->sendTestNotification(
            $targetUser,
            $reminderType,
            $title,
            $message,
            $actionUrl,
            [
                'minutes_before' => $stageMinutes,
                'stage' => $stageMinutes === 15 ? '15_min' : '5_min',
                'kind' => $definition['kind'],
                'test_note' => $note,
                'target_user_id' => $targetUser->id,
                'target_user_name' => $targetUser->name,
            ]
        );

        return redirect()
            ->route('test.reminder-delivery')
            ->withInput()
            ->with('success', $title . ' test reminder sent to ' . $targetUser->name . '.')
            ->with('reminderDeliveryResult', [
                'generated_at' => now()->toDateTimeString(),
                'user' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'role' => $targetUser->role->name ?? $targetUser->role->slug ?? 'Unknown',
                    'fcm_tokens_count' => FcmToken::where('user_id', $targetUser->id)->count(),
                    'push_subscriptions_count' => PushSubscription::where('user_id', $targetUser->id)->count(),
                ],
                'notification' => [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'action_url' => $notification->action_url,
                ],
                'stage_minutes' => $stageMinutes,
                'test_note' => $note,
            ]);
    }

    private function createDummyLead(int $createdBy, ?string $testNote = null): Lead
    {
        $stamp = now()->format('YmdHis');
        $suffix = Str::upper(Str::random(4));
        $phone = '9' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
        $note = trim(collect([
            'Notification delivery test lead.',
            $testNote,
        ])->filter()->implode(' '));

        return Lead::create([
            'name' => TestLeadNameGenerator::make(),
            'phone' => $phone,
            'email' => "test-lead-{$stamp}-" . strtolower($suffix) . '@example.com',
            'status' => 'new',
            'source' => Lead::normalizeSource('other'),
            'notes' => $note,
            'created_by' => $createdBy,
        ]);
    }

    private function buildResultPayload(
        User $actor,
        User $targetUser,
        ?Lead $lead,
        ?array $workflow,
        ?string $testNote,
        ?string $error
    ): array {
        $leadId = $lead?->id;
        $task = $leadId ? $this->resolveTask($leadId, $targetUser) : null;
        $databaseNotification = $leadId ? $this->resolveDatabaseNotification($targetUser, $leadId) : null;
        $appNotification = $leadId ? $this->resolveAppNotification($targetUser, $leadId) : null;
        $fcmCount = FcmToken::where('user_id', $targetUser->id)->count();
        $pushCount = PushSubscription::where('user_id', $targetUser->id)->count();
        $actionUrl = data_get($databaseNotification?->data, 'action_url')
            ?: $appNotification?->action_url
            ?: data_get($workflow, 'task_result.action_url');

        return [
            'generated_at' => now()->toDateTimeString(),
            'queue_connection' => config('queue.default'),
            'error' => $error,
            'test_note' => $testNote,
            'actor' => [
                'id' => $actor->id,
                'name' => $actor->name,
            ],
            'lead' => [
                'id' => $leadId,
                'name' => $lead?->name,
                'phone' => $lead?->phone,
                'email' => $lead?->email,
                'created_at' => optional($lead?->created_at)->toDateTimeString(),
                'show_url' => $leadId ? route('leads.show', $leadId) : null,
            ],
            'assignment' => [
                'exists' => (bool) optional($lead?->activeAssignments?->first()),
                'assignment_id' => data_get($workflow, 'assignment_id'),
                'assigned_to' => $targetUser->name,
                'assigned_to_role' => $targetUser->role->name ?? $targetUser->role->slug ?? 'Unknown',
                'assigned_by' => $actor->name,
                'event_dispatched' => (bool) data_get($workflow, 'event_dispatched', false),
                'old_owner_ids' => data_get($workflow, 'old_owner_ids', []),
                'transferred_counts' => data_get($workflow, 'transferred_counts', []),
            ],
            'task' => [
                'expected_skip' => $targetUser->isAdmin(),
                'skip_reason' => $targetUser->isAdmin() ? 'Admin is excluded from auto task creation by design.' : null,
                'task_type' => data_get($workflow, 'task_result.task_type') ?? data_get($task, 'task_type'),
                'task_id' => data_get($workflow, 'task_result.task_id') ?? data_get($task, 'id'),
                'task_status' => data_get($task, 'status'),
                'task_url' => data_get($workflow, 'task_result.action_url') ?: data_get($task, 'url'),
                'outcome' => $this->resolveTaskOutcome($workflow, $task, $targetUser),
                'task_error' => data_get($workflow, 'task_error'),
            ],
            'notifications' => [
                'database_present' => (bool) $databaseNotification,
                'database_id' => $databaseNotification?->id,
                'database_message' => data_get($databaseNotification?->data, 'message'),
                'database_type' => $databaseNotification?->type,
                'app_present' => (bool) $appNotification,
                'app_id' => $appNotification?->id,
                'app_title' => $appNotification?->title,
                'app_message' => $appNotification?->message,
                'action_url' => $actionUrl,
            ],
            'push' => [
                'fcm_tokens_count' => $fcmCount,
                'push_subscriptions_count' => $pushCount,
                'status' => $this->resolvePushStatus($targetUser, $databaseNotification, $appNotification, $fcmCount, $pushCount),
            ],
            'log_tail' => $this->tailRelevantLogs($leadId, $targetUser->id),
        ];
    }

    private function resolveTask(int $leadId, User $targetUser): ?array
    {
        $telecallerTask = TelecallerTask::where('lead_id', $leadId)
            ->where('assigned_to', $targetUser->id)
            ->latest('id')
            ->first();

        if ($telecallerTask) {
            return [
                'id' => $telecallerTask->id,
                'status' => $telecallerTask->status,
                'task_type' => 'telecaller_task',
                'url' => route('telecaller.tasks') . '?status=pending&task_id=' . $telecallerTask->id,
            ];
        }

        $task = Task::where('lead_id', $leadId)
            ->where('assigned_to', $targetUser->id)
            ->where('type', 'phone_call')
            ->latest('id')
            ->first();

        if (!$task) {
            return null;
        }

        return [
            'id' => $task->id,
            'status' => $task->status,
            'task_type' => 'task',
            'url' => url('/tasks?status=pending&task_id=' . $task->id),
        ];
    }

    private function resolveDatabaseNotification(User $targetUser, int $leadId): ?DatabaseNotification
    {
        return $targetUser->notifications()
            ->where('type', LeadAssignedNotification::class)
            ->latest()
            ->limit(20)
            ->get()
            ->first(function (DatabaseNotification $notification) use ($leadId) {
                return (int) data_get($notification->data, 'lead_id') === $leadId;
            });
    }

    private function resolveAppNotification(User $targetUser, int $leadId): ?AppNotification
    {
        return AppNotification::where('user_id', $targetUser->id)
            ->where('type', AppNotification::TYPE_NEW_LEAD)
            ->latest()
            ->limit(20)
            ->get()
            ->first(function (AppNotification $notification) use ($leadId) {
                return (int) data_get($notification->data, 'lead_id') === $leadId;
            });
    }

    private function resolveTaskOutcome(?array $workflow, ?array $task, User $targetUser): string
    {
        if ($targetUser->isAdmin()) {
            return 'skipped_for_admin';
        }

        if (data_get($workflow, 'task_result.created')) {
            return 'created_new';
        }

        if ($task) {
            return 'reused_or_transferred';
        }

        return 'not_found';
    }

    private function resolvePushStatus(
        User $targetUser,
        ?DatabaseNotification $databaseNotification,
        ?AppNotification $appNotification,
        int $fcmCount,
        int $pushCount
    ): string {
        if ($targetUser->isAdmin()) {
            return 'admin selected, task intentionally skipped';
        }

        if (($databaseNotification || $appNotification) && $fcmCount > 0) {
            return 'ready';
        }

        if (($databaseNotification || $appNotification) && $pushCount > 0) {
            return 'notification saved but lead flow has no FCM token';
        }

        if ($databaseNotification || $appNotification) {
            return 'notification saved but no push target';
        }

        return 'notification not confirmed';
    }

    private function tailRelevantLogs(?int $leadId, int $userId): array
    {
        $path = storage_path('logs/laravel.log');
        if (!File::exists($path)) {
            return [];
        }

        $lines = array_slice(file($path) ?: [], -250);

        $filtered = array_values(array_filter($lines, function ($line) use ($leadId, $userId) {
            $patterns = [
                'LeadAssigned',
                'CreateTelecallerTask',
                'SendLeadAssignedNotification',
                'SendNewLeadNotification',
                'FCM',
                'push',
                'notification',
            ];

            foreach ($patterns as $pattern) {
                if (stripos($line, $pattern) !== false) {
                    return true;
                }
            }

            if ($leadId && str_contains($line, (string) $leadId)) {
                return true;
            }

            return str_contains($line, (string) $userId);
        }));

        return array_slice(array_map('trim', $filtered), -15);
    }

    private function notificationTypes(): array
    {
        return [
            AppNotification::TYPE_NEW_LEAD => [
                'title' => 'New Lead Assigned',
                'message_prefix' => 'Test new lead assigned notification',
            ],
            AppNotification::TYPE_NEW_VERIFICATION => [
                'title' => 'New Verification',
                'message_prefix' => 'Test verification notification',
            ],
            AppNotification::TYPE_FOLLOWUP_REMINDER => [
                'title' => 'Follow-up Reminder',
                'message_prefix' => 'Test follow-up reminder notification',
            ],
            AppNotification::TYPE_MEETING_REMINDER => [
                'title' => 'Meeting Reminder',
                'message_prefix' => 'Test meeting reminder notification',
            ],
            AppNotification::TYPE_CALL_REMINDER => [
                'title' => 'Call Reminder',
                'message_prefix' => 'Test manager call reminder notification',
            ],
            AppNotification::TYPE_TASK_OVERDUE => [
                'title' => 'Overdue Task',
                'message_prefix' => 'Test overdue task notification',
            ],
            AppNotification::TYPE_FOLLOWUP_OVERDUE => [
                'title' => 'Overdue Follow-up',
                'message_prefix' => 'Test overdue follow-up notification',
            ],
            AppNotification::TYPE_SITE_VISIT => [
                'title' => 'Site Visit Scheduled',
                'message_prefix' => 'Test site visit notification',
            ],
            AppNotification::TYPE_MEETING => [
                'title' => 'Meeting Scheduled',
                'message_prefix' => 'Test meeting scheduled notification',
            ],
            AppNotification::TYPE_SITE_VISIT_REMINDER => [
                'title' => 'Site Visit Reminder',
                'message_prefix' => 'Test site visit reminder notification',
            ],
        ];
    }

    private function reminderTypes(): array
    {
        return [
            AppNotification::TYPE_FOLLOWUP_REMINDER => [
                'title' => 'Follow-up Reminder',
                'message_prefix' => 'Test follow-up reminder notification',
                'kind' => 'follow_up',
            ],
            AppNotification::TYPE_MEETING_REMINDER => [
                'title' => 'Meeting Reminder',
                'message_prefix' => 'Test meeting reminder notification',
                'kind' => 'meeting',
            ],
            AppNotification::TYPE_SITE_VISIT_REMINDER => [
                'title' => 'Site Visit Reminder',
                'message_prefix' => 'Test site visit reminder notification',
                'kind' => 'site_visit',
            ],
        ];
    }

    private function resolveTestActionUrl(User $targetUser, string $notificationType): string
    {
        if ($targetUser->isSalesExecutive()) {
            return match ($notificationType) {
                AppNotification::TYPE_NEW_VERIFICATION => url('/telecaller/prospects'),
                default => url('/telecaller/tasks?status=pending'),
            };
        }

        return match ($notificationType) {
            AppNotification::TYPE_NEW_VERIFICATION => url('/sales-manager/prospects'),
            AppNotification::TYPE_FOLLOWUP_REMINDER,
            AppNotification::TYPE_FOLLOWUP_OVERDUE => url('/sales-manager/followups'),
            AppNotification::TYPE_MEETING_REMINDER,
            AppNotification::TYPE_MEETING => url('/sales-manager/meetings'),
            AppNotification::TYPE_SITE_VISIT_REMINDER,
            AppNotification::TYPE_SITE_VISIT => url('/sales-manager/site-visits'),
            default => url('/sales-manager/tasks'),
        };
    }

    private function resolveReminderTestActionUrl(User $targetUser, string $notificationType): string
    {
        if ($targetUser->isSalesExecutive()) {
            return url('/telecaller/tasks?status=pending');
        }

        return match ($notificationType) {
            AppNotification::TYPE_FOLLOWUP_REMINDER => url('/sales-manager/followups'),
            AppNotification::TYPE_MEETING_REMINDER => url('/sales-manager/meetings'),
            AppNotification::TYPE_SITE_VISIT_REMINDER => url('/sales-manager/site-visits'),
            default => url('/sales-manager/tasks'),
        };
    }
}
