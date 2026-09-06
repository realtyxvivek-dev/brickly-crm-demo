<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InsightSheetCellAudit;
use App\Models\InsightSheetCellOverride;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\InsightSheetService;
use App\Services\LeadAssignmentWorkflowService;
use App\Services\LeadAuditorAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InsightSheetController extends Controller
{
    public function __construct(
        private readonly InsightSheetService $insightSheetService,
        private readonly LeadAssignmentWorkflowService $leadAssignmentWorkflowService,
    )
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $access = app(\App\Services\LeadAuditorAccessService::class);
            if ($access->restricted()) {
                if ($request->filled('row_key')) $access->authorizeRow((string) $request->row_key);
                foreach ((array) $request->input('cells', []) as $cell) {
                    if (is_array($cell) && isset($cell['row_key'])) $access->authorizeRow((string) $cell['row_key']);
                }
            }
            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');
        $isLeadQualityAuditor = $this->isLeadQualityAuditor($request);

        $savedColumns = data_get($request->user()?->ui_preferences, 'insight_sheet.visible_columns');
        $visibleColumns = is_array($savedColumns)
            ? array_values(array_intersect($this->insightSheetService->columnKeys($isLeadQualityAuditor), $savedColumns))
            : null;

        $transferUsers = collect();
        if ($isLeadQualityAuditor) {
            $allowedIds = app(LeadAuditorAccessService::class)->allowedIds();
            $transferUsers = User::query()
                ->where('is_active', true)
                ->whereIn('id', $allowedIds)
                ->whereHas('role', fn ($query) => $query->whereIn('slug', LeadAuditorAccessService::SALES_ROLES))
                ->with('role:id,name,slug')
                ->orderBy('name')
                ->get(['id', 'name', 'role_id']);
        }

        return view('admin.insight-sheet.index', [
            'columns' => $this->insightSheetService->columns($isLeadQualityAuditor),
            'visibleInsightSheetColumns' => $visibleColumns ?: null,
            'canEditInsightSheet' => $this->canUseInsightSheet($request, 'insight_sheet.edit'),
            'canExportInsightSheet' => $this->canUseInsightSheet($request, 'insight_sheet.export'),
            'canManageInsightSheetAccess' => $request->user()?->isAdmin() ?? false,
            'isLeadQualityAuditor' => $isLeadQualityAuditor,
            'canTransferInsightSheetLead' => $isLeadQualityAuditor,
            'transferUsers' => $transferUsers,
        ]);
    }

    public function dashboard(Request $request)
    {
        abort_unless($this->isLeadQualityAuditor($request), 403);

        return view('admin.lead-quality-auditor.dashboard');
    }

    public function profile(Request $request)
    {
        abort_unless($this->isLeadQualityAuditor($request), 403);

        return view('admin.lead-quality-auditor.profile', [
            'user' => $request->user()->loadMissing('role'),
        ]);
    }

    public function updateLayout(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        $validated = $request->validate([
            'visible_columns' => ['required', 'array', 'min:1'],
            'visible_columns.*' => ['required', 'string', Rule::in($this->insightSheetService->columnKeys($this->isLeadQualityAuditor($request)))],
        ]);
        $visibleColumns = array_values(array_unique($validated['visible_columns']));
        $user = $request->user();
        $preferences = is_array($user->ui_preferences) ? $user->ui_preferences : [];
        data_set($preferences, 'insight_sheet.visible_columns', $visibleColumns);
        $user->forceFill(['ui_preferences' => $preferences])->save();

        return response()->json(['visible_columns' => $visibleColumns]);
    }

    public function data(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        return response()->json($this->insightSheetService->paginatedRows($request, $this->isLeadQualityAuditor($request)));
    }

    public function audit(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        $validated = $this->validateCellPayload($request, false);
        $this->authorizeInternalAuditColumn($request, $validated['column_key']);
        $override = InsightSheetCellOverride::query()
            ->with('editor:id,name')
            ->where('sheet_key', $validated['sheet_key'])
            ->where('row_key', $validated['row_key'])
            ->where('column_key', $validated['column_key'])
            ->first();

        $historyQuery = InsightSheetCellAudit::query()
            ->with('editor:id,name')
            ->where('sheet_key', $validated['sheet_key'])
            ->where('row_key', $validated['row_key'])
            ->where('column_key', $validated['column_key'])
            ->latest('edited_at');

        if ($validated['column_key'] !== 'internal_remark') {
            $historyQuery->limit(30);
        }

        $history = $historyQuery->get()
            ->map(fn (InsightSheetCellAudit $audit) => [
                'source_value' => (string) ($audit->source_value ?? ''),
                'old_value' => (string) ($audit->old_value ?? ''),
                'new_value' => (string) ($audit->new_value ?? ''),
                'edited_by' => $audit->editor?->name ?? 'System',
                'edited_at' => $audit->edited_at?->toDateTimeString() ?? $audit->created_at?->toDateTimeString(),
            ]);

        return response()->json([
            'row_key' => $validated['row_key'],
            'column_key' => $validated['column_key'],
            'source_value' => (string) ($this->insightSheetService->sourceValue($validated['row_key'], $validated['column_key']) ?? ''),
            'override' => $override ? [
                'value' => (string) ($override->value ?? ''),
                'edited_by' => $override->editor?->name ?? 'System',
                'edited_at' => $override->updated_at?->toDateTimeString(),
            ] : null,
            'history' => $history,
        ]);
    }

    public function remarks(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        $validated = $request->validate([
            'row_key' => ['required', 'string', 'max:120'],
        ]);

        return response()->json([
            'row_key' => $validated['row_key'],
            'history' => $this->insightSheetService->remarkHistory($validated['row_key']),
        ]);
    }

    public function details(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        $validated = $request->validate([
            'row_key' => ['required', 'string', 'max:120'],
        ]);
        $details = $this->insightSheetService->leadDetails($validated['row_key']);

        abort_if($details === null, 404);

        $details['can_override'] = $this->canOverrideLeadDetails($request);

        return response()->json($details);
    }

    public function transferLead(Request $request)
    {
        abort_unless($this->isLeadQualityAuditor($request), 403);

        $validated = $request->validate([
            'row_key' => ['required', 'string', 'regex:/^lead:[1-9][0-9]*$/'],
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['required', 'string', 'max:1000', 'not_regex:/^\s*$/'],
        ]);

        $access = app(LeadAuditorAccessService::class);
        $access->authorizeRow($validated['row_key']);
        $access->authorizeUser((int) $validated['assigned_to']);

        $newOwner = User::query()
            ->whereKey($validated['assigned_to'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->whereIn('slug', LeadAuditorAccessService::SALES_ROLES))
            ->first();

        abort_unless($newOwner, 422, 'Select an active eligible sales user.');

        $lead = Lead::query()->findOrFail((int) substr($validated['row_key'], 5));
        abort_if(
            $lead->activeAssignments()->where('assigned_to', $newOwner->id)->exists(),
            422,
            'This user is already the lead owner.',
        );
        $result = $this->leadAssignmentWorkflowService->assignLead(
            $lead,
            $newOwner->id,
            $request->user()->id,
            trim($validated['notes']),
            true,
            true,
        );

        $oldOwnerId = collect($result['old_owner_ids'] ?? [])->first();
        if ($oldOwnerId) {
            $lead->fresh()->markAsFreshTransfer(
                (int) $oldOwnerId,
                $newOwner->id,
                $request->user()->id,
                trim($validated['notes']),
            );
        }

        return response()->json([
            'message' => "Lead transferred to {$newOwner->name} successfully.",
            'new_owner' => ['id' => $newOwner->id, 'name' => $newOwner->name],
        ]);
    }

    public function saveDetailsOverride(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');
        abort_unless($this->canOverrideLeadDetails($request), 403);

        $validated = $request->validate([
            'row_key' => ['required', 'string', 'max:120'],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:5000'],
        ]);
        $details = $this->insightSheetService->leadDetails($validated['row_key']);
        abort_if($details === null, 404);

        $allowed = collect($details['editable_fields'] ?? [])->pluck('key');
        abort_if(collect(array_keys($validated['values']))->diff($allowed)->isNotEmpty(), 422, 'Invalid detail field.');

        $optionErrors = [];
        foreach (($details['editor_options'] ?? []) as $key => $options) {
            $value = trim((string) ($validated['values'][$key] ?? ''));
            $submitted = $key === 'project'
                ? collect(explode(',', $value))->map(fn ($item) => trim($item))->filter()->all()
                : ($value === '' ? [] : [$value]);
            if (collect($submitted)->diff($options)->isNotEmpty()) {
                $optionErrors["values.$key"] = ['Select a value from the available options.'];
            }
        }
        if ($optionErrors) {
            throw \Illuminate\Validation\ValidationException::withMessages($optionErrors);
        }

        $values = collect($validated['values'])
            ->only($allowed->all())
            ->map(fn ($value) => $value === null ? '' : trim((string) $value))
            ->all();
        $sourceValues = collect($details['editable_fields'] ?? [])->mapWithKeys(
            fn (array $field) => [$field['key'] => $field['source_value'] ?? null]
        )->all();

        DB::transaction(function () use ($request, $validated, $values, $sourceValues): void {
            $existing = InsightSheetCellOverride::query()
                ->where('sheet_key', InsightSheetService::SHEET_MASTER)
                ->where('row_key', $validated['row_key'])
                ->where('column_key', 'customer_details')
                ->first();

            InsightSheetCellOverride::updateOrCreate([
                'sheet_key' => InsightSheetService::SHEET_MASTER,
                'row_key' => $validated['row_key'],
                'column_key' => 'customer_details',
            ], [
                'value' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'edited_by' => $request->user()->id,
            ]);

            InsightSheetCellAudit::create([
                'sheet_key' => InsightSheetService::SHEET_MASTER,
                'row_key' => $validated['row_key'],
                'column_key' => 'customer_details',
                'source_value' => json_encode($sourceValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'old_value' => $existing?->value,
                'new_value' => json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'edited_by' => $request->user()->id,
                'edited_at' => now(),
            ]);
        });

        $details = $this->insightSheetService->leadDetails($validated['row_key']);
        $details['can_override'] = true;

        return response()->json($details);
    }

    public function completionDetails(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.view');

        $validated = $request->validate([
            'row_key' => ['required', 'string', 'max:120'],
        ]);
        $details = $this->insightSheetService->completionDetails($validated['row_key']);

        abort_if($details === null, 404);

        return response()->json($details);
    }

    public function access(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json([
            'permissions' => $this->insightPermissions(),
            'roles' => Role::query()
                ->where('is_active', true)
                ->where('slug', '!=', Role::ADMIN)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'permissions'])
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'slug' => $role->slug,
                    'permissions' => array_values(array_intersect($role->permissions ?? [], $this->insightPermissions())),
                ]),
        ]);
    }

    public function updateAccess(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'roles' => ['nullable', 'array'],
            'roles.*.role_id' => ['required', 'integer', 'exists:roles,id'],
            'roles.*.permissions' => ['nullable', 'array'],
            'roles.*.permissions.*' => ['string', Rule::in($this->insightPermissions())],
        ]);

        DB::transaction(function () use ($validated) {
            foreach ($validated['roles'] ?? [] as $payload) {
                $role = Role::query()
                    ->whereKey($payload['role_id'])
                    ->where('slug', '!=', Role::ADMIN)
                    ->lockForUpdate()
                    ->first();

                if (! $role) {
                    continue;
                }

                $current = collect($role->permissions ?? [])
                    ->reject(fn (string $permission) => in_array($permission, $this->insightPermissions(), true));
                $next = collect($payload['permissions'] ?? [])->filter()->unique();
                if ($next->contains('insight_sheet.edit') || $next->contains('insight_sheet.export')) {
                    $next->push('insight_sheet.view');
                }
                $role->forceFill(['permissions' => $current->merge($next)->values()->all()])->save();
            }
        });

        return response()->json(['status' => 'saved']);
    }

    public function saveCell(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.edit');

        $validated = $this->validateCellPayload($request);
        $this->authorizeInternalAuditColumn($request, $validated['column_key'], true);

        $override = DB::transaction(function () use ($request, $validated) {
            $existing = InsightSheetCellOverride::query()
                ->where('sheet_key', $validated['sheet_key'])
                ->where('row_key', $validated['row_key'])
                ->where('column_key', $validated['column_key'])
                ->first();

            $sourceValue = $this->insightSheetService->sourceValue($validated['row_key'], $validated['column_key']);
            $oldValue = $existing?->value;

            $override = InsightSheetCellOverride::updateOrCreate(
                [
                    'sheet_key' => $validated['sheet_key'],
                    'row_key' => $validated['row_key'],
                    'column_key' => $validated['column_key'],
                ],
                [
                    'value' => $validated['value'] ?? null,
                    'edited_by' => $request->user()->id,
                ]
            );

            InsightSheetCellAudit::create([
                'sheet_key' => $validated['sheet_key'],
                'row_key' => $validated['row_key'],
                'column_key' => $validated['column_key'],
                'source_value' => $sourceValue,
                'old_value' => $oldValue,
                'new_value' => $validated['value'] ?? null,
                'edited_by' => $request->user()->id,
                'edited_at' => now(),
            ]);

            return $override;
        });

        return response()->json([
            'status' => 'saved',
            'cell' => [
                'value' => (string) ($override->value ?? ''),
                'is_overridden' => true,
                'edited_by' => $override->edited_by,
                'edited_at' => $override->updated_at?->toDateTimeString(),
            ],
        ]);
    }

    public function saveBulk(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.edit');

        $request->validate([
            'cells' => ['required', 'array', 'min:1', 'max:500'],
            'cells.*.sheet_key' => ['nullable', 'string', Rule::in([InsightSheetService::SHEET_MASTER])],
            'cells.*.row_key' => ['required', 'string', 'max:120'],
            'cells.*.column_key' => ['required', 'string', Rule::in($this->insightSheetService->columnKeys($this->isLeadQualityAuditor($request)))],
            'cells.*.value' => ['nullable', 'string', 'max:10000'],
        ]);

        $saved = 0;
        foreach ($request->input('cells', []) as $cell) {
            $cellRequest = new Request([
                'sheet_key' => $cell['sheet_key'] ?? InsightSheetService::SHEET_MASTER,
                'row_key' => $cell['row_key'],
                'column_key' => $cell['column_key'],
                'value' => $cell['value'] ?? null,
            ]);
            $cellRequest->setUserResolver(fn () => $request->user());
            $this->saveCell($cellRequest);
            $saved++;
        }

        return response()->json(['status' => 'saved', 'saved' => $saved]);
    }

    public function resetCell(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.edit');

        $validated = $this->validateCellPayload($request, false);
        $this->authorizeInternalAuditColumn($request, $validated['column_key'], true);

        DB::transaction(function () use ($request, $validated) {
            $existing = InsightSheetCellOverride::query()
                ->where('sheet_key', $validated['sheet_key'])
                ->where('row_key', $validated['row_key'])
                ->where('column_key', $validated['column_key'])
                ->first();

            $sourceValue = $this->insightSheetService->sourceValue($validated['row_key'], $validated['column_key']);

            if ($existing) {
                $existing->delete();
            }

            InsightSheetCellAudit::create([
                'sheet_key' => $validated['sheet_key'],
                'row_key' => $validated['row_key'],
                'column_key' => $validated['column_key'],
                'source_value' => $sourceValue,
                'old_value' => $existing?->value,
                'new_value' => null,
                'edited_by' => $request->user()->id,
                'edited_at' => now(),
            ]);
        });

        return response()->json([
            'status' => 'reset',
            'source_value' => (string) ($this->insightSheetService->sourceValue($validated['row_key'], $validated['column_key']) ?? ''),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizeInsightSheet($request, 'insight_sheet.export');

        $full = $request->boolean('full', false);
        $includeMeta = $request->boolean('include_meta') && ($request->user()?->isAdmin() ?? false);
        $isLeadQualityAuditor = $this->isLeadQualityAuditor($request);
        $columns = $this->insightSheetService->columns($isLeadQualityAuditor);
        $rows = $this->insightSheetService->exportRows($request, $full, $isLeadQualityAuditor);
        $filename = 'insight-sheet-' . ($full ? 'full' : 'filtered') . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($columns, $rows, $includeMeta) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            $header = collect($columns)->pluck('label')->all();
            if ($includeMeta) {
                foreach ($columns as $column) {
                    $header[] = $column['label'] . ' CRM Source Value';
                    $header[] = $column['label'] . ' Edited By';
                    $header[] = $column['label'] . ' Edited At';
                }
            }
            fputcsv($handle, $header);

            foreach ($rows as $row) {
                $values = collect($columns)
                    ->map(fn (array $column) => $row['cells'][$column['key']]['value'] ?? '')
                    ->all();

                if ($includeMeta) {
                    foreach ($columns as $column) {
                        $cell = $row['cells'][$column['key']] ?? [];
                        $values[] = $cell['source_value'] ?? '';
                        $values[] = $cell['edited_by_name'] ?? $cell['edited_by'] ?? '';
                        $values[] = $cell['edited_at'] ?? '';
                    }
                }

                fputcsv($handle, $values);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateCellPayload(Request $request, bool $includeValue = true): array
    {
        $validated = $request->validate([
            'sheet_key' => ['nullable', 'string', Rule::in([InsightSheetService::SHEET_MASTER])],
            'row_key' => ['required', 'string', 'max:120'],
            'column_key' => ['required', 'string', Rule::in($this->insightSheetService->columnKeys(true))],
            'value' => [$includeValue ? 'nullable' : 'sometimes', 'string', 'max:10000'],
        ]);

        $validated['sheet_key'] = $validated['sheet_key'] ?? InsightSheetService::SHEET_MASTER;

        return $validated;
    }

    private function authorizeInsightSheet(Request $request, string $permission): void
    {
        abort_unless($this->canUseInsightSheet($request, $permission), 403);
    }

    private function isLeadQualityAuditor(Request $request): bool
    {
        return $request->user()?->role?->slug === Role::LEAD_QUALITY_AUDITOR;
    }

    private function canOverrideLeadDetails(Request $request): bool
    {
        return ($request->user()?->isAdmin() ?? false) || $this->isLeadQualityAuditor($request);
    }

    private function authorizeInternalAuditColumn(Request $request, string $columnKey, bool $isWrite = false): void
    {
        $isInternalColumn = in_array($columnKey, ['internal_stage', 'internal_remark'], true);

        if ($isInternalColumn) {
            abort_unless($this->isLeadQualityAuditor($request), 403);
        }

        if ($isWrite && $this->isLeadQualityAuditor($request)) {
            abort_unless($isInternalColumn, 403);
        }
    }

    private function canUseInsightSheet(Request $request, string $permission): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'hasRolePermission')) {
            return $user->hasRolePermission($permission);
        }

        return in_array($permission, $user->role?->permissions ?? [], true);
    }

    private function insightPermissions(): array
    {
        return ['insight_sheet.view', 'insight_sheet.edit', 'insight_sheet.export'];
    }
}
