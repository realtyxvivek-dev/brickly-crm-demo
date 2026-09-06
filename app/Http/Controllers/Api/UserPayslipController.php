<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollPayslip;
use App\Services\AttendanceAccessService;
use App\Services\PayslipPdfService;
use App\Services\PayslipVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserPayslipController extends Controller
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
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        $payslips = PayrollPayslip::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $payslips]);
    }

    public function download(Request $request, PayrollPayslip $payslip)
    {
        $this->attendanceAccessService->ensureEnabledFor($request->user());

        abort_unless($payslip->user_id === $request->user()->id, 403);
        $payslip = $this->verificationService->ensurePdfIsBranded($payslip, $this->pdfService);

        abort_unless($payslip->pdf_path && Storage::disk('local')->exists($payslip->pdf_path), 404);

        return Storage::disk('local')->download($payslip->pdf_path);
    }
}
