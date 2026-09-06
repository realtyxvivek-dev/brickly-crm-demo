<?php

namespace App\Services;

use App\Models\User;

class AuthRedirectService
{
    public function redirectPathFor(User $user): string
    {
        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return match ($user->role->slug ?? '') {
            'telecaller' => route('calling-center.queue'),
            'sales_executive' => route('sales-executive.dashboard'),
            'sales_manager' => $user->isSalesHead()
                ? route('sales-head.dashboard')
                : route('sales-manager.dashboard'),
            'senior_manager' => route('sales-manager.dashboard'),
            'assistant_sales_manager' => route('sales-manager.dashboard'),
            'admin' => route('admin.dashboard'),
            'hr_manager' => route('hr-manager.hiring.index'),
            'junior_hr' => route('junior-hr.hiring.index'),
            'finance_manager' => route('finance-manager.dashboard'),
            'ad_manager' => route('dashboard'),
            'marketing_manager', 'marketing_executive' => route('marketing.dashboard'),
            'lead_quality_auditor' => route('lead-quality-auditor.dashboard'),
            'crm' => route('dashboard'),
            default => '/',
        };
    }
}
