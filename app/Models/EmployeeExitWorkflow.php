<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExitWorkflow extends Model
{
    use HasFactory;

    public const STATUS_ON_NOTICE = 'on_notice';
    public const STATUS_RESIGNED = 'resigned';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'employee_profile_id',
        'status',
        'notice_start_date',
        'resignation_date',
        'last_working_date',
        'exit_reason',
        'hr_clearance_completed_at',
        'finance_clearance_completed_at',
        'asset_clearance_completed_at',
        'login_disabled_at',
        'closed_at',
        'last_notice_reminder_sent_at',
        'last_asset_alert_sent_at',
        'notes',
    ];

    protected $casts = [
        'notice_start_date' => 'date',
        'resignation_date' => 'date',
        'last_working_date' => 'date',
        'hr_clearance_completed_at' => 'datetime',
        'finance_clearance_completed_at' => 'datetime',
        'asset_clearance_completed_at' => 'datetime',
        'login_disabled_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_notice_reminder_sent_at' => 'datetime',
        'last_asset_alert_sent_at' => 'datetime',
    ];

    public function employeeProfile(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class);
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
