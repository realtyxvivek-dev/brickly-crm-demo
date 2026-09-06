<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EmployeeProfile extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_ON_NOTICE = 'on_notice';
    public const STATUS_RESIGNED = 'resigned';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_LONG_LEAVE = 'long_leave';

    public const REQUIRED_DOCUMENT_TYPES = [
        'pan_card',
        'aadhaar_card',
        'id_proof',
        'address_proof',
        'appointment_letter',
    ];

    protected $fillable = [
        'user_id',
        'employee_code',
        'department_id',
        'designation_id',
        'joining_date',
        'date_of_birth',
        'employment_status',
        'employment_status_changed_at',
        'probation_end_date',
        'salary_day_of_month',
        'account_holder_name',
        'bank_account_number',
        'ifsc_code',
        'pan_number',
        'aadhaar_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'current_address',
        'permanent_address',
        'notes',
        'welcome_email_sent_at',
        'probation_alert_sent_at',
        'document_alert_sent_at',
        'salary_reminder_sent_on',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'date_of_birth' => 'date',
        'employment_status_changed_at' => 'datetime',
        'probation_end_date' => 'date',
        'welcome_email_sent_at' => 'datetime',
        'probation_alert_sent_at' => 'datetime',
        'document_alert_sent_at' => 'datetime',
        'salary_reminder_sent_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(EmployeeDepartment::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(EmployeeDesignation::class, 'designation_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function detailLinks(): HasMany
    {
        return $this->hasMany(EmployeeProfileLink::class);
    }

    public function latestDetailLink(): HasOne
    {
        return $this->hasOne(EmployeeProfileLink::class)->latestOfMany();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EmployeeAsset::class);
    }

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(EmployeeTimelineEvent::class)->orderByDesc('event_date')->orderByDesc('id');
    }

    public function exitWorkflow(): HasOne
    {
        return $this->hasOne(EmployeeExitWorkflow::class);
    }

    public function salaryRevisions(): HasMany
    {
        return $this->hasMany(EmployeeSalaryRevision::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    public function missingDocumentTypes(): array
    {
        $present = $this->documents->pluck('document_type')->filter()->unique()->values()->all();

        return array_values(array_diff(self::REQUIRED_DOCUMENT_TYPES, $present));
    }
}
