<?php

namespace App\Services;

use App\Events\LeadAssigned;
use App\Jobs\FetchFacebookLeadDetailsJob;
use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbPage;
use App\Models\FbWebhookEvent;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class MetaMissingLeadRecoveryService
{
    private const CALLBACK_LIMIT_MAX = 50;
    private const CALLBACK_LOCK_SECONDS = 120;

    public function __construct(
        private readonly MetaLeadCheckImportParser $parser,
        private readonly LeadDuplicateGuardService $leadDuplicateGuardService,
        private readonly SourceAutomationService $sourceAutomationService,
        private readonly LeadOwnerTaskService $leadOwnerTaskService,
    ) {
    }

    public function forms(): Collection
    {
        return FbForm::query()
            ->with('page')
            ->where('is_enabled', true)
            ->orderBy('form_name')
            ->get();
    }

    public function assignableUsers(): Collection
    {
        return User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $this->assignableRoleSlugs()))
            ->orderBy('name')
            ->get();
    }

    public function canUseAdvancedRecovery(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $permissions = $user->role?->permissions ?? [];

        return in_array('meta_missing_advanced_import', is_array($permissions) ? $permissions : [], true);
    }

    public function parseUploadedFile(UploadedFile $file): array
    {
        return $this->parser->parse($file);
    }

    public function filterRowsByDate(array $rows, array $dateFilter): array
    {
        return $this->parser->filterByDate($rows, $dateFilter['from'] ?? null, $dateFilter['to'] ?? null);
    }

    public function buildReport(array $rows, ?FbForm $fallbackForm = null): array
    {
        $normalizedRows = collect($rows)->map(function (array $row, int $index) {
            return [
                'row_number' => $row['row_number'] ?? ($index + 2),
                'leadgen_id' => trim((string) ($row['leadgen_id'] ?? '')),
                'name' => $row['name'] ?? $row['lead_name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'form_id' => $row['form_id'] ?? null,
                'form_name' => $row['form_name'] ?? null,
                'created_time' => $row['created_time'] ?? $row['submitted_at'] ?? null,
                'raw' => $row['raw'] ?? $row,
            ];
        })->values();

        $leadgenIds = $normalizedRows->pluck('leadgen_id')->filter()->unique()->values();

        $fbLeads = $leadgenIds->isNotEmpty()
            ? FbLead::with(['crmLead', 'form'])->whereIn('leadgen_id', $leadgenIds)->get()->keyBy('leadgen_id')
            : collect();

        $webhookEvents = $leadgenIds->isNotEmpty()
            ? FbWebhookEvent::whereIn('leadgen_id', $leadgenIds)->orderByDesc('id')->get()->groupBy('leadgen_id')
            : collect();

        $formsByExternalId = $this->forms()->keyBy('form_id');

        $reportRows = $normalizedRows->map(function (array $row) use ($fbLeads, $webhookEvents, $formsByExternalId, $fallbackForm) {
            if ($row['leadgen_id'] === '') {
                return $row + [
                    'status' => 'invalid',
                    'status_label' => 'Invalid CSV Row',
                    'status_class' => 'bg-slate-100 text-slate-700',
                    'reason' => 'No leadgen ID found in this row.',
                    'crm_lead_id' => null,
                    'resolved_form_id' => $fallbackForm?->id,
                    'resolved_form_name' => $fallbackForm?->form_name,
                    'importable' => false,
                ];
            }

            $fbLead = $fbLeads->get($row['leadgen_id']);
            if ($fbLead) {
                return $row + [
                    'status' => 'in_crm',
                    'status_label' => 'In CRM',
                    'status_class' => 'bg-emerald-50 text-emerald-700',
                    'reason' => 'fb_leads row found.',
                    'crm_lead_id' => $fbLead->crm_lead_id,
                    'resolved_form_id' => $fbLead->fb_form_id,
                    'resolved_form_name' => $fbLead->form?->form_name,
                    'importable' => false,
                ];
            }

            $event = optional($webhookEvents->get($row['leadgen_id']))->first();
            $resolvedForm = $this->resolveForm($row['form_id'], $fallbackForm, $formsByExternalId);
            $base = $row + [
                'crm_lead_id' => null,
                'resolved_form_id' => $resolvedForm?->id,
                'resolved_form_name' => $resolvedForm?->form_name,
                'importable' => (bool) ($resolvedForm && $resolvedForm->is_enabled),
            ];

            if ($event) {
                $isFailed = $event->status === 'failed';

                return $base + [
                    'status' => $isFailed ? 'webhook_failed' : 'webhook_received',
                    'status_label' => $isFailed ? 'Webhook Failed' : 'Webhook Received',
                    'status_class' => $isFailed ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700',
                    'reason' => $event->error ?: 'Webhook exists but fb_leads row was not found.',
                ];
            }

            return $base + [
                'status' => 'webhook_missing',
                'status_label' => 'Webhook Missing',
                'status_class' => 'bg-orange-50 text-orange-700',
                'reason' => 'No webhook or fb_leads record found in CRM.',
            ];
        })->values()->all();

        $stats = collect($reportRows)->countBy('status')->all();

        return [
            'rows' => $reportRows,
            'stats' => [
                'total' => count($reportRows),
                'in_crm' => $stats['in_crm'] ?? 0,
                'webhook_received' => $stats['webhook_received'] ?? 0,
                'webhook_failed' => $stats['webhook_failed'] ?? 0,
                'webhook_missing' => $stats['webhook_missing'] ?? 0,
                'invalid' => $stats['invalid'] ?? 0,
                'importable' => collect($reportRows)->where('importable', true)->count(),
            ],
            'fallback_form_id' => $fallbackForm?->id,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    public function callbackPageLeads(FbPage $page, ?Carbon $since = null, ?Carbon $until = null, int $limit = 50): array
    {
        $limit = min(max($limit, 1), self::CALLBACK_LIMIT_MAX);
        $since ??= now()->subDays(7);
        $until ??= now();

        $page->load(['forms' => fn ($query) => $query->with('mapping')->orderBy('form_name')]);

        if (empty($page->page_access_token)) {
            return [
                'success' => false,
                'message' => 'Page token missing. Update token first.',
                'summary' => $this->emptyCallbackSummary(),
                'forms' => [],
            ];
        }

        $lock = Cache::lock($this->pageCallbackLockKey($page), self::CALLBACK_LOCK_SECONDS);
        if (!$lock->get()) {
            return [
                'success' => false,
                'message' => 'Page callback already running. Thoda wait karke dobara try karo.',
                'summary' => $this->emptyCallbackSummary(),
                'forms' => [],
            ];
        }

        try {
            $settings = \App\Models\FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
            $remaining = $limit;
            $summary = $this->emptyCallbackSummary();
            $formResults = [];

            foreach ($page->forms as $form) {
                if ($remaining <= 0) {
                    break;
                }

                if (!$form->is_enabled) {
                    $summary['skipped']++;
                    $formResults[] = [
                        'form_id' => $form->form_id,
                        'form_name' => $form->form_name,
                        'status' => 'skipped',
                        'message' => 'Form disabled.',
                    ];
                    continue;
                }

                $formResult = $this->callbackSingleForm($form, $client, $since, $until, $remaining, 'meta_page_callback');
                $summary = $this->mergeCallbackSummary($summary, $formResult['summary']);
                $remaining -= (int) ($formResult['processed'] ?? 0);
                $formResults[] = $formResult['form'];
            }

            return [
                'success' => true,
                'message' => "Callback queued. Fetched {$summary['fetched']}, already present {$summary['already_present']}, queued {$summary['queued']}, failed {$summary['failed']}.",
                'summary' => $summary,
                'forms' => $formResults,
            ];
        } finally {
            $lock->release();
        }
    }

    public function callbackFormLeads(FbForm $form, ?Carbon $since = null, ?Carbon $until = null, int $limit = 50): array
    {
        $limit = min(max($limit, 1), self::CALLBACK_LIMIT_MAX);
        $since ??= now()->subDays(7);
        $until ??= now();

        $form->load(['page', 'mapping']);
        $summary = $this->emptyCallbackSummary();

        if (!$form->page || empty($form->page->page_access_token)) {
            return [
                'success' => false,
                'message' => 'Page token missing. Update token first.',
                'summary' => $summary,
                'forms' => [],
            ];
        }

        if (!$form->is_enabled) {
            $summary['skipped']++;

            return [
                'success' => false,
                'message' => 'Form disabled hai. Old leads callback se pehle form enable karo.',
                'summary' => $summary,
                'forms' => [[
                    'form_id' => $form->form_id,
                    'form_name' => $form->form_name,
                    'status' => 'skipped',
                    'message' => 'Form disabled.',
                ]],
            ];
        }

        $lock = Cache::lock($this->formCallbackLockKey($form), self::CALLBACK_LOCK_SECONDS);
        if (!$lock->get()) {
            return [
                'success' => false,
                'message' => 'Is form ka callback already running hai. Thoda wait karke dobara try karo.',
                'summary' => $summary,
                'forms' => [],
            ];
        }

        try {
            $settings = \App\Models\FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($form->page->page_access_token, $settings->graph_version ?? 'v18.0');
            $formResult = $this->callbackSingleForm($form, $client, $since, $until, $limit, 'meta_form_callback');

            return [
                'success' => ($formResult['form']['status'] ?? null) !== 'failed',
                'message' => $formResult['form']['message'] ?? 'Callback queued.',
                'summary' => $formResult['summary'],
                'forms' => [$formResult['form']],
            ];
        } finally {
            $lock->release();
        }
    }

    public function previewFormLeads(FbForm $form, ?Carbon $since = null, ?Carbon $until = null, int $limit = 50): array
    {
        $limit = min(max($limit, 1), self::CALLBACK_LIMIT_MAX);
        $since ??= now()->subDays(7);
        $until ??= now();

        $form->load(['page', 'mapping']);
        if (!$form->page || empty($form->page->page_access_token)) {
            return [
                'success' => false,
                'message' => 'Page token missing. Update token first.',
                'summary' => $this->emptyCallbackSummary(),
                'forms' => [],
            ];
        }

        if (!$form->is_enabled) {
            $summary = $this->emptyCallbackSummary();
            $summary['skipped']++;

            return [
                'success' => false,
                'message' => 'Form disabled hai. Old leads preview se pehle form enable karo.',
                'summary' => $summary,
                'forms' => [],
            ];
        }

        $settings = \App\Models\FbLeadAdsSettings::getSettings();
        $client = FacebookGraphService::fromToken($form->page->page_access_token, $settings->graph_version ?? 'v18.0');
        $fetch = $client->getFormLeads((string) $form->form_id, $since->toIso8601String(), $until->toIso8601String(), $limit);

        if (!($fetch['success'] ?? false)) {
            $summary = $this->emptyCallbackSummary();
            $summary['failed']++;

            return [
                'success' => false,
                'message' => $fetch['error'] ?? 'Meta API failed.',
                'summary' => $summary,
                'forms' => [],
            ];
        }

        $leads = collect($fetch['leads'] ?? []);
        $existingLeadgenIds = FbLead::whereIn('leadgen_id', $leads->pluck('id')->filter()->map(fn ($id) => (string) $id))
            ->pluck('leadgen_id')
            ->map(fn ($id) => (string) $id)
            ->flip();

        $previewRows = $leads->map(function (array $metaLead) use ($existingLeadgenIds) {
            $leadgenId = (string) ($metaLead['id'] ?? '');
            $flat = app(FacebookLeadMappingService::class)->fieldDataToFlat($metaLead['field_data'] ?? []);
            $alreadyPresent = $leadgenId !== '' && $existingLeadgenIds->has($leadgenId);

            return [
                'leadgen_id' => $leadgenId,
                'created_time' => !empty($metaLead['created_time'])
                    ? Carbon::parse($metaLead['created_time'])->format('d M Y, h:i A')
                    : null,
                'name' => $this->firstFlatValue($flat, ['full_name', 'name', 'first_name']) ?: '-',
                'phone' => $this->firstFlatValue($flat, ['phone_number', 'phone', 'mobile_number', 'mobile']) ?: '-',
                'email' => $this->firstFlatValue($flat, ['email', 'email_address']) ?: '-',
                'campaign_name' => $metaLead['campaign_name'] ?? null,
                'ad_name' => $metaLead['ad_name'] ?? null,
                'status' => $alreadyPresent ? 'already_present' : 'ready',
                'importable' => !$alreadyPresent && $leadgenId !== '',
            ];
        })->values();

        $summary = $this->emptyCallbackSummary();
        $summary['fetched'] = $previewRows->count();
        $summary['already_present'] = $previewRows->where('status', 'already_present')->count();
        $summary['queued'] = 0;
        $summary['skipped'] = $previewRows->where('importable', false)->count() - $summary['already_present'];
        $summary['importable'] = $previewRows->where('importable', true)->count();

        return [
            'success' => true,
            'message' => "Preview ready. {$summary['importable']} new leads import ho sakti hain, {$summary['already_present']} already CRM me hain.",
            'summary' => $summary,
            'leads' => $previewRows,
            'forms' => [[
                'form_id' => $form->form_id,
                'form_name' => $form->form_name,
                'status' => 'previewed',
                'message' => "Fetched {$summary['fetched']}, importable {$summary['importable']}, already present {$summary['already_present']}.",
            ]],
        ];
    }

    private function callbackSingleForm(FbForm $form, FacebookGraphService $client, Carbon $since, Carbon $until, int $limit, string $source): array
    {
        $summary = $this->emptyCallbackSummary();
        $processed = 0;

        $fetch = $client->getFormLeads((string) $form->form_id, $since->toIso8601String(), $until->toIso8601String(), $limit);
        if (!($fetch['success'] ?? false)) {
            $summary['failed']++;

            return [
                'summary' => $summary,
                'processed' => 0,
                'form' => [
                    'form_id' => $form->form_id,
                    'form_name' => $form->form_name,
                    'status' => 'failed',
                    'message' => $fetch['error'] ?? 'Meta API failed.',
                ],
            ];
        }

        $leads = collect($fetch['leads'] ?? []);
        $summary['fetched'] += $leads->count();
        $formAlreadyPresent = 0;
        $formFailed = 0;

        foreach ($leads as $metaLead) {
            if ($processed >= $limit) {
                break;
            }

            $leadgenId = (string) ($metaLead['id'] ?? '');
            if ($leadgenId === '') {
                $summary['skipped']++;
                continue;
            }

            if (FbLead::where('leadgen_id', $leadgenId)->exists()) {
                $summary['already_present']++;
                $formAlreadyPresent++;
                continue;
            }

            try {
                $latestEvent = FbWebhookEvent::where('leadgen_id', $leadgenId)->latest('id')->first();
                if ($latestEvent && $latestEvent->status === 'received') {
                    $summary['skipped']++;
                    continue;
                }

                $event = $latestEvent ?: new FbWebhookEvent();
                $rawPayload = array_merge($event->raw_payload ?? [], [
                    'source' => $source,
                    'page_id' => $form->page?->page_id,
                    'form_id' => $form->form_id,
                    'meta_lead' => $metaLead,
                    'callback_by' => auth()->id(),
                    'callback_at' => now()->toDateTimeString(),
                ]);

                $event->fill([
                    'leadgen_id' => $leadgenId,
                    'status' => 'received',
                    'error' => null,
                    'raw_payload' => $rawPayload,
                ])->save();

                FetchFacebookLeadDetailsJob::dispatch($leadgenId, (int) $form->id, false);
                $summary['queued']++;
            } catch (Throwable $e) {
                ($event ?? new FbWebhookEvent())->fill([
                    'leadgen_id' => $leadgenId,
                    'status' => 'failed',
                    'error' => $this->sanitizeErrorMessage($e->getMessage()),
                    'raw_payload' => [
                        'source' => $source,
                        'page_id' => $form->page?->page_id,
                        'form_id' => $form->form_id,
                        'meta_lead' => $metaLead,
                        'callback_by' => auth()->id(),
                        'callback_at' => now()->toDateTimeString(),
                    ],
                ])->save();
                $summary['failed']++;
                $formFailed++;

                Log::warning('Meta form callback lead import failed', [
                    'page_id' => $form->page?->id,
                    'form_id' => $form->id,
                    'leadgen_id' => $leadgenId,
                    'error' => $this->sanitizeErrorMessage($e->getMessage()),
                ]);
            }

            $processed++;
        }

        return [
            'summary' => $summary,
            'processed' => $processed,
            'form' => [
                'form_id' => $form->form_id,
                'form_name' => $form->form_name,
                'status' => 'queued',
                'message' => "Fetched {$leads->count()}, already present {$formAlreadyPresent}, queued {$summary['queued']}, failed {$formFailed}.",
            ],
        ];
    }

    private function firstFlatValue(array $flat, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $flat[$key] ?? null;
            if (is_array($value)) {
                $value = reset($value);
            }

            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function mergeCallbackSummary(array $summary, array $next): array
    {
        foreach ($this->emptyCallbackSummary() as $key => $value) {
            $summary[$key] = (int) ($summary[$key] ?? 0) + (int) ($next[$key] ?? 0);
        }

        return $summary;
    }

    public function importRows(Collection $rows, ?FbForm $fallbackForm, array $assignmentSettings, int $batchLimit): array
    {
        $batchLimit = min(max($batchLimit, 1), 50);
        $processedCount = 0;
        $assignIndex = 0;

        $eligibleCount = $rows->filter(function ($row) use ($assignmentSettings) {
            if (empty($row['leadgen_id'])) {
                return false;
            }

            if (($row['status'] ?? '') === 'in_crm') {
                return ($assignmentSettings['enabled'] ?? false) && ($assignmentSettings['reassign_existing'] ?? false);
            }

            return (bool) ($row['importable'] ?? false);
        })->count();

        $results = [];
        foreach ($rows as $row) {
            if (empty($row['leadgen_id'])) {
                continue;
            }

            $isExistingCrmRow = ($row['status'] ?? '') === 'in_crm';
            if ($isExistingCrmRow && (!($assignmentSettings['enabled'] ?? false) || !($assignmentSettings['reassign_existing'] ?? false))) {
                continue;
            }

            if (!$isExistingCrmRow && !($row['importable'] ?? false)) {
                continue;
            }

            if ($processedCount >= $batchLimit) {
                break;
            }

            $form = $this->resolveForm($row['form_id'] ?? null, $fallbackForm);
            if (!$isExistingCrmRow && (!$form || !$form->is_enabled)) {
                $results[] = [
                    'leadgen_id' => $row['leadgen_id'],
                    'name' => $row['name'] ?? null,
                    'status' => 'failed',
                    'message' => 'No enabled form found. Select correct fallback form and try again.',
                ];
                continue;
            }

            try {
                if (!$isExistingCrmRow && !FbWebhookEvent::where('leadgen_id', $row['leadgen_id'])->exists()) {
                    FbWebhookEvent::create([
                        'leadgen_id' => $row['leadgen_id'],
                        'status' => 'received',
                        'raw_payload' => [
                            'source' => 'manual_missing_checker_import',
                            'form_id' => $form->form_id,
                            'csv_row' => $row,
                            'imported_by' => $assignmentSettings['assigned_by'] ?? auth()->id(),
                        ],
                    ]);
                }

                if (!$isExistingCrmRow && $form?->page && !empty($form->page->page_access_token)) {
                    FetchFacebookLeadDetailsJob::dispatchSync((string) $row['leadgen_id'], (int) $form->id, false);
                }

                $fbLead = FbLead::where('leadgen_id', $row['leadgen_id'])->first();
                $event = FbWebhookEvent::where('leadgen_id', $row['leadgen_id'])->latest('id')->first();
                if (!$isExistingCrmRow && !$fbLead && $form) {
                    $fbLead = $this->importMissingLeadFromCsv($row, $form, $event, (int) ($assignmentSettings['assigned_by'] ?? auth()->id() ?: 1));
                    $event = FbWebhookEvent::where('leadgen_id', $row['leadgen_id'])->latest('id')->first();
                }

                $lead = $fbLead?->crmLead ?: (!empty($row['crm_lead_id']) ? Lead::find($row['crm_lead_id']) : null);
                $assignmentResult = null;

                if (($assignmentSettings['enabled'] ?? false) && $lead && $form) {
                    $assignmentResult = $this->assignLead($lead, $form, $assignmentSettings, $assignIndex);
                    if (($assignmentResult['assigned'] ?? false) === true) {
                        $assignIndex++;
                    }
                }

                $results[] = [
                    'leadgen_id' => $row['leadgen_id'],
                    'name' => $row['name'] ?? null,
                    'status' => $fbLead ? 'imported' : 'failed',
                    'assignment' => $assignmentResult,
                    'message' => $this->buildImportMessage($fbLead, $event, $assignmentResult),
                ];
            } catch (Throwable $e) {
                Log::warning('Meta missing checker import failed', [
                    'leadgen_id' => $row['leadgen_id'],
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'leadgen_id' => $row['leadgen_id'],
                    'name' => $row['name'] ?? null,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
            }

            $processedCount++;
        }

        return [
            'results' => $results,
            'processed_count' => $processedCount,
            'eligible_count' => $eligibleCount,
        ];
    }

    public function resolveForm(?string $externalFormId, ?FbForm $fallbackForm = null, ?Collection $formsByExternalId = null): ?FbForm
    {
        $externalFormId = trim((string) $externalFormId);
        if ($externalFormId !== '') {
            $formsByExternalId ??= $this->forms()->keyBy('form_id');
            $form = $formsByExternalId->get($externalFormId);
            if ($form) {
                return $form;
            }
        }

        return $fallbackForm;
    }

    private function assignLead(Lead $lead, FbForm $form, array $settings, int $assignIndex): array
    {
        $method = $settings['method'] ?? 'existing_rule';
        $lead->loadMissing('activeAssignments.assignedTo');
        $activeAssignment = $lead->activeAssignments->first();

        if ($activeAssignment && !($settings['reassign_existing'] ?? false)) {
            return [
                'assigned' => false,
                'skipped' => true,
                'method' => $method,
                'user_id' => $activeAssignment->assigned_to,
                'user_name' => $activeAssignment->assignedTo?->name,
                'message' => 'Already assigned to ' . ($activeAssignment->assignedTo?->name ?? 'existing owner') . '. Reassign skipped.',
            ];
        }

        if ($method === 'existing_rule') {
            $assigned = $this->sourceAutomationService->assignFromSource($lead, 'facebook_lead_ads', (int) $form->id);

            return [
                'assigned' => $assigned,
                'method' => 'existing_rule',
                'message' => $assigned ? 'Assigned via existing automation rule.' : 'No matching automation rule/user found.',
            ];
        }

        $user = $this->pickAssignmentUser($method, $settings, $assignIndex);
        if (!$user) {
            return [
                'assigned' => false,
                'method' => $method,
                'message' => 'No eligible assignment user selected.',
            ];
        }

        DB::transaction(function () use ($lead, $user, $settings, $method) {
            $lead->assignments()->where('is_active', true)->update([
                'is_active' => false,
                'unassigned_at' => now(),
            ]);

            LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'assigned_by' => (int) ($settings['assigned_by'] ?? 1),
                'assignment_type' => 'primary',
                'assignment_method' => 'meta_missing_checker_' . $method,
                'notes' => 'Assigned from Meta Missing Lead Checker recovery.',
                'assigned_at' => now(),
                'is_active' => true,
            ]);
        });

        if ($settings['create_calling_task'] ?? true) {
            try {
                Event::dispatch(new LeadAssigned($lead->fresh(), $user->id, (int) ($settings['assigned_by'] ?? 1)));
                $this->leadOwnerTaskService->ensureOpenTaskForOwner(
                    $lead->fresh(),
                    $user,
                    (int) ($settings['assigned_by'] ?? 1),
                    'Created from Meta Missing Lead Checker recovery.'
                );
            } catch (Throwable $e) {
                Log::warning('Meta missing checker task creation failed', [
                    'lead_id' => $lead->id,
                    'assigned_to' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'assigned' => true,
            'method' => $method,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'message' => 'Assigned to ' . $user->name . (($settings['create_calling_task'] ?? true) ? ' with calling task.' : '.'),
        ];
    }

    private function pickAssignmentUser(string $method, array $settings, int $assignIndex): ?User
    {
        if ($method === 'single_user') {
            return User::with('role')
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', $this->assignableRoleSlugs()))
                ->find($settings['single_user_id'] ?? null);
        }

        $userIds = collect($settings['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return null;
        }

        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $this->assignableRoleSlugs()))
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get()
            ->values();

        if ($users->isEmpty()) {
            return null;
        }

        if ($method === 'round_robin') {
            return $users[$assignIndex % $users->count()];
        }

        if ($method === 'first_available') {
            $counts = LeadAssignment::whereIn('assigned_to', $users->pluck('id'))
                ->where('is_active', true)
                ->selectRaw('assigned_to, COUNT(*) as total')
                ->groupBy('assigned_to')
                ->pluck('total', 'assigned_to');

            return $users->sortBy(fn (User $user) => (int) ($counts[$user->id] ?? 0))->first();
        }

        if ($method === 'percentage') {
            $weighted = [];
            foreach ($users as $user) {
                $weight = max(0, (int) round(($settings['percentages'][$user->id] ?? 0) * 100));
                for ($i = 0; $i < $weight; $i++) {
                    $weighted[] = $user->id;
                }
            }

            if (empty($weighted)) {
                return null;
            }

            $selectedId = $weighted[array_rand($weighted)];
            return $users->firstWhere('id', $selectedId);
        }

        return null;
    }

    private function importMissingLeadFromCsv(array $row, FbForm $form, ?FbWebhookEvent $event, int $createdBy): ?FbLead
    {
        $leadgenId = trim((string) ($row['leadgen_id'] ?? ''));
        if ($leadgenId === '' || FbLead::where('leadgen_id', $leadgenId)->exists()) {
            return FbLead::where('leadgen_id', $leadgenId)->first();
        }

        $mapped = [
            'name' => trim((string) ($row['name'] ?? '')),
            'phone' => trim((string) ($row['phone'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'notes' => 'Recovered from Meta Missing Lead Checker CSV.',
            'meta' => [
                'leadgen_id' => $leadgenId,
                'form_id' => $row['form_id'] ?? $form->form_id,
                'form_name' => $row['form_name'] ?? $form->form_name,
                'submitted_at' => $row['created_time'] ?? null,
                'recovery_source' => 'meta_missing_checker_csv',
            ],
        ];

        $crmLeadResult = $this->leadDuplicateGuardService->createOrAttachMetaLead($form, $mapped, $createdBy);
        $lead = $crmLeadResult['lead'] ?? null;
        if (!$lead) {
            return null;
        }

        $fieldData = array_filter([
            'full_name' => $mapped['name'] ?: null,
            'phone_number' => $mapped['phone'] ?: null,
            'email' => $mapped['email'] ?: null,
            'leadgen_id' => $leadgenId,
            'form_id' => $row['form_id'] ?? $form->form_id,
            'created_time' => $row['created_time'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $fbLead = FbLead::create([
            'leadgen_id' => $leadgenId,
            'fb_form_id' => $form->id,
            'crm_lead_id' => $lead->id,
            'field_data_json' => $fieldData,
            'raw_response_json' => [
                'source' => 'manual_missing_checker_csv',
                'created_time' => $row['created_time'] ?? null,
                'field_data' => $fieldData,
                'csv_row' => $row,
                'duplicate' => (bool) ($crmLeadResult['was_duplicate'] ?? false),
                'reopened' => (bool) ($crmLeadResult['was_reopened'] ?? false),
            ],
        ]);

        ($event ?: FbWebhookEvent::where('leadgen_id', $leadgenId)->latest('id')->first())?->update([
            'status' => 'processed',
            'error' => null,
            'raw_payload' => array_merge($event?->raw_payload ?? [], [
                'csv_fallback_imported' => true,
                'csv_fallback_imported_at' => now()->toDateTimeString(),
                'crm_lead_id' => $lead->id,
            ]),
        ]);

        return $fbLead;
    }

    private function buildImportMessage(?FbLead $fbLead, ?FbWebhookEvent $event, ?array $assignmentResult): string
    {
        if (!$fbLead) {
            return $event?->error ?: 'Meta API did not return lead details.';
        }

        $source = data_get($fbLead->raw_response_json, 'source') === 'manual_missing_checker_csv'
            ? 'Imported from CSV fallback'
            : 'Imported/attached successfully';

        $message = $source . '. CRM Lead ID: ' . ($fbLead->crm_lead_id ?: 'N/A');

        if ($assignmentResult) {
            $message .= ' | ' . ($assignmentResult['message'] ?? 'Assignment checked.');
        }

        return $message;
    }

    private function emptyCallbackSummary(): array
    {
        return [
            'fetched' => 0,
            'already_present' => 0,
            'queued' => 0,
            'imported' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];
    }

    private function formCallbackLockKey(FbForm $form): string
    {
        return 'meta:callback:form:' . $form->id;
    }

    private function pageCallbackLockKey(FbPage $page): string
    {
        return 'meta:callback:page:' . $page->id;
    }

    private function sanitizeErrorMessage(string $error): string
    {
        $error = preg_replace('/([?&](?:access_token|client_secret|appsecret_proof)=)[^&\\s]+/i', '$1[redacted]', $error) ?? $error;
        $error = preg_replace('/(Bearer\\s+)[A-Za-z0-9._\\-]+/i', '$1[redacted]', $error) ?? $error;

        return $error;
    }

    private function assignableRoleSlugs(): array
    {
        return [
            Role::SENIOR_MANAGER,
            Role::SALES_MANAGER,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SALES_EXECUTIVE,
            Role::MARKETING_MANAGER,
            Role::MARKETING_EXECUTIVE,
        ];
    }
}
