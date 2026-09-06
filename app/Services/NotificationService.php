<?php

namespace App\Services;

use App\Events\FollowupNotification;
use App\Events\NewLeadNotification;
use App\Events\NewVerificationNotification;
use App\Jobs\SendFcmNotificationJob;
use App\Models\AppNotification;
use App\Models\AttendanceOutsidePunchRequest;
use App\Models\BroadcastMessage;
use App\Models\BroadcastMessageUserState;
use App\Models\ExecutionTask;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Role;
use App\Models\Task;
use App\Models\TelecallerTask;
use App\Models\Meeting;
use App\Models\PurchaseOrder;
use App\Models\SiteVisit;
use App\Models\User;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Collection;

class NotificationService
{
    public function notifyPurchaseOrderApproved(User $user, PurchaseOrder $purchaseOrder, string $actionUrl): AppNotification
    {
        $number = $purchaseOrder->po_number ?: $purchaseOrder->request_number ?: '#' . $purchaseOrder->id;

        return $this->createNotification(
            $user,
            AppNotification::TYPE_PURCHASE_ORDER,
            'Purchase Order Approved',
            "PO {$number} has been approved and sent for payment.",
            AppNotification::ACTION_PURCHASE_ORDER,
            $actionUrl,
            [
                'kind' => 'purchase_order_approved',
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'request_number' => $purchaseOrder->request_number,
                'primary_action_label' => 'Open PO',
                'primary_action_url' => $actionUrl,
            ],
            null,
            'purchase-order-approved-' . $purchaseOrder->id . '-user-' . $user->id
        );
    }

    public function sendTestNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        string $actionUrl,
        array $data = []
    ): AppNotification {
        return $this->createNotification(
            $user,
            $type,
            $title,
            $message,
            $this->resolveTestActionType($type),
            $actionUrl,
            array_merge([
                'kind' => 'test_notification',
                'triggered_at' => now()->toIso8601String(),
            ], $data),
            null,
            'test-' . $type . '-' . $user->id
        );
    }

    public function notifyNewLead(User $user, $lead, string $actionUrl): AppNotification
    {
        $message = "New lead assigned: {$lead->name}";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_LEAD,
            'New Lead Assigned',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'kind' => 'lead_assigned',
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'lead_phone' => $lead->phone ?? null,
            ],
            null,
            'new-lead-' . $lead->id
        );
    }

    public function notifyLeadReenquiry(
        User $user,
        Lead $lead,
        string $actionUrl,
        string $source,
        bool $reopened = false
    ): AppNotification {
        $sourceLabel = ucfirst(trim($source)) ?: 'Lead';
        $message = $reopened
            ? "{$lead->name} has re-enquired via {$sourceLabel} and is back in your pipeline."
            : "{$lead->name} has re-enquired again via {$sourceLabel}.";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_LEAD,
            'Lead Re-enquiry',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'kind' => 'lead_reenquiry',
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'source' => $source,
                'reopened' => $reopened,
                'reenquiry_count' => $lead->reenquiry_count,
                'last_reenquiry_at' => optional($lead->last_reenquiry_at)?->toIso8601String(),
            ],
            null,
            'lead-reenquiry-' . $lead->id . '-' . $source . '-' . (int) ($lead->reenquiry_count ?? 0)
        );
    }

    public function notifyBulkLeadImport(User $user, int $importedCount, string $actionUrl, array $data = []): AppNotification
    {
        $title = 'Bulk Lead Import Completed';
        $message = "{$importedCount} new leads imported successfully.";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_LEAD,
            $title,
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            array_merge([
                'kind' => 'bulk_lead_import',
                'imported_count' => $importedCount,
                'triggered_at' => now()->toIso8601String(),
            ], $data),
            null,
            'bulk-lead-import-' . $user->id . '-' . now()->timestamp
        );
    }

    public function notifyBulkCallingTasksCreated(User $user, int $createdCount, string $actionUrl, array $data = []): AppNotification
    {
        $title = 'New Calling Tasks Assigned';
        $message = "You received {$createdCount} new calling tasks.";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_CALL_REMINDER,
            $title,
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            array_merge([
                'kind' => 'bulk_calling_tasks_created',
                'created_count' => $createdCount,
                'triggered_at' => now()->toIso8601String(),
            ], $data),
            null,
            'bulk-calling-tasks-' . $user->id . '-' . now()->timestamp
        );
    }

    public function notifyLeadBankAllocationSummary(User $user, int $allocatedCount, int $requestId, string $actionUrl, array $data = []): AppNotification
    {
        $title = 'Lead Bank Leads Allocated';
        $message = "You received {$allocatedCount} Lead Bank lead(s).";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_LEAD,
            $title,
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            array_merge([
                'kind' => 'lead_bank_allocation_summary',
                'lead_bank_request_id' => $requestId,
                'allocated_count' => $allocatedCount,
                'triggered_at' => now()->toIso8601String(),
            ], $data),
            null,
            'lead-bank-allocation-' . $requestId . '-user-' . $user->id
        );
    }

    public function notifyWhatsAppMessage(
        User $user,
        Lead $lead,
        WhatsAppConversation $conversation,
        string $actionUrl,
        array $data = []
    ): AppNotification {
        $contact = $conversation->contact_name ?: $lead->name ?: $conversation->phone_number;
        $message = "New WhatsApp message received from {$contact}.";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_LEAD,
            'New WhatsApp Message',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            array_merge([
                'kind' => 'whatsapp_message',
                'lead_id' => $lead->id,
                'lead_name' => $lead->name,
                'conversation_id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'triggered_at' => now()->toIso8601String(),
            ], $data),
            null,
            'whatsapp-message-' . $conversation->id . '-' . now()->timestamp
        );
    }

    public function notifyNewVerification(User $user, string $type, string $title, string $message, string $actionUrl, array $data = []): AppNotification
    {
        return $this->createNotification(
            $user,
            AppNotification::TYPE_NEW_VERIFICATION,
            $title,
            $message,
            AppNotification::ACTION_VERIFICATION,
            $actionUrl,
            array_merge([
                'verification_type' => $type,
            ], $data)
        );
    }

    public function notifyFollowup(User $user, $followup, string $actionUrl, int $minutesBefore = 5): AppNotification
    {
        $leadName = $followup->lead ? $followup->lead->name : 'Lead';
        $leadPhone = $followup->lead?->phone;
        $callUrl = $leadPhone ? 'tel:' . preg_replace('/\D+/', '', $leadPhone) : null;
        $scheduledTime = $followup->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $message = "Follow-up reminder: This lead \"{$leadName}\" has a follow-up at {$scheduledTime}.";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_FOLLOWUP_REMINDER,
            'Follow-up Reminder',
            $message,
            AppNotification::ACTION_FOLLOWUP,
            $actionUrl,
            [
                'followup_id' => $followup->id,
                'lead_id' => $followup->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'call_url' => $callUrl,
                'primary_action_label' => 'Call Lead',
                'primary_action_url' => $callUrl,
                'secondary_action_label' => 'Complete Task',
                'secondary_action_url' => url('/api/follow-ups/' . $followup->id . '/complete'),
                'complete_action_url' => url('/api/follow-ups/' . $followup->id . '/complete'),
                'scheduled_at' => $followup->scheduled_at ? $followup->scheduled_at->toIso8601String() : null,
                'minutes_before' => $minutesBefore,
                'stage' => $minutesBefore === 15 ? '15_min' : '5_min',
                'kind' => 'follow_up',
            ],
            null,
            'followup-reminder-' . $followup->id . '-' . $minutesBefore
        );
    }

    public function notifyFollowupReminder(FollowUp $followup, int $minutesBefore = 5): Collection
    {
        $followup->loadMissing(['creator.manager', 'lead.activeAssignments.assignedTo.manager']);
        $notifications = collect();

        foreach ($this->resolveFollowupReminderRecipients($followup) as $user) {
            $actionUrl = $this->resolveFollowupActionUrl($followup, $user);
            $notifications->push($this->notifyFollowup($user, $followup, $actionUrl, $minutesBefore));
        }

        return $notifications;
    }

    public function notifyMeetingReminder(Meeting $meeting, int $minutesBefore = 5): Collection
    {
        $meeting->loadMissing(['lead', 'assignedTo']);

        $leadName = $meeting->lead?->name ?? $meeting->customer_name ?? 'Lead';
        $leadPhone = $meeting->lead?->phone;
        $scheduledTime = $meeting->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $message = "Meeting reminder: This lead \"{$leadName}\" has a meeting at {$scheduledTime}.";
        $notifications = collect();

        foreach ($this->uniqueUsers([$meeting->assignedTo]) as $user) {
            $notifications->push($this->createNotification(
                $user,
                AppNotification::TYPE_MEETING_REMINDER,
                'Meeting Reminder',
                $message,
                AppNotification::ACTION_LEAD,
                $this->resolveMeetingActionUrl($meeting, $user),
                [
                    'meeting_id' => $meeting->id,
                    'lead_id' => $meeting->lead_id,
                    'lead_name' => $leadName,
                    'lead_phone' => $leadPhone,
                    'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                    'minutes_before' => $minutesBefore,
                    'stage' => $minutesBefore === 15 ? '15_min' : '5_min',
                    'kind' => 'meeting',
                ],
                null,
                'meeting-reminder-' . $meeting->id . '-' . $minutesBefore
            ));
        }

        return $notifications;
    }

    public function notifyMeetingSameDayMorningReminder(Meeting $meeting): Collection
    {
        $meeting->loadMissing(['lead', 'assignedTo']);

        $leadName = $meeting->lead?->name ?? $meeting->customer_name ?? 'Lead';
        $leadPhone = $meeting->lead?->phone;
        $scheduledTime = $meeting->scheduled_at?->format('h:i A') ?? 'scheduled time';

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$meeting->assignedTo]),
            AppNotification::TYPE_MEETING_REMINDER,
            'Meeting Today',
            "Meeting with {$leadName} is scheduled today at {$scheduledTime}.",
            AppNotification::ACTION_LEAD,
            $this->resolveMeetingActionUrl($meeting, $meeting->assignedTo),
            [
                'kind' => 'activity_lifecycle_reminder',
                'related_type' => 'meeting',
                'related_id' => $meeting->id,
                'meeting_id' => $meeting->id,
                'lead_id' => $meeting->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'stage' => 'same_day_morning',
            ],
            null,
            'meeting-same-day-morning-' . $meeting->id
        );
    }

    public function notifyMeetingOutcomeReminder(Meeting $meeting, string $stage): Collection
    {
        $meeting->loadMissing(['lead', 'assignedTo']);

        $leadName = $meeting->lead?->name ?? $meeting->customer_name ?? 'Lead';
        $leadPhone = $meeting->lead?->phone;

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$meeting->assignedTo]),
            AppNotification::TYPE_MEETING_REMINDER,
            'Meeting Outcome Pending',
            "Please update the meeting outcome for {$leadName}.",
            AppNotification::ACTION_LEAD,
            $this->resolveMeetingActionUrl($meeting, $meeting->assignedTo),
            [
                'kind' => 'activity_lifecycle_reminder',
                'related_type' => 'meeting',
                'related_id' => $meeting->id,
                'meeting_id' => $meeting->id,
                'lead_id' => $meeting->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $meeting->scheduled_at?->toIso8601String(),
                'stage' => $stage,
            ],
            null,
            'meeting-outcome-' . $stage . '-' . $meeting->id
        );
    }

    public function notifyTelecallerCallReminder(TelecallerTask $task, int $minutesBefore): Collection
    {
        $task->loadMissing(['lead', 'assignedTo']);

        $leadName = $task->lead?->name ?? 'Lead';
        $leadPhone = $task->lead?->phone;
        $scheduledTime = $task->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $actionUrl = $task->lead_id
            ? url('/telecaller/tasks?status=pending&task_id=' . $task->id)
            : url('/telecaller/tasks?status=pending&task_id=' . $task->id);

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$task->assignedTo]),
            AppNotification::TYPE_CALL_REMINDER,
            $minutesBefore === 1 ? 'Call Now' : 'Call Reminder',
            "Call {$leadName} at {$scheduledTime}.",
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'kind' => 'telecaller_call_reminder',
                'task_id' => $task->id,
                'lead_id' => $task->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'call_url' => $leadPhone ? 'tel:' . preg_replace('/\D+/', '', $leadPhone) : null,
                'primary_action_label' => 'Call Now',
                'primary_action_url' => $leadPhone ? 'tel:' . preg_replace('/\D+/', '', $leadPhone) : $actionUrl,
                'secondary_action_label' => 'Open Task',
                'secondary_action_url' => $actionUrl,
                'scheduled_at' => $task->scheduled_at?->toIso8601String(),
                'minutes_before' => $minutesBefore,
                'stage' => $minutesBefore . '_min',
            ],
            $task->id,
            'telecaller-call-reminder-' . $task->id . '-' . $minutesBefore
        );
    }

    public function notifyManagerTaskReminder(Task $task, int $minutesBefore = 15): Collection
    {
        $task->loadMissing(['lead', 'assignedTo.role']);

        $leadName = $task->lead?->name ?? 'Lead';
        $leadPhone = $task->lead?->phone;
        $message = $minutesBefore === 1 ? "Call now: {$leadName}" : "Call reminder: {$leadName}";
        if ($task->scheduled_at) {
            $message .= ' at ' . $task->scheduled_at->format('M d, Y h:i A');
        }
        $actionUrl = $this->resolveManagerTaskActionUrl($task);

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$task->assignedTo]),
            AppNotification::TYPE_CALL_REMINDER,
            $minutesBefore === 1 ? 'Call Now' : 'Call Reminder',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'task_id' => $task->id,
                'lead_id' => $task->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'call_url' => $leadPhone ? 'tel:' . preg_replace('/\D+/', '', $leadPhone) : null,
                'primary_action_label' => 'Call Now',
                'primary_action_url' => $leadPhone ? 'tel:' . preg_replace('/\D+/', '', $leadPhone) : $actionUrl,
                'secondary_action_label' => 'Open Task',
                'secondary_action_url' => $actionUrl,
                'scheduled_at' => $task->scheduled_at?->toIso8601String(),
                'minutes_before' => $minutesBefore,
                'stage' => $minutesBefore . '_min',
                'kind' => 'manager_task_reminder',
            ],
            null,
            'manager-call-reminder-' . $task->id . '-' . $minutesBefore
        );
    }

    public function notifyOverdueTask(TelecallerTask $task): Collection
    {
        $task->loadMissing(['lead', 'assignedTo.manager']);

        $leadName = $task->lead?->name ?? 'Lead';
        $scheduledTime = $task->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $actionUrl = $this->resolveTaskActionUrl($task);

        return $this->createNotificationsForUsers(
            $this->resolveOverdueTaskRecipients($task),
            AppNotification::TYPE_TASK_OVERDUE,
            'Overdue Task',
            "Overdue task for {$leadName}. It was due at {$scheduledTime}.",
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'task_id' => $task->id,
                'lead_id' => $task->lead_id,
                'lead_name' => $leadName,
                'scheduled_at' => $task->scheduled_at?->toIso8601String(),
                'kind' => 'task_overdue',
            ],
            $task->id,
            'task-overdue-' . $task->id
        );
    }

    public function notifyOverdueFollowup(FollowUp $followup): Collection
    {
        $followup->loadMissing(['creator.manager', 'lead.activeAssignments.assignedTo.manager']);

        $leadName = $followup->lead?->name ?? 'Lead';
        $scheduledTime = $followup->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $actionUrl = $this->resolveFollowupActionUrl($followup);

        return $this->createNotificationsForUsers(
            $this->resolveOverdueFollowupRecipients($followup),
            AppNotification::TYPE_FOLLOWUP_OVERDUE,
            'Overdue Follow-up',
            "Overdue follow-up for {$leadName}. It was due at {$scheduledTime}.",
            AppNotification::ACTION_FOLLOWUP,
            $actionUrl,
            [
                'followup_id' => $followup->id,
                'lead_id' => $followup->lead_id,
                'lead_name' => $leadName,
                'scheduled_at' => $followup->scheduled_at?->toIso8601String(),
                'kind' => 'followup_overdue',
            ],
            null,
            'followup-overdue-' . $followup->id
        );
    }

    public function notifySiteVisit(User $user, $siteVisit, string $actionUrl): AppNotification
    {
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $leadPhone = $siteVisit->lead?->phone;
        $message = "Hurry! Site visit scheduled for your lead {$leadName}";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_SITE_VISIT,
            'Site Visit Scheduled',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $siteVisit->scheduled_at ? (string) $siteVisit->scheduled_at : null,
            ],
            null,
            'site-visit-' . $siteVisit->id
        );
    }

    public function notifySiteVisitReminder($siteVisit, int $minutesBefore = 5): Collection
    {
        $siteVisit->loadMissing(['lead', 'assignedTo']);

        $leadName = $siteVisit->lead?->name ?? ($siteVisit->customer_name ?? 'Lead');
        $leadPhone = $siteVisit->lead?->phone;
        $scheduledTime = $siteVisit->scheduled_at?->format('M d, Y h:i A') ?? 'scheduled time';
        $message = "Site visit reminder: This lead \"{$leadName}\" has a site visit at {$scheduledTime}.";
        $notifications = collect();

        foreach ($this->uniqueUsers([$siteVisit->assignedTo]) as $user) {
            $notifications->push($this->createNotification(
                $user,
                AppNotification::TYPE_SITE_VISIT_REMINDER,
                'Site Visit Reminder',
                $message,
                AppNotification::ACTION_LEAD,
                $this->resolveSiteVisitActionUrl($siteVisit, $user),
                [
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'lead_name' => $leadName,
                    'lead_phone' => $leadPhone,
                    'scheduled_at' => $siteVisit->scheduled_at?->toIso8601String(),
                    'minutes_before' => $minutesBefore,
                    'stage' => $minutesBefore === 15 ? '15_min' : '5_min',
                    'kind' => 'site_visit',
                ],
                null,
                'site-visit-reminder-' . $siteVisit->id . '-' . $minutesBefore
            ));
        }

        return $notifications;
    }

    public function notifySiteVisitSameDayMorningReminder(SiteVisit $siteVisit): Collection
    {
        $siteVisit->loadMissing(['lead', 'assignedTo']);

        $leadName = $siteVisit->lead?->name ?? ($siteVisit->customer_name ?? 'Lead');
        $leadPhone = $siteVisit->lead?->phone;
        $scheduledTime = $siteVisit->scheduled_at?->format('h:i A') ?? 'scheduled time';

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$siteVisit->assignedTo]),
            AppNotification::TYPE_SITE_VISIT_REMINDER,
            'Site Visit Today',
            "Site visit for {$leadName} is scheduled today at {$scheduledTime}.",
            AppNotification::ACTION_LEAD,
            $this->resolveSiteVisitActionUrl($siteVisit, $siteVisit->assignedTo),
            [
                'kind' => 'activity_lifecycle_reminder',
                'related_type' => 'site_visit',
                'related_id' => $siteVisit->id,
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $siteVisit->scheduled_at?->toIso8601String(),
                'stage' => 'same_day_morning',
            ],
            null,
            'site-visit-same-day-morning-' . $siteVisit->id
        );
    }

    public function notifySiteVisitOutcomeReminder(SiteVisit $siteVisit, string $stage): Collection
    {
        $siteVisit->loadMissing(['lead', 'assignedTo']);

        $leadName = $siteVisit->lead?->name ?? ($siteVisit->customer_name ?? 'Lead');
        $leadPhone = $siteVisit->lead?->phone;

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$siteVisit->assignedTo]),
            AppNotification::TYPE_SITE_VISIT_REMINDER,
            'Site Visit Outcome Pending',
            "Please update the site visit outcome for {$leadName}.",
            AppNotification::ACTION_LEAD,
            $this->resolveSiteVisitActionUrl($siteVisit, $siteVisit->assignedTo),
            [
                'kind' => 'activity_lifecycle_reminder',
                'related_type' => 'site_visit',
                'related_id' => $siteVisit->id,
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $siteVisit->scheduled_at?->toIso8601String(),
                'stage' => $stage,
            ],
            null,
            'site-visit-outcome-' . $stage . '-' . $siteVisit->id
        );
    }

    public function notifyEligibleSiteVisitForIncentive(User $telecaller, $siteVisit, string $actionUrl): AppNotification
    {
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Your prospect '{$leadName}' site visit has been completed. Request incentive for this visit.";

        return $this->createNotification(
            $telecaller,
            AppNotification::TYPE_NEW_LEAD,
            'Eligible Site Visit for Incentive',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'type' => 'site_visit_incentive_eligible',
            ]
        );
    }

    public function notifyMeeting(User $user, $meeting, string $actionUrl): AppNotification
    {
        $leadName = $meeting->lead->name ?? ($meeting->customer_name ?? 'Lead');
        $leadPhone = $meeting->lead?->phone;
        $message = "Hurry! Meeting scheduled for your lead {$leadName}";

        return $this->createNotification(
            $user,
            AppNotification::TYPE_MEETING,
            'Meeting Scheduled',
            $message,
            AppNotification::ACTION_LEAD,
            $actionUrl,
            [
                'meeting_id' => $meeting->id,
                'lead_id' => $meeting->lead_id,
                'lead_name' => $leadName,
                'lead_phone' => $leadPhone,
                'scheduled_at' => $meeting->scheduled_at ? (string) $meeting->scheduled_at : null,
            ],
            null,
            'meeting-' . $meeting->id
        );
    }

    public function notifyAdminsNewUser(User $newUser): array
    {
        $admins = User::whereHas('role', function ($q) {
            $q->where('slug', Role::ADMIN);
        })->where('is_active', true)->get();

        $actionUrl = url('/users');
        $title = 'New user created';
        $message = "New user created: {$newUser->name} ({$newUser->email})";

        $notifications = [];
        foreach ($admins as $admin) {
            $notifications[] = $this->createNotification(
                $admin,
                AppNotification::TYPE_NEW_USER,
                $title,
                $message,
                AppNotification::ACTION_USER,
                $actionUrl,
                [
                    'new_user_id' => $newUser->id,
                    'new_user_name' => $newUser->name,
                    'new_user_email' => $newUser->email,
                ]
            );
        }

        return $notifications;
    }

    public function notifyExecutionTaskAssigned(ExecutionTask $task): AppNotification
    {
        $task->loadMissing(['creator', 'assignee']);

        return $this->createNotification(
            $task->assignee,
            AppNotification::TYPE_EXECUTION_TASK,
            'New Execution Task',
            "Task assigned: {$task->title}",
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_assigned',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
                'assigned_by' => $task->creator?->name,
            ]
        );
    }

    public function notifyExecutionTaskDueReminder(ExecutionTask $task): AppNotification
    {
        $task->loadMissing('assignee');

        return $this->createNotification(
            $task->assignee,
            AppNotification::TYPE_EXECUTION_TASK_DUE,
            'Execution Task Due Soon',
            "Task due soon: {$task->title}",
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_due',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
                'due_at' => $task->due_at?->toIso8601String(),
            ]
        );
    }

    public function notifyExecutionTaskOverdue(ExecutionTask $task): AppNotification
    {
        $task->loadMissing('assignee');

        return $this->createNotification(
            $task->assignee,
            AppNotification::TYPE_EXECUTION_TASK_OVERDUE,
            'Execution Task Overdue',
            "Overdue task: {$task->title}",
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_overdue',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
                'due_at' => $task->due_at?->toIso8601String(),
            ]
        );
    }

    public function notifyExecutionTaskCompleted(ExecutionTask $task): ?AppNotification
    {
        $task->loadMissing('creator');
        if (!$task->creator) {
            return null;
        }

        return $this->createNotification(
            $task->creator,
            AppNotification::TYPE_EXECUTION_TASK,
            'Execution Task Completed',
            "Task completed: {$task->title}",
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_completed',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
            ]
        );
    }

    public function notifyExecutionTaskClosed(ExecutionTask $task): ?AppNotification
    {
        $task->loadMissing('creator');
        if (!$task->creator) {
            return null;
        }

        return $this->createNotification(
            $task->creator,
            AppNotification::TYPE_EXECUTION_TASK,
            'Execution Task Closed',
            "Task closed: {$task->title}",
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_closed',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
            ]
        );
    }

    public function notifyExecutionTaskReopened(ExecutionTask $task, string $reason): Collection
    {
        $task->loadMissing(['creator', 'assignee']);

        return $this->createNotificationsForUsers(
            $this->uniqueUsers([$task->creator, $task->assignee]),
            AppNotification::TYPE_EXECUTION_TASK,
            'Execution Task Reopened',
            "Task reopened: {$task->title}" . ($reason !== '' ? " ({$reason})" : ''),
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_reopened',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
                'reason' => $reason,
            ]
        );
    }

    public function notifyExecutionTaskRejected(ExecutionTask $task, string $reason): ?AppNotification
    {
        $task->loadMissing('creator');
        if (!$task->creator) {
            return null;
        }

        return $this->createNotification(
            $task->creator,
            AppNotification::TYPE_EXECUTION_TASK,
            'Execution Task Rejected',
            "Task rejected: {$task->title}" . ($reason !== '' ? " ({$reason})" : ''),
            AppNotification::ACTION_USER,
            route('execution-desk.tasks.show', $task),
            [
                'kind' => 'execution_task_rejected',
                'execution_task_id' => $task->id,
                'task_code' => $task->task_code,
                'reason' => $reason,
            ]
        );
    }

    public function notifyOutsidePunchRequest(AttendanceOutsidePunchRequest $outsideRequest): Collection
    {
        $outsideRequest->loadMissing(['user.role', 'officeLocation']);

        $employeeName = $outsideRequest->user?->name ?? 'Employee';
        $punchLabel = $outsideRequest->punch_type === 'out' ? 'punch-out' : 'punch-in';
        $officeName = $outsideRequest->officeLocation?->name ?: 'office';
        $distance = $outsideRequest->geo_distance_meters !== null
            ? number_format((float) $outsideRequest->geo_distance_meters, 0) . 'm away'
            : 'outside office radius';

        $actionUrl = route('hr-manager.attendance.outside-punches', [
            'only_pending_requests' => 1,
            'date' => optional($outsideRequest->attendance_date)->toDateString() ?: now()->toDateString(),
        ]);

        $approveUrl = route('hr-manager.attendance.outside-punches.approve', $outsideRequest);
        $rejectUrl = route('hr-manager.attendance.outside-punches.reject', $outsideRequest);

        $hrUsers = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('slug', Role::HR_MANAGER))
            ->get();

        if ($hrUsers->isEmpty()) {
            return collect();
        }

        return $this->createNotificationsForUsers(
            $this->uniqueUsers($hrUsers->all()),
            AppNotification::TYPE_ATTENDANCE_OUTSIDE_PUNCH,
            'Outside Punch Request',
            "{$employeeName} requested outside {$punchLabel} from {$officeName} ({$distance}).",
            AppNotification::ACTION_ATTENDANCE,
            $actionUrl,
            [
                'kind' => 'outside_punch_request',
                'popup_type' => 'outside_punch_request',
                'primary_action_label' => 'Review Request',
                'secondary_action_label' => 'Open CRM',
                'primary_action_url' => $actionUrl,
                'secondary_action_url' => $actionUrl,
                'outside_punch_request_id' => $outsideRequest->id,
                'employee_id' => $outsideRequest->user_id,
                'employee_name' => $employeeName,
                'punch_type' => $outsideRequest->punch_type,
                'attendance_date' => optional($outsideRequest->attendance_date)->toDateString(),
                'requested_at' => optional($outsideRequest->requested_at)->toIso8601String(),
                'office_name' => $officeName,
                'geo_distance_meters' => $outsideRequest->geo_distance_meters,
                'reason' => $outsideRequest->reason,
                'approve_url' => $approveUrl,
                'reject_url' => $rejectUrl,
                'action_label' => 'Review Request',
            ],
            null,
            'outside-punch-request-' . $outsideRequest->id
        );
    }

    public function sendBroadcast(User $sender, array $attributes): array
    {
        $targetType = $attributes['target_type'] ?? 'all_users';
        $targetRoles = $attributes['target_roles'] ?? [];
        $targetUserIds = collect($attributes['target_user_ids'] ?? [])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
        $priority = $attributes['priority'] ?? BroadcastMessage::PRIORITY_NORMAL;
        $requiresAcknowledge = $attributes['requires_acknowledge']
            ?? in_array($priority, [BroadcastMessage::PRIORITY_IMPORTANT, BroadcastMessage::PRIORITY_URGENT], true);

        $broadcast = BroadcastMessage::create([
            'sender_id' => $sender->id,
            'title' => $attributes['title'],
            'message' => $attributes['message'],
            'priority' => $priority,
            'banner_enabled' => (bool) ($attributes['banner_enabled'] ?? false),
            'requires_acknowledge' => (bool) $requiresAcknowledge,
            'action_label' => $attributes['action_label'] ?? null,
            'action_url' => $attributes['action_url'] ?? null,
            'attachment_path' => $attributes['attachment_path'] ?? null,
            'attachment_name' => $attributes['attachment_name'] ?? null,
            'target_type' => $targetType,
            'target_roles' => $targetType === 'role_based' ? $targetRoles : null,
            'target_user_ids' => $targetType === 'specific_users' ? array_values($targetUserIds) : null,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'status' => $attributes['status'] ?? 'active',
        ]);

        $targetUsers = $this->getTargetUsers($targetType, $targetRoles, $targetUserIds);

        $notifications = [];
        foreach ($targetUsers as $user) {
            $displayMode = match ($broadcast->priority) {
                BroadcastMessage::PRIORITY_URGENT => 'critical',
                BroadcastMessage::PRIORITY_IMPORTANT => 'popup',
                default => 'notification',
            };
            $requiresAcknowledge = $broadcast->resolveRequiresAcknowledge();
            $isCritical = $displayMode === 'critical';
            $announcementActionUrl = $this->resolveAnnouncementLandingUrl($broadcast, $user);
            $primaryCtaLabel = $broadcast->action_label ?: 'View Announcement';

            BroadcastMessageUserState::query()->updateOrCreate(
                [
                    'broadcast_message_id' => $broadcast->id,
                    'user_id' => $user->id,
                ],
                [
                    'delivered_at' => now(),
                    'delivery_channel' => 'in_app,push',
                ]
            );

            $notifications[] = $this->createNotification(
                $user,
                AppNotification::TYPE_ADMIN_BROADCAST,
                $broadcast->title,
                $broadcast->message,
                AppNotification::ACTION_BROADCAST,
                $announcementActionUrl,
                [
                    'announcement_id' => $broadcast->id,
                    'priority' => $broadcast->priority,
                    'display_mode' => $displayMode,
                    'banner_enabled' => (bool) $broadcast->banner_enabled,
                    'requires_acknowledge' => $requiresAcknowledge,
                    'sticky' => $isCritical,
                    'action_label' => $broadcast->action_label,
                    'action_url' => $broadcast->action_url,
                    'primary_cta_label' => $primaryCtaLabel,
                    'secondary_cta_label' => $requiresAcknowledge ? 'Acknowledge' : 'Dismiss',
                    'primary_action_label' => $primaryCtaLabel,
                    'secondary_action_label' => $requiresAcknowledge ? 'Acknowledge' : 'Dismiss',
                    'popup_type' => 'announcement',
                    'attachment_name' => $broadcast->attachment_name,
                    'attachment_url' => $broadcast->attachment_path
                        ? route('announcements.attachment', $broadcast)
                        : null,
                    'starts_at' => optional($broadcast->starts_at)->toIso8601String(),
                    'ends_at' => optional($broadcast->ends_at)->toIso8601String(),
                    'sender_name' => $sender->name,
                    'kind' => 'announcement',
                    'full_screen' => $isCritical ? '1' : '0',
                ],
                null,
                'announcement-' . $broadcast->id
            );
        }

        return [
            'broadcast' => $broadcast->fresh(),
            'notifications' => $notifications,
            'sent_to' => $targetUsers->count(),
        ];
    }

    public function notifyClosingVerificationPending($siteVisit, int $requestedByUserId): void
    {
        $crmUsers = User::whereHas('role', function ($query) {
            $query->where('slug', Role::CRM);
        })->where('is_active', true)->get();

        $requestedBy = User::find($requestedByUserId);
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "New closing request pending verification for lead: {$leadName}";

        foreach ($crmUsers as $crmUser) {
            $this->createNotification(
                $crmUser,
                AppNotification::TYPE_NEW_LEAD,
                'Closing Verification Pending',
                $message,
                AppNotification::ACTION_LEAD,
                url('/crm/verifications'),
                [
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'lead_name' => $leadName,
                    'requested_by' => $requestedBy ? $requestedBy->name : 'Unknown',
                    'type' => 'closing_verification_pending',
                ]
            );
        }
    }

    public function notifyClosingVerified($siteVisit, int $verifiedByUserId): void
    {
        $requestedBy = $siteVisit->creator;
        if (!$requestedBy) {
            return;
        }

        $verifiedBy = User::find($verifiedByUserId);
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Your closing request for lead: {$leadName} has been verified. You can now request incentives.";

        $this->createNotification(
            $requestedBy,
            AppNotification::TYPE_NEW_LEAD,
            'Closing Verified',
            $message,
            AppNotification::ACTION_LEAD,
            url('/sales-manager/site-visits'),
            [
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'verified_by' => $verifiedBy ? $verifiedBy->name : 'CRM',
                'type' => 'closing_verified',
            ]
        );
    }

    public function notifyCloserTransferredToFinance($siteVisit, int $transferredByUserId): void
    {
        $financeManagers = User::whereHas('role', function ($query) {
            $query->where('slug', Role::FINANCE_MANAGER);
        })->where('is_active', true)->get();

        $transferredBy = User::find($transferredByUserId);
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "New closer transferred to Finance for lead: {$leadName}.";

        foreach ($financeManagers as $financeManager) {
            $this->createNotification(
                $financeManager,
                AppNotification::TYPE_NEW_LEAD,
                'Closer Finance Review Pending',
                $message,
                AppNotification::ACTION_LEAD,
                url('/finance-manager/incentives'),
                [
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'lead_name' => $leadName,
                    'transferred_by' => $transferredBy ? $transferredBy->name : 'CRM',
                    'type' => 'closer_finance_review_pending',
                ]
            );
        }
    }

    public function notifyCloserFinanceApproved($siteVisit, int $reviewedByUserId): void
    {
        $recipients = collect([$siteVisit->assignedTo, $siteVisit->creator])
            ->filter()
            ->unique('id');

        $reviewedBy = User::find($reviewedByUserId);
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Finance review completed for lead: {$leadName}. Incentive form is now unlocked.";

        foreach ($recipients as $recipient) {
            $this->createNotification(
                $recipient,
                AppNotification::TYPE_NEW_LEAD,
                'Finance Review Completed',
                $message,
                AppNotification::ACTION_LEAD,
                url('/sales-manager/closed'),
                [
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'lead_name' => $leadName,
                    'reviewed_by' => $reviewedBy ? $reviewedBy->name : 'Finance Manager',
                    'type' => 'closer_finance_review_completed',
                ]
            );
        }
    }

    public function notifyClosingRejected($siteVisit, int $rejectedByUserId, string $reason): void
    {
        $requestedBy = $siteVisit->creator;
        if (!$requestedBy) {
            return;
        }

        $rejectedBy = User::find($rejectedByUserId);
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Your closing request for lead: {$leadName} has been rejected. Reason: {$reason}";

        $this->createNotification(
            $requestedBy,
            AppNotification::TYPE_NEW_LEAD,
            'Closing Rejected',
            $message,
            AppNotification::ACTION_LEAD,
            url('/sales-manager/site-visits'),
            [
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'rejected_by' => $rejectedBy ? $rejectedBy->name : 'CRM',
                'rejection_reason' => $reason,
                'type' => 'closing_rejected',
            ]
        );
    }

    public function notifyIncentiveRequestPending($incentive): void
    {
        $financeManagers = User::whereHas('role', function ($query) {
            $query->where('slug', Role::FINANCE_MANAGER);
        })->where('is_active', true)->get();

        $requestedBy = $incentive->user;
        $siteVisit = $incentive->siteVisit;
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "New incentive request from {$requestedBy->name} for lead: {$leadName} (Amount: â‚¹{$incentive->amount})";

        foreach ($financeManagers as $financeManager) {
            $this->createNotification(
                $financeManager,
                AppNotification::TYPE_NEW_LEAD,
                'Incentive Request Pending',
                $message,
                AppNotification::ACTION_LEAD,
                url('/finance-manager/incentives'),
                [
                    'incentive_id' => $incentive->id,
                    'site_visit_id' => $siteVisit->id,
                    'lead_id' => $siteVisit->lead_id,
                    'lead_name' => $leadName,
                    'requested_by' => $requestedBy->name,
                    'amount' => $incentive->amount,
                    'type' => 'incentive_request_pending',
                ]
            );
        }
    }

    public function notifyIncentiveApproved($incentive): void
    {
        $requestedBy = $incentive->user;
        $siteVisit = $incentive->siteVisit;
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Your incentive request for lead: {$leadName} has been approved. Amount: â‚¹{$incentive->amount}";

        $this->createNotification(
            $requestedBy,
            AppNotification::TYPE_NEW_LEAD,
            'Incentive Approved',
            $message,
            AppNotification::ACTION_LEAD,
            url('/sales-manager/site-visits'),
            [
                'incentive_id' => $incentive->id,
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'amount' => $incentive->amount,
                'type' => 'incentive_approved',
            ]
        );
    }

    public function notifyIncentiveRejected($incentive, string $reason): void
    {
        $requestedBy = $incentive->user;
        $siteVisit = $incentive->siteVisit;
        $leadName = $siteVisit->lead->name ?? ($siteVisit->customer_name ?? 'Lead');
        $message = "Your incentive request for lead: {$leadName} has been rejected. Reason: {$reason}";

        $this->createNotification(
            $requestedBy,
            AppNotification::TYPE_NEW_LEAD,
            'Incentive Rejected',
            $message,
            AppNotification::ACTION_LEAD,
            url('/sales-manager/site-visits'),
            [
                'incentive_id' => $incentive->id,
                'site_visit_id' => $siteVisit->id,
                'lead_id' => $siteVisit->lead_id,
                'lead_name' => $leadName,
                'rejection_reason' => $reason,
                'type' => 'incentive_rejected',
            ]
        );
    }

    private function createNotificationsForUsers(
        Collection $users,
        string $type,
        string $title,
        string $message,
        ?string $actionType,
        ?string $actionUrl,
        array $data = [],
        ?int $telecallerTaskId = null,
        ?string $tag = null
    ): Collection {
        return $users->map(function (User $user) use ($type, $title, $message, $actionType, $actionUrl, $data, $telecallerTaskId, $tag) {
            return $this->createNotification(
                $user,
                $type,
                $title,
                $message,
                $actionType,
                $actionUrl,
                $data,
                $telecallerTaskId,
                $tag ? $tag . '-user-' . $user->id : null
            );
        });
    }

    private function createNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        ?string $actionType,
        ?string $actionUrl,
        array $data = [],
        ?int $telecallerTaskId = null,
        ?string $fcmTag = null
    ): AppNotification {
        $sound = app(NotificationSoundService::class)->resolveForNotification($type, $data);
        $data = $this->resolveNotificationActions($type, $actionUrl, $data);
        $data = array_merge($data, $sound);

        $phonePrivacy = app(PhonePrivacyService::class);
        if ($phonePrivacy->shouldMask($user)) {
            foreach (['primary_action_url', 'secondary_action_url', 'complete_action_url'] as $urlKey) {
                if (str_starts_with((string) ($data[$urlKey] ?? ''), 'tel:')) {
                    unset($data[$urlKey]);
                }
            }
            $data['primary_action_label'] = 'Open Lead';
            $data['primary_action_url'] = !empty($data['lead_id'])
                ? url('/leads/' . (int) $data['lead_id'])
                : $actionUrl;
            $data['primary_action_method'] = 'GET';
            $data = $phonePrivacy->maskPhoneFields($data, $user);
            $title = (string) $phonePrivacy->maskText($title, $user);
            $message = (string) $phonePrivacy->maskText($message, $user);
        }

        $notification = AppNotification::create([
            'user_id' => $user->id,
            'telecaller_task_id' => $telecallerTaskId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_type' => $actionType,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);

        if (app(NotificationQuietHoursService::class)->shouldMute($user)) {
            return $notification;
        }

        $this->broadcastNotification($notification);
        if (($data['kind'] ?? null) === 'announcement' && ($data['display_mode'] ?? 'notification') !== 'critical') {
            return $notification;
        }

        $this->dispatchFcmNotification(
            $user,
            $title,
            $message,
            $actionUrl,
            $fcmTag ?? $type . '-' . $notification->id,
            $this->withMobileActionUrls($user, $actionUrl, array_merge($data, [
                'notification_id' => $notification->id,
                'notification_type' => $type,
            ]))
        );

        return $notification;
    }

    private function broadcastNotification(AppNotification $notification): void
    {
        if ($notification->type === AppNotification::TYPE_NEW_VERIFICATION) {
            event(new NewVerificationNotification($notification));
            return;
        }

        if (in_array($notification->type, [
            AppNotification::TYPE_FOLLOWUP_REMINDER,
            AppNotification::TYPE_FOLLOWUP_OVERDUE,
            AppNotification::TYPE_MEETING_REMINDER,
            AppNotification::TYPE_SITE_VISIT_REMINDER,
        ], true)) {
            event(new FollowupNotification($notification));
            return;
        }

        event(new NewLeadNotification($notification));
    }

    private function dispatchFcmNotification(User $user, string $title, string $message, ?string $actionUrl, string $tag, array $data = []): void
    {
        if (!$actionUrl) {
            return;
        }

        SendFcmNotificationJob::dispatchSync($user->id, $title, $message, $actionUrl, $tag, $data);
    }

    private function resolveNotificationActions(string $type, ?string $actionUrl, array $data): array
    {
        $data['dismiss_label'] = $data['dismiss_label'] ?? 'Dismiss';

        $leadPhone = $this->normalizePhoneForAction($data['lead_phone'] ?? $data['phone'] ?? $data['mobile'] ?? null);
        $leadUrl = !empty($data['lead_id']) ? url('/leads/' . (int) $data['lead_id']) : $actionUrl;
        $taskUrl = $data['task_url'] ?? $actionUrl;
        $kind = (string) ($data['kind'] ?? '');
        $stage = (string) ($data['stage'] ?? '');

        $setPrimary = function (string $label, ?string $url, string $method = 'GET') use (&$data): void {
            if (!$url) {
                return;
            }
            $data['primary_action_label'] = $data['primary_action_label'] ?? $label;
            $data['primary_action_url'] = $data['primary_action_url'] ?? $url;
            $data['primary_action_method'] = $data['primary_action_method'] ?? strtoupper($method);
        };

        $setSecondary = function (string $label, ?string $url, string $method = 'GET', bool $confirm = false) use (&$data): void {
            if (!$url) {
                return;
            }
            $data['secondary_action_label'] = $data['secondary_action_label'] ?? $label;
            $data['secondary_action_url'] = $data['secondary_action_url'] ?? $url;
            $data['secondary_action_method'] = $data['secondary_action_method'] ?? strtoupper($method);
            if ($confirm) {
                $data['requires_confirm'] = $data['requires_confirm'] ?? true;
            }
        };

        $callUrl = $leadPhone ? 'tel:' . $leadPhone : null;

        switch ($type) {
            case AppNotification::TYPE_NEW_LEAD:
                if ($callUrl) {
                    $setPrimary('Call Lead', $callUrl);
                    $setSecondary('Open Lead', $leadUrl ?: $actionUrl);
                } else {
                    $setPrimary('Open Lead', $leadUrl ?: $actionUrl);
                }
                break;

            case AppNotification::TYPE_FOLLOWUP_REMINDER:
            case AppNotification::TYPE_FOLLOWUP_OVERDUE:
                $completeUrl = !empty($data['followup_id'])
                    ? url('/api/follow-ups/' . (int) $data['followup_id'] . '/complete')
                    : null;
                $setPrimary($callUrl ? 'Call Lead' : 'Open Lead', $callUrl ?: ($taskUrl ?: $leadUrl));
                $setSecondary('Complete Follow-up', $completeUrl, 'POST', true);
                $data['complete_action_url'] = $data['complete_action_url'] ?? $completeUrl;
                break;

            case AppNotification::TYPE_CALL_REMINDER:
                $setPrimary('Call Now', $callUrl ?: $taskUrl);
                $setSecondary('Open Task', $taskUrl);
                break;

            case AppNotification::TYPE_TASK_OVERDUE:
                $setPrimary($callUrl ? 'Call Lead' : 'Open Task', $callUrl ?: $taskUrl);
                $setSecondary('Open Task', $taskUrl);
                break;

            case AppNotification::TYPE_MEETING:
                if ($callUrl) {
                    $setPrimary('Call Lead', $callUrl);
                    $setSecondary('Open Meeting', $actionUrl);
                } else {
                    $setPrimary('Open Meeting', $actionUrl);
                }
                break;

            case AppNotification::TYPE_MEETING_REMINDER:
                if ($kind === 'activity_lifecycle_reminder' && str_contains($stage, 'outcome')) {
                    $setPrimary('Update Outcome', $actionUrl);
                    $setSecondary('Open Lead', $leadUrl);
                } else {
                    if ($callUrl) {
                        $setPrimary('Call Lead', $callUrl);
                        $setSecondary('Open Meeting', $actionUrl);
                    } else {
                        $setPrimary('Open Meeting', $actionUrl);
                    }
                }
                break;

            case AppNotification::TYPE_SITE_VISIT:
                if ($callUrl) {
                    $setPrimary('Call Lead', $callUrl);
                    $setSecondary('Open Visit', $actionUrl);
                } else {
                    $setPrimary('Open Visit', $actionUrl);
                }
                break;

            case AppNotification::TYPE_SITE_VISIT_REMINDER:
                if ($kind === 'activity_lifecycle_reminder' && str_contains($stage, 'outcome')) {
                    $setPrimary('Update Outcome', $actionUrl);
                    $setSecondary('Open Lead', $leadUrl);
                } else {
                    if ($callUrl) {
                        $setPrimary('Call Lead', $callUrl);
                        $setSecondary('Open Visit', $actionUrl);
                    } else {
                        $setPrimary('Open Visit', $actionUrl);
                    }
                }
                break;

            case AppNotification::TYPE_EXECUTION_TASK_DUE:
            case AppNotification::TYPE_EXECUTION_TASK_OVERDUE:
            case AppNotification::TYPE_EXECUTION_TASK:
                $setPrimary('Open Task', $actionUrl);
                break;

            case AppNotification::TYPE_ADMIN_BROADCAST:
                $setPrimary('View', $actionUrl);
                $data['secondary_action_label'] = $data['secondary_action_label'] ?? 'Dismiss';
                $data['secondary_action_method'] = $data['secondary_action_method'] ?? 'DISMISS';
                break;
        }

        if (($kind === 'attendance_reminder' || $type === 'attendance_reminder') && $actionUrl) {
            $slot = (string) ($data['slot'] ?? '');
            $label = $slot === 'punchout' ? 'Punch Out Now' : 'Punch In Now';
            $setPrimary($label, $actionUrl);
            $setSecondary('Open Attendance', $actionUrl);
        }

        if (empty($data['primary_action_url']) && $actionUrl) {
            $data['primary_action_label'] = $data['primary_action_label'] ?? 'Open CRM';
            $data['primary_action_url'] = $actionUrl;
            $data['primary_action_method'] = $data['primary_action_method'] ?? 'GET';
        }

        $data['primary_action_method'] = strtoupper((string) ($data['primary_action_method'] ?? 'GET'));
        if (!empty($data['secondary_action_url']) && empty($data['secondary_action_method'])) {
            $data['secondary_action_method'] = 'GET';
        }
        if (!empty($data['secondary_action_method'])) {
            $data['secondary_action_method'] = strtoupper((string) $data['secondary_action_method']);
        }

        return $data;
    }

    private function normalizePhoneForAction(mixed $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);

        return $digits !== '' ? $digits : null;
    }

    private function withMobileActionUrls(User $user, ?string $actionUrl, array $data): array
    {
        $leadId = isset($data['lead_id']) ? (int) $data['lead_id'] : null;
        if (!$leadId) {
            return $data;
        }

        $data['lead_url'] = url('/leads/' . $leadId);
        $data['task_url'] = $this->resolveMobileTaskUrl($user, $leadId) ?? $actionUrl ?? $data['lead_url'];

        return $data;
    }

    private function resolveMobileTaskUrl(User $user, int $leadId): ?string
    {
        if ($user->isSalesExecutive()) {
            $task = TelecallerTask::query()
                ->where('lead_id', $leadId)
                ->where('assigned_to', $user->id)
                ->whereIn('status', ['pending', 'overdue'])
                ->latest('id')
                ->first();

            return $task ? url('/telecaller/tasks?status=pending&task_id=' . $task->id) : null;
        }

        $task = Task::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $user->id)
            ->whereIn('status', ['pending', 'overdue'])
            ->latest('id')
            ->first();

        return $task ? url('/sales-manager/tasks?status=pending&task_id=' . $task->id) : null;
    }

    private function resolveTestActionType(string $type): ?string
    {
        return match ($type) {
            AppNotification::TYPE_NEW_VERIFICATION => AppNotification::ACTION_VERIFICATION,
            AppNotification::TYPE_FOLLOWUP_REMINDER,
            AppNotification::TYPE_FOLLOWUP_OVERDUE => AppNotification::ACTION_FOLLOWUP,
            default => AppNotification::ACTION_LEAD,
        };
    }

    private function resolveFollowupReminderRecipients(FollowUp $followup): Collection
    {
        return $this->uniqueUsers([
            $followup->creator,
            $this->resolveLeadOwner($followup->lead),
        ]);
    }

    private function resolveOverdueTaskRecipients(TelecallerTask $task): Collection
    {
        $recipients = [$task->assignedTo];
        $manager = $task->assignedTo?->manager;

        if ($manager?->wantsTeamNotification('task_overdue')) {
            $recipients[] = $manager;
        }

        return $this->uniqueUsers($recipients);
    }

    private function resolveOverdueFollowupRecipients(FollowUp $followup): Collection
    {
        $responsibleUser = $this->resolveLeadOwner($followup->lead) ?? $followup->creator;

        $recipients = [$responsibleUser];
        $manager = $responsibleUser?->manager;

        if ($manager?->wantsTeamNotification('followup_overdue')) {
            $recipients[] = $manager;
        }

        return $this->uniqueUsers($recipients);
    }

    private function resolveLeadOwner(?Lead $lead): ?User
    {
        if (!$lead) {
            return null;
        }

        $lead->loadMissing('activeAssignments.assignedTo.manager');

        return optional($lead->activeAssignments->first())->assignedTo;
    }

    private function resolveTaskActionUrl(TelecallerTask $task): string
    {
        if ($task->lead_id) {
            return url('/leads/' . $task->lead_id);
        }

        return url('/telecaller/tasks?status=pending&task_id=' . $task->id);
    }

    private function resolveFollowupActionUrl(FollowUp $followup, ?User $user = null): string
    {
        if ($followup->lead_id) {
            if ($taskUrl = $this->resolveRelatedTaskActionUrl(
                leadId: $followup->lead_id,
                user: $user,
                managerColumn: 'follow_up_id',
                managerValue: $followup->id,
                telecallerColumn: 'follow_up_id',
                telecallerValue: $followup->id
            )) {
                return $taskUrl;
            }

            return url('/leads/' . $followup->lead_id);
        }

        return url('/leads');
    }

    private function resolveMeetingActionUrl(Meeting $meeting, ?User $user = null): string
    {
        if ($meeting->lead_id) {
            if ($taskUrl = $this->resolveRelatedTaskActionUrl(
                leadId: $meeting->lead_id,
                user: $user,
                managerColumn: 'meeting_id',
                managerValue: $meeting->id,
                telecallerColumn: 'meeting_id',
                telecallerValue: $meeting->id
            )) {
                return $taskUrl;
            }

            return url('/leads/' . $meeting->lead_id);
        }

        return url('/sales-manager/meetings');
    }

    private function resolveSiteVisitActionUrl(SiteVisit $siteVisit, ?User $user = null): string
    {
        if ($siteVisit->lead_id) {
            if ($taskUrl = $this->resolveRelatedTaskActionUrl(
                leadId: $siteVisit->lead_id,
                user: $user,
                managerColumn: 'site_visit_id',
                managerValue: $siteVisit->id,
                telecallerColumn: 'site_visit_id',
                telecallerValue: $siteVisit->id
            )) {
                return $taskUrl;
            }

            return url('/leads/' . $siteVisit->lead_id);
        }

        return url('/sales-manager/site-visits');
    }

    private function resolveManagerTaskActionUrl(Task $task): string
    {
        if ($task->lead_id) {
            $query = http_build_query([
                'open_task' => $task->id,
                'task_scheduled_at' => optional($task->scheduled_at)->toIso8601String(),
                'back' => '/sales-manager/tasks',
            ]);

            return url('/leads/' . $task->lead_id) . ($query ? ('?' . $query) : '');
        }

        return url('/sales-manager/tasks?status=pending&task_id=' . $task->id);
    }

    private function resolveRelatedTaskActionUrl(
        int $leadId,
        ?User $user,
        string $managerColumn,
        int $managerValue,
        string $telecallerColumn,
        int $telecallerValue
    ): ?string {
        if (!$user) {
            return null;
        }

        if ($user->isSalesExecutive()) {
            $telecallerTask = TelecallerTask::query()
                ->where('lead_id', $leadId)
                ->where('assigned_to', $user->id)
                ->where($telecallerColumn, $telecallerValue)
                ->latest('id')
                ->first();

            if ($telecallerTask) {
                return url('/telecaller/tasks?status=pending&task_id=' . $telecallerTask->id);
            }

            return null;
        }

        $managerTask = Task::query()
            ->where('lead_id', $leadId)
            ->where('assigned_to', $user->id)
            ->where($managerColumn, $managerValue)
            ->latest('id')
            ->first();

        return $managerTask ? $this->resolveManagerTaskActionUrl($managerTask) : null;
    }

    private function uniqueUsers(array $users): Collection
    {
        return collect($users)
            ->filter(fn ($user) => $user instanceof User && $user->is_active)
            ->unique('id')
            ->values();
    }

    private function resolveAnnouncementLandingUrl(BroadcastMessage $broadcast, User $user): string
    {
        if ($broadcast->action_url) {
            return $broadcast->action_url;
        }

        $homeUrl = app(AuthRedirectService::class)->redirectPathFor($user);
        $separator = str_contains($homeUrl, '?') ? '&' : '?';

        return $homeUrl . $separator . 'announcement=' . $broadcast->id;
    }

    private function getTargetUsers(string $targetType, array $targetRoles = [], array $targetUserIds = []): Collection
    {
        if ($targetType === 'all_users') {
            return User::where('is_active', true)->get();
        }

        if ($targetType === 'specific_users' && !empty($targetUserIds)) {
            return User::where('is_active', true)
                ->whereIn('id', $targetUserIds)
                ->get();
        }

        if (!empty($targetRoles)) {
            $roleIds = Role::whereIn('slug', $targetRoles)->pluck('id');

            return User::where('is_active', true)
                ->whereIn('role_id', $roleIds)
                ->get();
        }

        return collect();
    }
}
