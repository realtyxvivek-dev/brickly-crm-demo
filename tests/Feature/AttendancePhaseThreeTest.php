<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\PayrollComputationService;
use App\Services\RegularizationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AttendancePhaseThreeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createBaseSchema();
        (require database_path('migrations/2026_04_15_200000_create_attendance_phase_one_tables.php'))->up();
        (require database_path('migrations/2026_04_15_213000_create_attendance_phase_two_tables.php'))->up();
        (require database_path('migrations/2026_04_15_223000_create_attendance_phase_three_tables.php'))->up();
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_monthly_rollup_computes_payable_days_and_estimated_salary(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'salary@example.com');
        $this->seedAttendanceSetupForUser($user, 30000, 'absent', 3);

        AttendanceRecord::insert([
            $this->record($user->id, '2026-04-01', 'present', 1),
            $this->record($user->id, '2026-04-02', 'present', 1),
            $this->record($user->id, '2026-04-03', 'late', 1),
            $this->record($user->id, '2026-04-04', 'late', 1),
            $this->record($user->id, '2026-04-05', 'late', 1),
            $this->record($user->id, '2026-04-06', 'half_day', 0.5),
            $this->record($user->id, '2026-04-07', 'leave', 1),
        ]);

        $leaveType = LeaveType::create([
            'name' => 'Paid Leave',
            'code' => 'PL',
            'is_paid' => true,
            'allow_half_day' => true,
            'annual_quota' => 12,
            'is_active' => true,
        ]);

        DB::table('leave_requests')->insert([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-04-07',
            'to_date' => '2026-04-07',
            'duration_mode' => 'full_day',
            'days_requested' => 1,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rollup = app(PayrollComputationService::class)->computeUserMonth($user, 2026, 4);

        $this->assertSame('5.00', $rollup->present_days);
        $this->assertSame('1.00', $rollup->half_days);
        $this->assertSame(3, $rollup->late_count);
        $this->assertSame('1.00', $rollup->late_penalty_days);
        $this->assertSame('5.50', $rollup->payable_days);
        $this->assertSame('5500.00', $rollup->estimated_salary);
    }

    public function test_payroll_freeze_blocks_new_leave_and_regularization_requests(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'frozen@example.com');
        $finance = $this->createUser($roles['finance'], 'finance@example.com');
        $officeId = $this->seedAttendanceSetupForUser($user, 25000, 'none', 3);

        app(PayrollComputationService::class)->freezeMonth(2026, 4, $finance, $officeId);

        $leaveType = LeaveType::create([
            'name' => 'Sick Leave',
            'code' => 'SL',
            'is_paid' => true,
            'allow_half_day' => true,
            'annual_quota' => 12,
            'is_active' => true,
        ]);

        try {
            app(LeaveService::class)->createRequest($user, [
                'leave_type_id' => $leaveType->id,
                'from_date' => '2026-04-20',
                'to_date' => '2026-04-20',
                'duration_mode' => 'full_day',
            ]);
            $this->fail('Leave request should be blocked for a frozen month.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('frozen', json_encode($exception->errors()));
        }

        try {
            app(RegularizationService::class)->createRequest($user, [
                'attendance_date' => '2026-04-20',
                'request_type' => 'missed_punch_in',
                'requested_in_time' => '09:10',
                'requested_status' => 'present',
            ]);
            $this->fail('Regularization request should be blocked for a frozen month.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('frozen', json_encode($exception->errors()));
        }
    }

    private function createBaseSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->foreignId('role_id')->nullable();
            $table->foreignId('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken()->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    private function seedRoles(): array
    {
        return [
            'sales' => Role::create(['name' => 'Sales Executive', 'slug' => Role::SALES_EXECUTIVE, 'is_active' => true]),
            'hr' => Role::create(['name' => 'HR Manager', 'slug' => Role::HR_MANAGER, 'is_active' => true]),
            'admin' => Role::create(['name' => 'Admin', 'slug' => Role::ADMIN, 'is_active' => true]),
            'finance' => Role::create(['name' => 'Finance Manager', 'slug' => Role::FINANCE_MANAGER, 'is_active' => true]),
        ];
    }

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => $role->name . ' User',
            'email' => $email,
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function seedAttendanceSetupForUser(User $user, float $baseSalary, string $latePenaltyType, int $latePenaltyThreshold): int
    {
        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ ' . $user->id,
            'code' => 'HQ_' . $user->id,
            'address' => 'Main Office',
            'latitude' => 28.6139000,
            'longitude' => 77.2090000,
            'radius_meters' => 200,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policyId = DB::table('attendance_policies')->insertGetId([
            'name' => 'Policy ' . $user->id,
            'office_location_id' => $officeId,
            'is_default' => 1,
            'reminder_time' => '09:00:00',
            'late_after_time' => '09:00:00',
            'grace_minutes' => 0,
            'normal_window_end_time' => '11:30:00',
            'half_day_start_time' => '13:00:00',
            'half_day_end_time' => '16:00:00',
            'geo_fence_required' => 1,
            'photo_required' => 0,
            'compress_max_width' => 1280,
            'compress_max_height' => 1280,
            'compress_quality' => 75,
            'suspicious_geo_threshold_meters' => 100,
            'approval_mode_leave' => 'hr_only',
            'approval_mode_regularization' => 'hr_only',
            'regularization_abuse_threshold' => 3,
            'late_penalty_type' => $latePenaltyType,
            'late_penalty_threshold' => $latePenaltyThreshold,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_attendance_profiles')->insert([
            'user_id' => $user->id,
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'employee_code' => 'EMP-' . $user->id,
            'salary_mode' => 'attendance_based',
            'attendance_enabled' => 1,
            'attendance_rollout_stage' => 'pilot',
            'base_salary' => $baseSalary,
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $officeId;
    }

    private function record(int $userId, string $date, string $status, float $fraction): array
    {
        return [
            'user_id' => $userId,
            'attendance_date' => $date,
            'status' => $status,
            'status_source' => 'auto',
            'payable_day_fraction' => $fraction,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
