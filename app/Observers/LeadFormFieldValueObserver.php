<?php

namespace App\Observers;

use App\Models\LeadFormFieldValue;
use App\Services\HighBudgetLeadAlertService;

class LeadFormFieldValueObserver
{
    public function saved(LeadFormFieldValue $value): void
    {
        if ($value->field_key !== 'budget') {
            return;
        }

        $value->loadMissing('lead.formFieldValues');
        if ($value->lead) {
            app(HighBudgetLeadAlertService::class)->checkLead($value->lead);
        }
    }
}
