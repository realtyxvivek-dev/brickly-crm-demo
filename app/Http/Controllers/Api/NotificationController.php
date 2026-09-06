<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\BroadcastMessage;
use App\Models\BroadcastMessageUserState;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $this->personalNotificationsQuery($user)
            ->recent(50)
            ->get();

        $personalUnreadCount = $this->personalNotificationsQuery($user)
            ->unread()
            ->count();

        [$announcements, $announcementUnreadCount] = $this->announcementPayload($user);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'notifications' => $notifications,
            'announcements' => $announcements,
            'counts' => [
                'notifications_unread' => $personalUnreadCount,
                'announcements_unread' => $announcementUnreadCount,
                'total_unread' => $personalUnreadCount + $announcementUnreadCount,
            ],
            'unread_count' => $personalUnreadCount + $announcementUnreadCount,
            'personal_unread_count' => $personalUnreadCount,
            'announcement_unread_count' => $announcementUnreadCount,
        ]);
    }

    public function getUnread(Request $request)
    {
        $user = $request->user();

        $notifications = $this->personalNotificationsQuery($user)
            ->unread()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        [$announcements, $announcementUnreadCount] = $this->announcementPayload($user);
        $personalUnreadCount = $notifications->count();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'notifications' => $notifications,
            'announcements' => $announcements,
            'counts' => [
                'notifications_unread' => $personalUnreadCount,
                'announcements_unread' => $announcementUnreadCount,
                'total_unread' => $personalUnreadCount + $announcementUnreadCount,
            ],
            'unread_count' => $personalUnreadCount + $announcementUnreadCount,
            'personal_unread_count' => $personalUnreadCount,
            'announcement_unread_count' => $announcementUnreadCount,
        ]);
    }

    public function markAsRead(Request $request, AppNotification $notification)
    {
        $user = $request->user();
        if ($notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification marked as read']);
    }

    public function markAsClicked(Request $request, AppNotification $notification)
    {
        $user = $request->user();
        if ($notification->user_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $notification->markAsClicked();

        $url = $notification->action_url;
        if (!$url) {
            if ($notification->type === AppNotification::TYPE_NEW_VERIFICATION
                && $notification->action_type === AppNotification::ACTION_VERIFICATION) {
                $url = url('/telecaller/verification-pending');
            } elseif ($notification->telecaller_task_id) {
                $task = $notification->telecallerTask;
                $leadId = $notification->data['lead_id'] ?? $task?->lead_id;
                $url = $leadId ? url('/leads/' . $leadId) : url('/telecaller/tasks?status=pending&task_id=' . $notification->telecaller_task_id);
            } elseif ($notification->type === AppNotification::TYPE_ADMIN_BROADCAST) {
                $url = $notification->data['action_url']
                    ?? $notification->data['attachment_url']
                    ?? url('/');
            } elseif ($notification->type === AppNotification::TYPE_NEW_VERIFICATION) {
                $url = url('/telecaller/verification-pending');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification clicked',
            'url' => $url,
            'task_id' => $notification->telecaller_task_id,
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $this->personalNotificationsQuery($user)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true, 'message' => 'All notifications marked as read']);
    }

    public function clear(Request $request, AppNotification $notification)
    {
        $user = $request->user();
        if ($notification->user_id !== $user->id || $notification->type === AppNotification::TYPE_ADMIN_BROADCAST) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json(['success' => true, 'message' => 'Notification cleared']);
    }

    public function clearAll(Request $request)
    {
        $user = $request->user();
        $this->personalNotificationsQuery($user)->delete();

        return response()->json(['success' => true, 'message' => 'All notifications cleared']);
    }

    public function markAnnouncementAsRead(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        if (!$this->userCanAccessAnnouncement($user, $broadcast)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->upsertAnnouncementState($broadcast->id, $user->id, [
            'delivered_at' => now(),
            'read_at' => now(),
        ]);
        $broadcast->markAsReadBy($user->id);

        return response()->json(['success' => true, 'message' => 'Announcement marked as read']);
    }

    public function markAllAnnouncementsAsRead(Request $request)
    {
        $user = $request->user();

        foreach ($this->announcementBaseQuery($user)->get() as $broadcast) {
            $this->upsertAnnouncementState($broadcast->id, $user->id, [
                'delivered_at' => now(),
                'read_at' => now(),
            ]);
            $broadcast->markAsReadBy($user->id);
        }

        return response()->json(['success' => true, 'message' => 'All announcements marked as read']);
    }

    public function acknowledgeAnnouncement(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        if (!$this->userCanAccessAnnouncement($user, $broadcast)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->upsertAnnouncementState($broadcast->id, $user->id, [
            'delivered_at' => now(),
            'read_at' => now(),
            'acknowledged_at' => now(),
        ]);
        $broadcast->markAsReadBy($user->id);

        return response()->json(['success' => true, 'message' => 'Announcement acknowledged']);
    }

    public function clickAnnouncement(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        if (!$this->userCanAccessAnnouncement($user, $broadcast)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->upsertAnnouncementState($broadcast->id, $user->id, [
            'delivered_at' => now(),
            'read_at' => now(),
            'clicked_at' => now(),
        ]);
        $broadcast->markAsReadBy($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Announcement click tracked',
            'url' => $broadcast->action_url,
        ]);
    }

    public function dismissAnnouncement(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        if (!$this->userCanAccessAnnouncement($user, $broadcast)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($broadcast->isUrgent()) {
            return response()->json(['success' => false, 'message' => 'Urgent announcements cannot be dismissed.'], 422);
        }

        $this->upsertAnnouncementState($broadcast->id, $user->id, [
            'delivered_at' => now(),
            'read_at' => now(),
            'dismissed_at' => now(),
        ]);
        $broadcast->markAsReadBy($user->id);

        return response()->json(['success' => true, 'message' => 'Announcement popup dismissed']);
    }

    public function dismissAllAnnouncements(Request $request)
    {
        $user = $request->user();

        foreach ($this->announcementBaseQuery($user)->get() as $broadcast) {
            if ($broadcast->isUrgent()) {
                continue;
            }

            $this->upsertAnnouncementState($broadcast->id, $user->id, [
                'delivered_at' => now(),
                'read_at' => now(),
                'dismissed_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'All non-urgent announcement popups dismissed']);
    }

    public function markAnnouncementPopupShown(Request $request, BroadcastMessage $broadcast)
    {
        $user = $request->user();
        if (!$this->userCanAccessAnnouncement($user, $broadcast)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $this->upsertAnnouncementState($broadcast->id, $user->id, [
            'delivered_at' => now(),
            'last_popup_shown_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }

    private function personalNotificationsQuery($user)
    {
        $query = AppNotification::where('user_id', $user->id)
            ->where('type', '!=', AppNotification::TYPE_ADMIN_BROADCAST)
            ->with('telecallerTask.lead')
            ->orderByDesc('created_at');

        if ($user && method_exists($user, 'isSalesExecutive') && $user->isSalesExecutive()) {
            $query->where(function ($q) {
                $q->where('type', '!=', AppNotification::TYPE_NEW_VERIFICATION)
                    ->orWhere(function ($verificationQuery) {
                        $verificationQuery->where('type', AppNotification::TYPE_NEW_VERIFICATION)
                            ->where(function ($typeQuery) {
                                $typeQuery->whereNull('data->verification_type')
                                    ->orWhere('data->verification_type', '!=', 'prospect');
                            })
                            ->where(function ($titleQuery) {
                                $titleQuery->whereNull('title')
                                    ->orWhere('title', '!=', 'New Prospect Verification');
                            });
                    });
            });
        }

        return $query;
    }

    private function announcementBaseQuery($user)
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        $roleSlug = $user->role->slug ?? null;

        return BroadcastMessage::query()
            ->with('sender')
            ->where(function ($query) use ($user, $roleSlug) {
                $query->where('target_type', 'all_users');

                if ($roleSlug) {
                    $query->orWhere(function ($roleQuery) use ($roleSlug) {
                        $roleQuery->where('target_type', 'role_based')
                            ->whereJsonContains('target_roles', $roleSlug);
                    });
                }

                $query->orWhere(function ($userQuery) use ($user) {
                    $userQuery->where('target_type', 'specific_users')
                        ->where(function ($targetedUserQuery) use ($user) {
                            $targetedUserQuery
                                ->whereJsonContains('target_user_ids', $user->id)
                                ->orWhereJsonContains('target_user_ids', (string) $user->id);
                        });
                });
            })
            ->latest();
    }

    private function announcementPayload($user): array
    {
        $announcements = $this->announcementBaseQuery($user)
            ->limit(50)
            ->get();

        $states = BroadcastMessageUserState::query()
            ->where('user_id', $user->id)
            ->whereIn('broadcast_message_id', $announcements->pluck('id'))
            ->get()
            ->keyBy('broadcast_message_id');

        $payload = $announcements
            ->map(function (BroadcastMessage $broadcast) use ($states) {
                $state = $states->get($broadcast->id);
                $requiresAcknowledge = $broadcast->resolveRequiresAcknowledge();
                $displayMode = match ($broadcast->priority) {
                    BroadcastMessage::PRIORITY_URGENT => 'critical',
                    BroadcastMessage::PRIORITY_IMPORTANT => 'popup',
                    default => 'notification',
                };
                $isActive = $broadcast->isCurrentlyActive();
                $status = $broadcast->ends_at && $broadcast->ends_at->lt(now())
                    ? 'expired'
                    : (($broadcast->starts_at && $broadcast->starts_at->gt(now())) ? 'scheduled' : 'active');

                return [
                    'id' => $broadcast->id,
                    'title' => $broadcast->title,
                    'message' => $broadcast->message,
                    'priority' => $broadcast->priority,
                    'display_mode' => $displayMode,
                    'banner_enabled' => (bool) $broadcast->banner_enabled,
                    'requires_acknowledge' => $requiresAcknowledge,
                    'action_label' => $broadcast->action_label,
                    'action_url' => $broadcast->action_url,
                    'primary_cta_label' => $broadcast->action_label ?: 'View Announcement',
                    'secondary_cta_label' => $requiresAcknowledge ? 'Acknowledge' : 'Dismiss',
                    'attachment_name' => $broadcast->attachment_name,
                    'attachment_url' => $broadcast->attachment_path ? route('announcements.attachment', $broadcast) : null,
                    'target_type' => $broadcast->target_type,
                    'sender_name' => $broadcast->sender?->name,
                    'created_at' => optional($broadcast->created_at)->toIso8601String(),
                    'starts_at' => optional($broadcast->starts_at)->toIso8601String(),
                    'ends_at' => optional($broadcast->ends_at)->toIso8601String(),
                    'read_at' => optional($state?->read_at)->toIso8601String(),
                    'acknowledged_at' => optional($state?->acknowledged_at)->toIso8601String(),
                    'clicked_at' => optional($state?->clicked_at)->toIso8601String(),
                    'dismissed_at' => optional($state?->dismissed_at)->toIso8601String(),
                    'last_popup_shown_at' => optional($state?->last_popup_shown_at)->toIso8601String(),
                    'sticky' => $broadcast->isUrgent(),
                    'is_active' => $isActive,
                    'status' => $status,
                    'can_dismiss' => !$broadcast->isUrgent(),
                    'type' => 'announcement',
                ];
            })
            ->values();

        $unreadCount = $payload
            ->filter(function (array $announcement) {
                if (!$announcement['is_active']) {
                    return false;
                }

                if (empty($announcement['read_at'])) {
                    return true;
                }

                return $announcement['requires_acknowledge'] && empty($announcement['acknowledged_at']);
            })
            ->count();

        return [$payload, $unreadCount];
    }

    private function userCanAccessAnnouncement($user, BroadcastMessage $broadcast): bool
    {
        return $broadcast->isTargetedTo($user);
    }

    private function upsertAnnouncementState(int $broadcastId, int $userId, array $attributes): void
    {
        $state = BroadcastMessageUserState::query()->firstOrNew([
            'broadcast_message_id' => $broadcastId,
            'user_id' => $userId,
        ]);

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $state->{$key} = $value;
            }
        }

        $state->save();
    }
}
