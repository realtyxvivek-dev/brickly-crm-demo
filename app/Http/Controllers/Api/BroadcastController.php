<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BroadcastMessage;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BroadcastController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function indexManage(Request $request)
    {
        $user = $request->user();
        if (!$this->canManageAnnouncements($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $announcements = BroadcastMessage::query()
            ->with(['sender:id,name', 'userStates'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (BroadcastMessage $announcement) => $this->serializeAnnouncementForManager($announcement));

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    public function audienceOptions(Request $request)
    {
        $user = $request->user();
        if (!$this->canManageAnnouncements($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'roles' => Role::query()
                ->whereIn('slug', [
                    Role::ADMIN,
                    Role::CRM,
                    Role::HR_MANAGER,
                    Role::FINANCE_MANAGER,
                    Role::SALES_MANAGER,
                    Role::SENIOR_MANAGER,
                    Role::ASSISTANT_SALES_MANAGER,
                    Role::SALES_EXECUTIVE,
                    Role::MARKETING_MANAGER,
                    Role::MARKETING_EXECUTIVE,
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
            'users' => User::query()
                ->with('role:id,name,slug')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role_id']),
        ]);
    }

    public function sendBroadcast(Request $request)
    {
        $user = $request->user();

        if (!$this->canManageAnnouncements($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only Admin, CRM, and HR can send announcements.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:normal,important,urgent',
            'banner_enabled' => 'nullable|boolean',
            'target_type' => 'required|in:all_users,role_based,specific_users',
            'target_roles' => 'required_if:target_type,role_based|array',
            'target_roles.*' => 'string|exists:roles,slug',
            'target_user_ids' => 'required_if:target_type,specific_users|array',
            'target_user_ids.*' => 'integer|exists:users,id',
            'action_label' => 'nullable|string|max:80',
            'action_url' => [
                'nullable',
                'string',
                'max:2000',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $isAbsoluteUrl = filter_var($value, FILTER_VALIDATE_URL) !== false;
                    $isRelativePath = is_string($value) && str_starts_with($value, '/');

                    if (!$isAbsoluteUrl && !$isRelativePath) {
                        $fail('The action url must be a valid URL or start with /.');
                    }
                },
            ],
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $attachmentPath = null;
            $attachmentName = null;

            if ($request->hasFile('attachment')) {
                $attachmentPath = $request->file('attachment')->store('announcements');
                $attachmentName = $request->file('attachment')->getClientOriginalName();
            }

            $result = $this->notificationService->sendBroadcast($user, [
                'title' => (string) $request->input('title'),
                'message' => (string) $request->input('message'),
                'priority' => (string) $request->input('priority'),
                'banner_enabled' => (bool) $request->boolean('banner_enabled'),
                'requires_acknowledge' => in_array($request->input('priority'), ['important', 'urgent'], true),
                'target_type' => (string) $request->input('target_type'),
                'target_roles' => $request->input('target_roles', []),
                'target_user_ids' => $request->input('target_user_ids', []),
                'action_label' => $request->input('action_label') ?: null,
                'action_url' => $request->input('action_url') ?: null,
                'starts_at' => $request->filled('starts_at') ? Carbon::parse($request->input('starts_at')) : null,
                'ends_at' => $request->filled('ends_at') ? Carbon::parse($request->input('ends_at')) : null,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'status' => 'active',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Announcement sent successfully.',
                'data' => [
                    'announcement' => $this->serializeAnnouncementForManager(
                        BroadcastMessage::query()->with(['sender:id,name', 'userStates'])->findOrFail($result['broadcast']->id)
                    ),
                    'sent_to' => $result['sent_to'],
                ],
            ]);
        } catch (\Throwable $exception) {
            \Log::error('Announcement Error', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send announcement: ' . $exception->getMessage(),
            ], 500);
        }
    }

    public function getUnreadBroadcasts(Request $request)
    {
        $user = $request->user();

        $announcements = BroadcastMessage::query()
            ->with(['sender:id,name', 'userStates' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest()
            ->get()
            ->filter(fn (BroadcastMessage $announcement) => $announcement->isTargetedTo($user))
            ->values()
            ->map(fn (BroadcastMessage $announcement) => $this->serializeAnnouncementForUser($announcement, $user))
            ->filter(fn (array $announcement) => empty($announcement['read_at']))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $announcements,
        ]);
    }

    public function markAsRead(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();

        if (!$broadcast->isTargetedTo($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $broadcast->markAsReadBy($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Broadcast marked as read',
        ]);
    }

    public function downloadAttachment(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        abort_unless($user && $broadcast->isTargetedTo($user), 403);
        abort_unless($broadcast->attachment_path, 404);

        return Storage::download($broadcast->attachment_path, $broadcast->attachment_name ?: basename($broadcast->attachment_path));
    }

    private function serializeAnnouncementForManager(BroadcastMessage $announcement): array
    {
        $analytics = $announcement->analyticsSummary($announcement->userStates);
        $displayMode = $this->resolveDisplayMode($announcement);
        $requiresAcknowledge = $announcement->resolveRequiresAcknowledge();
        if (!$announcement->resolveRequiresAcknowledge()) {
            $analytics['pending_acknowledge'] = 0;
        }

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'priority' => $announcement->priority,
            'display_mode' => $displayMode,
            'banner_enabled' => (bool) $announcement->banner_enabled,
            'requires_acknowledge' => $requiresAcknowledge,
            'target_type' => $announcement->target_type,
            'target_roles' => $announcement->target_roles ?? [],
            'target_user_ids' => $announcement->target_user_ids ?? [],
            'action_label' => $announcement->action_label,
            'action_url' => $announcement->action_url,
            'primary_cta_label' => $announcement->action_label ?: 'View Announcement',
            'secondary_cta_label' => $requiresAcknowledge ? 'Acknowledge' : 'Dismiss',
            'attachment_name' => $announcement->attachment_name,
            'attachment_url' => $announcement->attachment_path ? route('announcements.attachment', $announcement) : null,
            'starts_at' => optional($announcement->starts_at)->toIso8601String(),
            'ends_at' => optional($announcement->ends_at)->toIso8601String(),
            'status' => $this->resolveAnnouncementStatus($announcement),
            'created_at' => optional($announcement->created_at)->toIso8601String(),
            'sender_name' => $announcement->sender?->name,
            'analytics' => $analytics,
        ];
    }

    private function serializeAnnouncementForUser(BroadcastMessage $announcement, User $user): array
    {
        $state = $announcement->userStates->firstWhere('user_id', $user->id);
        $displayMode = $this->resolveDisplayMode($announcement);
        $requiresAcknowledge = $announcement->resolveRequiresAcknowledge();

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'message' => $announcement->message,
            'priority' => $announcement->priority,
            'display_mode' => $displayMode,
            'banner_enabled' => (bool) $announcement->banner_enabled,
            'requires_acknowledge' => $requiresAcknowledge,
            'action_label' => $announcement->action_label,
            'action_url' => $announcement->action_url,
            'primary_cta_label' => $announcement->action_label ?: 'View Announcement',
            'secondary_cta_label' => $requiresAcknowledge ? 'Acknowledge' : 'Dismiss',
            'attachment_name' => $announcement->attachment_name,
            'attachment_url' => $announcement->attachment_path ? route('announcements.attachment', $announcement) : null,
            'starts_at' => optional($announcement->starts_at)->toIso8601String(),
            'ends_at' => optional($announcement->ends_at)->toIso8601String(),
            'created_at' => optional($announcement->created_at)->toIso8601String(),
            'sender_name' => $announcement->sender?->name,
            'read_at' => optional($state?->read_at)->toIso8601String(),
            'acknowledged_at' => optional($state?->acknowledged_at)->toIso8601String(),
            'clicked_at' => optional($state?->clicked_at)->toIso8601String(),
            'dismissed_at' => optional($state?->dismissed_at)->toIso8601String(),
            'last_popup_shown_at' => optional($state?->last_popup_shown_at)->toIso8601String(),
            'sticky' => $announcement->isUrgent(),
            'is_active' => $announcement->isCurrentlyActive(),
            'status' => $this->resolveAnnouncementStatus($announcement),
            'type' => 'announcement',
        ];
    }

    private function resolveDisplayMode(BroadcastMessage $announcement): string
    {
        return match ($announcement->priority) {
            BroadcastMessage::PRIORITY_URGENT => 'critical',
            BroadcastMessage::PRIORITY_IMPORTANT => 'popup',
            default => 'notification',
        };
    }

    private function resolveAnnouncementStatus(BroadcastMessage $announcement): string
    {
        $now = now();

        if ($announcement->ends_at && $announcement->ends_at->lt($now)) {
            return 'expired';
        }

        if ($announcement->starts_at && $announcement->starts_at->gt($now)) {
            return 'scheduled';
        }

        return 'active';
    }

    private function canManageAnnouncements(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isCrm() || $user->isHrManager());
    }
}
