<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesktopIssueReport extends Model
{
    protected $fillable = [
        'reported_by_user_id',
        'assigned_to_user_id',
        'issue_type',
        'title',
        'description',
        'current_url',
        'page_title',
        'app_version_name',
        'app_version_code',
        'device_name',
        'os_version',
        'status',
        'activity_logs',
        'last_error',
        'reported_at',
        'resolved_at',
    ];

    protected $casts = [
        'activity_logs' => 'array',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public const ISSUE_TYPES = [
        'login' => 'Login',
        'lead' => 'Lead',
        'task' => 'Task',
        'whatsapp' => 'WhatsApp',
        'attendance' => 'Attendance',
        'slow_app' => 'Slow App',
        'other' => 'Other',
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function getIssueTypeLabelAttribute(): string
    {
        return self::ISSUE_TYPES[$this->issue_type] ?? ucfirst(str_replace('_', ' ', (string) $this->issue_type));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'resolved' => 'Resolved',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'open' => '#0f766e',
            'in_progress' => '#b45309',
            'resolved' => '#15803d',
            default => '#475569',
        };
    }

    public function getStatusBgAttribute(): string
    {
        return match ($this->status) {
            'open' => '#ccfbf1',
            'in_progress' => '#fef3c7',
            'resolved' => '#dcfce7',
            default => '#f1f5f9',
        };
    }
}
