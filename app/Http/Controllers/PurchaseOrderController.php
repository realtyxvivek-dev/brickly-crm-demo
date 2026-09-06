<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAccess;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\ExpenseSubcategory;
use App\Services\PurchaseOrderService;
use App\Services\PayslipPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'all');
        $orders = PurchaseOrder::query()
            ->with(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'category', 'subcategory', 'payments.paymentMethod'])
            ->where('created_by', $request->user()->id)
            ->where('status', '!=', PurchaseOrder::STATUS_DELETED)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrder::statuses(),
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        $requestTypes = PurchaseOrderAccess::allowedRequestTypes(auth()->user());
        abort_if(empty($requestTypes), 403, 'You do not have PO raise access.');

        return view('purchase-orders.form', [
            'order' => new PurchaseOrder([
                'request_type' => array_key_first($requestTypes),
                'purchase_type' => PurchaseOrder::TYPE_NON_PHYSICAL,
            ]),
            'categories' => $this->expenseCategories(),
            'companies' => $this->activeCompanies(),
            'requestTypes' => $requestTypes,
            'action' => route('purchase-orders.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $this->validateOrder($request);
        $this->ensureCanRaise($request, $validated['request_type']);

        DB::transaction(function () use ($request, $validated, $service) {
            $order = PurchaseOrder::create($this->payload($request, $validated));
            $service->storeItems($order, $this->itemPayloads($validated));

            if ($request->input('intent') === 'submit') {
                $service->submit($order->fresh('items'), $request->user());
            }
        });

        return redirect()->route('purchase-orders.index')->with('success', 'Purchase request saved.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeCreator($purchaseOrder);

        return view('purchase-orders.show', [
            'order' => $purchaseOrder->load(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'expenseEntry', 'logs.actor', 'payments.paymentMethod', 'payments.payer', 'deleteRequester', 'deleteReviewer']),
            'statuses' => PurchaseOrder::statuses(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless($purchaseOrder->isEditableByCreator(), 403);

        return view('purchase-orders.form', [
            'order' => $purchaseOrder->load('items'),
            'categories' => $this->expenseCategories(),
            'companies' => $this->activeCompanies(),
            'requestTypes' => PurchaseOrderAccess::allowedRequestTypes(auth()->user()),
            'action' => route('purchase-orders.update', $purchaseOrder),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless($purchaseOrder->isEditableByCreator(), 403);

        $validated = $this->validateOrder($request);
        $this->ensureCanRaise($request, $validated['request_type']);

        DB::transaction(function () use ($request, $purchaseOrder, $validated, $service) {
            $purchaseOrder->update($this->payload($request, $validated, $purchaseOrder));
            $service->storeItems($purchaseOrder, $this->itemPayloads($validated));

            if ($request->input('intent') === 'submit') {
                $service->submit($purchaseOrder->fresh('items'), $request->user(), $purchaseOrder->status === PurchaseOrder::STATUS_REJECTED ? 'resubmitted' : 'submitted');
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Purchase request updated.');
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless($purchaseOrder->isEditableByCreator(), 403);

        $service->submit($purchaseOrder, $request->user(), $purchaseOrder->status === PurchaseOrder::STATUS_REJECTED ? 'resubmitted' : 'submitted');

        return back()->with('success', 'Purchase request sent to Admin.');
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless(in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_REJECTED], true), 403);

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_CANCELLED]);
        $service->log($purchaseOrder, 'cancelled', $request->user(), $request->input('remark'));

        return back()->with('success', 'Purchase request cancelled.');
    }

    public function downloadAttachment(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless($purchaseOrder->attachment_path && Storage::disk('public')->exists($purchaseOrder->attachment_path), 404);

        return Storage::disk('public')->download($purchaseOrder->attachment_path);
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder, PayslipPdfService $pdfService)
    {
        $this->authorizeCreator($purchaseOrder);
        abort_unless($purchaseOrder->po_number, 404);

        $pdf = $pdfService->storeHtmlAsPdf(
            view('purchase-orders.pdf', ['order' => $purchaseOrder->load(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'payments.paymentMethod'])])->render(),
            'purchase-orders/pdf/' . now()->format('Y/m'),
            $purchaseOrder->po_number
        );

        return Storage::disk($pdf['disk'])->download($pdf['path'], $purchaseOrder->po_number . ($pdf['mime_type'] === 'application/pdf' ? '.pdf' : '.html'));
    }

    protected function validateOrder(Request $request): array
    {
        $requestTypes = PurchaseOrderAccess::allowedRequestTypes($request->user());

        if (!$request->has('items') && $request->filled('item_name')) {
            $request->merge([
                'items' => [[
                    'item_name' => $request->input('item_name'),
                    'expense_category_id' => $request->input('expense_category_id'),
                    'expense_subcategory_id' => $request->input('expense_subcategory_id'),
                    'quantity' => 1,
                    'rate' => $request->input('amount'),
                    'tax_amount' => 0,
                ]],
            ]);
        }

        $validated = $request->validate([
            'request_type' => ['required', Rule::in(array_keys($requestTypes))],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'items.*.expense_subcategory_id' => ['required', 'integer', 'exists:expense_subcategories,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:999999'],
            'items.*.rate' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'purpose' => ['required', 'string', 'max:5000'],
            'required_by_date' => ['nullable', 'date'],
            'attachment' => [
                'nullable',
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,pdf,webp',
            ],
            'existing_attachment_path' => ['nullable', 'string', 'max:2048'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $subcategoryMatches = ExpenseSubcategory::query()
                ->whereKey($item['expense_subcategory_id'])
                ->where('expense_category_id', $item['expense_category_id'])
                ->where('is_active', true)
                ->exists();

            if (!$subcategoryMatches) {
                throw ValidationException::withMessages([
                    "items.{$index}.expense_subcategory_id" => 'Selected subcategory does not match the selected category.',
                ]);
            }
        }

        $companyIsActive = Company::query()
            ->whereKey($validated['company_id'])
            ->where('is_active', true)
            ->exists();

        if (!$companyIsActive) {
            throw ValidationException::withMessages([
                'company_id' => 'Please select an active company.',
            ]);
        }

        return $validated;
    }

    protected function payload(Request $request, array $validated, ?PurchaseOrder $order = null): array
    {
        $attachmentPath = $order?->attachment_path;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('purchase-orders/attachments', 'public');
        } elseif (array_key_exists('existing_attachment_path', $validated)) {
            $attachmentPath = $validated['existing_attachment_path'];
        }

        $firstItem = $validated['items'][0] ?? [];

        return [
            'created_by' => $order?->created_by ?: $request->user()->id,
            'company_id' => $validated['company_id'],
            'request_type' => $validated['request_type'],
            'purchase_type' => $order?->purchase_type ?: PurchaseOrder::TYPE_NON_PHYSICAL,
            'vendor_name' => $validated['vendor_name'] ?: 'Not specified',
            'expense_category_id' => $firstItem['expense_category_id'] ?? null,
            'expense_subcategory_id' => $firstItem['expense_subcategory_id'] ?? null,
            'purpose' => $validated['purpose'],
            'required_by_date' => $validated['required_by_date'] ?? null,
            'attachment_path' => $attachmentPath,
        ];
    }

    protected function itemPayloads(array $validated): array
    {
        $categoryCodes = ExpenseCategory::query()
            ->whereIn('id', collect($validated['items'])->pluck('expense_category_id')->filter()->unique())
            ->pluck('code', 'id');

        return collect($validated['items'])->map(function (array $item) use ($categoryCodes) {
            return [
                'item_name' => $item['item_name'],
                'category' => $categoryCodes[$item['expense_category_id']] ?? 'other',
                'expense_category_id' => $item['expense_category_id'],
                'expense_subcategory_id' => $item['expense_subcategory_id'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'tax_amount' => $item['tax_amount'] ?? 0,
            ];
        })->all();
    }

    protected function expenseCategories()
    {
        return ExpenseCategory::query()
            ->with(['subcategories' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function activeCompanies()
    {
        return Company::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    protected function ensureCanRaise(Request $request, string $requestType): void
    {
        if (!PurchaseOrderAccess::canRaise($request->user(), $requestType)) {
            throw ValidationException::withMessages([
                'request_type' => 'You do not have access to raise this PO type.',
            ]);
        }
    }

    protected function authorizeCreator(PurchaseOrder $purchaseOrder): void
    {
        abort_unless((int) $purchaseOrder->created_by === (int) auth()->id(), 403);
    }
}
