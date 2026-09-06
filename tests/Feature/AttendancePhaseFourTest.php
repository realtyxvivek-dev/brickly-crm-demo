<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\SuspicionDetectionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhaseFourTest extends TestCase
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
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_suspicion_scan_logs_flags_for_flagged_punch_events(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'flagged@example.com');
        $officeId = $this->seedAttendanceSetupForUser($user);

        DB::table('attendance_events')->insert([
            'user_id' => $user->id,
            'event_date' => '2026-04-15',
            'event_type' => 'punch_in',
            'event_time' => '2026-04-15 09:20:00',
            'source' => 'web',
            'latitude' => 28.7000000,
            'longitude' => 77.3000000,
            'office_location_id' => $officeId,
            'geo_distance_meters' => 1200,
            'inside_geo_fence' => 0,
            'device_fingerprint' => 'device-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = app(SuspicionDetectionService::class)->reviewOpenForDate(Carbon::parse('2026-04-15'));

        $this->assertGreaterThan(0, $count);
        $this->assertDatabaseHas('attendance_suspicion_logs', [
            'user_id' => $user->id,
            'flag_type' => 'outside_radius',
        ]);
    }

    public function test_admin_simulator_api_returns_half_day_result(): void
    {
        $roles = $this->seedRoles();
        $admin = $this->createUser($roles['admin'], 'admin4@example.com');
        $user = $this->createUser($roles['sales'], 'sim@example.com');
        $this->seedAttendanceSetupForUser($user);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/attendance/simulator?user_id=' . $user->id . '&date=2026-04-15&punch_in_time=13:20');

        $response->assertOk()
            ->assertJsonPath('data.classification.status', 'half_day');
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
            'late_penalty_type' => 'none',
            'late_penalty_threshold' => 3,
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

        return $officeId;
    }
}
