<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telecaller_task_id',
        'type',
        'title',
        'message',
        'data',
        'action_type',
        'action_url',
        'read_at',
        'clicked_at',
    ];

    // Notification types
    public const TYPE_CALL_REMINDER = 'call_reminder';
    public const TYPE_NEW_LEAD = 'new_lead';
    public const TYPE_NEW_VERIFICATION = 'new_verification';
    public const TYPE_FOLLOWUP_REMINDER = 'followup_reminder';
    public const TYPE_MEETING_REMINDER = 'meeting_reminder';
    public const TYPE_SITE_VISIT_REMINDER = 'site_visit_reminder';
    public const TYPE_TASK_OVERDUE = 'task_overdue';
    public const TYPE_FOLLOWUP_OVERDUE = 'followup_overdue';
    public const TYPE_ADMIN_BROADCAST = 'admin_broadcast';
    public const TYPE_SITE_VISIT = 'site_visit';
    public const TYPE_MEETING = 'meeting';
    public const TYPE_NEW_USER = 'new_user';
    public const TYPE_EXECUTION_TASK = 'execution_task';
    public const TYPE_EXECUTION_TASK_DUE = 'execution_task_due';
    public const TYPE_EXECUTION_TASK_OVERDUE = 'execution_task_overdue';
    public const TYPE_SELF_TODO_REMINDER = 'self_todo_reminder';
    public const TYPE_KNOWLEDGE_BASE_ASSIGNED = 'knowledge_base_assigned';
    public const TYPE_KNOWLEDGE_BASE_DUE_SOON = 'knowledge_base_due_soon';
    public const TYPE_KNOWLEDGE_BASE_DUE_TODAY = 'knowledge_base_due_today';
    public const TYPE_KNOWLEDGE_BASE_OVERDUE = 'knowledge_base_overdue';
    public const TYPE_KNOWLEDGE_BASE_PATH_ASSIGNED = 'knowledge_base_path_assigned';
    public const TYPE_ATTENDANCE_OUTSIDE_PUNCH = 'attendance_outside_punch';
    public const TYPE_DESKTOP_ISSUE_REPORT = 'desktop_issue_report';
    public const TYPE_PASSWORD_CHANGE = 'password_change';
    public const TYPE_LOGIN_SECURITY = 'login_security';
    public const TYPE_PURCHASE_ORDER = 'purchase_order';

    // Action types
    public const ACTION_LEAD = 'lead';
    public const ACTION_USER = 'user';
    public const ACTION_VERIFICATION = 'verification';
    public const ACTION_FOLLOWUP = 'followup';
    public const ACTION_BROADCAST = 'broadcast';
    public const ACTION_KNOWLEDGE_BASE = 'knowledge_base';
    public const ACTION_ATTENDANCE = 'attendance';
    public const ACTION_EXECUTION_DESK = 'execution_desk';
    public const ACTION_DESKTOP_ISSUE = 'desktop_issue_report';
    public const ACTION_PASSWORD = 'password';
    public const ACTION_LOGIN_SECURITY = 'login_security';
    public const ACTION_PURCHASE_ORDER = 'purchase_order';

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (AppNotification $notification) {
            $recipient = User::query()->find($notification->user_id);
            $privacy = app(\App\Services\PhonePrivacyService::class);
            if (!$privacy->shouldMask($recipient)) {
                return;
            }

            $notification->data = $privacy->maskPhoneFields((array) $notification->data, $recipient);
            $notification->title = $privacy->maskText($notification->title, $recipient);
            $notification->message = $privacy->maskText($notification->message, $recipient);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telecallerTask(): BelongsTo
    {
        return $this->belongsTo(TelecallerTask::class);
    }

    /**
     * Scope to get unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to get recent notifications
     */
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark notification as clicked
     */
    public function markAsClicked(): void
    {
        if (!$this->clicked_at) {
            $this->update(['clicked_at' => now()]);
        }
        $this->markAsRead();
    }
}
