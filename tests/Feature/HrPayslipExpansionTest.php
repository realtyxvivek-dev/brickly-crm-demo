<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\FaceFraudReviewService;
use App\Services\PayslipGenerationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HrPayslipExpansionTest extends TestCase
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
        (require database_path('migrations/2026_04_24_220000_create_employee_master_tables.php'))->up();
        (require database_path('migrations/2026_04_24_233500_add_verification_token_to_payroll_payslips.php'))->up();
    }

    public function test_generates_payslip_with_components_and_manual_deduction(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'salary-hr@example.com');
        $finance = $this->createUser($roles['finance'], 'finance-hr@example.com');

        $structureId = DB::table('salary_structures')->insertGetId([
            'name' => 'Standard',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('salary_structure_components')->insert([
            'salary_structure_id' => $structureId,
            'component_type' => 'earning',
            'code' => 'HRA',
            'label' => 'HRA',
            'calc_type' => 'percent_of_base',
            'value' => 20,
            'display_order' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_salary_profiles')->insert([
            'user_id' => $user->id,
            'salary_structure_id' => $structureId,
            'base_salary' => 30000,
            'effective_from' => '2026-04-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $freezeId = DB::table('payroll_freezes')->insertGetId([
            'year' => 2026,
            'month' => 4,
            'freeze_scope' => 'company',
            'status' => 'frozen',
            'frozen_by' => $finance->id,
            'frozen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payroll_manual_adjustments')->insert([
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 4,
            'label' => 'Advance',
            'type' => 'deduction',
            'amount' => 1000,
            'created_by' => $finance->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payroll_freeze_items')->insert([
            'payroll_freeze_id' => $freezeId,
            'user_id' => $user->id,
            'snapshot_json' => json_encode([
                'year' => 2026,
                'month' => 4,
                'absent_days' => 1,
                'half_days' => 2,
                'late_penalty_days' => 1,
                'unpaid_leave_days' => 1,
                'estimated_salary' => 25000,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $count = app(PayslipGenerationService::class)->generateForFreeze(\App\Models\PayrollFreeze::find($freezeId));

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('payroll_payslips', [
            'user_id' => $user->id,
            'year' => 2026,
            'month' => 4,
            'gross_pay' => 36000,
            'total_deductions' => 5000,
            'net_pay' => 31000,
        ]);

        $payslip = \App\Models\PayrollPayslip::where('user_id', $user->id)->first();

        $this->assertNotNull($payslip?->verification_token);
        $this->get(route('payslips.verify', $payslip->verification_token))
            ->assertOk()
            ->assertSee($payslip->payslip_number);
    }

    public function test_duplicate_photo_creates_pending_face_review(): void
    {
        $roles = $this->seedRoles();
        $user = $this->createUser($roles['sales'], 'fraud-hr@example.com');
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
            'name' => 'Fraud Policy',
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
            'late_penalty_type' => 'none',
            'late_penalty_threshold' => 3,
            'overtime_enabled' => 0,
            'overtime_after_minutes' => 480,
            'overtime_min_minutes' => 30,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recordId = DB::table('attendance_records')->insertGetId([
            'user_id' => $user->id,
            'attendance_date' => '2026-04-16',
            'office_location_id' => $officeId,
            'attendance_policy_id' => $policyId,
            'status' => 'present',
            'status_source' => 'auto',
            'payable_day_fraction' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = DB::table('attendance_events')->insertGetId([
            'user_id' => $user->id,
            'event_date' => '2026-04-16',
            'event_type' => 'punch_in',
            'event_time' => now(),
            'source' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendance_photos')->insert([
            'user_id' => $user->id,
            'file_path' => 'attendance/photos/test-a.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'compressed_size' => 512,
            'width' => 200,
            'height' => 200,
            'compression_quality' => 75,
            'file_hash' => 'samehash',
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $photoId = DB::table('attendance_photos')->insertGetId([
            'user_id' => $user->id,
            'file_path' => 'attendance/photos/test-b.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'compressed_size' => 512,
            'width' => 200,
            'height' => 200,
            'compression_quality' => 75,
            'file_hash' => 'samehash',
            'captured_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = \App\Models\AttendanceRecord::find($recordId);
        $event = \App\Models\AttendanceEvent::find($eventId);
        $policy = \App\Models\AttendancePolicy::find($policyId);
        $photo = \App\Models\AttendancePhoto::find($photoId);

        $flags = app(FaceFraudReviewService::class)->evaluatePunchIn($user, $policy, $record, $event, $photo);

        $this->assertContains('duplicate_photo', $flags);
        $this->assertDatabaseHas('attendance_face_reviews', [
            'attendance_record_id' => $recordId,
            'status' => 'pending_review',
        ]);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $recordId,
            'fraud_review_status' => 'pending_review',
            'fraud_payroll_blocked' => 1,
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
    }

    private function seedRoles(): array
    {
        return [
            'sales' => Role::create(['name' => 'Sales Executive', 'slug' => Role::SALES_EXECUTIVE, 'is_active' => true]),
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
}
