<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceApprovalService;
use App\Services\LeaveService;
use App\Services\RegularizationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhaseTwoTest extends TestCase
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
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_approved_leave_creates_leave_attendance_record(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'sales@example.com');
        $this->createUser($roles['hr'], 'hr@example.com');
        $policyId = $this->seedAttendanceSetupForUser($user);

        $leaveType = LeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'is_paid' => true,
            'allow_half_day' => true,
            'annual_quota' => 12,
            'is_active' => true,
        ]);

        $leaveService = app(LeaveService::class);
        $approvalService = app(AttendanceApprovalService::class);
        $hr = User::where('email', 'hr@example.com')->firstOrFail();

        $request = $leaveService->createRequest($user, [
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-04-20',
            'to_date' => '2026-04-20',
            'duration_mode' => 'full_day',
            'reason' => 'Family function',
        ]);

        $decision = $approvalService->approve($request, $hr, 'hr_only');
        $this->assertSame('approved', $decision);
        $request->update(['status' => 'approved', 'final_approved_at' => now()]);
        $leaveService->applyApprovedLeave($request->fresh(['user', 'leaveType']));

        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', '2026-04-20')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('leave', $record->status);
    }

    public function test_approved_regularization_updates_attendance_record(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'exec@example.com');
        $hr = $this->createUser($roles['hr'], 'hr2@example.com');
        $this->seedAttendanceSetupForUser($user);

        AttendanceRecord::create([
            'user_id' => $user->id,
            'attendance_date' => '2026-04-21',
            'status' => 'absent',
            'status_source' => 'auto',
            'payable_day_fraction' => 0,
        ]);

        $regularizationService = app(RegularizationService::class);
        $approvalService = app(AttendanceApprovalService::class);

        $request = $regularizationService->createRequest($user, [
            'attendance_date' => '2026-04-21',
            'request_type' => 'missed_punch_in',
            'requested_in_time' => '09:10',
            'requested_out_time' => '18:05',
            'requested_status' => 'present',
            'reason' => 'Network issue',
        ]);

        $decision = $approvalService->approve($request, $hr, 'hr_only');
        $this->assertSame('approved', $decision);
        $request->update(['status' => 'approved', 'final_approved_at' => now()]);
        $regularizationService->applyApprovedRegularization($request->fresh());

        $record = AttendanceRecord::query()
            ->where('user_id', $user->id)
            ->whereDate('attendance_date', '2026-04-21')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame('present', $record->status);
        $this->assertSame('regularization', $record->status_source);
    }

    public function test_admin_can_update_leave_type_via_api(): void
    {
        $roles = $this->seedRoles();
        $admin = $this->createUser($roles['admin'], 'admin-leave@example.com');
        Sanctum::actingAs($admin);

        $leaveType = LeaveType::create([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'is_paid' => true,
            'allow_half_day' => false,
            'annual_quota' => 6,
            'is_active' => true,
        ]);

        $response = $this->putJson('/api/admin/attendance/leave-types/' . $leaveType->id, [
            'name' => 'Privilege Leave',
            'code' => 'PL',
            'is_paid' => true,
            'allow_half_day' => true,
            'annual_quota' => 12,
            'is_active' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Privilege Leave')
            ->assertJsonPath('data.code', 'PL');

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'name' => 'Privilege Leave',
            'code' => 'PL',
        ]);
    }

    public function test_leave_balances_api_creates_missing_active_type_balances_for_manager_roles(): void
    {
        $roles = $this->seedRoles();
        $asm = $this->createUser($roles['asm'], 'asm-leaves@example.com');
        $this->seedAttendanceSetupForUser($asm);
        Sanctum::actingAs($asm);

        foreach ([
            ['name' => 'Casual Leave', 'code' => 'CL', 'annual_quota' => 6],
            ['name' => 'Privilege Leave', 'code' => 'PL', 'annual_quota' => 12],
            ['name' => 'Sick Leave', 'code' => 'SL', 'annual_quota' => 6],
        ] as $leaveType) {
            LeaveType::create([
                'name' => $leaveType['name'],
                'code' => $leaveType['code'],
                'is_paid' => true,
                'allow_half_day' => true,
                'annual_quota' => $leaveType['annual_quota'],
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseCount('leave_balances', 0);

        $response = $this->getJson('/api/attendance/leave-balances');

        $response->assertOk();
        $codes = collect($response->json('data'))->pluck('leave_type.code')->sort()->values()->all();

        $this->assertSame(['CL', 'PL', 'SL'], $codes);
        $this->assertDatabaseCount('leave_balances', 3);
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
            'asm' => Role::create(['name' => 'Assistant Sales Manager', 'slug' => Role::ASSISTANT_SALES_MANAGER, 'is_active' => true]),
            'hr' => Role::create(['name' => 'HR Manager', 'slug' => Role::HR_MANAGER, 'is_active' => true]),
            'admin' => Role::create(['name' => 'Admin', 'slug' => Role::ADMIN, 'is_active' => true]),
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

    private function seedAttendanceSetupForUser(User $user): int
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
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $policyId;
    }
}
