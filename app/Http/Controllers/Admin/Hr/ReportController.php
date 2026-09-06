<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonthlyRollup;
use App\Models\PayrollPayslip;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $monthDate = Carbon::parse($request->input('month', now()->format('Y-m')));
        $rollups = AttendanceMonthlyRollup::query()
            ->where('year', $monthDate->year)
            ->where('month', $monthDate->month)
            ->get();
        $payslipCount = PayrollPayslip::query()
            ->where('year', $monthDate->year)
            ->where('month', $monthDate->month)
            ->count();

        return view('admin.hr.reports', compact('monthDate', 'rollups', 'payslipCount'));
    }
}
