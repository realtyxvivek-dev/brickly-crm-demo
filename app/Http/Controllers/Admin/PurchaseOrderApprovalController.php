<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use App\Services\PayslipPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PurchaseOrderApprovalController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', PurchaseOrder::STATUS_SUBMITTED);
        $orders = PurchaseOrder::query()
            ->with(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'payments.paymentMethod'])
            ->when($status !== PurchaseOrder::STATUS_DELETED, fn ($query) => $query->where('status', '!=', PurchaseOrder::STATUS_DELETED))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchase-orders.index', [
            'orders' => $orders,
            'statuses' => PurchaseOrder::statuses(),
            'status' => $status,
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        return view('admin.purchase-orders.show', [
            'order' => $purchaseOrder->load(['items.expenseCategory', 'items.expenseSubcategory', 'creator', 'company', 'category', 'subcategory', 'expenseEntry', 'logs.actor', 'payments.paymentMethod', 'payments.payer', 'receiver', 'deleteRequester', 'deleteReviewer']),
            'statuses' => PurchaseOrder::statuses(),
        ]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_SUBMITTED) {
            return back()->withErrors(['status' => 'Only submitted POs can be approved.']);
        }

        $service->approve($purchaseOrder, $request->user());

        return back()->with('success', 'PO approved and sent to Finance payment queue.');
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_SUBMITTED) {
            return back()->withErrors(['status' => 'Only submitted POs can be rejected.']);
        }

        $validated = $request->validate([
            'remark' => ['required', 'string', 'max:3000'],
        ]);

        $service->reject($purchaseOrder, $request->user(), $validated['remark']);

        return back()->with('success', 'PO rejected with remark.');
    }

    public function approveDelete(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DELETE_REQUESTED) {
            return back()->withErrors(['status' => 'Only delete requested POs can be deleted.']);
        }

        $service->approveDelete($purchaseOrder, $request->user());

        return redirect()->route('admin.purchase-orders.index', ['status' => PurchaseOrder::STATUS_DELETE_REQUESTED])->with('success', 'PO deleted/voided after approval.');
    }

    public function rejectDelete(Request $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DELETE_REQUESTED) {
            return back()->withErrors(['status' => 'Only delete requested POs can be rejected.']);
        }

        $validated = $request->validate([
            'delete_reject_reason' => ['required', 'string', 'max:3000'],
        ]);

        $service->rejectDelete($purchaseOrder, $request->user(), $validated['delete_reject_reason']);

        return redirect()->route('admin.purchase-orders.show', $purchaseOrder)->with('success', 'PO delete request rejected.');
    }

    public function downloadAttachment(PurchaseOrder $purchaseOrder)
    {
        abort_unless($purchaseOrder->attachment_path && Storage::disk('public')->exists($purchaseOrder->attachment_path), 404);

        return Storage::disk('public')->download($purchaseOrder->attachment_path);
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
}
