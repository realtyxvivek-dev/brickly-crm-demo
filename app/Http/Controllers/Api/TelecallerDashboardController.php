<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelecallerDashboardService;
use App\Services\CallLogService;
use Illuminate\Http\Request;

class TelecallerDashboardController extends Controller
{
    protected $dashboardService;
    protected $callLogService;

    public function __construct(TelecallerDashboardService $dashboardService, CallLogService $callLogService)
    {
        $this->dashboardService = $dashboardService;
        $this->callLogService = $callLogService;
    }

    /**
     * Get full dashboard data
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->dashboardService->getDashboardData($userId);
        
        // Add call statistics
        $callStats = $this->callLogService->getCallStatistics($userId, 'today');
        $callStatsWeek = $this->callLogService->getCallStatistics($userId, 'this_week');
        $callsPerHour = $this->callLogService->getCallsPerHour($userId, \Carbon\Carbon::today());
        
        // Get recent calls (last 5)
        $recentCalls = \App\Models\CallLog::forUser($userId)
            ->with(['lead:id,name,phone', 'user:id,name'])
            ->orderBy('start_time', 'desc')
            ->limit(5)
            ->get()
            ->map(function($call) {
                return [
                    'id' => $call->id,
                    'phone_number' => $call->phone_number,
                    'lead_name' => $call->lead->name ?? 'N/A',
                    'duration' => $call->formatted_duration,
                    'call_type' => $call->call_type_label,
                    'status' => $call->status_label,
                    'start_time' => $call->start_time->format('Y-m-d H:i:s'),
                ];
            });
        
        $data['call_statistics'] = [
            'today' => $callStats,
            'this_week' => $callStatsWeek,
            'calls_per_hour' => $callsPerHour,
            'recent_calls' => $recentCalls,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get today's stats only
     */
    public function stats(Request $request)
    {
        $userId = $request->user()->id;
        
        $data = [
            'today_stats' => $this->dashboardService->getTodayStats($userId),
            'daily_limit' => $this->dashboardService->getDailyLimit($userId),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get urgent tasks only
     */
    public function urgentTasks(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->dashboardService->getUrgentTasks($userId);
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get today's schedule
     */
    public function schedule(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->dashboardService->getTodaySchedule($userId);
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get performance metrics
     */
    public function performance(Request $request)
    {
        $userId = $request->user()->id;
        
        $data = [
            'performance_metrics' => $this->dashboardService->getPerformanceMetrics($userId),
            'call_quality_metrics' => $this->dashboardService->getCallQualityMetrics($userId),
            'sla_compliance' => $this->dashboardService->getSlaCompliance($userId),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}

