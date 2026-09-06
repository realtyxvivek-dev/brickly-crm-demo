<?php

namespace Tests\Feature;

use App\Models\AttendanceOvertime;
use App\Models\AttendanceSuspicionLog;
use App\Models\Role;
use App\Models\User;
use App\Services\OvertimeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRemainingTest extends TestCase
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
        (require database_path('migrations/2026_04_15_233000_create_attendance_phase_four_tables.php'))->up();
        (require database_path('migrations/2026_04_16_000500_create_attendance_remaining_tables.php'))->up();
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_overtime_candidate_is_created_and_can_be_approved(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'ot@example.com');
        $hr = $this->createUser($roles['hr'], 'hr-ot@example.com');
        [$officeId, $policyId] = $this->seedAttendanceSetupForUser($user);

        $recordId = DB::table('attendance_records')->insertGetId([
            'user_id' => $user->id,
            'attendance_date' => '2026-04-16',
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'first_punch_in_at' => '2026-04-16 09:00:00',
            'last_punch_out_at' => '2026-04-16 19:00:00',
            'status' => 'present',
            'status_source' => 'auto',
            'worked_minutes' => 600,
            'payable_day_fraction' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = \App\Models\AttendanceRecord::findOrFail($recordId);
        $candidate = app(OvertimeService::class)->syncForRecord($record->fresh(['user', 'attendancePolicy']));

        $this->assertNotNull($candidate);
        $this->assertSame(120, $candidate->overtime_minutes);

        app(OvertimeService::class)->approve($candidate, $hr);

        $this->assertDatabaseHas('attendance_overtimes', [
            'id' => $candidate->id,
            'status' => 'approved',
        ]);
    }

    public function test_suspicion_log_status_can_be_updated(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'sus@example.com');
        $hr = $this->createUser($roles['hr'], 'sus-hr@example.com');

        $log = AttendanceSuspicionLog::create([
            'user_id' => $user->id,
            'flag_type' => 'outside_radius',
            'severity' => 'high',
            'status' => 'open',
        ]);

        Sanctum::actingAs($hr);

        $response = $this->postJson('/api/hr/attendance/suspicion/' . $log->id, [
            'status' => 'reviewed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('attendance_suspicion_logs', [
            'id' => $log->id,
            'status' => 'reviewed',
            'reviewed_by' => $hr->id,
        ]);
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

    private function seedAttendanceSetupForUser(User $user): array
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
            'late_penalty_type' => 'none',
            'late_penalty_threshold' => 3,
            'overtime_enabled' => 1,
            'overtime_after_minutes' => 480,
            'overtime_min_minutes' => 30,
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
            'base_salary' => 25000,
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$officeId, $policyId];
    }
}
