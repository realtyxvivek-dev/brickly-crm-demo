<?php

namespace App\Services;

use App\Models\FbLead;
use App\Models\FbForm;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LeadQualityReportService
{
    private const ALL_SOURCES = 'all';

    private const META_SOURCES = ['meta', 'meta_awareness'];

    private const META_VIEWS = [
        'all' => 'All Meta Leads',
        'form' => 'Form Wise',
        'campaign' => 'Campaign Wise',
    ];

    private const PERIODS = [
        'month' => 'Selected Month',
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This Week',
        'last_week' => 'Last Week',
        'custom' => 'Custom Range',
    ];

    public const BUCKETS = [
        'interested' => 'Interested / Qualified',
        'cnp' => 'CNP / Not Reachable',
        'call_later' => 'Call Later / Follow-up',
        'junk' => 'Junk / Invalid',
        'not_interested' => 'Not Interested',
        'pending' => 'Pending / No Outcome',
    ];

    public function buildFromRequest(Request $request): array
    {
        $filters = $this->resolveFilters($request);
        $sourceOptions = $this->sourceOptionsForUser($request->user());

        if ($this->isAdManager($request->user()) && empty($sourceOptions)) {
            return $this->emptyReport($filters, $sourceOptions);
        }

        $leads = Lead::query()
            ->with([
                'currentAssignment.assignedTo:id,name',
            ])
            ->withCount([
                'siteVisits',
                'siteVisits as completed_site_visits_count' => fn ($query) => $query->whereIn('status', ['completed', 'visit_done', 'revisited_completed']),
                'siteVisits as closed_site_visits_count' => function ($query) {
                    $query->where(function ($closed) {
                        $closed->whereIn('closer_status', ['approved', 'verified'])
                            ->orWhereNotNull('closer_verified_at');
                        if (Schema::hasColumn('site_visits', 'closing_verification_status')) {
                            $closed->orWhereIn('closing_verification_status', ['verified', 'approved']);
                        }
                    });
                },
                'meetings',
                'meetings as completed_meetings_count' => fn ($query) => $query
                    ->where(fn ($completed) => $completed->where('status', 'completed')->orWhereNotNull('completed_at')),
            ])
            ->when(
                $filters['source'] === self::ALL_SOURCES,
                fn ($query) => !empty($filters['allowed_sources'])
                    ? $query->whereIn('source', $filters['allowed_sources'])
                    : $query,
                fn ($query) => $query->where('source', $filters['source'])
            )
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->latest('created_at')
            ->get();

        $latestOutcomeTasks = $this->latestOutcomeTasks($leads->pluck('id'));

        $metaLeads = $filters['is_meta_source']
            ? $this->metaLeadsForCrmLeads($leads->pluck('id'))
            : collect();

        $baseClassified = $leads
            ->map(fn (Lead $lead) => $this->classifyLead(
                $lead,
                $metaLeads->get($lead->id),
                $latestOutcomeTasks->get($lead->id)
            ))
            ->when($filters['user_id'], fn (Collection $rows) => $rows->where('owner_id', $filters['user_id']))
            ->when($filters['bucket'], fn (Collection $rows) => $rows->where('bucket', $filters['bucket']))
            ->values();

        $classified = $baseClassified
            ->when(!empty($filters['fb_form_ids']), fn (Collection $rows) => $rows->whereIn('meta_form_local_id', $filters['fb_form_ids']))
            ->when(!empty($filters['campaign_ids']), fn (Collection $rows) => $rows->whereIn('meta_campaign_id', $filters['campaign_ids']))
            ->values();

        $summary = $this->summary($classified);
        $sourceLabel = $filters['source'] === self::ALL_SOURCES
            ? 'All Sources'
            : ($sourceOptions[$filters['source']] ?? Lead::displaySourceLabel($filters['source']));

        return [
            'filters' => $filters,
            'sourceLabel' => $sourceLabel,
            'sourceOptions' => $sourceOptions,
            'periodOptions' => self::PERIODS,
            'bucketOptions' => self::BUCKETS,
            'metaViewOptions' => self::META_VIEWS,
            'isMetaSource' => $filters['is_meta_source'],
            'metaOptions' => $this->metaOptions($baseClassified, $filters),
            'metaBreakdown' => $this->metaBreakdown($baseClassified, $filters['meta_view']),
            'users' => $this->usersForSource($filters['source'], $filters['allowed_sources']),
            'summary' => $summary,
            'outcomes' => $classified->countBy('outcome_label')->sortDesc(),
            'statusBreakdown' => $classified->countBy('status_label')->sortDesc(),
            'dailyTrend' => $this->dailyTrend($classified, $filters['from'], $filters['to']),
            'ownerBreakdown' => $this->ownerBreakdown($classified),
            'sourceBreakdown' => $this->sourceBreakdown($classified),
            'evidenceRows' => $classified->take($filters['sample_limit'])->values(),
            'samples' => $this->samples($classified, $filters['sample_limit']),
            'recommendations' => $this->recommendations($summary, $sourceLabel),
            'generatedAt' => now(),
        ];
    }

    private function resolveFilters(Request $request): array
    {
        $allowedSourceOptions = $this->sourceOptionsForUser($request->user());
        $requestedSource = $request->input('source');
        $adManagerAllowedSourceOptions = $this->isAdManager($request->user())
            ? collect($allowedSourceOptions)->except(self::ALL_SOURCES)->all()
            : $allowedSourceOptions;
        $defaultSource = $this->isAdManager($request->user())
            ? (array_key_exists('meta', $adManagerAllowedSourceOptions) ? 'meta' : (array_key_first($adManagerAllowedSourceOptions) ?: ''))
            : '99acres';
        $source = $requestedSource === self::ALL_SOURCES
            ? self::ALL_SOURCES
            : Lead::normalizeSource($requestedSource ?: $defaultSource);

        if ($this->isAdManager($request->user())) {
            if (empty($allowedSourceOptions)) {
                $source = '';
            }

            if ($source === '') {
                $source = $defaultSource;
            }

            if (
                !empty($allowedSourceOptions)
                && $source !== self::ALL_SOURCES
                && ($source === '' || !array_key_exists($source, $allowedSourceOptions))
            ) {
                $source = $defaultSource;
            }
        }

        $source = $source ?: '99acres';
        if (!$this->isAdManager($request->user()) && $requestedSource === self::ALL_SOURCES) {
            $source = self::ALL_SOURCES;
        }

        $isMetaSource = in_array($source, self::META_SOURCES, true);
        $month = trim((string) $request->input('month', now()->format('Y-m')));
        $period = (string) $request->input('period', 'month');
        if (!array_key_exists($period, self::PERIODS)) {
            $period = 'month';
        }

        $fromInput = $request->input('from');
        $toInput = $request->input('to');

        if (filled($fromInput) || filled($toInput)) {
            $period = 'custom';
            $from = $this->dateFromRequest($fromInput, now()->startOfMonth());
            $to = $this->dateFromRequest($toInput, now())->endOfDay();
        } elseif ($period !== 'month') {
            [$from, $to] = $this->periodRange($period);
            $month = $from->format('Y-m');
        } else {
            try {
                $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
                $monthDate = now()->startOfMonth();
                $month = $monthDate->format('Y-m');
            }

            $from = $monthDate->copy()->startOfMonth();
            $to = $monthDate->copy()->endOfMonth()->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $bucket = (string) $request->input('bucket', '');
        if ($bucket === 'follow_up') {
            $bucket = 'call_later';
        }

        if (!array_key_exists($bucket, self::BUCKETS)) {
            $bucket = '';
        }

        $metaView = (string) $request->input('meta_view', 'all');
        if (!$isMetaSource || !array_key_exists($metaView, self::META_VIEWS)) {
            $metaView = 'all';
        }

        $fbFormIds = $isMetaSource
            ? $this->intFilterValues($request->input('fb_form_ids', $request->input('fb_form_id')))
            : [];
        $campaignIds = $isMetaSource
            ? $this->stringFilterValues($request->input('campaign_ids', $request->input('campaign_id')))
            : [];

        return [
            'source' => $source,
            'allowed_sources' => array_keys($allowedSourceOptions),
            'period' => $period,
            'month' => $month,
            'from' => $from,
            'to' => $to,
            'user_id' => $request->filled('user_id') ? (int) $request->input('user_id') : null,
            'bucket' => $bucket,
            'sample_limit' => max(5, min(100, (int) $request->input('sample_limit', 25))),
            'is_meta_source' => $isMetaSource,
            'meta_view' => $metaView,
            'fb_form_ids' => $fbFormIds,
            'fb_form_id' => $fbFormIds[0] ?? null,
            'campaign_ids' => $campaignIds,
            'campaign_id' => $campaignIds[0] ?? null,
        ];
    }

    private function intFilterValues(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn ($item) => filled($item))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function stringFilterValues(mixed $value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function sourceOptionsForUser(?User $user): array
    {
        $sourceOptions = Lead::sourceOptions();

        if (!$this->isAdManager($user)) {
            return [self::ALL_SOURCES => 'All'] + $sourceOptions;
        }

        $allowedSources = $this->allowedLeadSources($user);

        $options = collect($sourceOptions)
            ->only($allowedSources)
            ->all();

        return empty($options) ? [] : [self::ALL_SOURCES => 'All'] + $options;
    }

    private function emptyReport(array $filters, array $sourceOptions): array
    {
        $filters['source'] = '';
        $filters['is_meta_source'] = false;
        $filters['meta_view'] = 'all';
        $filters['fb_form_ids'] = [];
        $filters['fb_form_id'] = null;
        $filters['campaign_ids'] = [];
        $filters['campaign_id'] = null;

        $summary = $this->summary(collect());

        return [
            'filters' => $filters,
            'sourceLabel' => 'No source access assigned',
            'sourceOptions' => $sourceOptions,
            'periodOptions' => self::PERIODS,
            'bucketOptions' => self::BUCKETS,
            'metaViewOptions' => self::META_VIEWS,
            'isMetaSource' => false,
            'metaOptions' => ['forms' => collect(), 'campaigns' => collect()],
            'metaBreakdown' => collect(),
            'users' => collect(),
            'summary' => $summary,
            'outcomes' => collect(),
            'statusBreakdown' => collect(),
            'dailyTrend' => $this->dailyTrend(collect(), $filters['from'], $filters['to']),
            'ownerBreakdown' => collect(),
            'sourceBreakdown' => collect(),
            'evidenceRows' => collect(),
            'samples' => $this->samples(collect(), $filters['sample_limit']),
            'recommendations' => ['Ask an admin to assign lead sources before using this report.'],
            'generatedAt' => now(),
        ];
    }

    private function summary(Collection $classified): array
    {
        $bucketCounts = $classified->countBy('bucket');
        $total = $classified->count();

        $summary = [
            'total' => $total,
            'interested' => (int) ($bucketCounts['interested'] ?? 0),
            'not_interested' => (int) ($bucketCounts['not_interested'] ?? 0),
            'junk' => (int) ($bucketCounts['junk'] ?? 0),
            'cnp' => (int) ($bucketCounts['cnp'] ?? 0),
            'call_later' => (int) ($bucketCounts['call_later'] ?? 0),
            'pending' => (int) ($bucketCounts['pending'] ?? 0),
        ];

        $summary['follow_up'] = $summary['call_later'];
        $summary['good_leads'] = $summary['interested'];
        $summary['poor_leads'] = $summary['not_interested'] + $summary['junk'] + $summary['cnp'];
        $summary['quality_score'] = $this->percentage($summary['good_leads'], $total);
        $summary['poor_quality_rate'] = $this->percentage($summary['poor_leads'], $total);
        $summary['actioned'] = $total - $summary['pending'];
        $summary['actioned_rate'] = $this->percentage($summary['actioned'], $total);

        return $summary;
    }

    private function classifyLead(Lead $lead, ?FbLead $metaLead = null, ?object $latestTask = null): array
    {
        $outcome = strtolower((string) ($latestTask?->outcome ?? ''));
        $status = strtolower((string) $lead->status);
        $remarks = trim((string) ($latestTask?->outcome_remark ?? $latestTask?->notes ?? $lead->other_lead_reason ?? $lead->dead_reason ?? $lead->notes ?? ''));
        $assignment = $lead->currentAssignment;
        $hasInterestedSignal = in_array($outcome, ['interested', 'qualified', 'connected'], true)
            || in_array($status, ['verified_prospect', 'meeting_scheduled', 'meeting_completed', 'visit_scheduled', 'visit_done', 'revisited_scheduled', 'revisited_completed', 'closed'], true)
            || (int) $lead->site_visits_count > 0
            || (int) $lead->meetings_count > 0;

        $bucket = match (true) {
            $hasInterestedSignal => 'interested',
            in_array($outcome, ['not_interested', 'broker'], true) || $status === 'not_interested' => 'not_interested',
            in_array($outcome, ['junk', 'wrong_number', 'invalid'], true) || $status === 'junk' || $lead->is_dead => 'junk',
            $outcome === 'cnp' || (int) $lead->cnp_count > 0 => 'cnp',
            in_array($outcome, ['call_later', 'call_again', 'follow_up'], true) || filled($lead->next_followup_at) || $status === 'on_hold' => 'call_later',
            default => 'pending',
        };
        $pipelineStage = $bucket === 'interested' ? $this->interestedPipelineStage($lead, $status) : null;
        $displayStatus = $this->displayStatusForReport($lead, $status, $bucket, $pipelineStage);

        return [
            'lead' => $lead,
            'bucket' => $bucket,
            'bucket_label' => self::BUCKETS[$bucket],
            'pipeline_stage' => $pipelineStage,
            'source_key' => (string) $lead->source,
            'source_label' => Lead::displaySourceLabel($lead->source),
            'outcome' => $outcome ?: 'not_recorded',
            'outcome_label' => $this->label($outcome ?: 'not_recorded'),
            'status_label' => $this->label($displayStatus),
            'raw_status_label' => $this->label($lead->status),
            'owner_id' => $assignment?->assigned_to,
            'owner' => $assignment?->assignedTo?->name ?? 'Unassigned',
            'remarks' => $remarks,
            'latest_outcome_at' => filled($latestTask?->latest_at ?? null) ? Carbon::parse($latestTask->latest_at) : null,
            'site_visits_count' => (int) $lead->site_visits_count,
            'meetings_count' => (int) $lead->meetings_count,
            'meta_form_local_id' => $metaLead?->fb_form_id,
            'meta_form_external_id' => $metaLead?->form?->form_id,
            'meta_form_name' => $metaLead?->form?->form_name ?: ($metaLead?->form?->form_id ?: 'Unmapped Meta'),
            'meta_campaign_id' => (string) ($metaLead?->campaign_id ?? ''),
            'meta_campaign_name' => $metaLead?->campaign_name ?: ($metaLead?->campaign_id ?: 'Unmapped Campaign'),
            'meta_adset_name' => $metaLead?->adset_name,
            'meta_ad_name' => $metaLead?->ad_name,
            'meta_leadgen_id' => $metaLead?->leadgen_id,
        ];
    }

    private function metaLeadsForCrmLeads(Collection $leadIds): Collection
    {
        $ids = $leadIds->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return FbLead::query()
            ->with('form:id,form_id,form_name')
            ->whereIn('crm_lead_id', $ids)
            ->latest('id')
            ->get()
            ->unique('crm_lead_id')
            ->keyBy('crm_lead_id');
    }

    private function metaOptions(Collection $classified, ?array $filters = null): array
    {
        $forms = $classified
            ->filter(fn (array $row) => filled($row['meta_form_local_id']))
            ->map(fn (array $row) => [
                'id' => $row['meta_form_local_id'],
                'name' => $row['meta_form_name'] ?: 'Unnamed Form',
            ])
            ->unique('id')
            ->sortBy('name')
            ->values();

        $campaigns = $classified
            ->filter(fn (array $row) => filled($row['meta_campaign_id']))
            ->map(fn (array $row) => [
                'id' => $row['meta_campaign_id'],
                'name' => $row['meta_campaign_name'] ?: $row['meta_campaign_id'],
            ])
            ->unique('id')
            ->sortBy('name')
            ->values();

        if ($forms->isEmpty()) {
            $forms = FbForm::query()
                ->select('id', 'form_name', 'form_id')
                ->whereHas('fbLeads', fn ($query) => $this->applyMetaLeadOptionDateRange($query, $filters))
                ->orderBy('form_name')
                ->limit(300)
                ->get()
                ->map(fn (FbForm $form) => [
                    'id' => $form->id,
                    'name' => $form->form_name ?: ($form->form_id ?: 'Unnamed Form'),
                ])
                ->values();
        }

        if ($campaigns->isEmpty()) {
            $campaigns = FbLead::query()
                ->select('campaign_id', 'campaign_name')
                ->whereNotNull('campaign_id')
                ->where('campaign_id', '<>', '')
                ->whereHas('crmLead', fn ($query) => $this->applyLeadOptionDateRange($query, $filters))
                ->when(!empty($filters['fb_form_ids'] ?? []), fn ($query) => $query->whereIn('fb_form_id', $filters['fb_form_ids']))
                ->orderBy('campaign_name')
                ->limit(300)
                ->get()
                ->map(fn (FbLead $lead) => [
                    'id' => (string) $lead->campaign_id,
                    'name' => $lead->campaign_name ?: (string) $lead->campaign_id,
                ])
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        return [
            'forms' => $forms,
            'campaigns' => $campaigns,
        ];
    }

    private function applyMetaLeadOptionDateRange($query, ?array $filters)
    {
        if (empty($filters['from']) || empty($filters['to'])) {
            return $query;
        }

        return $query->whereHas('crmLead', fn ($leadQuery) => $this->applyLeadOptionDateRange($leadQuery, $filters));
    }

    private function applyLeadOptionDateRange($query, ?array $filters)
    {
        if (empty($filters['from']) || empty($filters['to'])) {
            return $query;
        }

        return $query->whereBetween('created_at', [$filters['from'], $filters['to']]);
    }

    private function metaBreakdown(Collection $classified, string $metaView): Collection
    {
        if (!in_array($metaView, ['form', 'campaign'], true)) {
            return collect();
        }

        $isFormView = $metaView === 'form';
        $keyField = $isFormView ? 'meta_form_local_id' : 'meta_campaign_id';
        $labelField = $isFormView ? 'meta_form_name' : 'meta_campaign_name';
        $fallbackLabel = $isFormView ? 'Unmapped Meta' : 'Unmapped Campaign';
        $queryKey = $isFormView ? 'fb_form_id' : 'campaign_id';

        return $classified
            ->groupBy(fn (array $row) => filled($row[$keyField]) ? (string) $row[$keyField] : 'unmapped')
            ->map(function (Collection $rows, string $key) use ($labelField, $fallbackLabel, $queryKey) {
                $summary = $this->summary($rows);
                $first = $rows->first();

                return [
                    'key' => $key,
                    'label' => $first[$labelField] ?: $fallbackLabel,
                    'query_key' => $queryKey,
                    'query_value' => $key === 'unmapped' ? null : $key,
                    'total' => $summary['total'],
                    'interested' => $summary['interested'],
                    'not_interested' => $summary['not_interested'],
                    'junk' => $summary['junk'],
                    'cnp' => $summary['cnp'],
                    'call_later' => $summary['call_later'],
                    'follow_up' => $summary['call_later'],
                    'pending' => $summary['pending'],
                    'quality_score' => $summary['quality_score'],
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function latestOutcomeTasks(Collection $leadIds): Collection
    {
        $leadIds = $leadIds->filter()->unique()->values();
        if ($leadIds->isEmpty()) {
            return collect();
        }

        $telecallerTasks = DB::table('telecaller_tasks')
            ->whereIn('lead_id', $leadIds)
            ->whereNotNull('outcome')
            ->where('outcome', '<>', '')
            ->whereNull('deleted_at')
            ->selectRaw('lead_id, outcome, NULL as outcome_remark, notes, NULL as outcome_recorded_at, completed_at, created_at');
        $managerTasks = DB::table('tasks')
            ->whereIn('lead_id', $leadIds)
            ->whereNotNull('outcome')
            ->where('outcome', '<>', '')
            ->whereNull('deleted_at')
            ->selectRaw('lead_id, outcome, outcome_remark, notes, outcome_recorded_at, completed_at, created_at');
        $allTasks = $telecallerTasks->unionAll($managerTasks);
        $ranked = DB::query()
            ->fromSub($allTasks, 'outcome_tasks')
            ->selectRaw('outcome_tasks.*, COALESCE(outcome_recorded_at, completed_at, created_at) as latest_at')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY lead_id ORDER BY COALESCE(outcome_recorded_at, completed_at, created_at) DESC) as outcome_rank');

        return DB::query()
            ->fromSub($ranked, 'ranked_outcome_tasks')
            ->where('outcome_rank', 1)
            ->get()
            ->keyBy('lead_id');
    }

    private function samples(Collection $classified, int $limit): array
    {
        return collect(array_keys(self::BUCKETS))
            ->mapWithKeys(fn (string $bucket) => [
                $bucket => $classified->where('bucket', $bucket)->take($limit)->values(),
            ])
            ->all();
    }

    private function dailyTrend(Collection $classified, Carbon $from, Carbon $to): Collection
    {
        $rows = $classified
            ->groupBy(fn (array $row) => $row['lead']->created_at->format('Y-m-d'))
            ->map(function (Collection $rows, string $date) {
                return [
                    'date' => Carbon::parse($date),
                    'total' => $rows->count(),
                    'interested' => $rows->where('bucket', 'interested')->count(),
                    'not_interested' => $rows->where('bucket', 'not_interested')->count(),
                    'junk' => $rows->where('bucket', 'junk')->count(),
                    'cnp' => $rows->where('bucket', 'cnp')->count(),
                    'call_later' => $rows->where('bucket', 'call_later')->count(),
                    'follow_up' => $rows->where('bucket', 'call_later')->count(),
                    'pending' => $rows->where('bucket', 'pending')->count(),
                ];
            })
            ->sortBy('date')
            ->values();

        if ($from->diffInDays($to) <= 1 && $rows->isEmpty()) {
            return collect([[
                'date' => $from,
                'total' => 0,
                'interested' => 0,
                'not_interested' => 0,
                'junk' => 0,
                'cnp' => 0,
                'call_later' => 0,
                'follow_up' => 0,
                'pending' => 0,
            ]]);
        }

        return $rows;
    }

    private function ownerBreakdown(Collection $classified): Collection
    {
        return $classified
            ->groupBy('owner')
            ->map(function (Collection $rows, string $owner) {
                $total = $rows->count();

                return [
                    'owner' => $owner,
                    'total' => $total,
                    'interested' => $rows->where('bucket', 'interested')->count(),
                    'not_interested' => $rows->where('bucket', 'not_interested')->count(),
                    'junk' => $rows->where('bucket', 'junk')->count(),
                    'cnp' => $rows->where('bucket', 'cnp')->count(),
                    'call_later' => $rows->where('bucket', 'call_later')->count(),
                    'quality_score' => $this->percentage(
                        $rows->where('bucket', 'interested')->count(),
                        $total
                    ),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function sourceBreakdown(Collection $classified): Collection
    {
        return $classified
            ->groupBy('source_key')
            ->map(function (Collection $rows) {
                $total = $rows->count();
                $interested = $rows->where('bucket', 'interested')->count();
                $first = $rows->first();
                $interestedRows = $rows->where('bucket', 'interested');

                return [
                    'source' => $first['source_label'] ?? 'Other',
                    'total' => $total,
                    'interested' => $interested,
                    'cnp' => $rows->where('bucket', 'cnp')->count(),
                    'call_later' => $rows->where('bucket', 'call_later')->count(),
                    'junk' => $rows->where('bucket', 'junk')->count(),
                    'not_interested' => $rows->where('bucket', 'not_interested')->count(),
                    'pending' => $rows->where('bucket', 'pending')->count(),
                    'interested_follow_up' => $interestedRows->where('pipeline_stage', 'follow_up')->count(),
                    'site_visit' => $interestedRows->where('pipeline_stage', 'site_visit')->count(),
                    'site_visit_followup' => $interestedRows->where('pipeline_stage', 'site_visit_followup')->count(),
                    'meeting' => $interestedRows->where('pipeline_stage', 'meeting')->count(),
                    'closed' => $interestedRows->where('pipeline_stage', 'closed')->count(),
                    'interested_rate' => $this->percentage($interested, $total),
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function interestedPipelineStage(Lead $lead, string $status): string
    {
        if ($status === 'closed' || $this->hasClosedSiteVisit($lead)) {
            return 'closed';
        }

        if ((int) $lead->meetings_count > 0 || in_array($status, ['meeting_scheduled', 'meeting_completed'], true)) {
            return 'meeting';
        }

        if (in_array($status, ['revisited_scheduled', 'revisited_completed'], true) || ($this->hasCompletedSiteVisit($lead) && filled($lead->next_followup_at))) {
            return 'site_visit_followup';
        }

        if ($lead->siteVisits->isNotEmpty() || in_array($status, ['visit_scheduled', 'visit_done'], true)) {
            return 'site_visit';
        }

        if (filled($lead->next_followup_at) || $status === 'on_hold') {
            return 'follow_up';
        }

        return 'interested';
    }

    private function displayStatusForReport(Lead $lead, string $status, string $bucket, ?string $pipelineStage): string
    {
        if (!in_array($status, ['new', 'fresh_transfer'], true)) {
            return $status;
        }

        if ($bucket === 'interested') {
            return match ($pipelineStage) {
                'closed' => 'closed',
                'meeting' => (int) $lead->completed_meetings_count > 0
                    ? 'meeting_completed'
                    : 'meeting_scheduled',
                'site_visit', 'site_visit_followup' => $this->hasCompletedSiteVisit($lead)
                    ? 'visit_done'
                    : 'visit_scheduled',
                default => filled($lead->next_followup_at) ? 'follow_up' : 'connected',
            };
        }

        return match ($bucket) {
            'cnp' => 'cnp',
            'call_later' => 'follow_up',
            default => $status,
        };
    }

    private function hasCompletedSiteVisit(Lead $lead): bool
    {
        return (int) $lead->completed_site_visits_count > 0;
    }

    private function hasClosedSiteVisit(Lead $lead): bool
    {
        return (int) $lead->closed_site_visits_count > 0;
    }

    private function usersForSource(string $source, array $allowedSources = []): Collection
    {
        $userIds = LeadAssignment::query()
            ->join('leads', 'leads.id', '=', 'lead_assignments.lead_id')
            ->where('lead_assignments.is_active', true)
            ->when(
                $source === self::ALL_SOURCES,
                fn ($query) => !empty($allowedSources) ? $query->whereIn('leads.source', $allowedSources) : $query,
                fn ($query) => $query->where('leads.source', $source)
            )
            ->whereNull('leads.deleted_at')
            ->distinct()
            ->pluck('lead_assignments.assigned_to');

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function recommendations(array $summary, string $sourceLabel): array
    {
        $reviewTarget = $sourceLabel === 'All Sources'
            ? 'the relevant lead source/vendor'
            : $sourceLabel;

        $items = [
            "Lead replacement/credit request: Not Interested, Junk and repeated CNP leads should be reviewed by {$reviewTarget} with call remarks as evidence.",
            'Duplicate and invalid-number filtering should be strengthened before pushing leads to CRM.',
            'Lead intent should be qualified at portal level: budget, location and project preference should be mandatory.',
            'Low quality buckets should be reconciled weekly so unusable leads are not billed as qualified enquiries.',
        ];

        if (($summary['quality_score'] ?? 0) < 40) {
            array_unshift($items, 'Current quality score is low; campaign/source optimization is required before increasing spend.');
        }

        return $items;
    }

    private function isAdManager(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isAdManager')) {
            return $user->isAdManager();
        }

        if (!$user->relationLoaded('role')) {
            $user->load('role');
        }

        return $user->role && $user->role->slug === 'ad_manager';
    }

    private function allowedLeadSources(?User $user): array
    {
        if (!$user) {
            return [];
        }

        if (method_exists($user, 'allowedLeadSources')) {
            return $user->allowedLeadSources();
        }

        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        $sources = $preferences['allowed_lead_sources'] ?? [];

        if (!is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->map(fn ($source) => Lead::normalizeSource((string) $source))
            ->filter(fn ($source) => is_string($source) && $source !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function dateFromRequest(mixed $value, Carbon $fallback): Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value)->startOfDay() : $fallback->copy();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    private function periodRange(string $period): array
    {
        return match ($period) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()->endOfDay()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()->endOfDay()],
            default => [now()->startOfMonth(), now()->endOfMonth()->endOfDay()],
        };
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function label(?string $value): string
    {
        $value = trim((string) $value);

        return $value === '' ? 'Not Recorded' : str($value)->replace('_', ' ')->title()->toString();
    }
}
