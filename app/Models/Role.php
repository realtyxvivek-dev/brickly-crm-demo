<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // Role constants
    public const ADMIN = 'admin';
    public const CRM = 'crm';
    public const HR_MANAGER = 'hr_manager';
    public const JUNIOR_HR = 'junior_hr';
    public const FINANCE_MANAGER = 'finance_manager';
    public const AD_MANAGER = 'ad_manager';
    public const LEAD_MANAGER = 'lead_manager';
    public const LEAD_QUALITY_AUDITOR = 'lead_quality_auditor';
    public const SALES_MANAGER = 'sales_manager';
    public const SENIOR_MANAGER = 'senior_manager';
    public const ASSISTANT_SALES_MANAGER = 'assistant_sales_manager';
    public     const SALES_EXECUTIVE = 'sales_executive';
    public const MARKETING_MANAGER = 'marketing_manager';
    public const MARKETING_EXECUTIVE = 'marketing_executive';
    public const TELECALLER = 'telecaller';
}
