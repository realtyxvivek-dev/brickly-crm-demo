<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRegularization;
use App\Models\AttendanceOvertime;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPayslip;
use App\Services\AttendanceAccessService;
use App\Services\LeaveService;
use App\Services\PayslipPdfService;
use App\Services\PayslipVerificationService;
use App\Services\PayrollWorkflowService;
use App\Services\RegularizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceRequestController extends Controller
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected PayslipVerificationService $verificationService,
        protected PayslipPdfService $pdfService
    )
    {
    }

    private function blocksOvertimeForManagerRole(Request $request): bool
    {
        $user = $request->user();

        return $user
            && ($user->isSalesManager() || $user->isSeniorManager() || $user->isAssistantSalesManager());
    }

    public function leaves(Request $request, LeaveService $leaveService)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $leaveService->ensureBalancesForUser($request->user(), now()->year);

        $leaveTypes = LeaveType::where('is_active', true)->orderBy('name')->get();
        $balances = LeaveBalance::with('leaveType')
            ->where('user_id', $request->user()->id)
            ->where('year', now()->year)
            ->whereHas('leaveType', fn ($query) => $query->where('is_active', true))
            ->get();
        $requests = LeaveRequest::with(['leaveType', 'approvals.actor'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('attendance.leaves', compact('leaveTypes', 'balances', 'requests'));
    }

    public function storeLeave(Request $request, LeaveService $leaveService)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date',
            'duration_mode' => 'required|string|in:full_day,half_day_am,half_day_pm',
            'reason' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:8192',
        ]);

        $leaveService->createRequest($request->user(), $validated, $request->file('attachment'));

        return back()->with('success', 'Leave request submitted.');
    }

    public function regularizations(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $requests = AttendanceRegularization::with('approvals.actor')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('attendance.regularizations', compact('requests'));
    }

    public function storeRegularization(Request $request, RegularizationService $regularizationService)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'attendance_date' => 'required|date',
            'request_type' => 'required|string|in:missed_punch_in,missed_punch_out,wrong_status,manual_present,manual_half_day',
            'requested_in_time' => 'nullable|date_format:H:i',
            'requested_out_time' => 'nullable|date_format:H:i',
            'requested_status' => 'nullable|string|in:present,late,half_day,absent',
            'reason' => 'nullable|string|max:2000',
            'proof' => 'nullable|file|max:8192',
        ]);

        $regularizationService->createRequest($request->user(), $validated, $request->file('proof'));

        return back()->with('success', 'Regularization request submitted.');
    }

    public function payslips(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $payslips = PayrollPayslip::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('attendance.payslips', compact('payslips'));
    }

    public function overtimes(Request $request)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        if ($this->blocksOvertimeForManagerRole($request)) {
            return redirect()
                ->route('attendance.regularizations')
                ->with('info', 'Overtime is not available for this role.');
        }

        $requests = AttendanceOvertime::with('approver')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('attendance.overtimes', compact('requests'));
    }

    public function downloadPayslip(Request $request, PayrollPayslip $payslip)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        abort_unless($payslip->user_id === $request->user()->id, 403);
        abort_unless($payslip->canDownloadFinal(), 403, 'Final payslip download is available only after Admin approval.');
        $payslip = $this->verificationService->ensurePdfIsBranded($payslip, $this->pdfService);

        abort_unless($payslip->pdf_path && Storage::disk('local')->exists($payslip->pdf_path), 404);

        return Storage::disk('local')->download($payslip->pdf_path);
    }

    public function requestPayslipCorrection(Request $request, PayrollPayslip $payslip, PayrollWorkflowService $workflow)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $validated = $request->validate([
            'employee_correction_note' => 'required|string|max:2000',
        ]);

        $workflow->requestCorrection($payslip, $request->user(), $validated['employee_correction_note']);

        return back()->with('success', 'Correction request sent to HR.');
    }
}
