<?php

namespace App\Observers;

use App\Models\SiteVisit;
use App\Services\HighBudgetLeadAlertService;

class SiteVisitObserver
{
    public function saved(SiteVisit $siteVisit): void
    {
        app(HighBudgetLeadAlertService::class)->checkSiteVisit($siteVisit);
    }
}
