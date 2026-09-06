<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class LeadProposal extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'created_by',
        'token',
        'status',
        'lead_capture_mode',
        'message',
        'expires_at',
        'revoked_at',
        'first_viewed_at',
        'last_viewed_at',
        'view_count',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'first_viewed_at' => 'datetime',
        'last_viewed_at' => 'datetime',
        'view_count' => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'lead_proposal_projects')
            ->withPivot('display_order')
            ->withTimestamps()
            ->orderBy('lead_proposal_projects.display_order');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LeadProposalEvent::class);
    }

    public function isUsable(): bool
    {
        if ($this->status === 'revoked' || $this->revoked_at) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->status === 'active';
    }

    public function markExpiredIfNeeded(): void
    {
        if ($this->status === 'active' && $this->expires_at && $this->expires_at->isPast()) {
            $this->forceFill(['status' => 'expired'])->save();
        }
    }

    public function analyticsSummary(): array
    {
        $events = $this->relationLoaded('events') ? $this->events : $this->events()->get();
        $projects = $this->relationLoaded('projects') ? $this->projects : $this->projects()->get();

        $durationEvents = $events->where('event_name', 'duration');
        $projectDurations = $durationEvents
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->map(fn (Collection $rows) => (int) $rows->sum('duration_ms'));

        $sectionDurations = $durationEvents
            ->filter(fn (LeadProposalEvent $event) => filled($event->section))
            ->groupBy('section')
            ->map(fn (Collection $rows) => (int) $rows->sum('duration_ms'))
            ->sortDesc();

        $projectViews = $events
            ->whereIn('event_name', ['project_view', 'section_view', 'floor_plan_view'])
            ->whereNotNull('project_id')
            ->groupBy('project_id')
            ->map(fn (Collection $rows) => $rows->count());

        $projectRows = $projects->map(function (Project $project) use ($projectDurations, $projectViews) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'duration_ms' => (int) ($projectDurations->get($project->id) ?? 0),
                'views' => (int) ($projectViews->get($project->id) ?? 0),
            ];
        })->sortByDesc('duration_ms')->values();

        $topProject = $projectRows->first();
        $topSection = $sectionDurations->keys()->first();
        $ctaClicks = $events->whereIn('event_name', ['cta_click', 'whatsapp_click', 'call_click', 'site_visit_click', 'cost_sheet_click'])->count();
        $downloads = $events->whereIn('event_name', ['details_pdf_download', 'brochure_download'])->count();
        $unitClicks = $events
            ->whereIn('event_name', ['floor_plan_view', 'unit_type_view', 'gallery_view'])
            ->map(fn (LeadProposalEvent $event) => $this->unitInterestLabel($event->meta ?? []))
            ->filter()
            ->countBy()
            ->sortDesc();
        $unitDurations = $durationEvents
            ->map(function (LeadProposalEvent $event) {
                $label = $this->unitInterestLabel($event->meta['floor_plan'] ?? []);
                return $label ? ['label' => $label, 'duration_ms' => (int) $event->duration_ms] : null;
            })
            ->filter()
            ->groupBy('label')
            ->map(fn (Collection $rows) => (int) $rows->sum('duration_ms'));
        $unitRows = $unitClicks
            ->keys()
            ->merge($unitDurations->keys())
            ->unique()
            ->map(fn ($label) => [
                'label' => $label,
                'views' => (int) ($unitClicks->get($label) ?? 0),
                'duration_ms' => (int) ($unitDurations->get($label) ?? 0),
            ])
            ->sortByDesc(fn (array $row) => [$row['duration_ms'], $row['views']])
            ->values();
        $topUnit = $unitRows->first()['label'] ?? null;
        $ctaBreakdown = $events
            ->whereIn('event_name', ['cta_click', 'whatsapp_click', 'call_click', 'site_visit_click', 'cost_sheet_click'])
            ->map(fn (LeadProposalEvent $event) => $event->meta['cta'] ?? str_replace('_click', '', $event->event_name))
            ->filter()
            ->countBy()
            ->sortDesc();
        $visitor = $this->visitorSummary($events);
        $visitRows = $this->visitTimeline($events, $projects);
        $suggestion = $this->buildSuggestion($topProject, $topSection, $topUnit);
        if (($visitor['context'] ?? '') !== 'Waiting for visitor data') {
            $suggestion = "Customer opened proposal from {$visitor['context']}. " . $suggestion;
        }

        return [
            'total_duration_ms' => (int) $durationEvents->sum('duration_ms'),
            'project_rows' => $projectRows,
            'top_project' => $topProject,
            'top_section' => $topSection,
            'top_unit' => $topUnit,
            'floor_plan_rows' => $unitRows,
            'cta_clicks' => $ctaClicks,
            'cta_breakdown' => $ctaBreakdown,
            'downloads' => $downloads,
            'visitor_device' => $visitor['device'],
            'visitor_browser' => $visitor['browser'],
            'visitor_os' => $visitor['os'],
            'visitor_city' => $visitor['city'],
            'visitor_region' => $visitor['region'],
            'visitor_country' => $visitor['country'],
            'visitor_screen' => $visitor['screen'],
            'visitor_location_status' => $visitor['location_status'],
            'visitor_exact_location' => $visitor['exact_location'],
            'last_visit_context' => $visitor['context'],
            'total_visits' => $visitRows->count(),
            'latest_visit' => $visitRows->first(),
            'visit_rows' => $visitRows,
            'suggestion' => $suggestion,
        ];
    }

    private function visitTimeline(Collection $events, Collection $projects): Collection
    {
        $groups = $events
            ->filter(fn (LeadProposalEvent $event) => filled($event->occurred_at))
            ->groupBy(fn (LeadProposalEvent $event) => data_get($event->meta, 'visit_id') ?: 'legacy_visit');

        $rows = $groups->map(function (Collection $visitEvents, string $visitId) use ($projects) {
            $durationEvents = $visitEvents->where('event_name', 'duration');
            $projectDurations = $durationEvents
                ->whereNotNull('project_id')
                ->groupBy('project_id')
                ->map(fn (Collection $rows) => (int) $rows->sum('duration_ms'));
            $projectViews = $visitEvents
                ->whereIn('event_name', ['project_view', 'section_view', 'floor_plan_view'])
                ->whereNotNull('project_id')
                ->groupBy('project_id')
                ->map(fn (Collection $rows) => $rows->count());
            $projectRows = $projects->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'duration_ms' => (int) ($projectDurations->get($project->id) ?? 0),
                'views' => (int) ($projectViews->get($project->id) ?? 0),
            ])->sortByDesc(fn (array $row) => ($row['duration_ms'] * 1000) + $row['views'])->values();

            $unitClicks = $visitEvents
                ->whereIn('event_name', ['floor_plan_view', 'unit_type_view', 'gallery_view'])
                ->map(fn (LeadProposalEvent $event) => $this->unitInterestLabel($event->meta ?? []))
                ->filter()
                ->countBy();
            $unitDurations = $durationEvents
                ->map(function (LeadProposalEvent $event) {
                    $label = $this->unitInterestLabel($event->meta['floor_plan'] ?? []);
                    return $label ? ['label' => $label, 'duration_ms' => (int) $event->duration_ms] : null;
                })
                ->filter()
                ->groupBy('label')
                ->map(fn (Collection $rows) => (int) $rows->sum('duration_ms'));
            $unitRows = $unitClicks
                ->keys()
                ->merge($unitDurations->keys())
                ->unique()
                ->map(fn ($label) => [
                    'label' => $label,
                    'views' => (int) ($unitClicks->get($label) ?? 0),
                    'duration_ms' => (int) ($unitDurations->get($label) ?? 0),
                ])
                ->sortByDesc(fn (array $row) => ($row['duration_ms'] * 1000) + $row['views'])
                ->values();

            return [
                'visit_id' => $visitId,
                'is_legacy' => $visitId === 'legacy_visit',
                'started_at' => $visitEvents->min('occurred_at'),
                'ended_at' => $visitEvents->max('occurred_at'),
                'duration_ms' => (int) $durationEvents->sum('duration_ms'),
                'top_project' => $projectRows->first(),
                'top_unit' => $unitRows->first()['label'] ?? null,
                'device_context' => $this->visitorSummary($visitEvents)['context'] ?? 'Waiting',
                'event_count' => $visitEvents->count(),
            ];
        })->sortBy('started_at')->values();

        $numbered = $rows->map(function (array $row, int $index) {
            $row['visit_number'] = $index + 1;
            $row['label'] = $row['is_legacy'] ? 'Legacy visit' : 'Visit ' . ($index + 1);
            return $row;
        });

        return $numbered->sortByDesc('started_at')->values();
    }

    private function visitorSummary(Collection $events): array
    {
        $openEvent = $events
            ->where('event_name', 'proposal_opened')
            ->sortByDesc('occurred_at')
            ->first();
        $locationEvent = $events
            ->whereIn('event_name', ['location_shared', 'location_denied'])
            ->sortByDesc('occurred_at')
            ->first();

        $meta = $openEvent?->meta ?? [];
        $device = $meta['device'] ?? [];
        $location = $meta['location'] ?? [];
        $hints = $meta['client_hints'] ?? [];
        $exact = $locationEvent?->meta['exact_location'] ?? null;

        $deviceType = $this->formatDeviceType((string) ($device['type'] ?? ''));
        $browser = (string) ($device['browser'] ?? 'Unknown');
        $os = (string) ($device['os'] ?? 'Unknown');
        $screen = $this->formatScreen($hints);
        $city = (string) ($location['city'] ?? '');
        $region = (string) ($location['region'] ?? '');
        $country = (string) ($location['country'] ?? '');
        $place = collect([$city, $region ?: $country])->filter()->implode(', ');
        $deviceLabel = trim(collect([$os !== 'Unknown' ? $os : null, $deviceType])->filter()->implode(' '));

        return [
            'device' => $deviceLabel ?: 'Unknown',
            'browser' => $browser,
            'os' => $os,
            'city' => $city ?: null,
            'region' => $region ?: null,
            'country' => $country ?: null,
            'screen' => $screen,
            'location_status' => (string) ($location['status'] ?? 'unavailable'),
            'exact_location' => $exact,
            'context' => trim(collect([$place ?: null, $deviceLabel ?: null, $browser !== 'Unknown' ? $browser : null])->filter()->implode(' · ')) ?: 'Waiting for visitor data',
        ];
    }

    private function formatDeviceType(string $type): string
    {
        return match ($type) {
            'mobile' => 'Mobile',
            'tablet' => 'Tablet',
            'desktop' => 'Desktop',
            default => 'Device',
        };
    }

    private function formatScreen(array $hints): ?string
    {
        $width = (int) ($hints['screen_width'] ?? 0);
        $height = (int) ($hints['screen_height'] ?? 0);

        return $width > 0 && $height > 0 ? "{$width} x {$height}" : null;
    }

    private function unitInterestLabel(array $meta): ?string
    {
        $label = trim((string) ($meta['label'] ?? ''));
        $unitType = trim((string) ($meta['unit_type'] ?? ''));
        $title = trim((string) ($meta['title'] ?? ''));

        if ($label !== '' && $unitType !== '' && !str_contains(strtolower($label), strtolower($unitType))) {
            return "{$unitType} - {$label}";
        }

        if ($label !== '') {
            return $label;
        }

        if ($unitType !== '') {
            return $unitType;
        }

        if ($title !== '' && preg_match('/\\b(\\d+\\s*BHK[^,|]*)/i', $title, $matches)) {
            return trim(preg_replace('/\\s+/', ' ', $matches[1]));
        }

        return null;
    }

    private function buildSuggestion(?array $topProject, ?string $topSection, ?string $topUnit): string
    {
        if (!$topProject || (int) ($topProject['duration_ms'] ?? 0) < 1) {
            return 'Proposal sent. Follow up after the customer opens the link.';
        }

        $parts = ["Customer spent most time on {$topProject['name']}"];
        if ($topUnit) {
            $parts[] = "especially {$topUnit}";
        } elseif ($topSection) {
            $parts[] = "in " . str_replace('_', ' ', $topSection);
        }

        return implode(' ', $parts) . '. Follow up with availability, pricing, and next visit options.';
    }
}
