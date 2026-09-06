<?php

namespace App\Services;

use App\Models\SiteVisit;
use App\Models\SiteVisitRevenueAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SiteVisitRevenueService
{
    public function updateRevenue(SiteVisit $siteVisit, User $actor, float $revenueValue, ?string $note = null): SiteVisit
    {
        $note = trim((string) $note);
        $note = $note !== '' ? $note : null;

        return DB::transaction(function () use ($siteVisit, $actor, $revenueValue, $note) {
            $oldRevenue = $siteVisit->revenue_value;
            $oldNote = $siteVisit->revenue_note;
            $isFirstEntry = $oldRevenue === null;

            SiteVisitRevenueAudit::create([
                'site_visit_id' => $siteVisit->id,
                'old_revenue_value' => $oldRevenue,
                'new_revenue_value' => $revenueValue,
                'old_revenue_note' => $oldNote,
                'new_revenue_note' => $note,
                'changed_by' => $actor->id,
            ]);

            $siteVisit->revenue_value = $revenueValue;
            $siteVisit->revenue_note = $note;

            if ($isFirstEntry) {
                $siteVisit->revenue_entered_by = $actor->id;
                $siteVisit->revenue_entered_at = now();
            }

            $siteVisit->revenue_updated_by = $actor->id;
            $siteVisit->revenue_updated_at = now();
            $siteVisit->save();

            return $siteVisit->fresh();
        });
    }
}
