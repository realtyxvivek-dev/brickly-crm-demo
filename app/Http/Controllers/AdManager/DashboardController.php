<?php

namespace App\Http\Controllers\AdManager;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\AttendanceSummaryService;
use App\Services\LeadQualityReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        AttendanceSummaryService $attendanceSummaryService,
        LeadQualityReportService $leadQualityReportService
    ): View
    {
        $user = $request->user();
        abort_unless($user?->isAdManager(), 403);

        $allowedSources = $user->allowedLeadSources();
        $sourceOptions = collect(Lead::sourceOptions())
            ->only($allowedSources)
            ->all();
        $defaultSource = in_array('meta', $allowedSources, true)
            ? 'meta'
            : ($allowedSources[0] ?? null);
        $requestedSource = (string) $request->input('source', '');
        $selectedSource = $request->filled('source')
            ? ($requestedSource === 'all' ? 'all' : Lead::normalizeSource($requestedSource))
            : $defaultSource;

        if ($selectedSource && $selectedSource !== 'all' && !in_array($selectedSource, $allowedSources, true)) {
            $selectedSource = $defaultSource;
            $request->merge(['source' => $selectedSource]);
        }

        if (!$request->filled('source') && $selectedSource) {
            $request->merge(['source' => $selectedSource]);
        }

        $attendanceToday = $user->hasAttendanceRolloutEnabled()
            ? $attendanceSummaryService->today($user)
            : null;
        $leadQualityReport = $leadQualityReportService->buildFromRequest($request);

        return view('ad-manager.dashboard', [
            'allowedSources' => $allowedSources,
            'defaultSource' => $defaultSource,
            'sourceOptions' => $sourceOptions,
            'sourceLabel' => $selectedSource ? ($sourceOptions[$selectedSource] ?? Lead::displaySourceLabel($selectedSource)) : 'No source access assigned',
            'attendanceToday' => $attendanceToday,
            'leadQualityReport' => $leadQualityReport,
            'quickActions' => [
                ['label' => 'Meta Ops', 'route' => route('ad-manager.meta.index'), 'icon' => 'fas fa-share-alt'],
                ['label' => 'Forms', 'route' => route('ad-manager.meta.facebook-lead-ads.forms'), 'icon' => 'fas fa-list-check'],
                ['label' => 'Diagnostics', 'route' => route('ad-manager.meta.facebook-lead-ads.diagnostics'), 'icon' => 'fas fa-stethoscope'],
                ['label' => 'Missing Checker', 'route' => route('ad-manager.meta.facebook-lead-ads.missing-checker'), 'icon' => 'fas fa-magnifying-glass'],
                ['label' => 'Retry Failed', 'route' => route('ad-manager.meta.facebook-lead-ads.diagnostics'), 'icon' => 'fas fa-rotate-right'],
                ['label' => 'Automation', 'route' => route('ad-manager.automation.index'), 'icon' => 'fas fa-bolt'],
            ],
        ]);
    }

    private function lightweightReport(?string $source, $from, $to, string $bucket): array
    {
        if (!$source) {
            return $this->emptyDashboardData();
        }

        $base = Lead::query()
            ->where('source', $source)
            ->whereBetween('created_at', [$from, $to]);

        $total = (clone $base)->count();
        $statusCounts = (clone $base)
            ->selectRaw('LOWER(COALESCE(status, "")) as status_key, COUNT(*) as total')
            ->groupBy('status_key')
            ->pluck('total', 'status_key');

        $interested = $this->sumStatuses($statusCounts, ['interested', 'verified_prospect', 'site_visit', 'site_visit_done', 'meeting', 'closed', 'closed_won']);
        $notInterested = $this->sumStatuses($statusCounts, ['not_interested', 'not interested']);
        $junk = $this->sumStatuses($statusCounts, ['junk', 'invalid', 'wrong_number', 'duplicate']);
        $cnp = $this->sumStatuses($statusCounts, ['cnp', 'not_reachable', 'not reachable']);
        $callLater = $this->sumStatuses($statusCounts, ['follow_up', 'followup', 'call_later', 'call later']);
        $pending = max(0, $total - $interested - $notInterested - $junk - $cnp - $callLater);
        $goodLeads = $interested;
        $poorLeads = $notInterested + $junk + $cnp;

        $summary = [
            'total' => $total,
            'interested' => $interested,
            'not_interested' => $notInterested,
            'junk' => $junk,
            'cnp' => $cnp,
            'call_later' => $callLater,
            'pending' => $pending,
            'good_leads' => $goodLeads,
            'poor_leads' => $poorLeads,
            'quality_score' => $total > 0 ? round(($goodLeads / $total) * 100, 1) : 0,
        ];
        $summary['poor_quality_rate'] = $total > 0 ? round(($poorLeads / $total) * 100, 1) : 0;
        $summary['actioned_rate'] = $total > 0 ? round((($total - $pending) / $total) * 100, 1) : 0;

        $evidenceQuery = clone $base;
        $evidenceRows = $evidenceQuery
            ->latest('created_at')
            ->limit(10)
            ->get(['id', 'name', 'phone', 'status', 'created_at', 'last_remark'])
            ->map(fn (Lead $lead) => [
                'lead' => $lead,
                'owner' => '-',
                'status_label' => $this->statusLabel($lead->status),
                'outcome_label' => $this->statusLabel($lead->status),
                'remarks' => $lead->last_remark,
            ]);

        return [
            'summary' => $summary,
            'outcomes' => collect([
                'Interested / Qualified' => $interested,
                'Not Interested' => $notInterested,
                'Junk / Invalid' => $junk,
                'CNP / Not Reachable' => $cnp,
                'Call Later / Follow-up' => $callLater,
                'Pending / No Outcome' => $pending,
            ])->filter(fn ($count) => $count > 0),
            'statusBreakdown' => $statusCounts
                ->mapWithKeys(fn ($count, $status) => [$this->statusLabel((string) $status) => $count])
                ->sortDesc(),
            'evidenceRows' => $evidenceRows,
            'healthSnapshot' => $this->lightweightHealthSnapshot($source),
            'metaHealthAlerts' => $this->lightweightHealthAlerts(),
        ];
    }

    private function sumStatuses($statusCounts, array $keys): int
    {
        return collect($keys)
            ->sum(fn ($key) => (int) ($statusCounts[strtolower($key)] ?? 0));
    }

    private function statusLabel(?string $status): string
    {
        $status = trim((string) $status);

        return $status === '' ? 'Not Recorded' : ucwords(str_replace('_', ' ', $status));
    }

    private function emptyDashboardData(): array
    {
        return [
            'summary' => [
                'total' => 0,
                'interested' => 0,
                'not_interested' => 0,
                'junk' => 0,
                'cnp' => 0,
                'call_later' => 0,
                'pending' => 0,
                'good_leads' => 0,
                'poor_leads' => 0,
                'quality_score' => 0,
                'poor_quality_rate' => 0,
                'actioned_rate' => 0,
            ],
            'outcomes' => collect(),
            'statusBreakdown' => collect(),
            'evidenceRows' => collect(),
            'healthSnapshot' => $this->lightweightHealthSnapshot(null),
            'metaHealthAlerts' => $this->lightweightHealthAlerts(),
        ];
    }

    private function lightweightHealthSnapshot(?string $source): array
    {
        return [
            'connected_pages' => 0,
            'enabled_forms' => $source === 'meta' ? null : 0,
            'mapped_enabled_forms' => 0,
            'failed_count' => 0,
        ];
    }

    private function lightweightHealthAlerts(): array
    {
        return [
            ['label' => 'Token Issues', 'display' => 'Open diagnostics', 'tone' => 'warn'],
            ['label' => 'Mapping Pending', 'display' => 'Open forms', 'tone' => 'warn'],
            ['label' => 'Failed Webhooks', 'display' => 'Open diagnostics', 'tone' => 'warn'],
        ];
    }
}
