<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\PayrollDeductionHead;
use App\Models\PayrollFreeze;
use App\Models\PayrollManualAdjustment;
use App\Models\PayrollPayslip;
use App\Models\User;
use App\Services\AttendanceAccessService;
use App\Services\PayslipGenerationService;
use App\Services\PayslipPdfService;
use App\Services\PayslipVerificationService;
use App\Services\PayrollAdjustmentService;
use App\Services\PayrollWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PayslipController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected PayslipVerificationService $verificationService,
        protected PayslipPdfService $pdfService
    )
    {
    }

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);
        $enabledUserIds = $this->attendanceAccessService->enabledProfilesQuery(now()->setDate($year, $month, 1))->select('user_id');

        $payslips = PayrollPayslip::with('user.role')
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $enabledUserIds)
            ->orderByDesc('generated_at')
            ->get();

        $paymentQueue = PayrollPayslip::with('user.role', 'payrollFreeze')
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('status', [PayrollPayslip::STATUS_ADMIN_APPROVED, PayrollPayslip::STATUS_PAYMENT_PENDING])
            ->whereIn('user_id', $enabledUserIds)
            ->latest('updated_at')
            ->get();

        $adjustments = PayrollManualAdjustment::with(['user.role', 'head', 'creator'])
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(now()->setDate($year, $month, 1))->select('user_id'))
            ->latest()
            ->get();

        $freeze = PayrollFreeze::query()
            ->where('year', $year)
            ->where('month', $month)
            ->where('status', 'frozen')
            ->latest()
            ->first();

        $users = User::with('role')
            ->where('is_active', true)
            ->whereIn('id', $this->attendanceAccessService->enabledProfilesQuery(now()->setDate($year, $month, 1))->select('user_id'))
            ->orderBy('name')
            ->get();
        $heads = PayrollDeductionHead::where('is_active', true)->orderBy('name')->get();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => compact('year', 'month', 'payslips', 'adjustments', 'freeze', 'paymentQueue'),
            ]);
        }

        return view('finance-manager.payslips.index', compact('year', 'month', 'payslips', 'adjustments', 'freeze', 'users', 'heads', 'paymentQueue'));
    }

    public function storeAdjustment(Request $request, PayrollAdjustmentService $service)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payroll_deduction_head_id' => 'nullable|exists:payroll_deduction_heads,id',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'label' => 'required|string|max:255',
            'type' => 'required|string|in:earning,deduction',
            'amount' => 'required|numeric|min:0|max:999999999.99',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $adjustment = $service->create($validated, $request->user());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $adjustment], 201);
        }

        return back()->with('success', 'Manual payroll adjustment saved.');
    }

    public function generate(Request $request, PayslipGenerationService $service)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $freeze = PayrollFreeze::query()
            ->where('year', $validated['year'])
            ->where('month', $validated['month'])
            ->where('status', 'frozen')
            ->latest()
            ->firstOrFail();

        $count = $service->generateForFreeze($freeze);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'generated' => $count]);
        }

        return back()->with('success', "{$count} payslips generated.");
    }

    public function download(PayrollPayslip $payslip)
    {
        $payslip = $this->verificationService->ensurePdfIsBranded($payslip, $this->pdfService);

        abort_unless($payslip->pdf_path && Storage::disk('local')->exists($payslip->pdf_path), 404);

        return Storage::disk('local')->download($payslip->pdf_path);
    }

    public function markPaid(Request $request, PayrollPayslip $payslip, PayrollWorkflowService $workflow)
    {
        $validated = $request->validate([
            'paid_amount' => 'required|numeric|min:0|max:999999999.99',
            'payment_mode' => 'required|string|max:100',
            'payment_reference' => 'required|string|max:255',
            'paid_at' => 'nullable|date',
            'finance_remark' => 'nullable|string|max:2000',
            'payment_proof' => 'nullable|file|max:8192',
        ]);

        if (round((float) $validated['paid_amount'], 2) !== round((float) $payslip->net_pay, 2) && empty($validated['finance_remark'])) {
            return back()->withErrors(['finance_remark' => 'Remark is required when paid amount differs from net salary.']);
        }

        $workflow->markPaid($payslip, $request->user(), $validated, $request->file('payment_proof'));

        return back()->with('success', 'Payslip marked paid.');
    }
}
