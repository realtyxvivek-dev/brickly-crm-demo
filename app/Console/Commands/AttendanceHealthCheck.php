<?php

namespace App\Console\Commands;

use App\Services\PayslipPdfService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AttendanceHealthCheck extends Command
{
    protected $signature = 'attendance:health-check';

    protected $description = 'Run a quick health check for the attendance module setup';

    public function handle(): int
    {
        $requiredTables = [
            'office_locations',
            'attendance_policies',
            'user_attendance_profiles',
            'attendance_events',
            'attendance_records',
            'leave_types',
            'leave_balances',
            'leave_requests',
            'attendance_regularizations',
            'attendance_approvals',
            'attendance_monthly_rollups',
            'payroll_freezes',
            'payroll_payslips',
            'salary_structures',
            'user_salary_profiles',
            'attendance_face_reviews',
            'attendance_suspicion_logs',
            'attendance_overtimes',
        ];

        $requiredRoutes = [
            'attendance.leaves',
            'attendance.regularizations',
            'attendance.overtimes',
            'hr-manager.attendance.index',
            'hr-manager.attendance.problems',
            'hr-manager.attendance.reports',
            'hr-manager.attendance.overtimes',
            'finance-manager.payroll',
            'finance-manager.payslips.index',
            'attendance.payslips',
            'admin.attendance.policies.index',
            'admin.attendance.simulator.index',
            'admin.hr.salary-structures.index',
            'admin.hr.salary-profiles.index',
            'admin.hr.payslip-settings.index',
            'hr-manager.attendance.fraud-reviews',
        ];

        $missingTables = collect($requiredTables)->reject(fn ($table) => Schema::hasTable($table))->values();
        $missingRoutes = collect($requiredRoutes)->reject(fn ($route) => Route::has($route))->values();
        $pdfBinary = app(PayslipPdfService::class)->resolvePdfBinary();
        $attendanceStorageReady = Storage::disk('local')->exists('attendance') || Storage::disk('local')->makeDirectory('attendance');

        $this->info('Attendance module health check');
        $this->newLine();

        if ($missingTables->isEmpty()) {
            $this->info('Tables: OK');
        } else {
            $this->error('Missing tables: ' . $missingTables->implode(', '));
        }

        if ($missingRoutes->isEmpty()) {
            $this->info('Routes: OK');
        } else {
            $this->error('Missing routes: ' . $missingRoutes->implode(', '));
        }

        if ($pdfBinary) {
            $this->info('PDF binary: OK (' . $pdfBinary . ')');
        } else {
            $this->warn('PDF binary: Missing. Payslips/reports will fall back to HTML until `WKHTMLTOPDF_BINARY` is configured.');
        }

        if ($attendanceStorageReady) {
            $this->info('Storage: OK');
        } else {
            $this->error('Storage: attendance directory is not writable.');
        }

        $this->line('Recommended manual checks:');
        $this->line('1. Verify geolocation permission and punch-in on mobile.');
        $this->line('2. Verify camera capture and compressed upload.');
        $this->line('3. Verify scheduler is running in production.');
        $this->line('4. Verify notifications/reminders with a real user.');
        $this->line('5. Verify PDF and Excel downloads on the deployment machine.');

        return $missingTables->isEmpty() && $missingRoutes->isEmpty() && $attendanceStorageReady ? self::SUCCESS : self::FAILURE;
    }
}
