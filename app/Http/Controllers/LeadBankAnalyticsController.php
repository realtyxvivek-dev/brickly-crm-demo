<?php

namespace App\Http\Controllers;

use App\Services\LeadBankAnalyticsService;

class LeadBankAnalyticsController extends Controller
{
    public function __invoke(LeadBankAnalyticsService $analyticsService)
    {
        return view('lead-bank.analytics', [
            'analytics' => $analyticsService->dashboard(),
        ]);
    }
}
