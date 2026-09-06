<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use App\Services\AdvisorPerformanceReportService;
use App\Services\LeadQualityReportService;
use App\Services\SiteVisitRevenueService;
use Illuminate\Http\Request;

class DataIntelligenceController extends Controller
{
    public function legacyIndex()
    {
        return redirect()->route('data-intelligence.index');
    }

    public function legacyAdvisorPerformance(Request $request)
    {
        return redirect()->route('data-intelligence.advisor-performance', $request->query());
    }

    public function legacyExportAdvisorPerformance(Request $request)
    {
        return redirect()->route('data-intelligence.advisor-performance.export', $request->query());
    }

    public function legacyLeadQuality(Request $request)
    {
        $defaultSource = $request->user()?->isAdManager()
            ? ($request->user()->allowedLeadSources()[0] ?? null)
            : '99acres';

        return redirect()->route('data-intelligence.lead-quality', array_merge($request->query(), [
            'source' => $request->query('source', $defaultSource),
        ]));
    }

    public function index(Request $request)
    {
        $leadQualityDefaultSource = $request->user()?->isAdManager()
            ? ($request->user()->allowedLeadSources()[0] ?? null)
            : '99acres';

        return view('admin.data-intelligence.index', compact('leadQualityDefaultSource'));
    }

    public function advisorPerformance(Request $request, AdvisorPerformanceReportService $reportService)
    {
        abort_if($request->user()?->isAdManager(), 403);

        $advisorId = $request->filled('advisor_id') ? (int) $request->input('advisor_id') : null;
        $period = $reportService->resolvePeriod($request->query());

        $report = $reportService->build($period, $advisorId);

        return view('admin.data-intelligence.advisor-performance', compact('report', 'period', 'advisorId'));
    }

    public function exportAdvisorPerformance(Request $request, AdvisorPerformanceReportService $reportService)
    {
        abort_if($request->user()?->isAdManager(), 403);

        $advisorId = $request->filled('advisor_id') ? (int) $request->input('advisor_id') : null;
        $period = $reportService->resolvePeriod($request->query());

        return $reportService->export($reportService->build($period, $advisorId));
    }

    public function leadQuality(Request $request, LeadQualityReportService $reportService)
    {
        $report = $reportService->buildFromRequest($request);

        return view('admin.data-intelligence.lead-quality', $report);
    }

    public function updateRevenue(Request $request, SiteVisit $siteVisit, SiteVisitRevenueService $revenueService)
    {
        if (!$request->user()->isAdmin() && !$request->user()->isFinanceManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'revenue_value' => ['required', 'numeric', 'min:0'],
            'revenue_note' => ['nullable', 'string', 'max:1000'],
            'period_type' => ['nullable', 'in:month,quarter,year,till_date,custom'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'quarter' => ['nullable', 'integer', 'min:1', 'max:4'],
            'till_basis' => ['nullable', 'in:system_start,financial_year,calendar_year'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'advisor_id' => ['nullable', 'integer'],
        ]);

        $revenueService->updateRevenue(
            $siteVisit,
            $request->user(),
            (float) $validated['revenue_value'],
            $validated['revenue_note'] ?? null
        );

        return redirect()
            ->route('data-intelligence.advisor-performance', array_filter([
                'period_type' => $validated['period_type'] ?? 'month',
                'year' => $validated['year'] ?? now()->year,
                'month' => $validated['month'] ?? now()->month,
                'quarter' => $validated['quarter'] ?? null,
                'till_basis' => $validated['till_basis'] ?? null,
                'start_date' => $validated['start_date'] ?? null,
                'end_date' => $validated['end_date'] ?? null,
                'advisor_id' => $validated['advisor_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'Revenue value updated successfully.');
    }
}
