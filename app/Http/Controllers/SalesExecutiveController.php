<?php

namespace App\Http\Controllers;

use App\Services\TelecallerDashboardService;
use Illuminate\Http\Request;

class SalesExecutiveController extends Controller
{
    private function redirectDedicatedTelecaller(string $route)
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'isDedicatedTelecaller') && $user->isDedicatedTelecaller()) {
            return redirect()->route($route, request()->query());
        }

        return null;
    }

    /**
     * Show sales executive dashboard
     */
    public function dashboard(Request $request, TelecallerDashboardService $service)
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.dashboard')) {
            return $redirect;
        }

        $userId = auth()->id();
        
        if (!$userId) {
            return redirect()->route('login')->with('error', 'Please login to access the dashboard.');
        }
        
        $dateRange = $request->get('date_range', 'today');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $targetMonth = $request->get('target_month', now()->format('Y-m')); // Default to current month
        $targetFilter = $request->get('target_filter', 'today'); // Default to today
        
        $data = $service->getDashboardData($userId, $dateRange, $startDate, $endDate);
        $cardStats = $service->getDashboardCardStats($userId, $dateRange, $startDate, $endDate, $targetMonth, $targetFilter);
        
        return view('sales-executive.sections.dashboard', compact('data', 'cardStats', 'dateRange', 'startDate', 'endDate', 'targetMonth', 'targetFilter'));
    }

    /**
     * Show sales executive tasks page
     */
    public function tasks()
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.tasks')) {
            return $redirect;
        }

        return view('sales-executive.sections.tasks');
    }

    /**
     * Show sales executive leads page
     */
    public function leads()
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.leads')) {
            return $redirect;
        }

        return view('sales-executive.sections.leads');
    }

    /**
     * Show sales executive reports page
     */
    public function reports()
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.reports')) {
            return $redirect;
        }

        return view('sales-executive.sections.reports');
    }

    /**
     * Show sales executive verification pending page
     */
    public function verificationPending()
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.verification-pending')) {
            return $redirect;
        }

        return view('sales-executive.sections.verification-pending');
    }

    /**
     * Show sales executive profile page
     */
    public function profile()
    {
        if ($redirect = $this->redirectDedicatedTelecaller('telecaller.profile')) {
            return $redirect;
        }

        return view('sales-executive.sections.profile');
    }
}
