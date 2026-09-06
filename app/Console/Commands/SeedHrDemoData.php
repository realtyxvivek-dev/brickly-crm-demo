<?php

namespace App\Console\Commands;

use App\Models\AttendanceApproval;
use App\Models\AttendanceEvent;
use App\Models\AttendanceFaceReview;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceMonthlyRollup;
use App\Models\AttendanceOvertime;
use App\Models\AttendancePhoto;
use App\Models\AttendancePolicy;
use App\Models\AttendanceRecord;
use App\Models\AttendanceRegularization;
use App\Models\AttendanceSuspicionLog;
use App\Models\AttendanceWeekoff;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OfficeLocation;
use App\Models\PayrollDeductionHead;
use App\Models\PayrollFreeze;
use App\Models\PayrollFreezeItem;
use App\Models\PayrollManualAdjustment;
use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipSetting;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureComponent;
use App\Models\User;
use App\Models\UserAttendanceProfile;
use App\Models\UserSalaryProfile;
use App\Services\PayslipGenerationService;
use App\Services\PayrollComputationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class SeedHrDemoData extends Command
{
    protected $signature = 'hr:seed-demo {--password=password123}';

    protected $description = 'Seed localhost-ready HR demo data';

    public function handle(
        PayrollComputationService $payrollComputationService,
        PayslipGenerationService $payslipGenerationService
    ): int {
        $password = (string) $this->option('password');
        $today = Carbon::today();
        $currentMonthStart = $today->copy()->startOfMonth();
        $previousMonthStart = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();

        $roles = $this->seedRoles();
        $users = $this->seedUsers($roles, $password);
        $office = $this->seedOffice();
        $policy = $this->seedPolicy($office);
        $this->seedWeekoffs($users, $currentMonthStart);
        $holidayDates = $this->seedHolidays($office, $previousMonthStart, $today);
        $leaveTypes = $this->seedLeaveTypes();
        $this->seedLeaveBalances($users, $leaveTypes, (int) $today->year);
        $structure = $this->seedSalaryStructure();
        $heads = $this->seedDeductionHeads();
        $this->seedPayslipSettings();
        $this->seedProfiles($users, $office, $policy, $structure, $previousMonthStart);

        $this->seedPreviousMonthAttendance($users, $office, $policy, $previousMonthStart, $previousMonthEnd, $holidayDates);
        $this->seedCurrentMonthAttendance($users, $office, $policy, $currentMonthStart, $today, $holidayDates);
        $this->seedLeaveRequests($users, $leaveTypes, $policy, $today);
        $this->seedRegularizations($users, $policy);
        $this->seedOvertimes($users, $today);
        $this->seedFraudReviewData($users, $office, $policy, $today);
        $this->seedManualAdjustments($users, $heads, $previousMonthStart);

        $this->resetPreviousMonthPayrollArtifacts($previousMonthStart);
        $freeze = $payrollComputationService->freezeMonth(
            (int) $previousMonthStart->year,
            (int) $previousMonthStart->month,
            $users['finance']
        );
        $generatedPayslips = $payslipGenerationService->generateForFreeze($freeze);

        $this->line('HR demo data seeded successfully.');
        $this->newLine();
        $this->info('Demo logins');
        $this->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', $users['admin']->email, $password],
                ['HR Manager', $users['hr']->email, $password],
                ['Finance Manager', $users['finance']->email, $password],
                ['Sales Executive 1', $users['employee_1']->email, $password],
                ['Sales Executive 2', $users['employee_2']->email, $password],
                ['Sales Executive 3', $users['employee_3']->email, $password],
                ['Sales Executive 4', $users['employee_4']->email, $password],
            ]
        );

        $this->newLine();
        $this->info('Seed summary');
        $this->line('Office: Demo HQ');
        $this->line('Attendance policy: Demo HR Main Policy');
        $this->line('Leave types: CL, SL, PL');
        $this->line('Pending HR inbox items: leave, regularization, overtime, fraud review');
        $this->line('Frozen payroll month: ' . $previousMonthStart->format('F Y'));
        $this->line('Generated payslips: ' . $generatedPayslips);

        return self::SUCCESS;
    }

    private function seedRoles(): array
    {
        $definitions = [
            Role::ADMIN => ['name' => 'Admin', 'description' => 'System Owner - Full system access'],
            Role::HR_MANAGER => ['name' => 'HR Manager', 'description' => 'Human Resources Manager - Manage HR related tasks'],
            Role::FINANCE_MANAGER => ['name' => 'Finance Manager', 'description' => 'Finance Manager - Manage payroll and finance tasks'],
            Role::SALES_EXECUTIVE => ['name' => 'Sales Executive', 'description' => 'Sales Executive - Attendance and lead user'],
        ];

        $roles = [];
        foreach ($definitions as $slug => $definition) {
            $roles[$slug] = Role::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_active' => true,
                ]
            );
        }

        return $roles;
    }

    private function seedUsers(array $roles, string $password): array
    {
        return [
            'admin' => $this->upsertUser('Demo Admin', 'admin.hr.demo@crm.test', '9000000001', $roles[Role::ADMIN], $password),
            'hr' => $this->upsertUser('Hema HR', 'hr.manager.demo@crm.test', '9000000002', $roles[Role::HR_MANAGER], $password),
            'finance' => $this->upsertUser('Farhan Finance', 'finance.manager.demo@crm.test', '9000000003', $roles[Role::FINANCE_MANAGER], $password),
            'employee_1' => $this->upsertUser('Rohit Sales', 'rohit.sales.demo@crm.test', '9000000101', $roles[Role::SALES_EXECUTIVE], $password),
            'employee_2' => $this->upsertUser('Priya Sales', 'priya.sales.demo@crm.test', '9000000102', $roles[Role::SALES_EXECUTIVE], $password),
            'employee_3' => $this->upsertUser('Aman Sales', 'aman.sales.demo@crm.test', '9000000103', $roles[Role::SALES_EXECUTIVE], $password),
            'employee_4' => $this->upsertUser('Nisha Sales', 'nisha.sales.demo@crm.test', '9000000104', $roles[Role::SALES_EXECUTIVE], $password),
        ];
    }

    private function upsertUser(string $name, string $email, string $phone, Role $role, string $password): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
                'role_id' => $role->id,
                'is_active' => true,
            ]
        );
    }

    private function seedOffice(): OfficeLocation
    {
        return OfficeLocation::updateOrCreate(
            ['code' => 'DEMO-HQ'],
            [
                'name' => 'Demo HQ',
                'address' => '4th Floor, Demo Plaza, Jaipur',
                'latitude' => 26.9124,
                'longitude' => 75.7873,
                'radius_meters' => 150,
                'is_active' => true,
            ]
        );
    }

    private function seedPolicy(OfficeLocation $office): AttendancePolicy
    {
        return AttendancePolicy::updateOrCreate(
            ['name' => 'Demo HR Main Policy'],
            [
                'office_location_id' => $office->id,
                'is_default' => true,
                'reminder_time' => '09:00:00',
                'late_after_time' => '09:30:00',
                'grace_minutes' => 10,
                'normal_window_end_time' => '11:30:00',
                'half_day_start_time' => '13:00:00',
                'half_day_end_time' => '16:00:00',
                'geo_fence_required' => true,
                'photo_required' => true,
                'selfie_required' => true,
                'face_review_required' => false,
                'payroll_block_on_pending_face_review' => true,
                'duplicate_photo_threshold' => 1,
                'face_compare_provider' => 'manual_review',
                'liveness_provider' => 'manual_review',
                'provider_settings_json' => ['mode' => 'demo'],
                'compress_max_width' => 1280,
                'compress_max_height' => 1280,
                'compress_quality' => 80,
                'suspicious_geo_threshold_meters' => 180,
                'approval_mode_leave' => 'hr_only',
                'approval_mode_regularization' => 'hr_only',
                'regularization_abuse_threshold' => 3,
                'late_penalty_type' => 'half_day',
                'late_penalty_threshold' => 3,
                'overtime_enabled' => true,
                'overtime_after_minutes' => 540,
                'overtime_min_minutes' => 30,
                'is_active' => true,
            ]
        );
    }

    private function seedWeekoffs(array $users, Carbon $effectiveFrom): void
    {
        foreach (['employee_1', 'employee_2', 'employee_3', 'employee_4'] as $key) {
            AttendanceWeekoff::updateOrCreate(
                [
                    'user_id' => $users[$key]->id,
                    'day_of_week' => 0,
                    'effective_from' => $effectiveFrom->toDateString(),
                ],
                ['effective_to' => null]
            );
        }
    }

    private function seedHolidays(OfficeLocation $office, Carbon $previousMonthStart, Carbon $today): array
    {
        $dates = [
            $previousMonthStart->copy()->day(14)->toDateString() => 'Holi Holiday',
            $today->copy()->startOfMonth()->day(min(10, $today->daysInMonth))->toDateString() => 'Founders Day',
        ];

        foreach ($dates as $date => $name) {
            AttendanceHoliday::updateOrCreate(
                ['office_location_id' => $office->id, 'holiday_date' => $date],
                ['name' => $name, 'is_paid' => true]
            );
        }

        return array_keys($dates);
    }

    private function seedLeaveTypes(): array
    {
        return [
            'CL' => LeaveType::updateOrCreate(
                ['code' => 'CL'],
                ['name' => 'Casual Leave', 'is_paid' => true, 'allow_half_day' => true, 'annual_quota' => 6, 'is_active' => true]
            ),
            'SL' => LeaveType::updateOrCreate(
                ['code' => 'SL'],
                ['name' => 'Sick Leave', 'is_paid' => true, 'allow_half_day' => true, 'annual_quota' => 6, 'is_active' => true]
            ),
            'PL' => LeaveType::updateOrCreate(
                ['code' => 'PL'],
                ['name' => 'Privilege Leave', 'is_paid' => true, 'allow_half_day' => true, 'annual_quota' => 12, 'is_active' => true]
            ),
        ];
    }

    private function seedLeaveBalances(array $users, array $leaveTypes, int $year): void
    {
        foreach (['employee_1', 'employee_2', 'employee_3', 'employee_4'] as $key) {
            foreach ($leaveTypes as $leaveType) {
                LeaveBalance::updateOrCreate(
                    [
                        'user_id' => $users[$key]->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                    ],
                    [
                        'opening_balance' => $leaveType->annual_quota,
                        'credited' => 0,
                        'used' => 0,
                        'remaining' => $leaveType->annual_quota,
                    ]
                );
            }
        }
    }

    private function seedSalaryStructure(): SalaryStructure
    {
        $structure = SalaryStructure::updateOrCreate(
            ['name' => 'Demo Sales Hybrid Structure'],
            ['is_active' => true]
        );

        $components = [
            ['component_type' => 'earning', 'code' => 'HRA', 'label' => 'HRA', 'calc_type' => 'percent_of_base', 'value' => 20, 'display_order' => 1],
            ['component_type' => 'earning', 'code' => 'CONV', 'label' => 'Conveyance', 'calc_type' => 'fixed', 'value' => 2500, 'display_order' => 2],
            ['component_type' => 'earning', 'code' => 'OA', 'label' => 'Other Allowance', 'calc_type' => 'fixed', 'value' => 1800, 'display_order' => 3],
        ];

        foreach ($components as $component) {
            SalaryStructureComponent::updateOrCreate(
                ['salary_structure_id' => $structure->id, 'code' => $component['code']],
                $component + ['is_active' => true]
            );
        }

        return $structure->fresh('components');
    }

    private function seedDeductionHeads(): array
    {
        return [
            'attendance' => PayrollDeductionHead::updateOrCreate(['code' => 'ATTN_DED'], ['name' => 'Attendance Deduction', 'type' => 'deduction', 'is_active' => true]),
            'late' => PayrollDeductionHead::updateOrCreate(['code' => 'LATE_PEN'], ['name' => 'Late Penalty', 'type' => 'deduction', 'is_active' => true]),
            'advance' => PayrollDeductionHead::updateOrCreate(['code' => 'ADVANCE'], ['name' => 'Advance Recovery', 'type' => 'deduction', 'is_active' => true]),
            'bonus' => PayrollDeductionHead::updateOrCreate(['code' => 'BONUS'], ['name' => 'Performance Bonus', 'type' => 'earning', 'is_active' => true]),
        ];
    }

    private function seedPayslipSettings(): void
    {
        PayrollPayslipSetting::query()->firstOrCreate([], [
            'payslip_prefix' => 'DEMO',
            'company_name' => 'Demo CRM Private Limited',
            'header_text' => 'Attendance linked payroll demo',
            'footer_text' => 'System generated payslip for localhost testing',
            'default_notes' => 'This is demo payroll data for localhost testing.',
            'signatory_name' => 'Demo HR Head',
            'signatory_title' => 'HR Director',
        ]);
    }

    private function seedProfiles(array $users, OfficeLocation $office, AttendancePolicy $policy, SalaryStructure $structure, Carbon $effectiveFrom): void
    {
        $profiles = [
            'employee_1' => ['code' => 'EMP101', 'base_salary' => 32000],
            'employee_2' => ['code' => 'EMP102', 'base_salary' => 36000],
            'employee_3' => ['code' => 'EMP103', 'base_salary' => 30000],
            'employee_4' => ['code' => 'EMP104', 'base_salary' => 28000],
        ];

        foreach ($profiles as $key => $profile) {
            UserAttendanceProfile::updateOrCreate(
                ['user_id' => $users[$key]->id],
                [
                    'office_location_id' => $office->id,
                    'attendance_policy_id' => $policy->id,
                    'employee_code' => $profile['code'],
                    'salary_mode' => 'hybrid',
                    'attendance_enabled' => true,
                    'attendance_rollout_stage' => 'pilot',
                    'base_salary' => $profile['base_salary'],
                    'effective_from' => $effectiveFrom->toDateString(),
                ]
            );

            UserSalaryProfile::updateOrCreate(
                [
                    'user_id' => $users[$key]->id,
                    'effective_from' => $effectiveFrom->toDateString(),
                ],
                [
                    'salary_structure_id' => $structure->id,
                    'base_salary' => $profile['base_salary'],
                    'effective_to' => null,
                ]
            );
        }
    }

    private function seedPreviousMonthAttendance(array $users, OfficeLocation $office, AttendancePolicy $policy, Carbon $start, Carbon $end, array $holidayDates): void
    {
        $overrides = [
            'employee_1' => [
                $start->copy()->day(4)->toDateString() => ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:58', 'out' => '18:12'],
                $start->copy()->day(11)->toDateString() => ['status' => AttendanceRecord::STATUS_HALF_DAY, 'in' => '13:15', 'out' => '18:05'],
                $start->copy()->day(24)->toDateString() => ['status' => AttendanceRecord::STATUS_ABSENT],
                $start->copy()->day(27)->toDateString() => ['status' => AttendanceRecord::STATUS_ABSENT],
                $start->copy()->day(30)->toDateString() => ['status' => AttendanceRecord::STATUS_PRESENT, 'in' => '09:18', 'out' => '20:15'],
            ],
            'employee_2' => [
                $start->copy()->day(5)->toDateString() => ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:55', 'out' => '18:01'],
                $start->copy()->day(6)->toDateString() => ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:52', 'out' => '18:00'],
                $start->copy()->day(7)->toDateString() => ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:59', 'out' => '18:04'],
                $start->copy()->day(14)->toDateString() => ['status' => AttendanceRecord::STATUS_ABSENT],
                $start->copy()->day(21)->toDateString() => ['status' => AttendanceRecord::STATUS_HALF_DAY, 'in' => '13:25', 'out' => '18:02'],
            ],
            'employee_3' => [
                $start->copy()->day(9)->toDateString() => ['status' => AttendanceRecord::STATUS_HALF_DAY, 'in' => '13:35', 'out' => '18:10'],
                $start->copy()->day(20)->toDateString() => ['status' => AttendanceRecord::STATUS_ABSENT],
            ],
            'employee_4' => [
                $start->copy()->day(12)->toDateString() => ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:50', 'out' => '18:00'],
                $start->copy()->day(26)->toDateString() => ['status' => AttendanceRecord::STATUS_ABSENT],
            ],
        ];

        foreach (['employee_1', 'employee_2', 'employee_3', 'employee_4'] as $key) {
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $date = $cursor->toDateString();
                $spec = $overrides[$key][$date] ?? null;

                if (!$spec) {
                    if (in_array($date, $holidayDates, true)) {
                        $spec = ['status' => AttendanceRecord::STATUS_HOLIDAY];
                    } elseif ($cursor->dayOfWeek === 0) {
                        $spec = ['status' => AttendanceRecord::STATUS_WEEK_OFF];
                    } else {
                        $spec = ['status' => AttendanceRecord::STATUS_PRESENT, 'in' => '09:20', 'out' => '18:10'];
                    }
                }

                $this->upsertAttendanceRecord($users[$key], $office, $policy, $cursor, $spec);
                $cursor->addDay();
            }
        }
    }

    private function seedCurrentMonthAttendance(array $users, OfficeLocation $office, AttendancePolicy $policy, Carbon $start, Carbon $today, array $holidayDates): void
    {
        foreach (['employee_1', 'employee_2', 'employee_3', 'employee_4'] as $key) {
            $cursor = $start->copy();
            while ($cursor->lt($today)) {
                $date = $cursor->toDateString();
                if (in_array($date, $holidayDates, true)) {
                    $this->upsertAttendanceRecord($users[$key], $office, $policy, $cursor, ['status' => AttendanceRecord::STATUS_HOLIDAY]);
                } elseif ($cursor->dayOfWeek === 0) {
                    $this->upsertAttendanceRecord($users[$key], $office, $policy, $cursor, ['status' => AttendanceRecord::STATUS_WEEK_OFF]);
                } else {
                    $this->upsertAttendanceRecord($users[$key], $office, $policy, $cursor, ['status' => AttendanceRecord::STATUS_PRESENT, 'in' => '09:18', 'out' => '18:05']);
                }
                $cursor->addDay();
            }
        }

        $this->upsertAttendanceRecord($users['employee_1'], $office, $policy, $today, ['status' => AttendanceRecord::STATUS_PRESENT, 'in' => '09:15', 'out' => '18:12']);
        $this->upsertAttendanceRecord($users['employee_2'], $office, $policy, $today, ['status' => AttendanceRecord::STATUS_LATE, 'in' => '09:54', 'out' => null, 'is_suspicious' => true, 'flags' => ['outside_radius', 'duplicate_photo']]);
        $this->upsertAttendanceRecord($users['employee_3'], $office, $policy, $today, ['status' => AttendanceRecord::STATUS_HALF_DAY, 'in' => '13:20', 'out' => null]);
        AttendanceRecord::query()
            ->where('user_id', $users['employee_4']->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->delete();
    }

    private function upsertAttendanceRecord(User $user, OfficeLocation $office, AttendancePolicy $policy, Carbon $date, array $spec): AttendanceRecord
    {
        $inAt = !empty($spec['in']) ? Carbon::parse($date->toDateString() . ' ' . $spec['in']) : null;
        $outAt = !empty($spec['out']) ? Carbon::parse($date->toDateString() . ' ' . $spec['out']) : null;
        $status = $spec['status'];
        $payable = match ($status) {
            AttendanceRecord::STATUS_HALF_DAY => 0.5,
            AttendanceRecord::STATUS_ABSENT => 0.0,
            default => 1.0,
        };

        $lateMinutes = 0;
        if ($status === AttendanceRecord::STATUS_LATE && $inAt) {
            $lateBaseline = Carbon::parse($date->toDateString() . ' 09:40:00');
            $lateMinutes = $inAt->gt($lateBaseline) ? $lateBaseline->diffInMinutes($inAt) : 0;
        }

        $workedMinutes = ($inAt && $outAt && $outAt->gt($inAt)) ? $inAt->diffInMinutes($outAt) : 0;
        $statusSource = $spec['source'] ?? match ($status) {
            AttendanceRecord::STATUS_HOLIDAY => 'holiday',
            AttendanceRecord::STATUS_WEEK_OFF => 'weekoff',
            default => 'auto',
        };

        return AttendanceRecord::updateOrCreate(
            [
                'user_id' => $user->id,
                'attendance_date' => $date->toDateString(),
            ],
            [
                'office_location_id' => $office->id,
                'attendance_policy_id' => $policy->id,
                'first_punch_in_at' => $inAt,
                'last_punch_out_at' => $outAt,
                'status' => $status,
                'status_source' => $statusSource,
                'late_minutes' => $lateMinutes,
                'worked_minutes' => $workedMinutes,
                'payable_day_fraction' => $payable,
                'has_missing_punch_out' => $inAt !== null && $outAt === null,
                'is_suspicious' => $spec['is_suspicious'] ?? false,
                'fraud_review_status' => $spec['fraud_review_status'] ?? 'clear',
                'fraud_payroll_blocked' => $spec['fraud_payroll_blocked'] ?? false,
                'fraud_review_reason' => $spec['fraud_review_reason'] ?? null,
                'suspicion_flags_json' => $spec['flags'] ?? [],
                'finalized_at' => now(),
            ]
        );
    }

    private function seedLeaveRequests(array $users, array $leaveTypes, AttendancePolicy $policy, Carbon $today): void
    {
        $approvedLeaveDate = $today->copy()->subMonthNoOverflow()->day(18);
        $approvedLeave = LeaveRequest::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'leave_type_id' => $leaveTypes['PL']->id,
                'from_date' => $approvedLeaveDate->toDateString(),
                'to_date' => $approvedLeaveDate->toDateString(),
            ],
            [
                'duration_mode' => 'full_day',
                'days_requested' => 1,
                'reason' => 'Family function demo approved leave',
                'status' => 'approved',
                'final_approved_at' => now()->subDays(10),
            ]
        );
        $this->syncApproval($approvedLeave, $users['hr'], 'hr', 'approved', 'Approved in demo seed.');
        $this->upsertAttendanceRecord(
            $users['employee_1'],
            OfficeLocation::firstOrFail(),
            $policy,
            $approvedLeaveDate,
            ['status' => AttendanceRecord::STATUS_LEAVE, 'source' => 'leave']
        );

        LeaveBalance::query()
            ->where('user_id', $users['employee_1']->id)
            ->where('leave_type_id', $leaveTypes['PL']->id)
            ->where('year', $today->year)
            ->update(['used' => 1, 'remaining' => max(0, (float) $leaveTypes['PL']->annual_quota - 1)]);

        $pendingLeaveFrom = $today->copy()->addDays(4);
        $pendingLeave = LeaveRequest::updateOrCreate(
            [
                'user_id' => $users['employee_4']->id,
                'leave_type_id' => $leaveTypes['CL']->id,
                'from_date' => $pendingLeaveFrom->toDateString(),
                'to_date' => $pendingLeaveFrom->copy()->addDay()->toDateString(),
            ],
            [
                'duration_mode' => 'full_day',
                'days_requested' => 2,
                'reason' => 'Demo pending leave request for HR inbox',
                'status' => 'pending',
                'final_approved_at' => null,
            ]
        );
        $this->ensurePendingApproval($pendingLeave, 'hr');
    }

    private function seedRegularizations(array $users, AttendancePolicy $policy): void
    {
        $approvedDate = Carbon::today()->subMonthNoOverflow()->day(27);
        $approvedRegularization = AttendanceRegularization::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'attendance_date' => $approvedDate->toDateString(),
            ],
            [
                'request_type' => 'missed_punch_out',
                'requested_in_time' => Carbon::parse($approvedDate->toDateString() . ' 09:25:00'),
                'requested_out_time' => Carbon::parse($approvedDate->toDateString() . ' 18:40:00'),
                'requested_status' => AttendanceRecord::STATUS_PRESENT,
                'reason' => 'Demo approved regularization',
                'status' => 'approved',
                'abuse_score_snapshot' => 0,
                'final_approved_at' => now()->subDays(7),
            ]
        );
        $this->syncApproval($approvedRegularization, $users['hr'], 'hr', 'approved', 'Approved in demo seed.');
        $this->upsertAttendanceRecord(
            $users['employee_1'],
            OfficeLocation::firstOrFail(),
            $policy,
            $approvedDate,
            [
                'status' => AttendanceRecord::STATUS_PRESENT,
                'in' => '09:25',
                'out' => '18:40',
                'source' => 'regularization',
            ]
        );

        $pendingDate = Carbon::today()->subDay();
        $pendingRegularization = AttendanceRegularization::updateOrCreate(
            [
                'user_id' => $users['employee_3']->id,
                'attendance_date' => $pendingDate->toDateString(),
            ],
            [
                'request_type' => 'missed_punch_in',
                'requested_in_time' => Carbon::parse($pendingDate->toDateString() . ' 09:40:00'),
                'requested_out_time' => Carbon::parse($pendingDate->toDateString() . ' 18:02:00'),
                'requested_status' => AttendanceRecord::STATUS_PRESENT,
                'reason' => 'Demo pending regularization request',
                'status' => 'pending',
                'abuse_score_snapshot' => 1,
                'final_approved_at' => null,
            ]
        );
        $this->ensurePendingApproval($pendingRegularization, 'hr');
    }

    private function seedOvertimes(array $users, Carbon $today): void
    {
        $approvedDate = $today->copy()->subMonthNoOverflow()->day(30);
        $approvedRecord = AttendanceRecord::query()
            ->where('user_id', $users['employee_1']->id)
            ->whereDate('attendance_date', $approvedDate->toDateString())
            ->firstOrFail();

        AttendanceOvertime::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'attendance_date' => $approvedDate->toDateString(),
            ],
            [
                'attendance_record_id' => $approvedRecord->id,
                'worked_minutes' => 657,
                'overtime_minutes' => 117,
                'status' => 'approved',
                'source' => 'system',
                'remarks' => 'Demo approved overtime',
                'approved_by' => $users['hr']->id,
                'approved_at' => now()->subDays(5),
            ]
        );

        $pendingDate = $today->copy()->subDay();
        $pendingRecord = AttendanceRecord::query()
            ->where('user_id', $users['employee_2']->id)
            ->whereDate('attendance_date', $pendingDate->toDateString())
            ->first();

        if ($pendingRecord) {
            AttendanceOvertime::updateOrCreate(
                [
                    'user_id' => $users['employee_2']->id,
                    'attendance_date' => $pendingDate->toDateString(),
                ],
                [
                    'attendance_record_id' => $pendingRecord->id,
                    'worked_minutes' => 620,
                    'overtime_minutes' => 80,
                    'status' => 'pending',
                    'source' => 'system',
                    'remarks' => 'Demo pending overtime approval',
                    'approved_by' => null,
                    'approved_at' => null,
                ]
            );
        }
    }

    private function seedFraudReviewData(array $users, OfficeLocation $office, AttendancePolicy $policy, Carbon $today): void
    {
        $image = $this->ensureDemoImage('attendance/demo/demo-selfie-priya.png');
        $previousDate = $today->copy()->subDay();

        $previousPhoto = AttendancePhoto::updateOrCreate(
            ['user_id' => $users['employee_2']->id, 'file_path' => 'attendance/demo/demo-selfie-priya-prev.png'],
            [
                'mime_type' => 'image/png',
                'file_size' => $image['size'],
                'compressed_size' => $image['size'],
                'width' => 1,
                'height' => 1,
                'compression_quality' => 80,
                'file_hash' => $image['hash'],
                'meta_json' => ['source' => 'demo'],
                'captured_at' => $previousDate->copy()->setTime(9, 55),
            ]
        );

        $currentPhoto = AttendancePhoto::updateOrCreate(
            ['user_id' => $users['employee_2']->id, 'file_path' => $image['path']],
            [
                'mime_type' => 'image/png',
                'file_size' => $image['size'],
                'compressed_size' => $image['size'],
                'width' => 1,
                'height' => 1,
                'compression_quality' => 80,
                'file_hash' => $image['hash'],
                'meta_json' => ['source' => 'demo'],
                'captured_at' => $today->copy()->setTime(9, 54),
            ]
        );

        AttendanceEvent::updateOrCreate(
            [
                'user_id' => $users['employee_2']->id,
                'event_date' => $previousDate->toDateString(),
                'event_type' => AttendanceEvent::TYPE_PUNCH_IN,
            ],
            [
                'event_time' => $previousDate->copy()->setTime(9, 55),
                'source' => 'web',
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'office_location_id' => $office->id,
                'geo_distance_meters' => 20,
                'inside_geo_fence' => true,
                'photo_id' => $previousPhoto->id,
                'device_fingerprint' => 'demo-device-priya',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo Browser',
                'meta_json' => ['demo' => true],
            ]
        );

        $todayPunchIn = AttendanceEvent::updateOrCreate(
            [
                'user_id' => $users['employee_2']->id,
                'event_date' => $today->toDateString(),
                'event_type' => AttendanceEvent::TYPE_PUNCH_IN,
            ],
            [
                'event_time' => $today->copy()->setTime(9, 54),
                'source' => 'web',
                'latitude' => $office->latitude + 0.0015,
                'longitude' => $office->longitude + 0.0015,
                'office_location_id' => $office->id,
                'geo_distance_meters' => 210,
                'inside_geo_fence' => false,
                'photo_id' => $currentPhoto->id,
                'device_fingerprint' => 'demo-device-priya',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo Browser',
                'meta_json' => ['demo' => true],
            ]
        );

        AttendanceEvent::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'event_date' => $today->toDateString(),
                'event_type' => AttendanceEvent::TYPE_PUNCH_IN,
            ],
            [
                'event_time' => $today->copy()->setTime(9, 15),
                'source' => 'web',
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'office_location_id' => $office->id,
                'geo_distance_meters' => 10,
                'inside_geo_fence' => true,
                'photo_id' => null,
                'device_fingerprint' => 'demo-device-rohit',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo Browser',
                'meta_json' => ['demo' => true],
            ]
        );

        AttendanceEvent::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'event_date' => $today->toDateString(),
                'event_type' => AttendanceEvent::TYPE_PUNCH_OUT,
            ],
            [
                'event_time' => $today->copy()->setTime(18, 12),
                'source' => 'web',
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'office_location_id' => $office->id,
                'geo_distance_meters' => 12,
                'inside_geo_fence' => true,
                'photo_id' => null,
                'device_fingerprint' => 'demo-device-rohit',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo Browser',
                'meta_json' => ['demo' => true],
            ]
        );

        AttendanceEvent::updateOrCreate(
            [
                'user_id' => $users['employee_3']->id,
                'event_date' => $today->toDateString(),
                'event_type' => AttendanceEvent::TYPE_PUNCH_IN,
            ],
            [
                'event_time' => $today->copy()->setTime(13, 20),
                'source' => 'web',
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'office_location_id' => $office->id,
                'geo_distance_meters' => 18,
                'inside_geo_fence' => true,
                'photo_id' => null,
                'device_fingerprint' => 'demo-device-aman',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Demo Browser',
                'meta_json' => ['demo' => true],
            ]
        );

        $record = AttendanceRecord::query()
            ->where('user_id', $users['employee_2']->id)
            ->whereDate('attendance_date', $today->toDateString())
            ->firstOrFail();

        $record->update([
            'office_location_id' => $office->id,
            'attendance_policy_id' => $policy->id,
            'fraud_review_status' => 'pending_review',
            'fraud_payroll_blocked' => true,
            'fraud_review_reason' => 'duplicate_photo, outside_radius',
            'is_suspicious' => true,
            'suspicion_flags_json' => ['duplicate_photo', 'outside_radius'],
        ]);

        AttendanceFaceReview::updateOrCreate(
            ['attendance_record_id' => $record->id],
            [
                'attendance_event_id' => $todayPunchIn->id,
                'attendance_photo_id' => $currentPhoto->id,
                'user_id' => $users['employee_2']->id,
                'status' => 'pending_review',
                'remarks' => 'Duplicate selfie hash and outside geo radius.',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        AttendanceSuspicionLog::updateOrCreate(
            [
                'user_id' => $users['employee_2']->id,
                'attendance_record_id' => $record->id,
                'flag_type' => 'geo_outside',
            ],
            [
                'event_id' => $todayPunchIn->id,
                'severity' => 'high',
                'details_json' => ['distance' => 210, 'threshold' => $policy->suspicious_geo_threshold_meters],
                'status' => 'open',
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );
    }

    private function seedManualAdjustments(array $users, array $heads, Carbon $month): void
    {
        PayrollManualAdjustment::updateOrCreate(
            [
                'user_id' => $users['employee_1']->id,
                'year' => $month->year,
                'month' => $month->month,
                'label' => 'Advance Recovery',
            ],
            [
                'payroll_deduction_head_id' => $heads['advance']->id,
                'type' => 'deduction',
                'amount' => 1500,
                'remarks' => 'Demo advance deduction',
                'created_by' => $users['finance']->id,
            ]
        );

        PayrollManualAdjustment::updateOrCreate(
            [
                'user_id' => $users['employee_2']->id,
                'year' => $month->year,
                'month' => $month->month,
                'label' => 'Performance Bonus',
            ],
            [
                'payroll_deduction_head_id' => $heads['bonus']->id,
                'type' => 'earning',
                'amount' => 2000,
                'remarks' => 'Demo bonus adjustment',
                'created_by' => $users['finance']->id,
            ]
        );
    }

    private function resetPreviousMonthPayrollArtifacts(Carbon $month): void
    {
        $freezeIds = PayrollFreeze::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->pluck('id');

        if ($freezeIds->isNotEmpty()) {
            PayrollPayslip::query()->whereIn('payroll_freeze_id', $freezeIds)->delete();
            PayrollFreezeItem::query()->whereIn('payroll_freeze_id', $freezeIds)->delete();
        }

        PayrollFreeze::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->delete();

        AttendanceMonthlyRollup::query()
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->update([
                'is_frozen' => false,
                'frozen_at' => null,
            ]);
    }

    private function syncApproval(object $approvable, User $actor, string $stepType, string $action, string $remarks): void
    {
        AttendanceApproval::updateOrCreate(
            [
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->id,
                'step_type' => $stepType,
            ],
            [
                'actor_user_id' => $actor->id,
                'action' => $action,
                'remarks' => $remarks,
                'acted_at' => now(),
            ]
        );
    }

    private function ensurePendingApproval(object $approvable, string $stepType): void
    {
        AttendanceApproval::firstOrCreate(
            [
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->id,
                'step_type' => $stepType,
            ]
        );
    }

    private function ensureDemoImage(string $path): array
    {
        $disk = Storage::disk('public');
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2p2iYAAAAASUVORK5CYII=');

        if (!$disk->exists($path)) {
            $disk->put($path, $contents);
        }

        return [
            'path' => $path,
            'hash' => md5($contents),
            'size' => strlen($contents),
        ];
    }
}
