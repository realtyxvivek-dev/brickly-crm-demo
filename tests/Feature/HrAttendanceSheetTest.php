<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\AttendanceFinalizerService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrAttendanceSheetTest extends TestCase
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
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
        (require database_path('migrations/2026_05_05_000000_add_manual_override_fields_to_attendance_records.php'))->up();
    }

    public function test_hr_attendance_sheet_json_renders_month_matrix(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));

        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $employeeRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $hr = $this->createUser($hrRole, 'hr-sheet@example.com');
        $employee = $this->createUser($employeeRole, 'employee-sheet@example.com');

        [$officeId, $policyId] = $this->createAttendanceBase();
        $this->createAttendanceProfile($employee->id, $officeId, $policyId, 'EMP-SHEET');

        $this->actingAs($hr);

        $response = $this->getJson('/hr-manager/attendance/sheet?month=2026-05');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.month', '2026-05')
            ->assertJsonPath('data.employees.0.user_id', $employee->id);
    }

    public function test_hr_manual_override_survives_finalizer_and_can_be_cleared(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-04 10:00:00'));

        $hrRole = $this->createRole(Role::HR_MANAGER, 'HR Manager');
        $employeeRole = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $hr = $this->createUser($hrRole, 'hr-override@example.com');
        $employee = $this->createUser($employeeRole, 'employee-override@example.com');

        [$officeId, $policyId] = $this->createAttendanceBase();
        $this->createAttendanceProfile($employee->id, $officeId, $policyId, 'EMP-OVERRIDE');

        $this->actingAs($hr);

        $overrideResponse = $this->post('/hr-manager/attendance/sheet/override', [
            'scope' => 'cell',
            'month' => '2026-05',
            'user_id' => $employee->id,
            'attendance_date' => '2026-05-04',
            'manual_status' => 'half_day',
            'manual_first_punch_in_at' => '13:15',
            'manual_last_punch_out_at' => '17:30',
            'manual_override_reason' => 'HR corrected same-day attendance.',
        ]);

        $overrideResponse->assertRedirect();

        $record = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->where('attendance_date', 'like', '2026-05-04%')
            ->first();

        $this->assertSame('half_day', $record->status);
        $this->assertSame('manual_override', $record->status_source);
        $this->assertSame('half_day', $record->manual_status);

        DB::table('attendance_events')->insert([
            'user_id' => $employee->id,
            'event_date' => '2026-05-04',
            'event_type' => 'punch_in',
            'event_time' => '2026-05-04 09:10:00',
            'source' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_events')->insert([
            'user_id' => $employee->id,
            'event_date' => '2026-05-04',
            'event_type' => 'punch_out',
            'event_time' => '2026-05-04 18:15:00',
            'source' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AttendanceFinalizerService::class)->finalizeUserForDate($employee, Carbon::parse('2026-05-04'));

        $recordAfterFinalizer = DB::table('attendance_records')
            ->where('user_id', $employee->id)
            ->where('attendance_date', 'like', '2026-05-04%')
            ->first();

        $this->assertSame('manual_override', $recordAfterFinalizer->status_source);
        $this->assertSame('half_day', $recordAfterFinalizer->status);
        $this->assertSame('late', $recordAfterFinalizer->auto_status);
        $this->assertSame('auto', $recordAfterFinalizer->auto_status_source);

        $clearResponse = $this->post('/hr-manager/attendance/sheet/override/clear', [
            'attendance_record_id' => $recordAfterFinalizer->id,
            'month' => '2026-05',
            'clear_reason' => 'Use computed auto state.',
        ]);

        $clearResponse->assertRedirect();

        $recordAfterClear = DB::table('attendance_records')->where('id', $recordAfterFinalizer->id)->first();
        $this->assertSame('late', $recordAfterClear->status);
        $this->assertSame('auto', $recordAfterClear->status_source);
        $this->assertNull($recordAfterClear->manual_status);
        $this->assertNotNull($recordAfterClear->manual_cleared_at);
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
    }

    private function createRole(string $slug, string $name): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'is_active' => true,
        ]);
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

    private function createAttendanceBase(): array
    {
        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ',
            'code' => 'HQ-HR-SHEET',
            'address' => 'Main Office',
            'latitude' => 28.6139000,
            'longitude' => 77.2090000,
            'radius_meters' => 200,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policyId = DB::table('attendance_policies')->insertGetId([
            'name' => 'Default Policy',
            'office_location_id' => $officeId,
            'is_default' => 1,
            'reminder_time' => '09:00:00',
            'late_after_time' => '09:00:00',
            'grace_minutes' => 0,
            'normal_window_end_time' => '11:30:00',
            'half_day_start_time' => '13:00:00',
            'half_day_end_time' => '16:00:00',
            'geo_fence_required' => 0,
            'photo_required' => 0,
            'compress_max_width' => 1280,
            'compress_max_height' => 1280,
            'compress_quality' => 75,
            'suspicious_geo_threshold_meters' => 100,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$officeId, $policyId];
    }

    private function createAttendanceProfile(int $userId, int $officeId, int $policyId, string $employeeCode): void
    {
        DB::table('user_attendance_profiles')->insert([
            'user_id' => $userId,
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'employee_code' => $employeeCode,
            'salary_mode' => 'attendance_based',
            'attendance_enabled' => 1,
            'attendance_rollout_stage' => 'pilot',
            'effective_from' => '2026-05-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
