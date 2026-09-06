<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReportExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceReportExportController extends Controller
{
    public function attendance(Request $request, AttendanceReportExportService $service)
    {
        $file = $service->exportAttendance($request->all());

        return Storage::disk($file['disk'])->download($file['path']);
    }

    public function payroll(Request $request, AttendanceReportExportService $service)
    {
        $file = $service->exportPayroll($request->all());

        return Storage::disk($file['disk'])->download($file['path']);
    }

    public function suspicious(Request $request, AttendanceReportExportService $service)
    {
        $file = $service->exportSuspicious($request->all());

        return Storage::disk($file['disk'])->download($file['path']);
    }
}
