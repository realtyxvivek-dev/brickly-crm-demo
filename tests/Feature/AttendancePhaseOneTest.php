<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhaseOneTest extends TestCase
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
        $migration = require database_path('migrations/2026_04_15_200000_create_attendance_phase_one_tables.php');
        $migration->up();
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_user_can_punch_in_and_receive_late_status_and_month_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:15:00'));

        $role = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $user = $this->createUser($role, 'sales@example.com');
        Sanctum::actingAs($user);

        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ',
            'code' => 'HQ',
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
            'geo_fence_required' => 1,
            'photo_required' => 0,
            'compress_max_width' => 1280,
            'compress_max_height' => 1280,
            'compress_quality' => 75,
            'suspicious_geo_threshold_meters' => 100,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_attendance_profiles')->insert([
            'user_id' => $user->id,
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'employee_code' => 'EMP-1',
            'salary_mode' => 'attendance_based',
            'attendance_enabled' => 1,
            'attendance_rollout_stage' => 'pilot',
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/attendance/punch-in', [
            'latitude' => 28.6139000,
            'longitude' => 77.2090000,
            'source' => 'web',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'late');

        $summary = $this->getJson('/api/attendance/month-summary');
        $summary->assertOk()
            ->assertJsonPath('data.late', 1)
            ->assertJsonPath('data.present', 0);
    }

    public function test_admin_attendance_office_api_loads(): void
    {
        $adminRole = $this->createRole(Role::ADMIN, 'Admin');
        $admin = $this->createUser($adminRole, 'admin@example.com');
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/attendance/offices');

        $response->assertOk();
        $response->assertJsonPath('success', true);
    }

    public function test_punch_in_rejects_uploaded_photo_without_camera_capture_mode(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:15:00'));

        $role = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $user = $this->createUser($role, 'sales-photo@example.com');
        Sanctum::actingAs($user);

        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ',
            'code' => 'HQP',
            'address' => 'Main Office',
            'latitude' => 28.6139000,
            'longitude' => 77.2090000,
            'radius_meters' => 200,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policyId = DB::table('attendance_policies')->insertGetId([
            'name' => 'Photo Policy',
            'office_location_id' => $officeId,
            'is_default' => 1,
            'reminder_time' => '09:00:00',
            'late_after_time' => '09:00:00',
            'grace_minutes' => 0,
            'normal_window_end_time' => '11:30:00',
            'half_day_start_time' => '13:00:00',
            'half_day_end_time' => '16:00:00',
            'geo_fence_required' => 1,
            'photo_required' => 1,
            'compress_max_width' => 1280,
            'compress_max_height' => 1280,
            'compress_quality' => 75,
            'suspicious_geo_threshold_meters' => 100,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_attendance_profiles')->insert([
            'user_id' => $user->id,
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'employee_code' => 'EMP-PHOTO',
            'salary_mode' => 'attendance_based',
            'attendance_enabled' => 1,
            'attendance_rollout_stage' => 'pilot',
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tempFile = storage_path('framework/testing/camera-only-test.png');
        File::ensureDirectoryExists(dirname($tempFile));
        file_put_contents($tempFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2p2iYAAAAASUVORK5CYII='));

        $response = $this->post('/api/attendance/punch-in', [
            'latitude' => 28.6139000,
            'longitude' => 77.2090000,
            'source' => 'web',
            'photo' => new UploadedFile($tempFile, 'gallery.png', 'image/png', null, true),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('photo');
    }

    public function test_disabled_user_cannot_access_attendance_api(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-15 09:15:00'));

        $role = $this->createRole(Role::SALES_EXECUTIVE, 'Sales Executive');
        $user = $this->createUser($role, 'disabled-attendance@example.com');
        Sanctum::actingAs($user);

        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ Disabled',
            'code' => 'HQD',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policyId = DB::table('attendance_policies')->insertGetId([
            'name' => 'Disabled Policy',
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
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_attendance_profiles')->insert([
            'user_id' => $user->id,
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'employee_code' => 'EMP-DISABLED',
            'salary_mode' => 'attendance_based',
            'attendance_enabled' => 0,
            'attendance_rollout_stage' => 'pilot',
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/attendance/today')->assertForbidden();
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
}
