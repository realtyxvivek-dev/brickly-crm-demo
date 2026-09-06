<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrDashboardTest extends TestCase
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
        (require database_path('migrations/2026_04_16_020000_create_hr_expansion_tables.php'))->up();
        (require database_path('migrations/2026_04_16_030000_add_rollout_fields_to_user_attendance_profiles.php'))->up();
    }

    public function test_hr_user_is_redirected_to_hr_dashboard_from_root_dashboard(): void
    {
        $roles = $this->seedRoles();
        $hr = $this->createUser($roles['hr'], 'hr-dashboard@example.com');

        $response = $this->actingAs($hr)->get('/dashboard');

        $response->assertRedirect(route('hr-manager.dashboard'));
    }

    public function test_hr_dashboard_shows_summary_pending_work_and_alerts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-16 10:00:00'));

        $roles = $this->seedRoles();
        $hr = $this->createUser($roles['hr'], 'hr-view@example.com');
        $sales1 = $this->createUser($roles['sales'], 'sales1@example.com');
        $sales2 = $this->createUser($roles['sales'], 'sales2@example.com');
        $sales3 = $this->createUser($roles['sales'], 'sales3@example.com');
        $sales4 = $this->createUser($roles['sales'], 'sales4@example.com');

        $officeId = DB::table('office_locations')->insertGetId([
            'name' => 'HQ',
            'code' => 'HQ',
            'address' => 'Main Office',
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'radius_meters' => 200,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policyId = DB::table('attendance_policies')->insertGetId([
            'name' => 'Main Policy',
            'office_location_id' => $officeId,
            'is_default' => 1,
            'reminder_time' => '09:00:00',
            'late_after_time' => '09:30:00',
            'grace_minutes' => 10,
            'normal_window_end_time' => '11:30:00',
            'half_day_start_time' => '13:00:00',
            'half_day_end_time' => '16:00:00',
            'geo_fence_required' => 1,
            'photo_required' => 1,
            'selfie_required' => 1,
            'face_review_required' => 0,
            'payroll_block_on_pending_face_review' => 1,
            'duplicate_photo_threshold' => 1,
            'compress_max_width' => 1280,
            'compress_max_height' => 1280,
            'compress_quality' => 75,
            'suspicious_geo_threshold_meters' => 100,
            'approval_mode_leave' => 'hr_only',
            'approval_mode_regularization' => 'hr_only',
            'regularization_abuse_threshold' => 3,
            'late_penalty_type' => 'half_day',
            'late_penalty_threshold' => 3,
            'overtime_enabled' => 1,
            'overtime_after_minutes' => 540,
            'overtime_min_minutes' => 30,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$sales1, $sales2, $sales3, $sales4] as $index => $user) {
            DB::table('user_attendance_profiles')->insert([
                'user_id' => $user->id,
                'office_location_id' => $officeId,
                    'attendance_policy_id' => $policyId,
                    'employee_code' => 'EMP-' . ($index + 1),
                    'salary_mode' => 'hybrid',
                    'attendance_enabled' => 1,
                    'attendance_rollout_stage' => 'pilot',
                    'effective_from' => '2026-04-01',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        DB::table('attendance_records')->insert([
            [
                'user_id' => $sales1->id,
                'attendance_date' => '2026-04-16',
                'office_location_id' => $officeId,
                'attendance_policy_id' => $policyId,
                'first_punch_in_at' => '2026-04-16 09:15:00',
                'last_punch_out_at' => '2026-04-16 18:05:00',
                'status' => 'present',
                'status_source' => 'auto',
                'late_minutes' => 0,
                'worked_minutes' => 530,
                'payable_day_fraction' => 1,
                'has_missing_punch_out' => 0,
                'is_suspicious' => 0,
                'fraud_review_status' => 'clear',
                'fraud_payroll_blocked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $sales2->id,
                'attendance_date' => '2026-04-16',
                'office_location_id' => $officeId,
                'attendance_policy_id' => $policyId,
                'first_punch_in_at' => '2026-04-16 09:50:00',
                'last_punch_out_at' => null,
                'status' => 'late',
                'status_source' => 'auto',
                'late_minutes' => 10,
                'worked_minutes' => 0,
                'payable_day_fraction' => 1,
                'has_missing_punch_out' => 1,
                'is_suspicious' => 1,
                'fraud_review_status' => 'pending_review',
                'fraud_payroll_blocked' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $sales3->id,
                'attendance_date' => '2026-04-16',
                'office_location_id' => $officeId,
                'attendance_policy_id' => $policyId,
                'first_punch_in_at' => '2026-04-16 13:20:00',
                'last_punch_out_at' => null,
                'status' => 'half_day',
                'status_source' => 'auto',
                'late_minutes' => 0,
                'worked_minutes' => 0,
                'payable_day_fraction' => 0.5,
                'has_missing_punch_out' => 1,
                'is_suspicious' => 0,
                'fraud_review_status' => 'clear',
                'fraud_payroll_blocked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $sales4->id,
                'attendance_date' => '2026-04-16',
                'office_location_id' => $officeId,
                'attendance_policy_id' => $policyId,
                'first_punch_in_at' => null,
                'last_punch_out_at' => null,
                'status' => 'leave',
                'status_source' => 'leave',
                'late_minutes' => 0,
                'worked_minutes' => 0,
                'payable_day_fraction' => 1,
                'has_missing_punch_out' => 0,
                'is_suspicious' => 0,
                'fraud_review_status' => 'clear',
                'fraud_payroll_blocked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('leave_types')->insert([
            'name' => 'Casual Leave',
            'code' => 'CL',
            'is_paid' => 1,
            'allow_half_day' => 1,
            'annual_quota' => 6,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'user_id' => $sales4->id,
            'leave_type_id' => 1,
            'from_date' => '2026-04-20',
            'to_date' => '2026-04-21',
            'duration_mode' => 'full_day',
            'days_requested' => 2,
            'reason' => 'Pending leave',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_regularizations')->insert([
            'user_id' => $sales3->id,
            'attendance_date' => '2026-04-15',
            'request_type' => 'missed_punch_in',
            'requested_status' => 'present',
            'reason' => 'Pending regularization',
            'status' => 'pending',
            'abuse_score_snapshot' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_overtimes')->insert([
            'user_id' => $sales2->id,
            'attendance_record_id' => 2,
            'attendance_date' => '2026-04-15',
            'worked_minutes' => 600,
            'overtime_minutes' => 60,
            'status' => 'pending',
            'source' => 'system',
            'remarks' => 'Pending OT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_face_reviews')->insert([
            'attendance_record_id' => 2,
            'user_id' => $sales2->id,
            'status' => 'pending_review',
            'remarks' => 'Pending fraud review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($hr)->get(route('hr-manager.dashboard'));

        $response->assertOk()
            ->assertSee('HR Dashboard')
            ->assertSee('Pending Work')
            ->assertSee('Alerts')
            ->assertSee('Quick Actions')
            ->assertSee('Attendance Register')
            ->assertSee('Fraud Reviews');

        $response->assertViewHas('todaySummary', [
            'present' => 1,
            'late' => 1,
            'half_day' => 1,
            'absent' => 0,
            'on_leave' => 1,
        ]);

        $response->assertViewHas('pendingWork', [
            'leave_approvals' => 1,
            'regularizations' => 1,
            'overtimes' => 1,
            'fraud_reviews' => 1,
        ]);

        $response->assertViewHas('alerts', [
            'missing_punch_in' => 0,
            'missing_punch_out' => 2,
            'suspicious_cases' => 1,
        ]);

        Carbon::setTestNow();
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

        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key')->unique();
            $table->text('setting_value')->nullable();
            $table->timestamps();
        });
    }

    private function seedRoles(): array
    {
        return [
            'hr' => Role::create(['name' => 'HR Manager', 'slug' => Role::HR_MANAGER, 'is_active' => true]),
            'sales' => Role::create(['name' => 'Sales Executive', 'slug' => Role::SALES_EXECUTIVE, 'is_active' => true]),
        ];
    }

    private function createUser(Role $role, string $email): User
    {
        return User::create([
            'name' => $role->name . ' ' . $email,
            'email' => $email,
            'password' => 'password123',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
