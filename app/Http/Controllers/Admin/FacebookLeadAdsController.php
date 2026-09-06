<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\FetchFacebookLeadDetailsJob;
use App\Jobs\ScanMetaBulkRecoveryLeadsJob;
use App\Http\Controllers\Controller;
use App\Models\FbForm;
use App\Models\FbLead;
use App\Models\FbLeadAdsSettings;
use App\Models\FbPage;
use App\Models\FbWebhookEvent;
use App\Models\FbCustomMappingField;
use App\Models\FacebookPortfolio;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\MetaBulkRecoveryScan;
use App\Models\MetaBulkRecoveryScanLead;
use App\Models\Role;
use App\Models\SourceAutomationRule;
use App\Models\User;
use App\Events\LeadAssigned;
use App\Services\FacebookGraphService;
use App\Services\FacebookLeadAdsHealthService;
use App\Services\FacebookLeadMappingService;
use App\Services\LeadDuplicateGuardService;
use App\Services\LeadOwnerTaskService;
use App\Services\MetaMissingLeadRecoveryService;
use App\Services\SourceAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Throwable;

class FacebookLeadAdsController extends Controller
{
    private const RETRY_LIMIT_DEFAULT = 5;
    private const RETRY_LIMIT_MAX = 10;
    private const RETRY_LOCK_SECONDS = 120;

    /**
     * Landing: link to settings and list of configured forms (standalone section).
     */
    public function index()
    {
        $settings = FbLeadAdsSettings::getSettings();
        $addedPages = FbPage::with('portfolio')->whereNotNull('page_access_token')->orderBy('page_name')->get();
        $pagesWithToken = $addedPages->pluck('id');
        $forms = FbForm::with(['page', 'mapping'])
            ->whereIn('fb_page_id', $pagesWithToken)
            ->orderBy('form_name')
            ->get();
        $portfolios = FacebookPortfolio::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $activeAutomationFormIds = $this->activeAutomationFormIds($forms->pluck('id'));
        $hasToken = $addedPages->isNotEmpty();
        $webhookUrl = url('/api/webhooks/facebook/leads');

        $recentLeads = FbLead::with('form')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        $lastLeadByForm = FbLead::query()
            ->select('fb_form_id', DB::raw('MAX(created_at) as last_lead_at'))
            ->whereIn('fb_form_id', $forms->pluck('id'))
            ->groupBy('fb_form_id')
            ->pluck('last_lead_at', 'fb_form_id');

        $webhookEvents = \App\Models\FbWebhookEvent::orderByDesc('created_at')
            ->limit(30)
            ->get();

        $retryableFailedWebhookCount = $this->countRetryableFailedWebhookEvents();
        $metaChecks = $this->buildMetaFormsChecks($addedPages, $settings);
        $healthSnapshot = app(FacebookLeadAdsHealthService::class)->snapshot($metaChecks);

        $traceQuery = request()->string('trace')->trim()->value();
        $traceReport = $traceQuery !== '' ? $this->buildTraceReport($traceQuery) : null;

        return view('integrations.facebook-lead-ads.index', compact(
            'settings',
            'hasToken',
            'forms',
            'webhookUrl',
            'recentLeads',
            'lastLeadByForm',
            'addedPages',
            'webhookEvents',
            'retryableFailedWebhookCount',
            'healthSnapshot',
            'metaChecks',
            'traceQuery',
            'traceReport',
            'portfolios',
            'activeAutomationFormIds'
        ));
    }

    public function diagnostics(Request $request)
    {
        $range = $this->resolveDiagnosticsRange($request);
        $settings = FbLeadAdsSettings::getSettings();
        $addedPages = FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get();
        $forms = FbForm::with(['page', 'mapping'])->orderBy('form_name')->get();
        $formsByExternalId = $forms->keyBy(fn (FbForm $form) => (string) $form->form_id);

        $webhookEvents = FbWebhookEvent::query()
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->orderByDesc('created_at')
            ->get();

        $fbLeads = FbLead::with(['form.page', 'crmLead'])
            ->whereBetween('created_at', [$range['from'], $range['to']])
            ->orderByDesc('created_at')
            ->get();

        $retryableFailedWebhookCount = $this->countRetryableFailedWebhookEvents();
        $metaChecks = $this->buildMetaFormsChecks($addedPages, $settings);
        $formRows = $this->buildDiagnosticsFormRows($forms, $webhookEvents, $fbLeads);
        $leadgenIdsWithLead = $fbLeads->pluck('leadgen_id')->filter()->map(fn ($id) => (string) $id)->flip();
        $missingWebhookEvents = $webhookEvents
            ->filter(fn (FbWebhookEvent $event) => $event->leadgen_id && !$leadgenIdsWithLead->has((string) $event->leadgen_id))
            ->unique('leadgen_id')
            ->values();
        $missingWebhookRows = $missingWebhookEvents
            ->take(20)
            ->map(fn (FbWebhookEvent $event) => [
                'event' => $event,
                'form_id' => $this->extractWebhookFormId($event),
            ]);
        $fbLeadsWithoutCrm = $fbLeads->filter(fn (FbLead $lead) => empty($lead->crm_lead_id))->values();
        $latestFailedEvents = $this->buildDiagnosticsFailedEvents($webhookEvents);
        $recentMetaLeads = FbLead::with(['form.page', 'crmLead'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        $healthSnapshot = app(FacebookLeadAdsHealthService::class)->snapshot($metaChecks);

        $summary = [
            'webhooks_received' => $webhookEvents->count(),
            'processed' => $webhookEvents->where('status', 'processed')->count(),
            'failed' => $webhookEvents->where('status', 'failed')->count(),
            'pending' => $webhookEvents->where('status', 'received')->count(),
            'crm_leads_created' => $fbLeads->whereNotNull('crm_lead_id')->count(),
            'fb_leads_stored' => $fbLeads->count(),
            'unlinked_fb_leads' => $fbLeadsWithoutCrm->count(),
            'unassigned_meta_leads_7d' => $healthSnapshot['unassigned_meta_leads_7d'] ?? 0,
            'failed_meta_jobs' => $healthSnapshot['failed_meta_jobs'] ?? 0,
            'duplicates' => max(0, $webhookEvents->count() - $webhookEvents->pluck('leadgen_id')->filter()->unique()->count()),
            'suspected_missing' => $missingWebhookEvents->count() + $fbLeadsWithoutCrm->count(),
            'connected_pages' => $addedPages->count(),
            'enabled_forms' => $forms->where('is_enabled', true)->count(),
            'mapped_forms' => $forms->filter(fn (FbForm $form) => $form->mapping !== null)->count(),
            'retryable_failed' => $retryableFailedWebhookCount,
            'last_webhook_at' => optional(FbWebhookEvent::latest('created_at')->first())->created_at,
            'last_crm_lead_at' => optional(FbLead::whereNotNull('crm_lead_id')->latest('created_at')->first())->created_at,
        ];

        return view('integrations.facebook-lead-ads.diagnostics', [
            'settings' => $settings,
            'range' => $range,
            'addedPages' => $addedPages,
            'forms' => $forms,
            'summary' => $summary,
            'healthSnapshot' => $healthSnapshot,
            'metaChecks' => $metaChecks,
            'formRows' => $formRows,
            'latestFailedEvents' => $latestFailedEvents,
            'recentMetaLeads' => $recentMetaLeads,
            'missingWebhookRows' => $missingWebhookRows,
            'fbLeadsWithoutCrm' => $fbLeadsWithoutCrm->take(20),
            'webhookUrl' => url('/api/webhooks/facebook/leads'),
            'formsByExternalId' => $formsByExternalId,
        ]);
    }

    public function metaFormsChecksForHealth(): array
    {
        return $this->buildMetaFormsChecks(
            FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get(),
            FbLeadAdsSettings::getSettings()
        );
    }

    public function retryFailedWebhooks(Request $request)
    {
        $lock = Cache::lock('meta:retry-failed-webhooks', self::RETRY_LOCK_SECONDS);
        if (!$lock->get()) {
            return redirect()
                ->route('integrations.facebook-lead-ads.index')
                ->with('warning', 'Failed webhook retry already running. Thoda wait karke dobara try karo.');
        }

        $limit = min(max((int) $request->input('limit', self::RETRY_LIMIT_DEFAULT), 1), self::RETRY_LIMIT_MAX);
        $enabledFormIds = FbForm::where('is_enabled', true)->pluck('form_id')->map(fn ($id) => (string) $id)->flip();
        $events = $this->retryableFailedWebhookEventsQuery()
            ->orderBy('created_at')
            ->get()
            ->unique('leadgen_id')
            ->filter(fn (FbWebhookEvent $event) => $enabledFormIds->has((string) $this->extractWebhookFormId($event)))
            ->take($limit)
            ->values();

        $queued = 0;
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        try {
            foreach ($events as $event) {
                $formId = $this->extractWebhookFormId($event);

                if (!$formId) {
                    $skipped++;
                    continue;
                }

                $fbForm = FbForm::where('form_id', $formId)
                    ->where('is_enabled', true)
                    ->first() ?: $this->resolveFallbackFormForEvent($event, $formId);

                if (!$fbForm) {
                    $skipped++;
                    continue;
                }

                if (FbLead::where('leadgen_id', $event->leadgen_id)->exists()) {
                    $event->update(['status' => 'processed', 'error' => null]);
                    $processed++;
                    continue;
                }

                try {
                    $event->update(['status' => 'received', 'error' => null]);
                    FetchFacebookLeadDetailsJob::dispatch((string) $event->leadgen_id, (int) $fbForm->id);
                    $queued++;
                } catch (Throwable $e) {
                    $failed++;
                    $event->update(['status' => 'failed', 'error' => $e->getMessage()]);

                    Log::warning('Meta failed webhook retry failed', [
                        'event_id' => $event->id,
                        'leadgen_id' => $event->leadgen_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $lock->release();
        }

        if ($events->isEmpty()) {
            return redirect()
                ->route('integrations.facebook-lead-ads.index')
                ->with('warning', 'No retryable failed webhook found. Pehle failed form ko enable/map karo, phir retry chalega.');
        }

        return redirect()
            ->route('integrations.facebook-lead-ads.index')
            ->with('success', "Failed webhook retry queued. Queued {$queued}, already processed {$processed}, failed {$failed}, skipped {$skipped}.");
    }

    /**
     * Settings form (token, graph version, page select after test).
     */
    public function settings()
    {
        $settings = FbLeadAdsSettings::getSettings();
        $pageName = $settings->page_id ? optional(FbPage::where('page_id', $settings->page_id)->first())->page_name : '';
        $addedPages = FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get();

        return view('integrations.facebook-lead-ads.settings', compact('settings', 'pageName', 'addedPages'));
    }

    /**
     * Save settings (token, graph_version, page_id, webhook_verify_token, etc.)
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'page_access_token' => 'nullable|string',
            'marketing_access_token' => 'nullable|string',
            'ad_account_id' => 'nullable|string|max:80',
            'cpl_sync_enabled' => 'boolean',
            'graph_version' => 'nullable|string|max:20',
            'page_id' => 'nullable|string|max:50',
            'page_name' => 'nullable|string|max:255',
            'webhook_verify_token' => 'nullable|string|max:255',
            'app_secret' => 'nullable|string|max:255',
            'signature_verification_enabled' => 'boolean',
        ]);

        $settings = FbLeadAdsSettings::getSettings();
        $settings->fill($request->only([
            'page_access_token', 'ad_account_id', 'cpl_sync_enabled',
            'graph_version', 'page_id', 'webhook_verify_token', 'app_secret', 'signature_verification_enabled',
        ]));
        if ($request->filled('marketing_access_token')) {
            $settings->marketing_access_token = $request->string('marketing_access_token')->value();
        }
        if ($settings->ad_account_id) {
            $settings->ad_account_id = FacebookGraphService::normalizeAdAccountId($settings->ad_account_id);
        }
        $settings->signature_verification_enabled = $request->boolean('signature_verification_enabled');
        $settings->cpl_sync_enabled = $request->boolean('cpl_sync_enabled');
        $settings->save();

        if ($request->filled('page_id')) {
            $pageUpdates = ['page_name' => $request->page_name ?? null];
            if ($request->filled('page_access_token')) {
                $pageUpdates['page_access_token'] = $request->string('page_access_token')->value();
            }

            FbPage::updateOrCreate(
                ['page_id' => $request->page_id],
                $pageUpdates
            );
        }

        if ($request->filled('page_access_token')) {
            $this->syncExistingPagesFromToken(
                $request->string('page_access_token')->value(),
                $request->input('graph_version', 'v18.0')
            );
        }

        return response()->json(['success' => true, 'message' => 'Settings saved.']);
    }

    public function testMarketingConnection(Request $request)
    {
        $request->validate([
            'marketing_access_token' => 'required|string',
            'ad_account_id' => 'required|string|max:80',
            'graph_version' => 'nullable|string|max:20',
        ]);

        $client = FacebookGraphService::fromToken(
            $request->string('marketing_access_token')->value(),
            $request->input('graph_version', 'v18.0')
        );

        return response()->json($client->testMarketingInsightsAccess($request->string('ad_account_id')->value()));
    }

    private function syncExistingPagesFromToken(string $token, string $graphVersion): void
    {
        try {
            $result = FacebookGraphService::fromToken($token, $graphVersion)->testConnection();

            if (!($result['success'] ?? false)) {
                return;
            }

            foreach (($result['pages'] ?? []) as $page) {
                $pageId = (string) ($page['id'] ?? '');
                if ($pageId === '') {
                    continue;
                }

                $storedPage = FbPage::where('page_id', $pageId)->first();
                if (!$storedPage) {
                    continue;
                }

                $storedPage->page_name = $page['name'] ?? $storedPage->page_name;
                $storedPage->page_access_token = $page['access_token'] ?? $token;
                $storedPage->save();
            }
        } catch (Throwable $e) {
            Log::warning('Meta page token sync from settings failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Add a page (from Test connection result). Stores page_id, page_name, page_access_token in fb_pages.
     */
    public function addPage(Request $request)
    {
        $request->validate([
            'page_id' => 'required|string|max:50',
            'page_name' => 'required|string|max:255',
            'page_access_token' => 'required|string',
            'facebook_portfolio_id' => 'nullable|exists:facebook_portfolios,id',
        ]);

        FbPage::updateOrCreate(
            ['page_id' => $request->page_id],
            [
                'page_name' => $request->page_name,
                'page_access_token' => $request->page_access_token,
                'facebook_portfolio_id' => $request->integer('facebook_portfolio_id') ?: null,
            ]
        );

        $addedPages = FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get(['id', 'page_id', 'page_name']);
        return response()->json([
            'success' => true,
            'message' => 'Page added.',
            'added_pages' => $addedPages,
        ]);
    }

    public function storePortfolio(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        FacebookPortfolio::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('integrations.facebook-lead-ads.index')
            ->with('success', 'Portfolio created. Ab pages ko is portfolio me assign kar sakte ho.');
    }

    public function updatePortfolio(Request $request, FacebookPortfolio $portfolio)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $portfolio->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()
            ->route('integrations.facebook-lead-ads.index')
            ->with('success', 'Portfolio updated.');
    }

    public function assignPagePortfolio(Request $request, FbPage $page)
    {
        $validated = $request->validate([
            'facebook_portfolio_id' => ['nullable', 'exists:facebook_portfolios,id'],
        ]);

        $page->update([
            'facebook_portfolio_id' => $validated['facebook_portfolio_id'] ?? null,
        ]);

        return redirect()
            ->back()
            ->with('success', 'Page portfolio updated.');
    }

    public function syncForms(Request $request, ?FbPage $page = null)
    {
        $settings = FbLeadAdsSettings::getSettings();
        $pages = $page
            ? collect([$page])
            : FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get();

        $synced = 0;
        $created = 0;
        $failed = 0;

        foreach ($pages as $syncPage) {
            if (empty($syncPage->page_access_token)) {
                continue;
            }

            $client = FacebookGraphService::fromToken($syncPage->page_access_token, $settings->graph_version ?? 'v18.0');
            $result = $client->getLeadgenForms((string) $syncPage->page_id);

            if (!($result['success'] ?? false)) {
                $failed++;
                Log::warning('Facebook lead ads form sync failed', [
                    'fb_page_id' => $syncPage->id,
                    'page_id' => $syncPage->page_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                continue;
            }

            $summary = $this->syncPageFormsFromMeta($syncPage, $result['forms'] ?? [], $client);
            $synced += $summary['synced'];
            $created += $summary['created'];
        }

        $message = "Form sync completed. {$synced} form(s) checked, {$created} new pending form(s) added.";
        if ($failed > 0) {
            $message .= " {$failed} page(s) failed; check token/diagnostics.";
        }

        $indexRoute = request()->routeIs('ad-manager.*')
            ? 'ad-manager.meta.facebook-lead-ads.index'
            : 'integrations.facebook-lead-ads.index';

        return redirect()
            ->route($indexRoute)
            ->with($failed > 0 ? 'warning' : 'success', $message);
    }

    /**
     * Remove page: clear token so it no longer appears in Select Form. Keeps row for existing forms.
     */
    public function removePage(Request $request)
    {
        $request->validate(['page_id' => 'required|string|max:50']);
        $page = FbPage::where('page_id', $request->page_id)->first();
        if ($page) {
            $page->update(['page_access_token' => null]);
        }
        return response()->json(['success' => true, 'message' => 'Page removed.']);
    }

    /**
     * Test connection: call Graph API, return pages list.
     */
    public function testConnection(Request $request)
    {
        $request->validate(['page_access_token' => 'required|string']);
        $settings = FbLeadAdsSettings::getSettings();
        $settings->page_access_token = $request->page_access_token;
        $settings->graph_version = $request->input('graph_version', $settings->graph_version ?? 'v18.0');
        $settings->save();

        $client = FacebookGraphService::fromSettings($settings);
        $result = $client->testConnection();

        return response()->json($result);
    }

    /**
     * Form selector: with page_id show forms for that page; without page_id show "Choose a page" list.
     */
    public function forms(Request $request)
    {
        $pageId = $request->query('page_id');
        $settings = FbLeadAdsSettings::getSettings();

        if ($pageId) {
            $page = FbPage::where('page_id', $pageId)->first();
            if (!$page || empty($page->page_access_token)) {
                return redirect()->route('integrations.facebook-lead-ads.forms')
                    ->with('error', 'Page not found or token missing. Re-add the page from Settings.');
            }
            $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
            $result = $client->getLeadgenForms($pageId);
            $syncSucceeded = (bool) ($result['success'] ?? false);
            $metaForms = collect($syncSucceeded ? ($result['forms'] ?? []) : [])
                ->keyBy(fn ($form) => (string) ($form['id'] ?? ''));

            if ($syncSucceeded) {
                $this->syncPageFormsFromMeta($page, $metaForms->values()->all(), $client);
            }

            $localForms = FbForm::with('mapping')
                ->where('fb_page_id', $page->id)
                ->orderBy('form_name')
                ->get();
            foreach ($localForms as $localForm) {
                $metaForms->put((string) $localForm->form_id, $metaForms->get((string) $localForm->form_id, [
                    'id' => (string) $localForm->form_id,
                    'name' => $localForm->form_name ?: ('Form ' . $localForm->form_id),
                    'created_time' => optional($localForm->created_at)->toIso8601String(),
                ]));
            }

            $forms = $metaForms->filter(fn ($form, $id) => $id !== '')->values()->all();
            $existingFormIds = $localForms->pluck('form_id', 'id')->toArray();
            $existingFormStates = $localForms->keyBy('form_id');
            return view('integrations.facebook-lead-ads.forms', [
                'forms' => $forms,
                'existingFormIds' => $existingFormIds,
                'existingFormStates' => $existingFormStates,
                'automationRules' => $this->facebookAutomationRules(),
                'syncWarning' => $syncSucceeded ? null : ($result['error'] ?? 'Meta sync unavailable. Saved forms are shown below.'),
                'page' => $page,
                'pages' => null,
            ]);
        }

        $pages = FbPage::whereNotNull('page_access_token')->orderBy('page_name')->get();
        if ($pages->isEmpty()) {
            return redirect()->route('integrations.facebook-lead-ads.settings')
                ->with('warning', 'Add at least one page (Test connection then Add page) first.');
        }
        return view('integrations.facebook-lead-ads.forms', [
            'forms' => [],
            'existingFormIds' => [],
            'existingFormStates' => collect(),
            'automationRules' => collect(),
            'page' => null,
            'pages' => $pages,
        ]);
    }

    public function missingChecker()
    {
        $recovery = app(MetaMissingLeadRecoveryService::class);

        return view('integrations.facebook-lead-ads.missing-checker', [
            'forms' => $recovery->forms(),
            'assignableUsers' => $recovery->assignableUsers(),
            'report' => session('meta_missing_checker_report'),
            'importResults' => session('meta_missing_checker_import_results'),
        ]);
    }

    public function bulkRecoveryIndex()
    {
        $activeScan = MetaBulkRecoveryScan::query()
            ->whereIn('status', ['queued', 'scanning', 'importing'])
            ->latest('id')
            ->first();
        $latestScan = $activeScan ?: MetaBulkRecoveryScan::query()->latest('id')->first();

        return view('integrations.facebook-lead-ads.bulk-recovery', [
            'activeScan' => $activeScan,
            'scan' => $latestScan ? $this->loadBulkRecoveryScan($latestScan) : null,
            'scans' => MetaBulkRecoveryScan::query()->with('starter')->latest('id')->limit(10)->get(),
            'portfolios' => FacebookPortfolio::query()->where('is_active', true)->orderBy('name')->get(),
            'pages' => FbPage::query()->whereNotNull('page_access_token')->orderBy('page_name')->get(),
            'forms' => FbForm::query()->with('page')->where('is_enabled', true)->orderBy('form_name')->get(),
        ]);
    }

    public function bulkRecoveryStoreScan(Request $request)
    {
        $validated = $request->validate([
            'scope_type' => ['required', 'in:all,portfolio,page,form'],
            'scope_id' => ['nullable', 'integer'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'per_form_limit' => ['required', 'integer', 'min:1', 'max:100'],
            'total_limit' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $activeScan = MetaBulkRecoveryScan::query()
            ->whereIn('status', ['queued', 'scanning', 'importing'])
            ->latest('id')
            ->first();
        if ($activeScan) {
            return redirect()
                ->route($this->bulkRecoveryRouteName('show'), $activeScan)
                ->with('warning', 'Ek Meta bulk recovery already running hai. Pehle uska result complete hone do.');
        }

        $scan = MetaBulkRecoveryScan::create([
            'status' => 'queued',
            'scope_type' => $validated['scope_type'],
            'scope_id' => $validated['scope_type'] === 'all' ? null : ($validated['scope_id'] ?? null),
            'date_from' => $validated['date_from'],
            'date_to' => $validated['date_to'],
            'per_form_limit' => min((int) $validated['per_form_limit'], 100),
            'total_limit' => min((int) $validated['total_limit'], 500),
            'started_by' => auth()->id(),
        ]);

        ScanMetaBulkRecoveryLeadsJob::dispatch($scan->id);

        return redirect()
            ->route($this->bulkRecoveryRouteName('show'), $scan)
            ->with('success', 'Meta bulk scan queue ho gaya. Page auto-refresh hoga jab tak scan complete nahi hota.');
    }

    public function bulkRecoveryShow(MetaBulkRecoveryScan $scan)
    {
        $scan = $this->refreshBulkRecoveryImportStatus($scan);

        return view('integrations.facebook-lead-ads.bulk-recovery', [
            'activeScan' => MetaBulkRecoveryScan::query()->whereIn('status', ['queued', 'scanning', 'importing'])->latest('id')->first(),
            'scan' => $this->loadBulkRecoveryScan($scan),
            'scans' => MetaBulkRecoveryScan::query()->with('starter')->latest('id')->limit(10)->get(),
            'portfolios' => FacebookPortfolio::query()->where('is_active', true)->orderBy('name')->get(),
            'pages' => FbPage::query()->whereNotNull('page_access_token')->orderBy('page_name')->get(),
            'forms' => FbForm::query()->with('page')->where('is_enabled', true)->orderBy('form_name')->get(),
        ]);
    }

    public function bulkRecoveryImport(Request $request, MetaBulkRecoveryScan $scan)
    {
        $validated = $request->validate([
            'import_mode' => ['required', 'in:selected,all_ready'],
            'lead_ids' => ['nullable', 'array'],
            'lead_ids.*' => ['integer'],
        ]);

        $scan = $this->refreshBulkRecoveryImportStatus($scan);
        if (!in_array($scan->status, ['completed', 'failed'], true)) {
            return redirect()
                ->route($this->bulkRecoveryRouteName('show'), $scan)
                ->with('warning', 'Scan complete hone ke baad import karo.');
        }

        $query = $scan->leads()->where('status', 'ready');
        if ($validated['import_mode'] === 'selected') {
            $ids = collect($validated['lead_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            if ($ids->isEmpty()) {
                return redirect()
                    ->route($this->bulkRecoveryRouteName('show'), $scan)
                    ->with('warning', 'Import ke liye kam se kam ek lead select karo.');
            }
            $query->whereIn('id', $ids);
        }

        $rows = $query->limit(500)->get();
        if ($rows->isEmpty()) {
            return redirect()
                ->route($this->bulkRecoveryRouteName('show'), $scan)
                ->with('warning', 'Import ke liye koi ready lead nahi mili.');
        }

        $queued = 0;
        $alreadyPresent = 0;
        foreach ($rows->chunk(25) as $chunk) {
            foreach ($chunk as $row) {
                if (FbLead::where('leadgen_id', $row->leadgen_id)->exists()) {
                    $row->update(['status' => 'already_present', 'error' => null]);
                    $alreadyPresent++;
                    continue;
                }

                $row->update(['status' => 'queued', 'error' => null]);
                FetchFacebookLeadDetailsJob::dispatch((string) $row->leadgen_id, (int) $row->fb_form_id, false, (int) $row->id);
                $queued++;
            }
        }

        $this->recountBulkRecoveryScan($scan);
        $scan->update(['status' => 'importing']);

        return redirect()
            ->route($this->bulkRecoveryRouteName('show'), $scan)
            ->with('success', "Import queued. {$queued} leads queue hue, {$alreadyPresent} duplicate skip hue.");
    }

    public function previewMissingChecker(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|max:5120|mimes:csv,txt',
            'fallback_form_id' => 'nullable|exists:fb_forms,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $fallbackForm = $request->filled('fallback_form_id')
            ? FbForm::with('page')->where('is_enabled', true)->find($request->integer('fallback_form_id'))
            : null;

        $recovery = app(MetaMissingLeadRecoveryService::class);
        $parsedRows = $recovery->parseUploadedFile($request->file('csv_file'));
        $dateFilter = [
            'from' => $request->input('date_from'),
            'to' => $request->input('date_to'),
        ];
        $filteredRows = $recovery->filterRowsByDate($parsedRows, $dateFilter);
        $report = $recovery->buildReport($filteredRows, $fallbackForm);
        $report['date_filter'] = $dateFilter;
        $report['csv_total_rows'] = count($parsedRows);

        session([
            'meta_missing_checker_rows' => $report['rows'],
            'meta_missing_checker_report' => $report,
        ]);
        session()->forget('meta_missing_checker_import_results');

        return redirect()->route('integrations.facebook-lead-ads.missing-checker')
            ->with('success', 'CSV checked successfully.');
    }

    public function importMissingLeads(Request $request)
    {
        $request->validate([
            'fallback_form_id' => 'nullable|exists:fb_forms,id',
            'import_action' => 'nullable|in:import,import_assign',
            'assignment_method' => 'nullable|required_if:import_action,import_assign|in:existing_rule,single_user,round_robin,first_available,percentage',
            'assignee_user_id' => 'nullable|required_if:assignment_method,single_user|exists:users,id',
            'assignment_user_ids' => 'nullable|array',
            'assignment_user_ids.*' => 'exists:users,id',
            'user_percentages' => 'nullable|array',
            'user_percentages.*' => 'nullable|numeric|min:0|max:100',
            'create_calling_task' => 'nullable|boolean',
            'reassign_existing' => 'nullable|boolean',
            'batch_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $rows = collect(session('meta_missing_checker_rows', []));
        if ($rows->isEmpty()) {
            return redirect()->route('integrations.facebook-lead-ads.missing-checker')
                ->with('error', 'No checked CSV rows found. Upload CSV first.');
        }

        $fallbackForm = $request->filled('fallback_form_id')
            ? FbForm::with('page')->where('is_enabled', true)->find($request->integer('fallback_form_id'))
            : null;

        $assignmentSettings = [
            'enabled' => $request->input('import_action', 'import') === 'import_assign',
            'method' => $request->input('assignment_method', 'existing_rule'),
            'single_user_id' => $request->integer('assignee_user_id') ?: null,
            'user_ids' => collect($request->input('assignment_user_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all(),
            'percentages' => collect($request->input('user_percentages', []))->mapWithKeys(fn ($value, $id) => [(int) $id => (float) $value])->all(),
            'create_calling_task' => $request->boolean('create_calling_task', true),
            'reassign_existing' => $request->boolean('reassign_existing', false),
            'assigned_by' => auth()->id() ?: 1,
        ];
        $batchLimit = min(max((int) $request->input('batch_limit', 20), 1), 50);
        $recovery = app(MetaMissingLeadRecoveryService::class);
        $importResult = $recovery->importRows($rows, $fallbackForm, $assignmentSettings, $batchLimit);
        $results = $importResult['results'];
        $processedCount = $importResult['processed_count'];
        $eligibleCount = $importResult['eligible_count'];

        $updatedReport = $recovery->buildReport($rows->all(), $fallbackForm);
        $existingReport = session('meta_missing_checker_report', []);
        $updatedReport['date_filter'] = $existingReport['date_filter'] ?? ['from' => null, 'to' => null];
        $updatedReport['csv_total_rows'] = $existingReport['csv_total_rows'] ?? $updatedReport['stats']['total'];
        session([
            'meta_missing_checker_rows' => $updatedReport['rows'],
            'meta_missing_checker_report' => $updatedReport,
            'meta_missing_checker_import_results' => $results,
        ]);

        return redirect()->route('integrations.facebook-lead-ads.missing-checker')
            ->with('success', "Import attempt completed. Processed {$processedCount} of {$eligibleCount} eligible rows. Run again for remaining rows.");
    }

    public function pageDetail(FbPage $page)
    {
        return response()->json([
            'success' => true,
            'data' => $this->buildPageDetailPayload($page),
        ]);
    }

    public function formDetail(FbForm $form)
    {
        $form->load(['page', 'mapping']);
        $settings = FbLeadAdsSettings::getSettings();
        $metaPayload = null;
        $metaError = null;

        if ($form->page?->page_access_token) {
            $client = FacebookGraphService::fromToken($form->page->page_access_token, $settings->graph_version ?? 'v18.0');
            $result = $client->getFormDetails((string) $form->form_id);

            if ($result['success'] ?? false) {
                $metaPayload = $result['form'] ?? null;
            } else {
                $metaError = $result['error'] ?? 'Could not fetch Meta form detail.';
            }
        } else {
            $metaError = 'Page token missing. Re-add/update page token to fetch live Meta form fields.';
        }

        $questions = collect($metaPayload['questions'] ?? [])->map(function ($question, int $index) {
            $options = collect($question['options'] ?? [])->map(function ($option) {
                return is_array($option)
                    ? ($option['value'] ?? $option['key'] ?? $option['label'] ?? json_encode($option))
                    : (string) $option;
            })->filter()->values();

            return [
                'position' => $index + 1,
                'key' => $question['key'] ?? $question['name'] ?? null,
                'label' => $question['label'] ?? $question['name'] ?? $question['key'] ?? ('Question ' . ($index + 1)),
                'type' => $question['type'] ?? $question['question_type'] ?? 'field',
                'options' => $options,
            ];
        })->values();

        $mapping = collect($form->mapping?->mapping_json ?? [])->map(fn ($crmField, $fbField) => [
            'fb_field' => $fbField,
            'crm_field' => $crmField ?: 'Unmapped',
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $form->id,
                'form_id' => $form->form_id,
                'name' => $metaPayload['name'] ?? $form->form_name ?? $form->form_id,
                'status' => $metaPayload['status'] ?? ($form->is_enabled ? 'Enabled in CRM' : 'Disabled in CRM'),
                'created_time' => $metaPayload['created_time'] ?? $this->formatDateTime($form->created_at),
                'updated_time' => $metaPayload['updated_time'] ?? $this->formatDateTime($form->updated_at),
                'crm_created_at' => $this->formatDateTime($form->created_at),
                'crm_updated_at' => $this->formatDateTime($form->updated_at),
                'is_enabled' => (bool) $form->is_enabled,
                'mapping_ready' => $form->mapping !== null,
                'page' => [
                    'id' => $form->page?->id,
                    'page_id' => $form->page?->page_id,
                    'page_name' => $form->page?->page_name ?: $form->page?->page_id,
                ],
                'questions' => $questions,
                'mapping' => $mapping,
                'meta_error' => $metaError,
                'mapping_url' => route('integrations.facebook-lead-ads.mapping', [
                    'formId' => $form->form_id,
                    'form_name' => $form->form_name,
                    'page_id' => $form->page?->page_id,
                ]),
            ],
        ]);
    }

    public function callbackPageLeads(Request $request, FbPage $page)
    {
        $validated = $request->validate([
            'since' => 'nullable|date',
            'until' => 'nullable|date|after_or_equal:since',
            'limit' => 'nullable|integer|min:1|max:100',
            'action' => 'nullable|in:preview,import',
        ], [
            'until.after_or_equal' => 'To date From date se pehle nahi ho sakti.',
            'limit.max' => 'Ek callback me maximum 100 leads import kar sakte hain.',
            'limit.min' => 'Limit kam se kam 1 honi chahiye.',
        ]);

        $since = !empty($validated['since']) ? Carbon::parse($validated['since'])->startOfDay() : now()->subDays(7);
        $until = !empty($validated['until']) ? Carbon::parse($validated['until'])->endOfDay() : now();
        $limit = min(max((int) ($validated['limit'] ?? 50), 1), 100);

        $result = app(MetaMissingLeadRecoveryService::class)->callbackPageLeads($page, $since, $until, $limit);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? 'Callback completed.',
            'summary' => $result['summary'] ?? [],
            'forms' => $result['forms'] ?? [],
            'data' => $this->buildPageDetailPayload($page->fresh()),
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function callbackFormLeads(Request $request, FbForm $form)
    {
        $validated = $request->validate([
            'since' => 'nullable|date',
            'until' => 'nullable|date|after_or_equal:since',
            'limit' => 'nullable|integer|min:1|max:100',
            'action' => 'nullable|in:preview,import',
        ], [
            'until.after_or_equal' => 'To date From date se pehle nahi ho sakti.',
            'limit.max' => 'Ek callback me maximum 100 leads import kar sakte hain.',
            'limit.min' => 'Limit kam se kam 1 honi chahiye.',
        ]);

        $since = !empty($validated['since']) ? Carbon::parse($validated['since'])->startOfDay() : now()->subDays(7);
        $until = !empty($validated['until']) ? Carbon::parse($validated['until'])->endOfDay() : now();
        $limit = min(max((int) ($validated['limit'] ?? 50), 1), 100);

        if (($validated['action'] ?? 'import') === 'preview') {
            $result = app(MetaMissingLeadRecoveryService::class)->previewFormLeads($form, $since, $until, $limit);

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'mode' => 'preview',
                'message' => $result['message'] ?? 'Preview completed.',
                'summary' => $result['summary'] ?? [],
                'leads' => $result['leads'] ?? [],
                'forms' => $result['forms'] ?? [],
            ], ($result['success'] ?? false) ? 200 : 422);
        }

        $result = app(MetaMissingLeadRecoveryService::class)->callbackFormLeads($form, $since, $until, $limit);

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'mode' => 'import',
            'message' => $result['message'] ?? 'Callback completed.',
            'summary' => $result['summary'] ?? [],
            'forms' => $result['forms'] ?? [],
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Mapping UI for a form (by Meta form_id). Create FbForm on first visit if needed.
     */
    public function mapping(Request $request, string $formId)
    {
        $settings = FbLeadAdsSettings::getSettings();
        $pageId = $request->query('page_id');
        $page = null;
        if ($pageId) {
            $page = FbPage::where('page_id', $pageId)->first();
        }
        if (!$page) {
            $fbFormExisting = FbForm::where('form_id', $formId)->with('page')->first();
            $page = $fbFormExisting?->page;
        }
        if (!$page) {
            return redirect()->route('integrations.facebook-lead-ads.forms')
                ->with('error', 'Select a page first, then choose a form.');
        }
        if (empty($page->page_access_token)) {
            return redirect()->route('integrations.facebook-lead-ads.settings')
                ->with('error', 'Page token missing. Re-add this page from Settings (Test connection → Add page).');
        }

        $formName = $request->input('form_name', 'Form ' . $formId);
        $fbForm = FbForm::firstOrCreate(
            ['form_id' => $formId],
            ['fb_page_id' => $page->id, 'form_name' => $formName]
        );
        $fbForm->load('page');

        $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
        $fieldsResult = $client->getFormFieldsSample($formId);
        $fieldNames = $fieldsResult['fields'] ? array_column($fieldsResult['fields'], 'name') : [];
        if (empty($fieldNames)) {
            $fieldNames = ['full_name', 'email', 'phone_number', 'city', 'state', 'zip_code'];
        }
        $suggestedMapping = FacebookLeadMappingService::suggestMapping($fieldNames);
        $crmKeys = FacebookLeadMappingService::getCrmFieldKeys();
        $currentMapping = $fbForm->mapping?->mapping_json ?? $suggestedMapping;
        $fieldMeta = collect($fieldNames)->mapWithKeys(function ($fieldName) use ($crmKeys) {
            $normalizedKey = FacebookLeadMappingService::normalizeFieldKey($fieldName);
            $prettyLabel = FacebookLeadMappingService::prettifyFieldName($fieldName);
            $exists = in_array($normalizedKey, $crmKeys, true);

            return [$fieldName => [
                'normalized_key' => $normalizedKey,
                'pretty_label' => $prettyLabel,
                'can_auto_create' => !$exists,
            ]];
        })->all();

        return view('integrations.facebook-lead-ads.mapping', [
            'fbForm' => $fbForm,
            'fieldNames' => $fieldNames,
            'suggestedMapping' => $suggestedMapping,
            'currentMapping' => $currentMapping,
            'crmKeys' => $crmKeys,
            'fieldMeta' => $fieldMeta,
            'automationRules' => $automationRules = $this->facebookAutomationRules(),
            'currentAutomationRule' => $this->automationForForm($fbForm, $automationRules),
        ]);
    }

    /**
     * Save mapping and enable form.
     */
    public function saveMapping(Request $request)
    {
        $automationRules = $this->facebookAutomationRules();
        $request->validate([
            'fb_form_id' => 'required|exists:fb_forms,id',
            'mapping' => 'required|array',
            'mapping.*' => 'nullable|string|max:50',
            'automation_rule_id' => [
                $automationRules->isNotEmpty() ? 'required' : 'nullable',
                Rule::in($automationRules->pluck('id')->all()),
            ],
        ]);

        $fbForm = FbForm::findOrFail($request->fb_form_id);
        $automationRule = $automationRules->firstWhere('id', $request->integer('automation_rule_id'));

        DB::transaction(function () use ($fbForm, $request, $automationRule) {
            $fbForm->mappings()->create([
                'mapping_json' => $request->mapping,
                'created_by' => auth()->id(),
            ]);
            $fbForm->update(['is_enabled' => true]);

            if ($automationRule) {
                $this->attachFormToAutomation($fbForm, $automationRule);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $automationRule
                ? "Mapping saved. Form enabled and added to {$automationRule->name}."
                : 'Mapping saved and form enabled.',
            'redirect' => route(request()->routeIs('ad-manager.*') ? 'ad-manager.meta.facebook-lead-ads.index' : 'integrations.facebook-lead-ads.index'),
        ]);
    }

    public function assignAutomation(Request $request, FbForm $form)
    {
        $automationRules = $this->facebookAutomationRules();
        $validated = $request->validate([
            'automation_rule_id' => ['required', Rule::in($automationRules->pluck('id')->all())],
        ]);
        $rule = $automationRules->firstWhere('id', (int) $validated['automation_rule_id']);

        DB::transaction(fn () => $this->attachFormToAutomation($form, $rule));

        return back()->with('success', "Form '{$form->form_name}' added to '{$rule->name}'.");
    }

    public function toggleForm(FbForm $form)
    {
        $form->update([
            'is_enabled' => !$form->is_enabled,
        ]);

        $status = $form->is_enabled ? 'enabled' : 'disabled';

        return redirect()
            ->back()
            ->with('success', "Form '{$form->form_name}' {$status}. Disabled forms will not create CRM leads.");
    }

    public function setFormState(Request $request)
    {
        $validated = $request->validate([
            'form_id' => ['required', 'string', 'max:120'],
            'form_name' => ['nullable', 'string', 'max:255'],
            'page_id' => ['required', 'string', 'max:120'],
            'is_enabled' => ['required', 'boolean'],
        ]);

        $page = FbPage::where('page_id', $validated['page_id'])->firstOrFail();
        $form = FbForm::firstOrCreate(
            ['form_id' => $validated['form_id']],
            [
                'fb_page_id' => $page->id,
                'form_name' => $validated['form_name'] ?: ('Meta Form ' . $validated['form_id']),
                'is_enabled' => false,
            ]
        );

        $form->update([
            'fb_page_id' => $page->id,
            'form_name' => $validated['form_name'] ?: $form->form_name,
            'is_enabled' => (bool) $validated['is_enabled'],
        ]);

        if ($page->page_access_token) {
            $settings = FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($page->page_access_token, $settings->graph_version ?? 'v18.0');
            $this->ensureAutoMappingForForm($form->fresh('mapping'), $client);
        }

        $status = $form->is_enabled ? 'enabled' : 'disabled';

        return redirect()
            ->back()
            ->with('success', "Form '{$form->form_name}' {$status}. Disabled forms will not create CRM leads.");
    }

    private function syncPageFormsFromMeta(FbPage $page, array $forms, FacebookGraphService $client): array
    {
        $synced = 0;
        $created = 0;

        foreach ($forms as $formRow) {
            $formId = (string) ($formRow['id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $form = FbForm::firstOrNew(['form_id' => $formId]);
            $isNew = !$form->exists;

            if (!$form->exists) {
                $form->is_enabled = false;
            }

            $form->fb_page_id = $page->id;
            $form->form_name = $formRow['name'] ?? $form->form_name ?? ('Meta Form ' . $formId);
            $form->save();

            if (!$isNew) {
                $this->ensureAutoMappingForForm($form->fresh('mapping'), $client);
            } else {
                $created++;
            }

            $synced++;
        }

        return [
            'synced' => $synced,
            'created' => $created,
        ];
    }

    private function ensureAutoMappingForForm(?FbForm $form, FacebookGraphService $client): bool
    {
        if (!$form || $form->mapping) {
            return false;
        }

        try {
            $fieldsResult = $client->getFormFieldsSample((string) $form->form_id);
            $fieldNames = ($fieldsResult['fields'] ?? [])
                ? array_column($fieldsResult['fields'], 'name')
                : [];

            if (empty($fieldNames)) {
                $fieldNames = ['full_name', 'email', 'phone_number', 'city', 'state', 'zip_code'];
            }

            $fieldNames = array_values(array_unique(array_filter(array_map('strval', $fieldNames))));
            if (empty($fieldNames)) {
                return false;
            }

            $form->mappings()->create([
                'mapping_json' => FacebookLeadMappingService::suggestMapping($fieldNames),
                'created_by' => auth()->id(),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::warning('Facebook lead form auto mapping failed', [
                'fb_form_id' => $form->id,
                'form_id' => $form->form_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Create a custom mapping field (for CRM field dropdown on mapping page).
     */
    public function storeCustomField(Request $request)
    {
        $standardKeys = ['name', 'email', 'phone', 'address', 'city', 'state', 'pincode', 'requirements', 'notes', 'meta'];
        $request->validate([
            'field_key' => 'required|string|max:255',
            'label' => 'nullable|string|max:100',
        ]);

        $rawKey = $request->string('field_key')->value();
        $label = $request->filled('label') ? trim((string) $request->label) : null;
        $key = $this->generateCustomFieldKey($rawKey, $standardKeys);

        if ($key === null) {
            return response()->json([
                'success' => false,
                'message' => 'Could not generate a valid field key. Use letters, numbers, or underscores.',
            ], 422);
        }

        FbCustomMappingField::create([
            'field_key' => $key,
            'label' => $label ?: FacebookLeadMappingService::prettifyFieldName($key),
        ]);

        $crmKeys = \App\Services\FacebookLeadMappingService::getCrmFieldKeys();
        return response()->json([
            'success' => true,
            'message' => 'Custom field added.',
            'crm_keys' => $crmKeys,
            'field_key' => $key,
            'label' => $label ?: FacebookLeadMappingService::prettifyFieldName($key),
        ]);
    }

    private function generateCustomFieldKey(string $rawKey, array $reservedKeys): ?string
    {
        $key = Str::of($rawKey)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();

        if ($key === '') {
            $key = 'meta_field';
        }

        $baseKey = Str::limit($key, 50, '');
        $candidate = $baseKey;
        $suffix = 2;

        while ($candidate !== '' && (
            in_array($candidate, $reservedKeys, true) ||
            FbCustomMappingField::where('field_key', $candidate)->exists()
        )) {
            $suffixText = '_' . $suffix;
            $candidate = Str::limit($baseKey, 50 - strlen($suffixText), '') . $suffixText;
            $suffix++;
        }

        return $candidate !== '' ? $candidate : null;
    }

    private function checkerForms(): Collection
    {
        return FbForm::with('page')
            ->where('is_enabled', true)
            ->orderBy('form_name')
            ->get();
    }

    private function checkerAssignableUsers(): Collection
    {
        return User::with('role')
            ->where('is_active', true)
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', $this->checkerAssignableRoleSlugs());
            })
            ->orderBy('name')
            ->get();
    }

    private function checkerAssignableRoleSlugs(): array
    {
        return [
            Role::SALES_EXECUTIVE,
            Role::ASSISTANT_SALES_MANAGER,
            Role::SENIOR_MANAGER,
            Role::SALES_MANAGER,
            Role::MARKETING_MANAGER,
            Role::MARKETING_EXECUTIVE,
        ];
    }

    private function assignCheckerLead(Lead $lead, FbForm $form, array $settings, int $assignIndex): array
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
            $assigned = app(SourceAutomationService::class)->assignFromSource($lead, 'facebook_lead_ads', (int) $form->id);

            return [
                'assigned' => $assigned,
                'method' => 'existing_rule',
                'message' => $assigned ? 'Assigned via existing automation rule.' : 'No matching automation rule/user found.',
            ];
        }

        $user = $this->pickCheckerAssignmentUser($method, $settings, $assignIndex);
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
                app(LeadOwnerTaskService::class)->ensureOpenTaskForOwner(
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

    private function pickCheckerAssignmentUser(string $method, array $settings, int $assignIndex): ?User
    {
        if ($method === 'single_user') {
            return User::with('role')
                ->where('is_active', true)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', $this->checkerAssignableRoleSlugs()))
                ->find($settings['single_user_id'] ?? null);
        }

        $userIds = collect($settings['user_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($userIds->isEmpty()) {
            return null;
        }

        $users = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $this->checkerAssignableRoleSlugs()))
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

    private function buildCheckerImportMessage(?FbLead $fbLead, ?FbWebhookEvent $event, ?array $assignmentResult): string
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

    private function importMissingLeadFromCsv(array $row, FbForm $form, ?FbWebhookEvent $event = null): ?FbLead
    {
        $leadgenId = trim((string) ($row['leadgen_id'] ?? ''));
        if ($leadgenId === '' || FbLead::where('leadgen_id', $leadgenId)->exists()) {
            return FbLead::where('leadgen_id', $leadgenId)->first();
        }

        $createdBy = auth()->id() ?: (User::orderBy('id')->value('id') ?: 1);
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

        $crmLeadResult = app(LeadDuplicateGuardService::class)->createOrAttachMetaLead($form, $mapped, (int) $createdBy);
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

    private function parseMetaCsv(string $path): array
    {
        $content = $this->normalizeCsvContent((string) file_get_contents($path));
        $delimiter = $this->detectCsvDelimiterFromContent($content);
        $handle = fopen('php://temp', 'r+');
        if (!$handle) {
            return [];
        }
        fwrite($handle, $content);
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return [];
        }

        $normalizedHeaders = array_map(fn ($header) => $this->normalizeCsvHeader((string) $header), $headers);
        $rows = [];
        $rowNumber = 1;

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            $assoc = [];
            foreach ($normalizedHeaders as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($data[$index] ?? ''));
            }

            $rows[] = $this->normalizeMetaCsvRow($assoc, $rowNumber);
        }

        fclose($handle);
        return $rows;
    }

    private function normalizeCsvContent(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = (string) @iconv('UTF-16BE', 'UTF-8//IGNORE', $content);
        } elseif (str_contains(substr($content, 0, 200), "\0")) {
            $content = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    }

    private function detectCsvDelimiterFromContent(string $content): string
    {
        $line = strtok($content, "\r\n") ?: '';
        $candidates = [
            "\t" => substr_count($line, "\t"),
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
        ];

        arsort($candidates);
        $delimiter = array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }

    private function normalizeCsvHeader(string $header): string
    {
        return Str::of($header)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    private function normalizeMetaCsvRow(array $row, int $rowNumber): array
    {
        return [
            'row_number' => $rowNumber,
            'leadgen_id' => $this->cleanMetaIdentifier($this->firstCsvValue($row, [
                'leadgen_id', 'lead_id', 'leadid', 'id', 'leadgenid', 'meta_lead_id',
                'facebook_lead_id', 'fb_lead_id',
            ])),
            'name' => $this->firstCsvValue($row, [
                'full_name', 'name', 'customer_name', 'lead_name', 'first_name',
            ]),
            'phone' => $this->cleanMetaIdentifier($this->firstCsvValue($row, [
                'phone_number', 'phone', 'mobile', 'mobile_number', 'whatsapp_number',
            ]), false),
            'email' => $this->firstCsvValue($row, [
                'email', 'email_id', 'email_address',
            ]),
            'form_id' => $this->cleanMetaIdentifier($this->firstCsvValue($row, [
                'form_id', 'lead_form_id', 'formid', 'facebook_form_id',
            ])),
            'form_name' => $this->firstCsvValue($row, [
                'form_name', 'form', 'lead_form', 'lead_form_name',
            ]),
            'created_time' => $this->firstCsvValue($row, [
                'created_time', 'created_at', 'submitted_at', 'submission_time', 'date', 'time',
            ]),
            'raw' => $row,
        ];
    }

    private function firstCsvValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeCsvHeader($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return null;
    }

    private function cleanMetaIdentifier(?string $value, bool $digitsOnly = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/^[a-z]+:/i', '', $value) ?? $value;
        $value = trim($value);

        return $digitsOnly ? (preg_replace('/\D+/', '', $value) ?: null) : $value;
    }

    private function buildMissingLeadReport(array $rows, ?FbForm $fallbackForm = null): array
    {
        $normalizedRows = collect($rows)->map(function (array $row, int $index) {
            return [
                'row_number' => $row['row_number'] ?? ($index + 2),
                'leadgen_id' => trim((string) ($row['leadgen_id'] ?? '')),
                'name' => $row['name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'form_id' => $row['form_id'] ?? null,
                'form_name' => $row['form_name'] ?? null,
                'created_time' => $row['created_time'] ?? null,
                'raw' => $row['raw'] ?? [],
            ];
        })->values();

        $leadgenIds = $normalizedRows->pluck('leadgen_id')->filter()->unique()->values();

        $fbLeads = $leadgenIds->isNotEmpty()
            ? FbLead::with(['crmLead', 'form'])->whereIn('leadgen_id', $leadgenIds)->get()->keyBy('leadgen_id')
            : collect();

        $webhookEvents = $leadgenIds->isNotEmpty()
            ? FbWebhookEvent::whereIn('leadgen_id', $leadgenIds)->orderByDesc('id')->get()->groupBy('leadgen_id')
            : collect();

        $formsByExternalId = $this->checkerForms()->keyBy('form_id');

        $reportRows = $normalizedRows->map(function (array $row) use ($fbLeads, $webhookEvents, $formsByExternalId, $fallbackForm) {
            if ($row['leadgen_id'] === '') {
                return $row + [
                    'status' => 'invalid',
                    'status_label' => 'Invalid CSV Row',
                    'status_class' => 'bg-slate-100 text-slate-700',
                    'reason' => 'No leadgen ID found in this CSV row.',
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
            $resolvedForm = $this->resolveCheckerForm($row['form_id'], $fallbackForm, $formsByExternalId);
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

    private function filterCheckerRowsByDate(array $rows, array $dateFilter): array
    {
        $from = !empty($dateFilter['from']) ? Carbon::parse($dateFilter['from'])->startOfDay() : null;
        $to = !empty($dateFilter['to']) ? Carbon::parse($dateFilter['to'])->endOfDay() : null;

        if (!$from && !$to) {
            return $rows;
        }

        return collect($rows)->filter(function (array $row) use ($from, $to) {
            $createdAt = $this->parseMetaCsvDate($row['created_time'] ?? null);
            if (!$createdAt) {
                return false;
            }

            if ($from && $createdAt->lt($from)) {
                return false;
            }

            if ($to && $createdAt->gt($to)) {
                return false;
            }

            return true;
        })->values()->all();
    }

    private function parseMetaCsvDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveCheckerForm(?string $externalFormId, ?FbForm $fallbackForm = null, ?Collection $formsByExternalId = null): ?FbForm
    {
        $externalFormId = trim((string) $externalFormId);
        if ($externalFormId !== '') {
            $formsByExternalId ??= $this->checkerForms()->keyBy('form_id');
            $form = $formsByExternalId->get($externalFormId);
            if ($form) {
                return $form;
            }
        }

        return $fallbackForm;
    }

    private function resolveDiagnosticsRange(Request $request): array
    {
        $preset = $request->input('range', 'today');
        $now = now();

        if ($preset === 'custom') {
            $from = $request->filled('date_from')
                ? Carbon::parse($request->input('date_from'))->startOfDay()
                : $now->copy()->startOfDay();
            $to = $request->filled('date_to')
                ? Carbon::parse($request->input('date_to'))->endOfDay()
                : $now->copy()->endOfDay();

            if ($to->lt($from)) {
                $to = $from->copy()->endOfDay();
            }

            return [
                'preset' => 'custom',
                'from' => $from,
                'to' => $to,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ];
        }

        return match ($preset) {
            '7d' => [
                'preset' => '7d',
                'from' => $now->copy()->subDays(6)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
                'date_from' => null,
                'date_to' => null,
            ],
            '30d' => [
                'preset' => '30d',
                'from' => $now->copy()->subDays(29)->startOfDay(),
                'to' => $now->copy()->endOfDay(),
                'date_from' => null,
                'date_to' => null,
            ],
            default => [
                'preset' => 'today',
                'from' => $now->copy()->startOfDay(),
                'to' => $now->copy()->endOfDay(),
                'date_from' => null,
                'date_to' => null,
            ],
        };
    }

    private function buildMetaFormsChecks(Collection $pages, FbLeadAdsSettings $settings): array
    {
        return $pages->map(function (FbPage $page) use ($settings) {
            try {
                $token = $page->page_access_token;

                if (empty($token)) {
                    return [
                        'page_id' => $page->page_id,
                        'page_name' => $page->page_name,
                        'success' => false,
                        'forms_count' => 0,
                        'error' => 'Page token missing.',
                    ];
                }

                $client = FacebookGraphService::fromToken($token, $settings->graph_version ?? 'v18.0');
                $result = $client->getLeadgenForms((string) $page->page_id);

                return [
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name,
                    'success' => (bool) ($result['success'] ?? false),
                    'forms_count' => count($result['forms'] ?? []),
                    'error' => $result['success'] ?? false ? null : ($result['error'] ?? 'Failed to fetch forms.'),
                ];
            } catch (Throwable $e) {
                return [
                    'page_id' => $page->page_id,
                    'page_name' => $page->page_name,
                    'success' => false,
                    'forms_count' => 0,
                    'error' => $e->getMessage(),
                ];
            }
        })->values()->all();
    }

    private function buildDiagnosticsFormRows(Collection $forms, Collection $webhookEvents, Collection $fbLeads): Collection
    {
        $eventsByFormId = $webhookEvents->groupBy(fn (FbWebhookEvent $event) => (string) ($this->extractWebhookFormId($event) ?? ''));
        $leadsByFormId = $fbLeads->groupBy(fn (FbLead $lead) => (int) $lead->fb_form_id);

        return $forms->map(function (FbForm $form) use ($eventsByFormId, $leadsByFormId) {
            $events = $eventsByFormId->get((string) $form->form_id, collect());
            $leads = $leadsByFormId->get((int) $form->id, collect());
            $latestError = $events
                ->filter(fn (FbWebhookEvent $event) => $event->status === 'failed' && filled($event->error))
                ->sortByDesc('created_at')
                ->first();

            return [
                'form' => $form,
                'webhooks_received' => $events->count(),
                'crm_leads_created' => $leads->whereNotNull('crm_lead_id')->count(),
                'fb_leads_stored' => $leads->count(),
                'fallback_leads' => $leads->filter(fn (FbLead $lead) => data_get($lead->raw_response_json, '_crm_import_mode') === 'fallback_mapping')->count(),
                'failed' => $events->where('status', 'failed')->count(),
                'last_received_at' => optional($events->sortByDesc('created_at')->first())->created_at,
                'latest_error' => $latestError?->error,
                'mapping_ready' => $form->mapping !== null,
            ];
        })->sortByDesc(fn ($row) => $row['webhooks_received'])->values();
    }

    private function buildDiagnosticsFailedEvents(Collection $webhookEvents): Collection
    {
        return $webhookEvents
            ->where('status', 'failed')
            ->sortByDesc('created_at')
            ->take(30)
            ->map(fn (FbWebhookEvent $event) => [
                'event' => $event,
                'form_id' => $this->extractWebhookFormId($event),
                'page_id' => data_get($event->raw_payload, 'entry.0.changes.0.value.page_id') ?: data_get($event->raw_payload, 'entry.0.id'),
                'retryable' => $this->isRetryableFailedWebhookEvent($event),
            ])
            ->values();
    }

    private function buildFacebookLeadAdsHealthSnapshot(
        ?int $failedCount = null,
        ?int $retryableFailedCount = null,
        ?int $suspectedMissingCount = null,
        ?array $metaChecks = null
    ): array {
        $connectedPages = FbPage::whereNotNull('page_access_token')->count();
        $enabledForms = FbForm::where('is_enabled', true)->count();
        $mappedForms = FbForm::where('is_enabled', true)->whereHas('mapping')->count();
        $unmappedEnabledForms = max(0, $enabledForms - $mappedForms);
        $failedCount ??= FbWebhookEvent::where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();
        $retryableFailedCount ??= $this->countRetryableFailedWebhookEvents();
        $suspectedMissingCount ??= 0;
        $lastWebhook = FbWebhookEvent::latest('created_at')->first();
        $metaChecksFailed = is_array($metaChecks) && collect($metaChecks)->contains(fn ($check) => !($check['success'] ?? false));

        $status = 'healthy';
        $label = 'Healthy';
        $tone = 'green';
        $reasons = [];

        if ($connectedPages === 0) {
            $status = 'broken';
            $label = 'Broken';
            $tone = 'red';
            $reasons[] = 'No connected Facebook page token.';
        }

        if ($metaChecksFailed) {
            $status = 'broken';
            $label = 'Broken';
            $tone = 'red';
            $reasons[] = 'Meta API forms fetch failed.';
        }

        if ($enabledForms === 0) {
            $status = $status === 'broken' ? $status : 'warning';
            $label = $status === 'broken' ? $label : 'Warning';
            $tone = $status === 'broken' ? $tone : 'amber';
            $reasons[] = 'No enabled forms.';
        }

        if ($status !== 'broken' && ($failedCount > 0 || $retryableFailedCount > 0 || $suspectedMissingCount > 0 || $unmappedEnabledForms > 0)) {
            $status = 'warning';
            $label = 'Warning';
            $tone = 'amber';
        }

        if ($failedCount > 0) {
            $reasons[] = "{$failedCount} failed webhook event(s).";
        }

        if ($retryableFailedCount > 0) {
            $reasons[] = "{$retryableFailedCount} retryable failed webhook(s).";
        }

        if ($suspectedMissingCount > 0) {
            $reasons[] = "{$suspectedMissingCount} suspected missing CRM handoff(s).";
        }

        if ($unmappedEnabledForms > 0) {
            $reasons[] = "{$unmappedEnabledForms} enabled form(s) need mapping.";
        }

        if (!$lastWebhook) {
            $reasons[] = 'No webhook received yet.';
        }

        return [
            'status' => $status,
            'label' => $label,
            'tone' => $tone,
            'reasons' => $reasons ?: ['No current issue found.'],
            'connected_pages' => $connectedPages,
            'enabled_forms' => $enabledForms,
            'mapped_enabled_forms' => $mappedForms,
            'last_webhook_at' => $lastWebhook?->created_at,
        ];
    }

    private function retryableFailedWebhookEventsQuery()
    {
        return FbWebhookEvent::query()
            ->where('status', 'failed')
            ->whereNotNull('leadgen_id')
            ->whereRaw("leadgen_id REGEXP '^[0-9]+$'")
            ->where(function ($query) {
                $query->whereNull('error')
                    ->orWhere('error', 'like', 'Form not found:%')
                    ->orWhere('error', 'like', 'Configured form/page missing%')
                    ->orWhere('error', 'like', 'No token for page%');
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('fb_leads')
                    ->whereColumn('fb_leads.leadgen_id', 'fb_webhook_events.leadgen_id');
            });
    }

    private function isRetryableFailedWebhookEvent(FbWebhookEvent $event): bool
    {
        if ($event->status !== 'failed' || empty($event->leadgen_id) || !preg_match('/^[0-9]+$/', (string) $event->leadgen_id)) {
            return false;
        }

        if (FbLead::where('leadgen_id', $event->leadgen_id)->exists()) {
            return false;
        }

        $error = (string) $event->error;

        return $error === ''
            || str_starts_with($error, 'Form not found:')
            || str_starts_with($error, 'Configured form/page missing')
            || str_starts_with($error, 'No token for page');
    }

    private function resolveFallbackFormForEvent(FbWebhookEvent $event, ?string $formId): ?FbForm
    {
        if (!$formId) {
            return null;
        }

        $pageId = data_get($event->raw_payload, 'entry.0.changes.0.value.page_id') ?: data_get($event->raw_payload, 'entry.0.id');
        if (!$pageId) {
            return null;
        }

        $page = FbPage::where('page_id', $pageId)->first();
        if (!$page) {
            return null;
        }

        $formName = 'Meta Form ' . $formId;

        try {
            $token = $page->page_access_token;
            if (!$token) {
                return null;
            }

            $settings = FbLeadAdsSettings::getSettings();
            $client = FacebookGraphService::fromToken($token, $settings->graph_version ?? 'v18.0');
            $metaForm = $client->getForm($formId);

            if (($metaForm['success'] ?? false) && !empty($metaForm['form']['name'])) {
                $formName = $metaForm['form']['name'];
            }
        } catch (Throwable $e) {
            Log::warning('Meta retry fallback form name fetch failed', [
                'event_id' => $event->id,
                'form_id' => $formId,
                'error' => $e->getMessage(),
            ]);
        }

        return FbForm::firstOrCreate(
            ['form_id' => $formId],
            [
                'fb_page_id' => $page->id,
                'form_name' => $formName,
                'is_enabled' => true,
            ]
        );
    }

    private function countRetryableFailedWebhookEvents(): int
    {
        $enabledFormIds = FbForm::where('is_enabled', true)->pluck('form_id')->map(fn ($id) => (string) $id)->flip();

        if ($enabledFormIds->isEmpty()) {
            return 0;
        }

        return $this->retryableFailedWebhookEventsQuery()
            ->orderByDesc('created_at')
            ->get()
            ->unique('leadgen_id')
            ->filter(fn (FbWebhookEvent $event) => $enabledFormIds->has((string) $this->extractWebhookFormId($event)))
            ->count();
    }

    private function buildPageDetailPayload(FbPage $page): array
    {
        $page->load(['forms' => fn ($query) => $query->with(['mapping'])->orderBy('form_name')]);

        $settings = FbLeadAdsSettings::getSettings();
        $tokenCheck = collect($this->buildMetaFormsChecks(collect([$page]), $settings))->first() ?? [];
        $tokenOk = (bool) ($tokenCheck['success'] ?? false);
        $forms = $page->forms;
        $formIds = $forms->pluck('id');
        $externalFormIds = $forms->pluck('form_id')->map(fn ($id) => (string) $id)->filter()->flip();
        $lastLeadByForm = FbLead::query()
            ->select('fb_form_id', DB::raw('MAX(created_at) as last_lead_at'))
            ->whereIn('fb_form_id', $formIds)
            ->groupBy('fb_form_id')
            ->pluck('last_lead_at', 'fb_form_id');
        $recentLeads = FbLead::with(['form'])
            ->whereIn('fb_form_id', $formIds)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
        $webhookEvents = FbWebhookEvent::query()
            ->orderByDesc('created_at')
            ->limit(120)
            ->get()
            ->filter(fn (FbWebhookEvent $event) => $externalFormIds->has((string) ($this->extractWebhookFormId($event) ?? '')))
            ->take(20)
            ->values();
        $pageLastLeadAt = $lastLeadByForm->filter()->sortDesc()->first();
        $enabledCount = $forms->where('is_enabled', true)->count();
        $mappedCount = $forms->filter(fn (FbForm $form) => $form->mapping !== null)->count();

        return [
            'page' => [
                'id' => $page->id,
                'page_id' => $page->page_id,
                'page_name' => $page->page_name ?: $page->page_id,
                'token_ok' => $tokenOk,
                'token_label' => $tokenOk ? 'OK' : 'Expired',
                'token_error' => $tokenCheck['error'] ?? null,
                'updated_at' => $this->formatDateTime($page->updated_at),
            ],
            'stats' => [
                'forms' => $forms->count(),
                'active' => $enabledCount,
                'mapped' => $mappedCount,
                'unmapped' => max(0, $forms->count() - $mappedCount),
                'disabled' => max(0, $forms->count() - $enabledCount),
                'last_lead_at' => $pageLastLeadAt ? $this->formatDateTime(Carbon::parse($pageLastLeadAt)) : null,
                'webhook_processed' => $webhookEvents->where('status', 'processed')->count(),
                'webhook_failed' => $webhookEvents->where('status', 'failed')->count(),
                'webhook_pending' => $webhookEvents->where('status', 'received')->count(),
            ],
            'webhook' => [
                'url' => url('/api/webhooks/facebook/leads'),
                'health' => $tokenOk ? ($webhookEvents->where('status', 'failed')->isNotEmpty() ? 'Warning' : 'Ready') : 'Token Issue',
            ],
            'callback' => [
                'enabled' => $tokenOk && $enabledCount > 0,
                'default_days' => 7,
                'limit' => 100,
                'url' => route('integrations.facebook-lead-ads.pages.callback', $page),
                'disabled_reason' => !$tokenOk
                    ? 'Page token expired or missing.'
                    : ($enabledCount === 0 ? 'No enabled forms on this page.' : null),
            ],
            'forms' => $forms->map(fn (FbForm $form) => $this->buildPageDetailFormPayload($page, $form, $lastLeadByForm[$form->id] ?? null))->values(),
            'recent_leads' => $recentLeads->map(fn (FbLead $lead) => $this->buildPageDetailLeadPayload($lead))->values(),
            'webhook_events' => $webhookEvents->map(fn (FbWebhookEvent $event) => $this->buildPageDetailWebhookPayload($event))->values(),
        ];
    }

    private function buildPageDetailFormPayload(FbPage $page, FbForm $form, ?string $lastLeadAt): array
    {
        $mapping = $form->mapping?->mapping_json ?? [];

        return [
            'id' => $form->id,
            'form_id' => $form->form_id,
            'form_name' => $form->form_name ?: $form->form_id,
            'is_enabled' => (bool) $form->is_enabled,
            'mapping_ready' => $form->mapping !== null,
            'last_lead_at' => $lastLeadAt ? $this->formatDateTime(Carbon::parse($lastLeadAt)) : null,
            'mapping_url' => route('integrations.facebook-lead-ads.mapping', [
                'formId' => $form->form_id,
                'form_name' => $form->form_name,
                'page_id' => $page->page_id,
            ]),
            'mapping' => collect($mapping)->map(fn ($crmField, $fbField) => [
                'fb_field' => $fbField,
                'crm_field' => $crmField ?: 'Unmapped',
            ])->values(),
        ];
    }

    private function facebookAutomationRules(): Collection
    {
        $with = ['fbForm'];
        if (Schema::hasTable('source_automation_rule_forms')) {
            $with[] = 'fbForms';
        }

        return SourceAutomationRule::query()
            ->with($with)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('source', 'facebook_lead_ads')
                    ->orWhere('source_type', 'facebook_lead_ads');
            })
            ->orderBy('name')
            ->get();
    }

    private function automationForForm(FbForm $form, Collection $rules): ?SourceAutomationRule
    {
        return $rules->first(function (SourceAutomationRule $rule) use ($form) {
            return (int) $rule->fb_form_id === (int) $form->id
                || ($rule->source_type === 'facebook_lead_ads' && (int) $rule->source_id === (int) $form->id)
                || ($rule->relationLoaded('fbForms') && $rule->fbForms->contains('id', $form->id));
        });
    }

    private function attachFormToAutomation(FbForm $form, SourceAutomationRule $targetRule): void
    {
        abort_unless(Schema::hasTable('source_automation_rule_forms'), 422, 'Multi-form automation table is not available.');

        $otherRules = SourceAutomationRule::query()
            ->where('is_active', true)
            ->whereKeyNot($targetRule->id)
            ->where(function ($query) use ($form) {
                $query->where('fb_form_id', $form->id)
                    ->orWhere(function ($inner) use ($form) {
                        $inner->where('source_type', 'facebook_lead_ads')
                            ->where('source_id', (string) $form->id);
                    })
                    ->orWhereHas('fbForms', fn ($forms) => $forms->where('fb_forms.id', $form->id));
            })
            ->get();

        foreach ($otherRules as $otherRule) {
            $otherRule->fbForms()->detach($form->id);
            $replacement = $otherRule->fbForms()->orderBy('fb_forms.id')->first();
            $directlyOwned = (int) $otherRule->fb_form_id === (int) $form->id
                || ($otherRule->source_type === 'facebook_lead_ads' && (int) $otherRule->source_id === (int) $form->id);

            if ($directlyOwned && $replacement) {
                $otherRule->update([
                    'fb_form_id' => $replacement->id,
                    'source_id' => (string) $replacement->id,
                ]);
            } elseif ($directlyOwned || (!$replacement && !$otherRule->fb_form_id && blank($otherRule->source_id))) {
                $otherRule->update(['is_active' => false]);
            }
        }

        $targetRule->fbForms()->syncWithoutDetaching([$form->id]);
        if (!$targetRule->fb_form_id) {
            $targetRule->update([
                'fb_form_id' => $form->id,
                'source_id' => (string) $form->id,
            ]);
        }
    }

    private function activeAutomationFormIds(Collection $formIds): Collection
    {
        $ids = $formIds->map(fn ($id) => (string) $id)->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = SourceAutomationRule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($ids) {
                $query->where(function ($inner) use ($ids) {
                    $inner->where('source_type', 'facebook_lead_ads')
                        ->whereIn('source_id', $ids);
                })->orWhere(function ($inner) use ($ids) {
                    $inner->where('source', 'facebook_lead_ads')
                        ->whereIn('fb_form_id', $ids);
                });

                if (Schema::hasTable('source_automation_rule_forms')) {
                    $query->orWhereHas('fbForms', fn ($forms) => $forms->whereIn('fb_forms.id', $ids));
                }
            });

        if (Schema::hasTable('source_automation_rule_forms')) {
            $query->with('fbForms');
        }

        return $query
            ->get()
            ->flatMap(fn (SourceAutomationRule $rule) => [
                $rule->effectiveSourceId(),
                $rule->fb_form_id ? (string) $rule->fb_form_id : null,
                ...($rule->relationLoaded('fbForms') ? $rule->fbForms->pluck('id')->map(fn ($id) => (string) $id)->all() : []),
            ])
            ->filter()
            ->unique()
            ->values()
            ->flip();
    }

    private function bulkRecoveryRouteName(string $name): string
    {
        $prefix = request()->routeIs('ad-manager.meta.facebook-lead-ads.*')
            ? 'ad-manager.meta.facebook-lead-ads.bulk-recovery.'
            : 'integrations.facebook-lead-ads.bulk-recovery.';

        return $prefix . $name;
    }

    private function loadBulkRecoveryScan(MetaBulkRecoveryScan $scan): MetaBulkRecoveryScan
    {
        return $this->refreshBulkRecoveryImportStatus($scan)->load([
            'starter',
            'forms.page.portfolio',
            'forms.form',
            'forms.leads' => fn ($query) => $query
                ->orderByRaw("CASE status WHEN 'ready' THEN 1 WHEN 'selected' THEN 2 WHEN 'queued' THEN 3 WHEN 'failed' THEN 4 WHEN 'imported' THEN 5 WHEN 'already_present' THEN 6 ELSE 7 END")
                ->orderByDesc('meta_created_time')
                ->orderByDesc('id'),
        ]);
    }

    private function refreshBulkRecoveryImportStatus(MetaBulkRecoveryScan $scan): MetaBulkRecoveryScan
    {
        if ($scan->status === 'importing' && $scan->leads()->where('status', 'queued')->count() === 0) {
            $scan->update([
                'status' => 'completed',
                'finished_at' => now(),
            ]);
        }

        $this->recountBulkRecoveryScan($scan);

        return $scan->fresh();
    }

    private function recountBulkRecoveryScan(MetaBulkRecoveryScan $scan): void
    {
        $scan->update([
            'forms_scanned' => $scan->forms()->whereIn('status', ['completed', 'failed'])->count(),
            'fetched_count' => $scan->forms()->sum('fetched_count'),
            'importable_count' => $scan->leads()->where('status', 'ready')->count(),
            'already_present_count' => $scan->leads()->where('status', 'already_present')->count(),
            'failed_count' => $scan->forms()->where('status', 'failed')->count() + $scan->leads()->where('status', 'failed')->count(),
        ]);
    }

    private function buildPageDetailLeadPayload(FbLead $lead): array
    {
        $data = $lead->field_data_json ?? [];

        return [
            'id' => $lead->id,
            'leadgen_id' => $lead->leadgen_id,
            'name' => $data['name'] ?? $data['full_name'] ?? '-',
            'phone' => $data['phone'] ?? $data['phone_number'] ?? '-',
            'email' => $data['email'] ?? '-',
            'form_name' => $lead->form?->form_name ?: '-',
            'crm_lead_id' => $lead->crm_lead_id,
            'crm_url' => $lead->crm_lead_id ? route('leads.show', $lead->crm_lead_id) : null,
            'campaign_name' => $lead->campaign_name,
            'ad_name' => $lead->ad_name,
            'platform' => $lead->platform,
            'created_at' => $this->formatDateTime($lead->created_at),
            'meta_created_time' => $lead->meta_created_time ? $this->formatDateTime($lead->meta_created_time) : null,
            'raw' => $lead->raw_response_json,
        ];
    }

    private function buildPageDetailWebhookPayload(FbWebhookEvent $event): array
    {
        return [
            'id' => $event->id,
            'leadgen_id' => $event->leadgen_id,
            'form_id' => $this->extractWebhookFormId($event),
            'status' => $event->status,
            'error' => $event->error,
            'created_at' => $this->formatDateTime($event->created_at),
            'raw' => $event->raw_payload,
        ];
    }

    private function formatDateTime(?Carbon $date): ?string
    {
        return $date?->format('d M Y, h:i A');
    }

    private function extractWebhookFormId(FbWebhookEvent $event): ?string
    {
        $payload = $event->raw_payload;
        $formId = data_get($payload, 'entry.0.changes.0.value.form_id')
            ?: data_get($payload, 'form_id')
            ?: data_get($payload, 'csv_row.form_id');

        if (!$formId && is_string($event->error) && preg_match('/Form not found:\s*(\d+)/', $event->error, $matches)) {
            $formId = $matches[1];
        }

        return $formId ? (string) $formId : null;
    }

    private function buildTraceReport(string $rawQuery): array
    {
        $normalized = preg_replace('/\D+/', '', $rawQuery);
        $query = trim($rawQuery);

        $leads = DB::table('leads')
            ->select(['id', 'name', 'phone', 'email', 'source', 'status', 'created_by', 'created_at', 'notes'])
            ->where(function ($builder) use ($normalized, $query) {
                if ($normalized !== '') {
                    $builder->whereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$normalized]);
                }

                if (ctype_digit($query)) {
                    $builder->orWhere('id', (int) $query);
                }
            })
            ->limit(5)
            ->get();

        $leadIds = $leads->pluck('id')->filter()->values();

        $fbLeads = DB::table('fb_leads')
            ->where(function ($builder) use ($leadIds, $query, $normalized) {
                if ($leadIds->isNotEmpty()) {
                    $builder->whereIn('crm_lead_id', $leadIds);
                }

                if ($query !== '') {
                    $builder->orWhere('leadgen_id', $query);
                }

                if ($normalized !== '') {
                    $builder
                        ->orWhere('field_data_json', 'like', '%' . $normalized . '%')
                        ->orWhere('raw_response_json', 'like', '%' . $normalized . '%');
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $leadgenIds = $fbLeads->pluck('leadgen_id')
            ->push($query)
            ->filter()
            ->unique()
            ->values();

        $fbWebhookEvents = DB::table('fb_webhook_events')
            ->where(function ($builder) use ($leadgenIds, $normalized) {
                if ($leadgenIds->isNotEmpty()) {
                    $builder->whereIn('leadgen_id', $leadgenIds);
                }

                if ($normalized !== '') {
                    $builder->orWhere('raw_payload', 'like', '%' . $normalized . '%');
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $importedLeads = DB::table('imported_leads')
            ->where(function ($builder) use ($leadIds, $normalized) {
                if ($leadIds->isNotEmpty()) {
                    $builder->whereIn('lead_id', $leadIds);
                }

                if ($normalized !== '') {
                    $builder->orWhere('import_data', 'like', '%' . $normalized . '%');
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $leadAssignments = $leadIds->isNotEmpty()
            ? DB::table('lead_assignments')->whereIn('lead_id', $leadIds)->orderByDesc('id')->get()
            : collect();

        $crmAssignments = DB::table('crm_assignments')
            ->where(function ($builder) use ($leadIds, $normalized) {
                if ($leadIds->isNotEmpty()) {
                    $builder->whereIn('lead_id', $leadIds);
                }

                if ($normalized !== '') {
                    $builder->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', '') = ?", [$normalized]);
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $userIds = collect([
            ...$leads->pluck('created_by')->filter()->all(),
            ...$leadAssignments->pluck('assigned_to')->filter()->all(),
            ...$leadAssignments->pluck('assigned_by')->filter()->all(),
            ...$crmAssignments->pluck('assigned_to')->filter()->all(),
            ...$crmAssignments->pluck('assigned_by')->filter()->all(),
        ])->unique()->values();

        $users = $userIds->isNotEmpty()
            ? DB::table('users')->whereIn('id', $userIds)->get(['id', 'name', 'email', 'role_id'])->keyBy('id')
            : collect();

        $roleMap = $users->isNotEmpty()
            ? DB::table('roles')->whereIn('id', $users->pluck('role_id')->filter()->unique())->pluck('name', 'id')
            : collect();

        return [
            'query' => $rawQuery,
            'normalized' => $normalized,
            'leads' => $leads,
            'fb_leads' => $fbLeads,
            'fb_webhook_events' => $fbWebhookEvents,
            'imported_leads' => $importedLeads,
            'lead_assignments' => $leadAssignments,
            'crm_assignments' => $crmAssignments,
            'users' => $users,
            'role_map' => $roleMap,
            'summary' => $this->buildTraceSummary($leads, $fbWebhookEvents, $fbLeads, $importedLeads, $leadAssignments, $crmAssignments),
            'timeline' => $this->buildTraceTimeline($leads, $fbWebhookEvents, $fbLeads, $importedLeads, $leadAssignments, $crmAssignments),
        ];
    }

    private function buildTraceSummary(
        Collection $leads,
        Collection $fbWebhookEvents,
        Collection $fbLeads,
        Collection $importedLeads,
        Collection $leadAssignments,
        Collection $crmAssignments
    ): array {
        if ($leads->isNotEmpty() && $fbWebhookEvents->isEmpty() && $fbLeads->isEmpty() && $importedLeads->isEmpty()) {
            return [
                'source_guess' => 'Manual / internal creation likely',
                'root_cause' => 'Lead exists in CRM, but no Meta webhook, fb_leads, or import trail was found.',
            ];
        }

        if ($fbWebhookEvents->isNotEmpty() && $fbLeads->isEmpty()) {
            return [
                'source_guess' => 'Meta webhook reached CRM',
                'root_cause' => 'Webhook event exists, but fb_leads row is missing. Fetch/store likely failed after receipt.',
            ];
        }

        if ($fbLeads->isNotEmpty() && $leads->isEmpty()) {
            return [
                'source_guess' => 'Meta lead fetched',
                'root_cause' => 'fb_leads row exists, but linked CRM lead is missing.',
            ];
        }

        if ($leads->isNotEmpty() && $leadAssignments->isNotEmpty() && $crmAssignments->isEmpty()) {
            return [
                'source_guess' => 'Lead created in CRM',
                'root_cause' => 'Lead exists and has lead assignment, but crm_assignments row is missing. Some CRM views may not show it.',
            ];
        }

        if ($importedLeads->isNotEmpty()) {
            return [
                'source_guess' => 'Import flow',
                'root_cause' => 'Import trace found. Check import batch and assignment handoff.',
            ];
        }

        if ($fbWebhookEvents->isEmpty() && $fbLeads->isEmpty()) {
            return [
                'source_guess' => 'No Meta evidence found',
                'root_cause' => 'No Facebook webhook or fb_leads record matched this query.',
            ];
        }

        return [
            'source_guess' => 'Meta webhook path',
            'root_cause' => 'Meta webhook and CRM traces are both present.',
        ];
    }

    private function buildTraceTimeline(
        Collection $leads,
        Collection $fbWebhookEvents,
        Collection $fbLeads,
        Collection $importedLeads,
        Collection $leadAssignments,
        Collection $crmAssignments
    ): array {
        return [
            [
                'label' => 'Webhook Received',
                'status' => $fbWebhookEvents->isNotEmpty() ? 'yes' : 'no',
                'detail' => $fbWebhookEvents->isNotEmpty() ? 'Webhook events found.' : 'No webhook event found.',
            ],
            [
                'label' => 'FB Lead Stored',
                'status' => $fbLeads->isNotEmpty() ? 'yes' : 'no',
                'detail' => $fbLeads->isNotEmpty() ? 'fb_leads row found.' : 'No fb_leads row found.',
            ],
            [
                'label' => 'CRM Lead Created',
                'status' => $leads->isNotEmpty() ? 'yes' : 'no',
                'detail' => $leads->isNotEmpty() ? 'Lead row found in CRM.' : 'No CRM lead row found.',
            ],
            [
                'label' => 'Lead Assigned',
                'status' => $leadAssignments->isNotEmpty() ? 'yes' : 'no',
                'detail' => $leadAssignments->isNotEmpty() ? 'lead_assignments row found.' : 'No lead assignment found.',
            ],
            [
                'label' => 'CRM Queue Created',
                'status' => $crmAssignments->isNotEmpty() ? 'yes' : 'no',
                'detail' => $crmAssignments->isNotEmpty() ? 'crm_assignments row found.' : 'No crm_assignments row found.',
            ],
            [
                'label' => 'Import Trace',
                'status' => $importedLeads->isNotEmpty() ? 'yes' : 'no',
                'detail' => $importedLeads->isNotEmpty() ? 'Import trace found.' : 'No import trace found.',
            ],
        ];
    }
}
