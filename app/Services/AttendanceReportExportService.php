<?php

namespace App\Services;

use App\Models\AttendanceMonthlyRollup;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSuspicionLog;
use App\Models\EmployeeProfile;
use App\Models\PayrollFreeze;
use App\Models\PayrollPayslip;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AttendanceReportExportService
{
    public function __construct(
        protected AttendanceAccessService $attendanceAccessService,
        protected PayslipPdfService $pdfService,
        protected ProductiveDayTrackerService $productiveDayTrackerService
    ) {
    }

    public function exportAttendance(array $filters): array
    {
        $format = strtolower($filters['format'] ?? 'xlsx');
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $officeLocationId = !empty($filters['office_location_id']) ? (int) $filters['office_location_id'] : null;
        $monthDate = Carbon::create($year, $month, 1);
        $profiles = $this->attendanceRegisterProfiles($filters, $monthDate, $officeLocationId);
        $records = AttendanceRecord::query()
            ->with(['user.role', 'officeLocation'])
            ->whereBetween('attendance_date', [$monthDate->copy()->startOfMonth()->toDateString(), $monthDate->copy()->endOfMonth()->toDateString()])
            ->whereIn('user_id', $profiles->pluck('user_id'))
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->orderBy('attendance_date')
            ->orderBy('user_id')
            ->get();
        $recordsByUser = $records->groupBy('user_id');
        $profiles = $this->filterAttendanceRegisterProfilesByRecords($profiles, $recordsByUser, $filters);
        $productiveDayTracker = $this->productiveDayTrackerService->forUsers($profiles, $monthDate->copy()->startOfMonth(), $monthDate->copy()->endOfMonth());
        $rollups = AttendanceMonthlyRollup::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $profiles->pluck('user_id'))
            ->get()
            ->keyBy('user_id');
        $payslips = PayrollPayslip::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $profiles->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        if ($format === 'pdf') {
            $html = view('hr-manager.attendance.exports.monthly-summary-pdf', compact('records', 'year', 'month'))->render();
            return $this->pdfService->storeHtmlAsPdf($html, 'attendance/reports/' . now()->format('Y/m'), "attendance-summary-{$year}-{$month}");
        }

        return $this->storeAttendanceMatrixXlsx($profiles, $recordsByUser, $rollups, $payslips, $productiveDayTracker, $monthDate, "attendance-register-{$year}-{$month}");
    }

    public function exportPayroll(array $filters): array
    {
        $format = strtolower($filters['format'] ?? 'xlsx');
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);
        $officeLocationId = !empty($filters['office_location_id']) ? (int) $filters['office_location_id'] : null;

        $rollups = AttendanceMonthlyRollup::query()
            ->with(['user.role', 'officeLocation'])
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(Carbon::create($year, $month, 1))->select('user_id'))
            ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
            ->orderBy('user_id')
            ->get();

        $payslips = PayrollPayslip::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery(Carbon::create($year, $month, 1))->select('user_id'))
            ->get()
            ->keyBy('user_id');

        if ($format === 'pdf') {
            $freeze = PayrollFreeze::query()
                ->where('year', $year)
                ->where('month', $month)
                ->when($officeLocationId, fn ($query) => $query->where('office_location_id', $officeLocationId))
                ->where('status', 'frozen')
                ->latest()
                ->first();

            if (!$freeze) {
                abort(422, 'Payroll PDF export is available only for frozen months.');
            }

            $html = view('finance-manager.payslips.payroll-summary-pdf', compact('rollups', 'payslips', 'year', 'month'))->render();
            return $this->pdfService->storeHtmlAsPdf($html, 'attendance/reports/' . now()->format('Y/m'), "payroll-summary-{$year}-{$month}");
        }

        $rows = $rollups->map(function (AttendanceMonthlyRollup $rollup) use ($payslips) {
            $payslip = $payslips->get($rollup->user_id);
            return [
                $rollup->user?->name,
                $rollup->officeLocation?->name ?? 'Company',
                (float) $rollup->payable_days,
                (float) $rollup->late_penalty_days,
                (int) $rollup->overtime_minutes,
                (float) $rollup->estimated_salary,
                $payslip?->gross_pay,
                $payslip?->total_deductions,
                $payslip?->net_pay,
                $payslip?->status ?? 'not_generated',
            ];
        });

        return $this->storeXlsx(
            ['Employee', 'Office', 'Payable Days', 'Late Penalty Days', 'Overtime Minutes', 'Estimated Salary', 'Gross Pay', 'Deductions', 'Net Pay', 'Payslip Status'],
            $rows,
            "payroll-summary-{$year}-{$month}"
        );
    }

    public function exportSuspicious(array $filters): array
    {
        $month = Carbon::parse(($filters['year'] ?? now()->year) . '-' . sprintf('%02d', (int) ($filters['month'] ?? now()->month)) . '-01');
        $rows = AttendanceSuspicionLog::query()
            ->with('user')
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month)
            ->whereIn('user_id', $this->attendanceAccessService->enabledProfilesQuery($month)->select('user_id'))
            ->latest()
            ->get()
            ->map(fn ($log) => [
                $log->user?->name,
                $log->flag_type,
                $log->severity,
                $log->status,
                $log->created_at?->format('Y-m-d H:i:s'),
                $log->reviewed_at?->format('Y-m-d H:i:s'),
            ]);

        return $this->storeXlsx(
            ['Employee', 'Flag', 'Severity', 'Status', 'Created At', 'Reviewed At'],
            $rows,
            "suspicious-review-{$month->format('Y-m')}"
        );
    }

    private function storeXlsx(array $headers, Collection $rows, string $baseName): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($rows->values()->all(), null, 'A2');

        $relativePath = 'attendance/reports/' . now()->format('Y/m') . '/' . $baseName . '.xlsx';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return [
            'disk' => 'local',
            'path' => $relativePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    private function attendanceRegisterProfiles(array $filters, Carbon $month, ?int $officeLocationId): Collection
    {
        return User::query()
            ->with(['role', 'employeeProfile', 'salaryProfile', 'attendanceProfile.officeLocation'])
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->where('is_active', true)
                        ->orWhereHas('employeeProfile', function ($profileQuery) {
                            $profileQuery->whereIn('employment_status', [
                                EmployeeProfile::STATUS_ACTIVE,
                                EmployeeProfile::STATUS_ON_NOTICE,
                                EmployeeProfile::STATUS_LONG_LEAVE,
                            ]);
                        });
                });
            })
            ->whereHas('role', fn ($roleQuery) => $roleQuery->whereNotNull('slug'))
            ->where(function ($query) use ($filters) {
                $search = trim((string) ($filters['search'] ?? ''));
                if ($search !== '') {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhereHas('employeeProfile', fn ($profileQuery) => $profileQuery->where('employee_code', 'like', '%' . $search . '%'))
                            ->orWhereHas('attendanceProfile', fn ($profileQuery) => $profileQuery->where('employee_code', 'like', '%' . $search . '%'));
                    });
                }

                if (!empty($filters['role'])) {
                    $query->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', (string) $filters['role']));
                }
            })
            ->when($officeLocationId, function ($query) use ($officeLocationId) {
                $query->whereHas('attendanceProfile', fn ($profileQuery) => $profileQuery->where('office_location_id', $officeLocationId));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->attendanceProfileForExport($user))
            ->values();
    }

    private function attendanceProfileForExport(User $user): UserAttendanceProfile
    {
        $profile = $user->attendanceProfile ?: new UserAttendanceProfile([
            'user_id' => $user->id,
            'employee_code' => $user->employeeProfile?->employee_code,
            'attendance_enabled' => false,
        ]);

        if (!$profile->employee_code && $user->employeeProfile?->employee_code) {
            $profile->employee_code = $user->employeeProfile->employee_code;
        }

        $profile->setRelation('user', $user);
        if ($user->attendanceProfile?->relationLoaded('officeLocation')) {
            $profile->setRelation('officeLocation', $user->attendanceProfile->officeLocation);
        } elseif (!$profile->relationLoaded('officeLocation')) {
            $profile->setRelation('officeLocation', null);
        }

        return $profile;
    }

    private function filterAttendanceRegisterProfilesByRecords(Collection $profiles, Collection $recordsByUser, array $filters): Collection
    {
        $status = (string) ($filters['status'] ?? '');
        $manualOnly = !empty($filters['manual_only']);

        if ($status === '' && !$manualOnly) {
            return $profiles;
        }

        return $profiles->filter(function (UserAttendanceProfile $profile) use ($recordsByUser, $status, $manualOnly) {
            $records = $recordsByUser->get($profile->user_id, collect());

            if ($manualOnly && !$records->contains(fn (AttendanceRecord $record) => $record->hasActiveManualOverride())) {
                return false;
            }

            if ($status !== '' && !$records->contains(fn (AttendanceRecord $record) => $record->status === $status)) {
                return false;
            }

            return true;
        })->values();
    }

    private function storeAttendanceMatrixXlsx(Collection $profiles, Collection $recordsByUser, Collection $rollups, Collection $payslips, Collection $productiveDayTracker, Carbon $month, string $baseName): array
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Sheet');

        $daysInMonth = $month->daysInMonth;
        $lastColumn = Coordinate::stringFromColumnIndex($daysInMonth + 1);
        $recordsCount = $recordsByUser->flatten(1)->count();
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'Attendance Sheet - ' . $month->format('F Y'));
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Auto + manual override matrix | Employees: ' . $profiles->count() . ' | Month Records: ' . $recordsCount . ' | Exported: ' . now()->format('d M Y, h:i A'));

        $sheet->setCellValue('A4', "Employee\nRole\nOffice\nCode");

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $month->copy()->day($day);
            $column = Coordinate::stringFromColumnIndex($day + 1);
            $sheet->setCellValue($column . '4', $date->format('d') . "\n" . strtoupper($date->format('D')));
            $sheet->getColumnDimension($column)->setWidth(17);
        }

        $sheet->getColumnDimension('A')->setWidth(34);
        $row = 5;
        foreach ($profiles as $profile) {
            $user = $profile->user;
            $employeeCode = $profile->employee_code ?: $user?->employeeProfile?->employee_code ?: 'No employee code';
            $role = $user?->role?->display_name ?? $user?->role?->name ?? 'Role not mapped';
            $office = $profile->officeLocation?->name ?? 'No office mapped';
            $sheet->setCellValue('A' . $row, trim(($user?->name ?: 'Employee') . "\n" . $role . "\n" . $office . "\n" . $employeeCode));

            $userRecords = $recordsByUser->get($profile->user_id, collect())->keyBy(fn (AttendanceRecord $record) => $record->attendance_date?->toDateString());
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date = $month->copy()->day($day);
                $column = Coordinate::stringFromColumnIndex($day + 1);
                $cell = $column . $row;
                $record = $userRecords->get($date->toDateString());
                $productiveEntry = $this->productiveDayTrackerService->isSalesProfile($profile)
                    ? $this->productiveDayTrackerService->forCell($productiveDayTracker, $profile->user_id, $date)
                    : null;
                $sheet->setCellValue($cell, $this->attendanceMatrixCellText($record, $productiveEntry));
                $this->applyAttendanceStatusStyle($sheet, $cell, $record?->status);
            }
            $sheet->getRowDimension($row)->setRowHeight(78);
            $row++;
        }

        $lastRow = max(5, $row - 1);
        $sheet->freezePane('B5');
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(4)->setRowHeight(44);
        $sheet->setAutoFilter("A4:{$lastColumn}{$lastRow}");

        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
        $sheet->getStyle("A1:{$lastColumn}4")->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(18)->getColor()->setRGB('0B2B1D');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAF8');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('475569');
        $sheet->getStyle('A2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7FAF8');
        $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6F4');
        $sheet->getStyle("A4:{$lastColumn}4")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A5:A{$lastRow}")->getFont()->setBold(true)->getColor()->setRGB('0F172A');
        $sheet->getStyle("A5:A{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');
        $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B5:{$lastColumn}{$lastRow}")->getFont()->setSize(9);

        for ($rowIndex = 5; $rowIndex <= $lastRow; $rowIndex++) {
            if ($rowIndex % 2 === 0) {
                $sheet->getStyle("A{$rowIndex}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FAFBFC');
            }
        }

        $this->addSalarySummarySheet($spreadsheet, $profiles, $recordsByUser, $rollups, $payslips, $productiveDayTracker, $month);
        $this->addStatusLegendSheet($spreadsheet);

        $relativePath = 'attendance/reports/' . now()->format('Y/m') . '/' . $baseName . '.xlsx';
        $absolutePath = storage_path('app/' . $relativePath);
        if (!is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($absolutePath);

        return [
            'disk' => 'local',
            'path' => $relativePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }

    private function attendanceMatrixCellText(?AttendanceRecord $record, ?array $productiveEntry = null): string
    {
        if (!$record) {
            return trim("-\nNO RECORD\n-- --" . ($productiveEntry ? "\n" . $productiveEntry['label'] : ''));
        }

        $status = strtoupper((string) $record->status);
        $source = strtoupper((string) ($record->status_source ?: 'auto'));
        if ($record->hasActiveManualOverride()) {
            $source = 'MANUAL';
        }

        $in = $record->first_punch_in_at?->format('h:i A') ?: '--';
        $out = $record->last_punch_out_at?->format('h:i A') ?: '--';
        $label = match ($record->status) {
            AttendanceRecord::STATUS_PRESENT => 'P',
            AttendanceRecord::STATUS_ABSENT => 'A',
            AttendanceRecord::STATUS_HALF_DAY => 'HD',
            AttendanceRecord::STATUS_LATE => 'LT',
            AttendanceRecord::STATUS_LEAVE => 'LV',
            AttendanceRecord::STATUS_WEEK_OFF => 'WO',
            AttendanceRecord::STATUS_HOLIDAY => 'H',
            default => $status ?: 'A',
        };

        $note = match ($record->status) {
            AttendanceRecord::STATUS_WEEK_OFF => 'WEEK OFF',
            AttendanceRecord::STATUS_HOLIDAY => 'HOLIDAY',
            AttendanceRecord::STATUS_LEAVE => 'LEAVE',
            default => "{$in} - {$out}",
        };

        return trim("{$label}\n{$source}\n{$note}" . ($productiveEntry ? "\n" . $productiveEntry['label'] : ''));
    }

    private function applyAttendanceStatusStyle($sheet, string $cell, ?string $status): void
    {
        [$fill, $font] = match ($status) {
            AttendanceRecord::STATUS_PRESENT => ['E8F8EF', '046C3C'],
            AttendanceRecord::STATUS_LATE => ['FEF3C7', '92400E'],
            AttendanceRecord::STATUS_HALF_DAY => ['F3E8FF', '6D28D9'],
            AttendanceRecord::STATUS_LEAVE => ['DBEAFE', '1D4ED8'],
            AttendanceRecord::STATUS_WEEK_OFF => ['F1F5F9', '475569'],
            AttendanceRecord::STATUS_HOLIDAY => ['ECFDF5', '047857'],
            AttendanceRecord::STATUS_ABSENT => ['FEE2E2', 'B91C1C'],
            null => ['F8FAFC', '64748B'],
            default => ['F8FAFC', '64748B'],
        };

        $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fill);
        $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setRGB($font);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    private function addSalarySummarySheet(Spreadsheet $spreadsheet, Collection $profiles, Collection $recordsByUser, Collection $rollups, Collection $payslips, Collection $productiveDayTracker, Carbon $month): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Salary Summary');

        $headers = [
            'Employee',
            'Role',
            'Office',
            'Code',
            'Month Days',
            'Monthly Salary',
            'Per Day Formula',
            'Payable Days',
            'Formula Salary',
            'Rollup Salary',
            'Gross Pay',
            'Deductions',
            'Net Pay',
            'Present',
            'Late',
            'Half Day',
            'Week Off',
            'Holiday',
            'Paid Leave',
            'Unpaid Leave',
            'Absent',
            'PD Days',
            'Pending Days',
            'NPD Days',
            'Overtime Min',
            'Payslip Status',
        ];

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', 'Salary Summary - ' . $month->format('F Y'));
        $sheet->mergeCells("A2:{$lastColumn}2");
        $sheet->setCellValue('A2', 'Formula Salary = Monthly Salary / Month Days * Payable Days. Rollup/Payslip values come from payroll records when available.');
        $sheet->fromArray($headers, null, 'A4');

        $row = 5;
        foreach ($profiles as $profile) {
            $user = $profile->user;
            $userRecords = $recordsByUser->get($profile->user_id, collect());
            $rollup = $rollups->get($profile->user_id);
            $payslip = $payslips->get($profile->user_id);
            $counts = $this->attendanceSummaryCounts($userRecords);
            $productiveCounts = $this->productiveDayCountsForProfile($profile, $productiveDayTracker, $month);
            $monthDays = $month->daysInMonth;
            $baseSalary = $this->resolveMonthlySalary($profile, $rollup, $monthDays);
            $payableDays = $rollup ? (float) $rollup->payable_days : $counts['payable_days'];

            $sheet->fromArray([
                $user?->name ?: 'Employee',
                $user?->role?->display_name ?? $user?->role?->name ?? 'Role not mapped',
                $profile->officeLocation?->name ?? 'No office mapped',
                $profile->employee_code ?: $user?->employeeProfile?->employee_code ?: 'No employee code',
                $monthDays,
                $baseSalary,
                null,
                $payableDays,
                null,
                $rollup?->estimated_salary !== null ? (float) $rollup->estimated_salary : null,
                $payslip?->gross_pay !== null ? (float) $payslip->gross_pay : null,
                $payslip?->total_deductions !== null ? (float) $payslip->total_deductions : null,
                $payslip?->net_pay !== null ? (float) $payslip->net_pay : null,
                $rollup ? (float) $rollup->present_days : $counts['present'],
                $rollup ? (int) $rollup->late_count : $counts['late'],
                $rollup ? (float) $rollup->half_days : $counts['half_day'],
                $rollup ? (float) $rollup->weekoff_days : $counts['week_off'],
                $rollup ? (float) $rollup->holiday_days : $counts['holiday'],
                $rollup ? (float) $rollup->paid_leave_days : $counts['paid_leave'],
                $rollup ? (float) $rollup->unpaid_leave_days : $counts['unpaid_leave'],
                $rollup ? (float) $rollup->absent_days : $counts['absent'],
                $productiveCounts['pd'],
                $productiveCounts['pending'],
                $productiveCounts['npd'],
                $rollup ? (int) $rollup->overtime_minutes : 0,
                $payslip?->status ?? 'not_generated',
            ], null, 'A' . $row);

            $sheet->setCellValue("G{$row}", "=IFERROR(F{$row}/E{$row},0)");
            $sheet->setCellValue("I{$row}", "=IFERROR(G{$row}*H{$row},0)");
            $row++;
        }

        $totalRow = $row + 1;
        $lastDataRow = max(5, $row - 1);
        $sheet->setCellValue("A{$totalRow}", 'Total');
        foreach (range('F', 'Y') as $column) {
            if ($column === 'G') {
                continue;
            }
            $sheet->setCellValue("{$column}{$totalRow}", "=SUM({$column}5:{$column}{$lastDataRow})");
        }

        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:{$lastColumn}{$lastDataRow}");
        $sheet->getStyle("A1:{$lastColumn}{$totalRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A1:{$lastColumn}{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
        $sheet->getStyle("A1:{$lastColumn}4")->getFont()->setBold(true);
        $sheet->getStyle('A1')->getFont()->setSize(18)->getColor()->setRGB('0B2B1D');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('475569');
        $sheet->getStyle("A4:{$lastColumn}4")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6F4');
        $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8F8EF');
        $sheet->getStyle("A{$totalRow}:{$lastColumn}{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("F5:M{$totalRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
        $sheet->getStyle("N5:Y{$totalRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }
    }

    private function addStatusLegendSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Status Legend');
        $rows = [
            ['Code', 'Meaning', 'Salary impact'],
            ['P', 'Present', '1 payable day'],
            ['LT', 'Late', '1 payable day before late penalty'],
            ['HD', 'Half Day', '0.5 payable day'],
            ['WO', 'Week Off', 'Payable as per policy'],
            ['H', 'Holiday', 'Payable as per policy'],
            ['LV', 'Leave', 'Paid or unpaid based on record fraction'],
            ['A', 'Absent', '0 payable day'],
            ['-', 'No Record', 'Not counted unless payroll rollup exists'],
            ['PD', 'Productive Day', 'Assigned site visit verified on that date'],
            ['Pending', 'Productive Pending', 'Assigned site visit exists but verification is pending/rejected'],
            ['NPD', 'Non Productive Day', 'Sales user has no assigned site visit on that date'],
        ];

        $sheet->fromArray($rows, null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
        $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F6F4');
        $sheet->getStyle('A1:C' . count($rows))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E5E7EB');
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('C')->setWidth(36);
    }

    private function attendanceSummaryCounts(Collection $records): array
    {
        $present = (float) $records->where('status', AttendanceRecord::STATUS_PRESENT)->count()
            + (float) $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $late = (int) $records->where('status', AttendanceRecord::STATUS_LATE)->count();
        $halfDay = (float) $records->where('status', AttendanceRecord::STATUS_HALF_DAY)->count();
        $paidLeave = (float) $records->where('status', AttendanceRecord::STATUS_LEAVE)
            ->filter(fn (AttendanceRecord $record) => (float) $record->payable_day_fraction > 0)
            ->count();
        $unpaidLeave = (float) $records->where('status', AttendanceRecord::STATUS_LEAVE)
            ->filter(fn (AttendanceRecord $record) => (float) $record->payable_day_fraction <= 0)
            ->count();
        $weekOff = (float) $records->where('status', AttendanceRecord::STATUS_WEEK_OFF)->count();
        $holiday = (float) $records->where('status', AttendanceRecord::STATUS_HOLIDAY)->count();
        $absent = (float) $records->where('status', AttendanceRecord::STATUS_ABSENT)->count();

        return [
            'present' => $present,
            'late' => $late,
            'half_day' => $halfDay,
            'paid_leave' => $paidLeave,
            'unpaid_leave' => $unpaidLeave,
            'week_off' => $weekOff,
            'holiday' => $holiday,
            'absent' => $absent,
            'payable_days' => max(0, $present + ($halfDay * 0.5) + $paidLeave + $weekOff + $holiday),
        ];
    }

    private function productiveDayCountsForProfile(UserAttendanceProfile $profile, Collection $productiveDayTracker, Carbon $month): array
    {
        $counts = [
            ProductiveDayTrackerService::STATUS_PD => 0,
            ProductiveDayTrackerService::STATUS_PENDING => 0,
            ProductiveDayTrackerService::STATUS_NPD => 0,
        ];

        if (!$this->productiveDayTrackerService->isSalesProfile($profile)) {
            return $counts;
        }

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $entry = $this->productiveDayTrackerService->forCell($productiveDayTracker, $profile->user_id, $month->copy()->day($day));
            $counts[$entry['status']]++;
        }

        return $counts;
    }

    private function resolveMonthlySalary(UserAttendanceProfile $profile, ?AttendanceMonthlyRollup $rollup, int $monthDays): float
    {
        $salary = (float) ($profile->user?->salaryProfile?->base_salary ?? 0);

        if ($salary <= 0 && $rollup && (float) $rollup->estimated_salary > 0 && (float) $rollup->payable_days > 0) {
            $salary = round(((float) $rollup->estimated_salary / (float) $rollup->payable_days) * $monthDays, 2);
        }

        return $salary;
    }
}
