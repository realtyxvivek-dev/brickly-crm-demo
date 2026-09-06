<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ResolvesExpenseView;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseEntry;
use App\Models\ExpensePaymentMethod;
use App\Models\ExpenseSubcategory;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseDashboardSummaryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseEntryController extends Controller
{
    use ResolvesExpenseView;

    public function __construct(
        private readonly ExpenseDashboardSummaryService $expenseDashboardSummaryService
    ) {
    }

    public function dashboard(Request $request): View
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        $snapshot = $this->expenseDashboardSummaryService->getMonthSnapshot($year, $month);

        return view($this->expenseView('dashboard'), [
            'year' => $snapshot['year'],
            'month' => $snapshot['month'],
            'summary' => $snapshot['summary'],
            'statusTotals' => $snapshot['status_totals'],
            'latestEntries' => $snapshot['latest_entries'],
            'subcategoryTotals' => $snapshot['subcategory_totals'],
            'pendingCount' => $snapshot['pending_count'],
        ]);
    }

    public function queue(Request $request): View
    {
        $filters = [
            'company_id' => $request->input('company_id'),
            'expense_category_id' => $request->input('expense_category_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'status' => ExpenseEntry::STATUS_DRAFT,
            'assigned_scope' => $request->input('assigned_scope', 'all'),
            'approval_assigned_to' => $request->input('approval_assigned_to'),
        ];

        $query = $this->filteredQuery($filters);
        $entries = (clone $query)
            ->with(['company', 'category', 'subcategory', 'creator', 'approver', 'rejector', 'assignedApprover', 'assignedBy', 'paymentMethod'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view($this->expenseView('queue'), [
            'companies' => Company::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'approvers' => $this->eligibleApprovers(),
            'entries' => $entries,
            'filters' => $filters,
        ]);
    }

    public function monthlyReport(Request $request): View
    {
        [$year, $month, $filters] = $this->resolveMonthlyFilters($request);
        $query = $this->monthlyFilteredQuery($year, $month, $filters);

        $companyCategoryTotals = (clone $query)
            ->selectRaw('company_id, expense_category_id, SUM(amount) as total_amount, COUNT(*) as entry_count')
            ->with(['company:id,name', 'category:id,name'])
            ->groupBy('company_id', 'expense_category_id')
            ->orderByDesc('total_amount')
            ->get();

        $subcategoryTotals = (clone $query)
            ->selectRaw('expense_subcategory_id, SUM(amount) as total_amount, COUNT(*) as entry_count')
            ->with(['subcategory:id,name', 'subcategory.category:id,name'])
            ->groupBy('expense_subcategory_id')
            ->orderByDesc('total_amount')
            ->get();

        return view($this->expenseView('monthly-report'), [
            'year' => $year,
            'month' => $month,
            'filters' => $filters,
            'companies' => Company::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'summary' => $this->buildSummary(clone $query),
            'statusTotals' => $this->buildStatusTotals(clone $query),
            'companyCategoryTotals' => $companyCategoryTotals,
            'subcategoryTotals' => $subcategoryTotals,
        ]);
    }

    public function printSummary(Request $request): View
    {
        [$year, $month, $filters] = $this->resolveMonthlyFilters($request);
        $query = $this->monthlyFilteredQuery($year, $month, $filters);

        return view($this->expenseView('print-summary'), [
            'year' => $year,
            'month' => $month,
            'filters' => $filters,
            'summary' => $this->buildSummary(clone $query),
            'statusTotals' => $this->buildStatusTotals(clone $query),
            'companyTotals' => (clone $query)
                ->selectRaw('company_id, SUM(amount) as total_amount')
                ->with('company:id,name')
                ->groupBy('company_id')
                ->orderByDesc('total_amount')
                ->get(),
            'categoryTotals' => (clone $query)
                ->selectRaw('expense_category_id, SUM(amount) as total_amount')
                ->with('category:id,name')
                ->groupBy('expense_category_id')
                ->orderByDesc('total_amount')
                ->get(),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => trim((string) $request->input('search')),
            'company_id' => $request->input('company_id'),
            'expense_category_id' => $request->input('expense_category_id'),
            'expense_subcategory_id' => $request->input('expense_subcategory_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $query = $this->filteredQuery($filters);
        $perPage = $this->resolveEntriesPerPage($request);
        $paginationSize = $perPage === 'all'
            ? max((clone $query)->count(), 1)
            : (int) $perPage;

        $entries = (clone $query)
            ->with(['company', 'category', 'subcategory', 'creator', 'approver', 'rejector', 'assignedApprover', 'assignedBy', 'paymentMethod'])
            ->latest('expense_date')
            ->latest('id')
            ->paginate($paginationSize)
            ->withQueryString();
        $hasExpenseDeleteColumns = $this->hasExpenseDeleteColumns();

        return view($this->expenseView('entries.index'), [
            'companies' => Company::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->with(['subcategories' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->orderBy('name')->get(),
            'subcategories' => ExpenseSubcategory::query()->where('is_active', true)->with('category')->orderBy('sort_order')->orderBy('name')->get(),
            'approvers' => $this->eligibleApprovers(),
            'entries' => $entries,
            'filters' => $filters,
            'summary' => $this->buildSummary(clone $query),
            'paymentModes' => $this->paymentModes(),
            'statuses' => $this->statuses(),
            'perPage' => $perPage,
            'perPageOptions' => $this->entriesPerPageOptions(),
            'deletedSummary' => $hasExpenseDeleteColumns
                ? $this->buildDeletedSummary($filters)
                : ['entry_count' => 0, 'total_amount' => 0, 'latest_deleted_at' => null],
            'deletedEntries' => $hasExpenseDeleteColumns
                ? $this->deletedExpenseLogQuery($filters)
                ->with(['company', 'category', 'subcategory', 'creator', 'deleter'])
                ->latest('deleted_at')
                ->latest('id')
                ->limit(20)
                ->get()
                : collect(),
            'deletedUserTotals' => $hasExpenseDeleteColumns
                ? $this->deletedExpenseLogQuery($filters)
                ->selectRaw('deleted_by, COUNT(*) as entry_count, SUM(amount) as total_amount, MAX(deleted_at) as latest_deleted_at')
                ->with('deleter:id,name')
                ->groupBy('deleted_by')
                ->orderByDesc('latest_deleted_at')
                ->get()
                : collect(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'search' => trim((string) $request->input('search')),
            'company_id' => $request->input('company_id'),
            'expense_category_id' => $request->input('expense_category_id'),
            'expense_subcategory_id' => $request->input('expense_subcategory_id'),
            'status' => $request->input('status'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $rows = $this->filteredQuery($filters)
            ->with(['company', 'category', 'subcategory', 'creator', 'paymentMethod'])
            ->latest('expense_date')
            ->latest('id')
            ->get();

        $filename = 'expense-ledger-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Company', 'Category', 'Subcategory', 'Amount', 'Payment Mode', 'Payment Method', 'Paid To', 'Reference No', 'Status', 'Attachment Path', 'Remarks', 'Created By']);

            foreach ($rows as $entry) {
                fputcsv($handle, [
                    optional($entry->expense_date)->format('Y-m-d'),
                    $entry->company?->name,
                    $entry->category?->name,
                    $entry->subcategory?->name,
                    (float) $entry->amount,
                    $entry->payment_mode,
                    $entry->paymentMethod?->name,
                    $entry->paid_to,
                    $entry->reference_no,
                    $entry->status,
                    $entry->attachment_path,
                    $entry->remarks,
                    $entry->creator?->name,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportSummary(Request $request): StreamedResponse
    {
        [$year, $month, $filters] = $this->resolveMonthlyFilters($request);
        $rows = $this->monthlyFilteredQuery($year, $month, $filters)
            ->selectRaw('company_id, expense_category_id, expense_subcategory_id, status, SUM(amount) as total_amount, COUNT(*) as entry_count')
            ->with(['company:id,name', 'category:id,name', 'subcategory:id,name'])
            ->groupBy('company_id', 'expense_category_id', 'expense_subcategory_id', 'status')
            ->orderByDesc('total_amount')
            ->get();

        $filename = sprintf('expense-summary-%04d-%02d.csv', $year, $month);

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Company', 'Category', 'Subcategory', 'Status', 'Entry Count', 'Total Amount']);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->company?->name,
                    $row->category?->name,
                    $row->subcategory?->name,
                    $row->status,
                    (int) $row->entry_count,
                    (float) $row->total_amount,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(): View
    {
        return view($this->expenseView('entries.form'), [
            'entry' => new ExpenseEntry([
                'expense_date' => now()->toDateString(),
                'status' => ExpenseEntry::STATUS_DRAFT,
            ]),
            'companies' => Company::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->with(['subcategories' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->orderBy('name')->get(),
            'approvers' => $this->eligibleApprovers(),
            'paymentModes' => $this->paymentModes(),
            'paymentMethods' => $this->paymentMethods(),
            'statuses' => $this->statuses(),
            'formAction' => $this->expenseRoute('entries.store'),
            'formMethod' => 'POST',
            'pageMode' => 'create',
            'isRejectedResubmission' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $validated['created_by'] = $request->user()->id;
        $validated['updated_by'] = $request->user()->id;
        $validated['approval_assigned_by'] = $request->user()->id;
        $validated['approval_assigned_at'] = now();
        $validated['status'] = ExpenseEntry::STATUS_DRAFT;
        $validated['reject_reason'] = null;
        $validated['approved_by'] = null;
        $validated['approved_at'] = null;
        $validated['rejected_by'] = null;
        $validated['rejected_at'] = null;

        ExpenseEntry::create($validated);

        $assignedApprover = User::query()->find($validated['approval_assigned_to']);

        return redirect($this->expenseRoute('queue'))->with('success', sprintf(
            'Expense saved as draft and sent to %s for approval.',
            $assignedApprover?->name ?: 'selected approver'
        ));
    }

    public function edit(ExpenseEntry $entry): View
    {
        $this->authorizeRejectedFinanceResubmission($entry);

        $entry->load(['company', 'category', 'subcategory', 'creator', 'rejector', 'assignedApprover', 'paymentMethod']);

        return view($this->expenseView('entries.form'), [
            'entry' => $entry,
            'companies' => Company::query()
                ->where(function ($query) use ($entry) {
                    $query->where('is_active', true);

                    if ($entry->company_id) {
                        $query->orWhere('id', $entry->company_id);
                    }
                })
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'categories' => ExpenseCategory::query()
                ->where(function ($query) use ($entry) {
                    $query->where('is_active', true);

                    if ($entry->expense_category_id) {
                        $query->orWhere('id', $entry->expense_category_id);
                    }
                })
                ->with(['subcategories' => function ($query) use ($entry) {
                    $query->where(function ($subquery) use ($entry) {
                        $subquery->where('is_active', true);

                        if ($entry->expense_subcategory_id) {
                            $subquery->orWhere('id', $entry->expense_subcategory_id);
                        }
                    });
                }])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'approvers' => $this->eligibleApprovers($entry->approval_assigned_to),
            'paymentModes' => $this->paymentModes(),
            'paymentMethods' => $this->paymentMethods($entry->expense_payment_method_id),
            'statuses' => $this->statuses(),
            'formAction' => $this->expenseRoute('entries.update', $entry),
            'formMethod' => 'PUT',
            'pageMode' => 'edit',
            'isRejectedResubmission' => $entry->status === ExpenseEntry::STATUS_REJECTED,
        ]);
    }

    public function update(Request $request, ExpenseEntry $entry): RedirectResponse
    {
        $this->authorizeRejectedFinanceResubmission($entry);

        $wasRejected = $entry->status === ExpenseEntry::STATUS_REJECTED;
        $validated = $this->validatePayload($request);
        $validated['updated_by'] = $request->user()->id;
        $validated['approval_assigned_by'] = $request->user()->id;
        $validated['approval_assigned_at'] = now();
        $validated['status'] = ExpenseEntry::STATUS_DRAFT;
        $validated['reject_reason'] = null;
        $validated['approved_by'] = null;
        $validated['approved_at'] = null;
        $validated['rejected_by'] = null;
        $validated['rejected_at'] = null;

        $entry->update($validated);

        $assignedApprover = User::query()->find($validated['approval_assigned_to']);

        return redirect($this->expenseRoute($this->isFinanceManagerRoute() ? 'entries.index' : 'queue'))
            ->with('success', $wasRejected
                ? sprintf('Expense updated and resubmitted to %s for approval.', $assignedApprover?->name ?: 'selected approver')
                : sprintf('Expense updated and moved back to %s approval queue.', $assignedApprover?->name ?: 'selected approver'));
    }

    public function approve(Request $request, ExpenseEntry $entry): RedirectResponse
    {
        if ($entry->status !== ExpenseEntry::STATUS_DRAFT) {
            return back()->with('error', 'Only draft expenses can be approved.');
        }

        $entry->update([
            'status' => ExpenseEntry::STATUS_APPROVED,
            'reject_reason' => null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'rejected_by' => null,
            'rejected_at' => null,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Expense approved successfully.');
    }

    public function reject(Request $request, ExpenseEntry $entry): RedirectResponse
    {
        if ($entry->status !== ExpenseEntry::STATUS_DRAFT) {
            return back()->with('error', 'Only draft expenses can be rejected.');
        }

        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:2000'],
        ]);

        $entry->update([
            'status' => ExpenseEntry::STATUS_REJECTED,
            'reject_reason' => $validated['reject_reason'],
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Expense rejected and returned to finance manager for correction.');
    }

    public function destroy(Request $request, ExpenseEntry $entry): RedirectResponse
    {
        abort_unless($this->canDeleteExpenses(), 403);
        if (!$this->hasExpenseDeleteColumns()) {
            return back()->with('error', 'Delete setup pending hai. Migration run hone ke baad delete available hoga.');
        }

        abort_if($this->hasExpenseDeleteColumns() && $entry->deleted_at, 404);

        $validated = $request->validate([
            'delete_password' => ['required', 'string'],
            'delete_reason' => ['required', 'string', 'max:1000'],
        ]);

        $expectedPassword = (string) config('expenses.delete_password', env('EXPENSE_DELETE_PASSWORD', '9559180196'));
        if (!hash_equals($expectedPassword, (string) $validated['delete_password'])) {
            throw ValidationException::withMessages([
                'delete_password' => 'Delete password galat hai.',
            ]);
        }

        $entry->forceFill([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'],
            'deleted_at' => now(),
            'updated_by' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Expense entry deleted from ledger. Audit copy database me retained hai.');
    }

    public function downloadAttachment(ExpenseEntry $entry)
    {
        abort_unless($entry->attachment_path && Storage::disk('public')->exists($entry->attachment_path), 404);

        return Storage::disk('public')->download($entry->attachment_path);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'expense_subcategory_id' => ['required', 'exists:expense_subcategories,id'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_mode' => ['required', Rule::in(array_keys($this->paymentModes()))],
            'expense_payment_method_id' => ['nullable', 'integer', 'exists:expense_payment_methods,id'],
            'approval_assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) {
                $query->where('is_active', true)->whereIn('role_id', $this->eligibleApproverRoleIds());
            })],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
            'existing_attachment_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $subcategory = ExpenseSubcategory::query()->findOrFail($validated['expense_subcategory_id']);
        if ((int) $subcategory->expense_category_id !== (int) $validated['expense_category_id']) {
            throw ValidationException::withMessages([
                'expense_subcategory_id' => 'Selected subcategory does not belong to the selected category.',
            ]);
        }

        $paymentMethodRequiredModes = [
            ExpenseEntry::PAYMENT_MODE_BANK,
            ExpenseEntry::PAYMENT_MODE_UPI,
            ExpenseEntry::PAYMENT_MODE_CREDIT_CARD,
        ];

        if (in_array($validated['payment_mode'], $paymentMethodRequiredModes, true) && empty($validated['expense_payment_method_id'])) {
            $fallbackPaymentMethod = ExpensePaymentMethod::query()
                ->where('type', $validated['payment_mode'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();

            if (!$fallbackPaymentMethod) {
                throw ValidationException::withMessages([
                    'expense_payment_method_id' => 'Please select the related payment source.',
                ]);
            }

            $validated['expense_payment_method_id'] = $fallbackPaymentMethod->id;
        }

        if (!empty($validated['expense_payment_method_id'])) {
            $paymentMethod = ExpensePaymentMethod::query()->findOrFail($validated['expense_payment_method_id']);

            if ($paymentMethod->type !== $validated['payment_mode']) {
                throw ValidationException::withMessages([
                    'expense_payment_method_id' => 'Selected payment source does not match the payment mode.',
                ]);
            }

            if (!$paymentMethod->is_active) {
                throw ValidationException::withMessages([
                    'expense_payment_method_id' => 'Selected payment source is inactive.',
                ]);
            }
        }

        $validated['attachment_path'] = $validated['existing_attachment_path'] ?? null;

        if ($request->hasFile('attachment')) {
            if (!empty($validated['attachment_path']) && Storage::disk('public')->exists($validated['attachment_path'])) {
                Storage::disk('public')->delete($validated['attachment_path']);
            }

            $validated['attachment_path'] = $request->file('attachment')->store('expenses/attachments', 'public');
        }

        unset($validated['attachment'], $validated['existing_attachment_path']);

        return $validated;
    }

    private function filteredQuery(array $filters): Builder
    {
        return $this->visibleExpenseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $amountSearch = str_replace([',', 'Rs', 'rs', '₹', ' '], '', $search);

                $query->where(function (Builder $inner) use ($search, $amountSearch) {
                    $inner->where('paid_to', 'like', '%' . $search . '%')
                        ->orWhere('id', ltrim(str_ireplace('EXP-', '', $search), '0') ?: $search)
                        ->orWhere('reference_no', 'like', '%' . $search . '%')
                        ->orWhere('remarks', 'like', '%' . $search . '%')
                        ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('subcategory', fn (Builder $subcategoryQuery) => $subcategoryQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('approver', fn (Builder $approverQuery) => $approverQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assignedApprover', fn (Builder $assignedQuery) => $assignedQuery->where('name', 'like', '%' . $search . '%'));

                    if (is_numeric($amountSearch)) {
                        $inner->orWhere('amount', (float) $amountSearch);
                    }
                });
            })
            ->when($filters['company_id'] ?? null, fn (Builder $query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['expense_subcategory_id'] ?? null, fn (Builder $query, $subcategoryId) => $query->where('expense_subcategory_id', $subcategoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(($filters['assigned_scope'] ?? 'all') === 'mine' && $this->isAdminRoute(), fn (Builder $query) => $query->where('approval_assigned_to', request()->user()->id))
            ->when($filters['approval_assigned_to'] ?? null, fn (Builder $query, $approverId) => $query->where('approval_assigned_to', $approverId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $dateFrom) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $dateTo) => $query->whereDate('expense_date', '<=', $dateTo));
    }

    private function monthlyFilteredQuery(int $year, int $month, array $filters): Builder
    {
        return $this->visibleExpenseQuery()
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->when($filters['company_id'], fn (Builder $query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['expense_category_id'], fn (Builder $query, $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['status'], fn (Builder $query, $status) => $query->where('status', $status));
    }

    private function buildSummary(Builder $query): array
    {
        $totalAmount = (clone $query)->sum('amount');

        $companyTotals = (clone $query)
            ->selectRaw('company_id, SUM(amount) as total_amount')
            ->with('company:id,name')
            ->groupBy('company_id')
            ->orderByDesc('total_amount')
            ->get();

        $categoryTotals = (clone $query)
            ->selectRaw('expense_category_id, SUM(amount) as total_amount')
            ->with('category:id,name')
            ->groupBy('expense_category_id')
            ->orderByDesc('total_amount')
            ->get();

        return [
            'total_amount' => (float) $totalAmount,
            'entry_count' => (clone $query)->count(),
            'company_totals' => $companyTotals,
            'category_totals' => $categoryTotals,
            'average_amount' => (float) ((clone $query)->avg('amount') ?? 0),
            'pending_count' => (clone $query)->where('status', ExpenseEntry::STATUS_DRAFT)->count(),
            'rejected_count' => (clone $query)->where('status', ExpenseEntry::STATUS_REJECTED)->count(),
        ];
    }

    private function buildStatusTotals(Builder $query): array
    {
        $grouped = (clone $query)
            ->selectRaw('status, SUM(amount) as total_amount, COUNT(*) as entry_count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return collect($this->statuses())->mapWithKeys(function ($label, $status) use ($grouped) {
            $row = $grouped->get($status);

            return [$status => [
                'label' => $label,
                'entry_count' => (int) ($row->entry_count ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
            ]];
        })->all();
    }

    private function resolveMonthlyFilters(Request $request): array
    {
        return [
            (int) $request->input('year', now()->year),
            (int) $request->input('month', now()->month),
            [
                'company_id' => $request->input('company_id'),
                'expense_category_id' => $request->input('expense_category_id'),
                'status' => $request->input('status'),
            ],
        ];
    }

    private function paymentModes(): array
    {
        return ExpensePaymentMethod::types();
    }

    private function paymentMethods(?int $includeId = null)
    {
        return ExpensePaymentMethod::query()
            ->where(function (Builder $query) use ($includeId) {
                $query->where('is_active', true);

                if ($includeId) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function statuses(): array
    {
        return [
            ExpenseEntry::STATUS_DRAFT => 'Draft',
            ExpenseEntry::STATUS_APPROVED => 'Approved',
            ExpenseEntry::STATUS_REJECTED => 'Rejected',
        ];
    }

    private function resolveEntriesPerPage(Request $request): int|string
    {
        $sessionKey = $this->entriesPerPageSessionKey($request);
        $allowed = array_keys($this->entriesPerPageOptions());

        if ($request->has('per_page')) {
            $requested = (string) $request->input('per_page');
            $perPage = in_array($requested, $allowed, true) ? $requested : '100';
            $request->session()->put($sessionKey, $perPage);

            return $perPage === 'all' ? 'all' : (int) $perPage;
        }

        $stored = (string) $request->session()->get($sessionKey, '100');
        $perPage = in_array($stored, $allowed, true) ? $stored : '100';

        return $perPage === 'all' ? 'all' : (int) $perPage;
    }

    private function entriesPerPageOptions(): array
    {
        return [
            '100' => '100',
            '500' => '500',
            '1000' => '1000',
            'all' => 'All',
        ];
    }

    private function entriesPerPageSessionKey(Request $request): string
    {
        return 'expense_entries_per_page_user_' . $request->user()->id;
    }

    private function visibleExpenseQuery(): Builder
    {
        return ExpenseEntry::query()
            ->when($this->hasExpenseDeleteColumns(), fn (Builder $query) => $query->whereNull('deleted_at'));
    }

    private function deletedExpenseLogQuery(array $filters = []): Builder
    {
        $query = ExpenseEntry::query();

        if (!$this->hasExpenseDeleteColumns()) {
            return $query->whereRaw('1 = 0');
        }

        return $this->applyExpenseFilters($query->whereNotNull('deleted_at'), $filters);
    }

    private function buildDeletedSummary(array $filters): array
    {
        $query = $this->deletedExpenseLogQuery($filters);

        return [
            'entry_count' => (clone $query)->count(),
            'total_amount' => (float) (clone $query)->sum('amount'),
            'latest_deleted_at' => (clone $query)->max('deleted_at'),
        ];
    }

    private function applyExpenseFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $amountSearch = str_replace([',', 'Rs', 'rs', 'â‚¹', ' '], '', $search);

                $query->where(function (Builder $inner) use ($search, $amountSearch) {
                    $inner->where('paid_to', 'like', '%' . $search . '%')
                        ->orWhere('id', ltrim(str_ireplace('EXP-', '', $search), '0') ?: $search)
                        ->orWhere('reference_no', 'like', '%' . $search . '%')
                        ->orWhere('remarks', 'like', '%' . $search . '%')
                        ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('subcategory', fn (Builder $subcategoryQuery) => $subcategoryQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('creator', fn (Builder $creatorQuery) => $creatorQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('approver', fn (Builder $approverQuery) => $approverQuery->where('name', 'like', '%' . $search . '%'))
                        ->orWhereHas('assignedApprover', fn (Builder $assignedQuery) => $assignedQuery->where('name', 'like', '%' . $search . '%'));

                    if (is_numeric($amountSearch)) {
                        $inner->orWhere('amount', (float) $amountSearch);
                    }
                });
            })
            ->when($filters['company_id'] ?? null, fn (Builder $query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['expense_category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['expense_subcategory_id'] ?? null, fn (Builder $query, $subcategoryId) => $query->where('expense_subcategory_id', $subcategoryId))
            ->when($filters['status'] ?? null, fn (Builder $query, $status) => $query->where('status', $status))
            ->when(($filters['assigned_scope'] ?? 'all') === 'mine' && $this->isAdminRoute(), fn (Builder $query) => $query->where('approval_assigned_to', request()->user()->id))
            ->when($filters['approval_assigned_to'] ?? null, fn (Builder $query, $approverId) => $query->where('approval_assigned_to', $approverId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, $dateFrom) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn (Builder $query, $dateTo) => $query->whereDate('expense_date', '<=', $dateTo));
    }

    private function hasExpenseDeleteColumns(): bool
    {
        return Schema::hasColumn('expense_entries', 'deleted_at')
            && Schema::hasColumn('expense_entries', 'deleted_by')
            && Schema::hasColumn('expense_entries', 'delete_reason');
    }

    private function authorizeRejectedFinanceResubmission(ExpenseEntry $entry): void
    {
        if (!$this->isFinanceManagerRoute() || $entry->status !== ExpenseEntry::STATUS_REJECTED) {
            return;
        }

        abort_if(
            (int) $entry->created_by !== (int) request()->user()->id,
            403,
            'Only the creator can edit and resubmit this rejected expense.'
        );
    }

    private function isFinanceManagerRoute(): bool
    {
        return request()->routeIs('finance-manager.*');
    }

    private function isAdminRoute(): bool
    {
        return request()->routeIs('admin.*');
    }

    private function canDeleteExpenses(): bool
    {
        return $this->isAdminRoute() || $this->isFinanceManagerRoute();
    }

    private function eligibleApprovers(?int $currentApproverId = null)
    {
        return User::query()
            ->with('role:id,slug')
            ->where(function (Builder $query) use ($currentApproverId) {
                $query->where(function (Builder $innerQuery) {
                    $innerQuery->where('is_active', true)
                        ->whereIn('role_id', $this->eligibleApproverRoleIds());
                });

                if ($currentApproverId) {
                    $query->orWhere('id', $currentApproverId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    private function eligibleApproverRoleIds(): array
    {
        return Role::query()
            ->where('slug', Role::ADMIN)
            ->pluck('id')
            ->all();
    }
}
