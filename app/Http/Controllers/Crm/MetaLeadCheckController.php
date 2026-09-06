<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\FacebookLeadCenterAudit;
use App\Models\FacebookLeadCenterAuditRow;
use App\Models\FbForm;
use App\Services\FacebookLeadCenterAuditService;
use App\Services\MetaLeadCheckImportParser;
use App\Services\MetaMissingLeadRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MetaLeadCheckController extends Controller
{
    public function __construct(
        private readonly FacebookLeadCenterAuditService $auditService,
        private readonly MetaLeadCheckImportParser $parser,
        private readonly MetaMissingLeadRecoveryService $recoveryService,
    ) {
    }

    public function index(Request $request)
    {
        $auditId = session('crm_meta_lead_check_audit_id');
        $latestAudit = $auditId
            ? FacebookLeadCenterAudit::with('user')->find($auditId)
            : null;

        $latestAudit ??= FacebookLeadCenterAudit::with('user')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->first();

        $rows = $latestAudit
            ? FacebookLeadCenterAuditRow::with('crmLead')
                ->where('facebook_lead_center_audit_id', $latestAudit->id)
                ->latest('id')
                ->paginate(50)
                ->withQueryString()
            : null;

        $recentAudits = FacebookLeadCenterAudit::with('user')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->limit(6)
            ->get();

        $metaForms = FbForm::query()
            ->with('page:id,page_name,page_id')
            ->where('is_enabled', true)
            ->orderBy('form_name')
            ->get(['id', 'fb_page_id', 'form_id', 'form_name']);

        return view('crm.meta-lead-check.index', [
            'latestAudit' => $latestAudit,
            'rows' => $rows,
            'recentAudits' => $recentAudits,
            'metaForms' => $metaForms,
            'recoveryReport' => session('crm_meta_missing_checker_report'),
            'recoveryRows' => session('crm_meta_missing_checker_rows', []),
            'assignableUsers' => $this->recoveryService->assignableUsers(),
            'canUseAdvancedRecovery' => $this->recoveryService->canUseAdvancedRecovery($request->user()),
            'importResults' => session('crm_meta_lead_check_import_results'),
            'activeTab' => $request->query('tab', $latestAudit ? 'missing' : 'upload'),
            'stats' => $this->auditStats($latestAudit),
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'lead_file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx,xls'],
            'fb_form_id' => [
                'required',
                'integer',
                Rule::exists('fb_forms', 'id')->where(fn ($query) => $query->where('is_enabled', true)),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $selectedForm = FbForm::query()
            ->with('page:id,page_name,page_id')
            ->findOrFail($validated['fb_form_id']);
        $file = $validated['lead_file'];
        $rows = $this->parser->parse($file);
        $dateFilteredRows = $this->parser->filterByDate(
            $rows,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null
        );
        $selectedExternalFormId = trim((string) $selectedForm->form_id);
        $selectedPageId = trim((string) ($selectedForm->page?->page_id ?? ''));
        $filteredRows = collect($dateFilteredRows)
            ->filter(function (array $row) use ($selectedExternalFormId) {
                $rowFormId = trim((string) ($row['form_id'] ?? ''));

                return $rowFormId === '' || $rowFormId === $selectedExternalFormId;
            })
            ->map(function (array $row) use ($selectedExternalFormId, $selectedPageId) {
                $row['form_id'] = $selectedExternalFormId;
                if (trim((string) ($row['page_id'] ?? '')) === '' && $selectedPageId !== '') {
                    $row['page_id'] = $selectedPageId;
                }

                return $row;
            })
            ->values()
            ->all();

        if (empty($filteredRows)) {
            return redirect()->route('crm.meta-lead-check.index', ['tab' => 'upload'])
                ->withInput()
                ->with('error', 'Selected Meta Form se matching readable leads nahi mile. File, form aur date filter check karo.');
        }

        $result = $this->auditService->compareAndStore($request->user(), [
            'source_url' => $file->getClientOriginalName(),
            'page_title' => 'CRM Meta lead file check - ' . ($selectedForm->form_name ?: $selectedForm->form_id),
            'leads' => $filteredRows,
            'browser_meta' => [
                'extension_version' => 'crm-upload',
                'scan_mode' => 'excel_csv_upload',
                'expected_rows' => count($rows),
                'scanned_rows' => count($filteredRows),
                'scan_complete' => true,
                'selected_form_id' => $selectedForm->id,
                'selected_external_form_id' => $selectedForm->form_id,
                'selected_form_name' => $selectedForm->form_name,
                'selected_page_id' => $selectedForm->page?->page_id,
                'selected_page_name' => $selectedForm->page?->page_name,
                'date_filter' => [
                    'from' => $validated['date_from'] ?? null,
                    'to' => $validated['date_to'] ?? null,
                ],
            ],
        ]);

        session([
            'crm_meta_lead_check_audit_id' => $result['audit_id'],
            'crm_meta_missing_checker_rows' => $filteredRows,
        ]);

        $recoveryReport = $this->recoveryService->buildReport($filteredRows, $selectedForm);
        $recoveryReport['date_filter'] = [
            'from' => $validated['date_from'] ?? null,
            'to' => $validated['date_to'] ?? null,
        ];
        $recoveryReport['csv_total_rows'] = count($rows);
        session(['crm_meta_missing_checker_report' => $recoveryReport]);
        session()->forget('crm_meta_lead_check_import_results');

        return redirect()->route('crm.meta-lead-check.index', ['tab' => 'missing'])
            ->with('success', 'Meta file checked successfully.');
    }

    public function import(Request $request)
    {
        if ($request->has('import_action')) {
            return $this->importRecoveryRows($request);
        }

        $validated = $request->validate([
            'row_ids' => ['required', 'array', 'min:1'],
            'row_ids.*' => ['integer', 'exists:facebook_lead_center_audit_rows,id'],
            'assign_existing_rule' => ['nullable', 'boolean'],
        ]);

        $result = $this->auditService->importSelected($request->user(), $validated['row_ids'], [
            'assign_existing_rule' => $request->boolean('assign_existing_rule'),
        ]);
        session(['crm_meta_lead_check_import_results' => $result]);

        return redirect()->route('crm.meta-lead-check.index', ['tab' => 'missing'])
            ->with('success', "Import completed. Imported {$result['imported_count']}, failed {$result['failed_count']}.");
    }

    private function importRecoveryRows(Request $request)
    {
        $canUseAdvancedRecovery = $this->recoveryService->canUseAdvancedRecovery($request->user());

        $validated = $request->validate([
            'fallback_form_id' => ['nullable', 'exists:fb_forms,id'],
            'import_action' => ['required', 'in:import,import_assign'],
            'assignment_method' => ['nullable', 'in:existing_rule,single_user,round_robin,first_available,percentage'],
            'assignee_user_id' => ['nullable', 'exists:users,id'],
            'assignment_user_ids' => ['nullable', 'array'],
            'assignment_user_ids.*' => ['exists:users,id'],
            'user_percentages' => ['nullable', 'array'],
            'user_percentages.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'create_calling_task' => ['nullable', 'boolean'],
            'reassign_existing' => ['nullable', 'boolean'],
            'batch_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $reportRows = collect(session('crm_meta_missing_checker_report.rows', []));
        if ($reportRows->isEmpty()) {
            return redirect()->route('crm.meta-lead-check.index', ['tab' => 'upload'])
                ->with('error', 'No checked rows found. Upload file first.');
        }

        $fallbackForm = !empty($validated['fallback_form_id'])
            ? FbForm::with('page')->where('is_enabled', true)->find((int) $validated['fallback_form_id'])
            : null;

        $advancedMethod = $canUseAdvancedRecovery
            ? ($request->input('assignment_method', 'existing_rule') ?: 'existing_rule')
            : 'existing_rule';

        $percentageMap = $canUseAdvancedRecovery
            ? collect($request->input('user_percentages', []))->mapWithKeys(fn ($value, $id) => [(int) $id => (float) $value])->all()
            : [];
        $assignmentUserIds = $canUseAdvancedRecovery
            ? collect($request->input('assignment_user_ids', []))->map(fn ($id) => (int) $id)->filter()->unique()->values()->all()
            : [];
        if ($canUseAdvancedRecovery && $advancedMethod === 'percentage' && empty($assignmentUserIds)) {
            $assignmentUserIds = collect($percentageMap)->filter(fn ($value) => $value > 0)->keys()->map(fn ($id) => (int) $id)->values()->all();
        }

        $assignmentSettings = [
            'enabled' => $request->input('import_action') === 'import_assign',
            'method' => $advancedMethod,
            'single_user_id' => $canUseAdvancedRecovery ? ($request->integer('assignee_user_id') ?: null) : null,
            'user_ids' => $assignmentUserIds,
            'percentages' => $percentageMap,
            'create_calling_task' => $canUseAdvancedRecovery ? $request->boolean('create_calling_task', true) : true,
            'reassign_existing' => $canUseAdvancedRecovery ? $request->boolean('reassign_existing', false) : false,
            'assigned_by' => $request->user()->id,
        ];

        $importResult = $this->recoveryService->importRows(
            $reportRows,
            $fallbackForm,
            $assignmentSettings,
            min(max((int) $request->input('batch_limit', 20), 1), 50)
        );

        $updatedReport = $this->recoveryService->buildReport($reportRows->all(), $fallbackForm);
        $existingReport = session('crm_meta_missing_checker_report', []);
        $updatedReport['date_filter'] = $existingReport['date_filter'] ?? ['from' => null, 'to' => null];
        $updatedReport['csv_total_rows'] = $existingReport['csv_total_rows'] ?? $updatedReport['stats']['total'];

        session([
            'crm_meta_missing_checker_report' => $updatedReport,
            'crm_meta_lead_check_import_results' => [
                'imported_count' => collect($importResult['results'])->where('status', 'imported')->count(),
                'skipped_count' => 0,
                'failed_count' => collect($importResult['results'])->where('status', 'failed')->count(),
                'rows' => $importResult['results'],
                'assignment_rule_checked' => $assignmentSettings['enabled'],
            ],
        ]);

        return redirect()->route('crm.meta-lead-check.index', ['tab' => 'missing'])
            ->with('success', "Import attempt completed. Processed {$importResult['processed_count']} of {$importResult['eligible_count']} eligible rows.");
    }

    private function auditStats(?FacebookLeadCenterAudit $audit): array
    {
        if (!$audit) {
            return [
                'total' => 0,
                'in_crm' => 0,
                'missing' => 0,
                'duplicate' => 0,
                'invalid' => 0,
                'importable' => 0,
            ];
        }

        $importable = FacebookLeadCenterAuditRow::query()
            ->where('facebook_lead_center_audit_id', $audit->id)
            ->whereIn('status', ['missing', 'not_enough_data'])
            ->where(function ($query) {
                $query->whereNotNull('phone')->orWhereNotNull('email');
            })
            ->count();

        return [
            'total' => (int) $audit->total_rows,
            'in_crm' => (int) $audit->matched_rows,
            'missing' => (int) $audit->missing_rows,
            'duplicate' => (int) $audit->possible_duplicate_rows,
            'invalid' => (int) $audit->unreadable_rows,
            'importable' => $importable,
        ];
    }
}
