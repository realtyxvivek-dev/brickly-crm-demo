<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayrollFreeze;
use App\Services\PayrollWorkflowService;
use Illuminate\Http\Request;

class PayrollApprovalController extends Controller
{
    public function __construct(protected PayrollWorkflowService $workflow)
    {
    }

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $status = (string) $request->input('status', 'all');

        $freezes = PayrollFreeze::query()
            ->with(['items.user.role', 'items.payslip.versions.changedBy', 'lockedByUser'])
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('status', [
                PayrollWorkflowService::FREEZE_HR_LOCKED,
                PayrollWorkflowService::FREEZE_ADMIN_PARTIALLY_APPROVED,
                PayrollWorkflowService::FREEZE_ADMIN_APPROVED,
                PayrollWorkflowService::FREEZE_ADMIN_REJECTED,
            ])
            ->latest()
            ->get();

        $payslips = $freezes
            ->flatMap(fn (PayrollFreeze $freeze) => $freeze->items->map(function ($item) use ($freeze) {
                return $item->payslip?->setRelation('user', $item->user)->setRelation('payrollFreeze', $freeze);
            }))
            ->filter()
            ->when($status !== 'all', fn ($items) => $items->where('status', $status))
            ->values();

        return view('admin.payroll-approvals.index', compact('year', 'month', 'status', 'freezes', 'payslips'));
    }

    public function approve(Request $request, PayrollFreeze $freeze)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array|min:1',
            'payslip_ids.*' => 'integer|exists:payroll_payslips,id',
        ]);

        $count = $this->workflow->approveSelected($freeze, $request->user(), $validated['payslip_ids']);

        return back()->with('success', "{$count} payslip(s) approved.");
    }

    public function reject(Request $request, PayrollFreeze $freeze)
    {
        $validated = $request->validate([
            'payslip_ids' => 'required|array|min:1',
            'payslip_ids.*' => 'integer|exists:payroll_payslips,id',
            'admin_remark' => 'required|string|max:2000',
        ]);

        $count = $this->workflow->rejectSelected($freeze, $request->user(), $validated['payslip_ids'], $validated['admin_remark']);

        return back()->with('success', "{$count} payslip(s) rejected.");
    }
}
