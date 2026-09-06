<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\HighBudgetLeadAlertService;

class LeadObserver
{
    public function updating(Lead $lead): void
    {
        if (!$lead->isDirty(['status', 'is_dead', 'cnp_count', 'next_followup_at'])) return;
        $boundary = app(\App\Services\LeadReopenService::class)->boundary((int) $lead->id);
        if (!$boundary) return;
        $current = Lead::whereKey($lead->id)->first();
        abort_if($current && $lead->getRawOriginal('updated_at') !== $current->getRawOriginal('updated_at')
            && $lead->getRawOriginal('status') !== $current->getRawOriginal('status'), 409, 'Lead cycle changed. Refresh before updating.');
    }

    public function saved(Lead $lead): void
    {
        app(HighBudgetLeadAlertService::class)->checkLead($lead);
    }
}
