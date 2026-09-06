<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SourceAutomationRule extends Model
{
    protected $fillable = [
        'name',
        'source',
        'source_type',
        'source_id',
        'source_label',
        'fb_form_id',
        'google_sheet_config_id',
        'assignment_method',
        'distribution_method',
        'single_user_id',
        'auto_create_task',
        'task_enabled',
        'notification_enabled',
        'daily_limit',
        'fallback_user_id',
        'skip_lead_off_users',
        'duplicate_handling',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'auto_create_task' => 'boolean',
        'task_enabled' => 'boolean',
        'notification_enabled' => 'boolean',
        'skip_lead_off_users' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(SourceAutomationRuleUser::class, 'rule_id');
    }

    public function fbForm()
    {
        return $this->belongsTo(FbForm::class, 'fb_form_id');
    }

    public function ruleForms()
    {
        return $this->hasMany(SourceAutomationRuleForm::class, 'rule_id');
    }

    public function fbForms()
    {
        return $this->belongsToMany(FbForm::class, 'source_automation_rule_forms', 'rule_id', 'fb_form_id')
            ->withTimestamps();
    }

    public function googleSheetConfig()
    {
        return $this->belongsTo(GoogleSheetsConfig::class, 'google_sheet_config_id');
    }

    public function singleUser()
    {
        return $this->belongsTo(User::class, 'single_user_id');
    }

    public function fallbackUser()
    {
        return $this->belongsTo(User::class, 'fallback_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function getSourceLabel(string $source): string
    {
        return match($source) {
            'meta'              => 'Meta Leads',
            'meta_awareness'    => 'Meta Awareness',
            'facebook_lead_ads' => 'Facebook Lead Ads',
            'pabbly'            => 'Pabbly',
            'mcube', 'ivr'      => 'IVR',
            'google_sheets'     => 'Google Sheets',
            'csv'               => 'CSV Import',
            'manual_import'     => 'Manual Import',
            'website'           => 'Custom Website',
            'whatsapp'          => 'WhatsApp',
            '99acres'           => '99acres',
            'instagram'         => 'Instagram Automation',
            'all'               => 'All Sources',
            default             => ucfirst($source),
        };
    }

    public static function getMethodLabel(string $method): string
    {
        return match($method) {
            'round_robin'     => 'Round Robin',
            'first_available' => 'First Available',
            'percentage'      => 'Percentage',
            'single_user'     => 'Single User',
            default           => ucfirst($method),
        };
    }

    public function effectiveSourceType(): string
    {
        return $this->source_type ?: ($this->source === 'mcube' ? 'ivr' : $this->source);
    }

    public function effectiveSourceId(): ?string
    {
        if ($this->source_id !== null && $this->source_id !== '') {
            return (string) $this->source_id;
        }

        if ($this->fb_form_id) {
            return (string) $this->fb_form_id;
        }

        if ($this->google_sheet_config_id) {
            return (string) $this->google_sheet_config_id;
        }

        return null;
    }

    public function effectiveDistributionMethod(): string
    {
        return $this->distribution_method ?: $this->assignment_method;
    }

    public function taskEnabled(): bool
    {
        return $this->task_enabled ?? $this->auto_create_task ?? true;
    }

    public function notificationEnabled(): bool
    {
        return $this->notification_enabled ?? true;
    }
}
