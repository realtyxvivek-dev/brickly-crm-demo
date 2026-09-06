<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\PurchaseOrderController as BasePurchaseOrderController;
use App\Models\Company;
use App\Models\ExpenseEntry;
use App\Models\ExpensePaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderAccess;
use App\Models\PurchaseOrderPayment;
use App\Services\PurchaseOrderService;
use App\Services\PayslipPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PurchaseOrderController extends BasePurchaseOrderController
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'all');
        $orders = PurchaseOrder::query()
            ->with(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'payments.paymentMethod'])
            ->where('status', '!=', PurchaseOrder::STATUS_DELETED)
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('finance-manager.purchase-orders.index', [
            'orders' => $orders,
            'statuses' => array_diff_key(PurchaseOrder::statuses(), [PurchaseOrder::STATUS_DELETED => true]),
            'status' => $status,
            'paymentQueueCount' => PurchaseOrder::where('status', PurchaseOrder::STATUS_PAYMENT_PENDING)->count(),
        ]);
    }

    public function create(): View
    {
        return view('finance-manager.purchase-orders.form', [
            'order' => new PurchaseOrder([
                'request_type' => PurchaseOrder::REQUEST_TYPE_NEED_PURCHASE,
                'purchase_type' => PurchaseOrder::TYPE_NON_PHYSICAL,
            ]),
            'categories' => $this->expenseCategories(),
            'companies' => $this->activeCompanies(),
            'requestTypes' => PurchaseOrder::requestTypes(),
            'action' => route('finance-manager.purchase-orders.store'),
            'method' => 'POST',
        ]);
    }

    public function store(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        $validated = $this->validateOrder($request);

        DB::transaction(function () use ($request, $validated, $service) {
            $order = PurchaseOrder::create($this->payload($request, $validated));
            $service->storeItems($order, $this->itemPayloads($validated));

            if ($request->input('intent') === 'submit') {
                $service->submit($order->fresh('items'), $request->user());
            }
        });

        return redirect()->route('finance-manager.purchase-orders.index')->with('success', 'Purchase request saved.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('finance-manager.purchase-orders.show', [
            'order' => $purchaseOrder->load(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'expenseEntry', 'logs.actor', 'payments.paymentMethod', 'payments.payer', 'receiver', 'deleteRequester', 'deleteReviewer']),
            'statuses' => PurchaseOrder::statuses(),
            'paymentMethods' => $this->paymentMethods(),
            'paymentModes' => ExpensePaymentMethod::types(),
            'companies' => $this->activeCompanies(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        abort_unless($purchaseOrder->isEditableByFinanceManager(), 403);

        return view('finance-manager.purchase-orders.form', [
            'order' => $purchaseOrder->load('items'),
            'categories' => $this->expenseCategories(),
            'companies' => $this->activeCompanies(),
            'requestTypes' => PurchaseOrder::requestTypes(),
            'action' => route('finance-manager.purchase-orders.update', $purchaseOrder),
            'method' => 'PUT',
        ]);
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        abort_unless($purchaseOrder->isEditableByFinanceManager(), 403);

        $validated = $this->validateOrder($request);

        DB::transaction(function () use ($request, $purchaseOrder, $validated, $service) {
            $purchaseOrder->update($this->payload($request, $validated, $purchaseOrder));
            $service->storeItems($purchaseOrder, $this->itemPayloads($validated));

            if ($request->input('intent') === 'submit' && in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_REJECTED], true)) {
                $service->submit($purchaseOrder->fresh('items'), $request->user(), $purchaseOrder->status === PurchaseOrder::STATUS_REJECTED ? 'resubmitted' : 'submitted');
            } else {
                $service->log($purchaseOrder->fresh(), 'finance_updated', $request->user());
            }
        });

        return redirect()->route('finance-manager.purchase-orders.show', $purchaseOrder)->with('success', 'Purchase request updated.');
    }

    public function requestDelete(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if (!$purchaseOrder->canRequestDeleteByFinanceManager()) {
            return back()->withErrors(['status' => 'Only draft, submitted, rejected, or payment pending POs can be sent for delete approval.']);
        }

        $validated = $request->validate([
            'delete_request_reason' => ['required', 'string', 'max:3000'],
        ]);

        $service->requestDelete($purchaseOrder, $request->user(), $validated['delete_request_reason']);

        return redirect()->route('finance-manager.purchase-orders.show', $purchaseOrder)->with('success', 'Delete request sent to Admin.');
    }

    public function destroy(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if (!$purchaseOrder->canDeleteDirectlyByFinanceManager()) {
            return back()->withErrors(['status' => 'Only draft, submitted, rejected, payment pending, or delete requested POs can be deleted by Finance Manager.']);
        }

        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'max:3000'],
        ]);

        $service->deleteByFinanceManager($purchaseOrder, $request->user(), $validated['delete_reason']);

        return redirect()->route('finance-manager.purchase-orders.index')->with('success', 'PO deleted/voided by Finance Manager.');
    }

    public function markPaid(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_PAYMENT_PENDING) {
            return back()->withErrors(['status' => 'Only payment pending POs can be paid.']);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'payment_mode' => ['required', Rule::in(array_keys(ExpensePaymentMethod::types()))],
            'expense_payment_method_id' => ['nullable', 'integer', 'exists:expense_payment_methods,id'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
            'remark' => ['nullable', 'string', 'max:3000'],
            'proof' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        if (round((float) $validated['amount'], 2) !== round((float) $purchaseOrder->total_amount, 2)) {
            throw ValidationException::withMessages(['amount' => 'Full PO amount payment is required.']);
        }

        if (!PurchaseOrderAccess::canPay($request->user(), (float) $validated['amount'])) {
            throw ValidationException::withMessages(['amount' => 'You do not have permission or limit to mark this PO payment done.']);
        }

        $this->validatePaymentMethod($validated);
        $this->validatePaymentCompany($validated);

        $proofPath = $request->hasFile('proof')
            ? $request->file('proof')->store('purchase-orders/payments', 'public')
            : null;

        DB::transaction(function () use ($purchaseOrder, $validated, $proofPath, $request) {
            $purchaseOrder->update([
                'company_id' => $validated['company_id'],
            ]);

            PurchaseOrderPayment::create([
                'purchase_order_id' => $purchaseOrder->id,
                'amount' => $validated['amount'],
                'payment_mode' => $validated['payment_mode'],
                'expense_payment_method_id' => $validated['expense_payment_method_id'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'proof_path' => $proofPath,
                'paid_by' => $request->user()->id,
                'paid_at' => $validated['paid_at'],
                'remark' => $validated['remark'] ?? null,
            ]);

            $expenseEntry = $this->createExpenseEntryFromPo($purchaseOrder, $validated, $proofPath, $request);

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_CLOSED,
                'expense_entry_id' => $expenseEntry->id,
            ]);
        });

        return back()->with('success', 'PO payment saved.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if ($purchaseOrder->purchase_type !== PurchaseOrder::TYPE_PHYSICAL || $purchaseOrder->status !== PurchaseOrder::STATUS_PAID) {
            return back()->withErrors(['status' => 'Only paid physical POs can be marked received.']);
        }

        $validated = $request->validate([
            'received_date' => ['required', 'date'],
            'receiving_note' => ['nullable', 'string', 'max:3000'],
            'received_attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,pdf,webp'],
        ]);

        $attachmentPath = $request->hasFile('received_attachment')
            ? $request->file('received_attachment')->store('purchase-orders/receiving', 'public')
            : null;

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_RECEIVED,
            'received_by' => $request->user()->id,
            'received_date' => $validated['received_date'],
            'receiving_note' => $validated['receiving_note'] ?? null,
            'received_attachment_path' => $attachmentPath,
        ]);

        $service->log($purchaseOrder, 'received', $request->user(), $validated['receiving_note'] ?? null);

        return back()->with('success', 'PO marked received.');
    }

    public function downloadAttachment(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->attachment_path && Storage::disk('public')->exists($purchaseOrder->attachment_path), 404);

        return Storage::disk('public')->download($purchaseOrder->attachment_path);
    }

    public function downloadPaymentProof(PurchaseOrderPayment $payment)
    {
        abort_unless($payment->proof_path && Storage::disk('public')->exists($payment->proof_path), 404);

        return Storage::disk('public')->download($payment->proof_path);
    }

    public function downloadPdf(PurchaseOrder $purchaseOrder, PayslipPdfService $pdfService)
    {
        abort_unless($purchaseOrder->po_number, 404);

        $pdf = $pdfService->storeHtmlAsPdf(
            view('purchase-orders.pdf', ['order' => $purchaseOrder->load(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'payments.paymentMethod'])])->render(),
            'purchase-orders/pdf/' . now()->format('Y/m'),
            $purchaseOrder->po_number
        );

        return Storage::disk($pdf['disk'])->download($pdf['path'], $purchaseOrder->po_number . ($pdf['mime_type'] === 'application/pdf' ? '.pdf' : '.html'));
    }

    private function paymentMethods()
    {
        return ExpensePaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function validatePaymentMethod(array $validated): void
    {
        $requiredModes = [
            ExpensePaymentMethod::TYPE_BANK,
            ExpensePaymentMethod::TYPE_UPI,
            ExpensePaymentMethod::TYPE_CREDIT_CARD,
        ];

        if (!in_array($validated['payment_mode'], $requiredModes, true)) {
            return;
        }

        if (empty($validated['expense_payment_method_id'])) {
            throw ValidationException::withMessages(['expense_payment_method_id' => 'Please select payment source.']);
        }

        $method = ExpensePaymentMethod::findOrFail($validated['expense_payment_method_id']);
        if (!$method->is_active || $method->type !== $validated['payment_mode']) {
            throw ValidationException::withMessages(['expense_payment_method_id' => 'Payment source does not match selected mode.']);
        }
    }

    private function validatePaymentCompany(array $validated): void
    {
        $companyExists = Company::query()
            ->whereKey($validated['company_id'])
            ->where('is_active', true)
            ->exists();

        if (!$companyExists) {
            throw ValidationException::withMessages(['company_id' => 'Please select an active company.']);
        }
    }

    private function createExpenseEntryFromPo(PurchaseOrder $purchaseOrder, array $validated, ?string $proofPath, Request $request): ExpenseEntry
    {
        if ($purchaseOrder->expense_entry_id) {
            $existing = $purchaseOrder->expenseEntry()->first();
            if ($existing) {
                return $existing;
            }
        }

        $purchaseOrder->loadMissing(['items.expenseCategory', 'items.expenseSubcategory', 'creator']);
        $poLabel = $purchaseOrder->po_number ?: $purchaseOrder->request_number;
        $firstEntry = null;

        $items = $purchaseOrder->items->isNotEmpty()
            ? $purchaseOrder->items
            : collect([(object) [
                'item_name' => 'Purchase order total',
                'expense_category_id' => $purchaseOrder->expense_category_id,
                'expense_subcategory_id' => $purchaseOrder->expense_subcategory_id,
                'line_total' => $validated['amount'],
            ]]);

        foreach ($items as $item) {
            $entry = ExpenseEntry::create([
                'company_id' => $validated['company_id'],
                'expense_category_id' => $item->expense_category_id ?: $purchaseOrder->expense_category_id,
                'expense_subcategory_id' => $item->expense_subcategory_id ?: $purchaseOrder->expense_subcategory_id,
                'expense_date' => $validated['paid_at'],
                'amount' => round((float) $item->line_total, 2),
                'payment_mode' => $validated['payment_mode'],
                'expense_payment_method_id' => $validated['expense_payment_method_id'] ?? null,
                'paid_to' => $purchaseOrder->vendor_name ?: $purchaseOrder->creator?->name,
                'reference_no' => $validated['reference_no'] ?? null,
                'remarks' => trim('Auto-created from PO ' . $poLabel . ' / item: ' . ($item->item_name ?: 'Item') . '. ' . ($validated['remark'] ?? '')),
                'status' => ExpenseEntry::STATUS_APPROVED,
                'attachment_path' => $proofPath ?: $purchaseOrder->attachment_path,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            $firstEntry ??= $entry;
        }

        return $firstEntry;
    }
}
