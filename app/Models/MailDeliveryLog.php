<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MailDeliveryLog extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const TYPE_DAILY_ADMIN_REPORT = 'daily_admin_report';
    public const TYPE_PAYROLL_PREVIEW = 'payroll_preview';
    public const TYPE_LEAD_DOWNLOAD_READY = 'lead_download_ready';
    public const TYPE_NEW_LEAD_SLA_ESCALATION = 'new_lead_sla_escalation';
    public const TYPE_HIGH_BUDGET_LEAD_ALERT = 'high_budget_lead_alert';
    public const TYPE_POST_SALE_DEMAND_TEST = 'post_sale_demand_test';
    public const TYPE_POST_SALE_DEMAND_REMINDER = 'post_sale_demand_reminder';
    public const TYPE_DEMO_REQUEST = 'demo_request';

    protected $fillable = [
        'mail_type',
        'subject',
        'recipient_email',
        'recipient_user_id',
        'status',
        'payload_summary',
        'related_type',
        'related_id',
        'sent_at',
        'failed_at',
        'error_message',
        'created_by',
        'resend_of_log_id',
    ];

    protected $casts = [
        'payload_summary' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_DAILY_ADMIN_REPORT => 'Daily Admin Report',
            self::TYPE_PAYROLL_PREVIEW => 'Payroll Preview',
            self::TYPE_LEAD_DOWNLOAD_READY => 'Lead Download Ready',
            self::TYPE_NEW_LEAD_SLA_ESCALATION => 'New Lead SLA Escalation',
            self::TYPE_HIGH_BUDGET_LEAD_ALERT => 'High Budget Lead Alert',
            self::TYPE_POST_SALE_DEMAND_TEST => 'Post Sales Demand Test',
            self::TYPE_POST_SALE_DEMAND_REMINDER => 'Post Sales Demand Reminder',
            self::TYPE_DEMO_REQUEST => 'Demo Request',
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_SKIPPED => 'Skipped',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resendOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resend_of_log_id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'related_type', 'related_id');
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->mail_type] ?? str($this->mail_type)->replace('_', ' ')->title()->toString();
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? ucfirst((string) $this->status);
    }
}
