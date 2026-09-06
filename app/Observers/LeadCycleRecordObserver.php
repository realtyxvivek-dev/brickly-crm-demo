<?php

namespace App\Observers;

use App\Services\LeadReopenService;
use Illuminate\Database\Eloquent\Model;

class LeadCycleRecordObserver
{
    public function updating(Model $record): void
    {
        if ($record->isDirty(['status', 'call_status', 'outcome', 'completed_at', 'scheduled_at', 'verification_status'])) {
            app(LeadReopenService::class)->assertCurrentRecord($record);
        }
    }
}
