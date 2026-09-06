<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\AsmCnpAutomationService;
use App\Services\NotificationService;
use App\Services\SiteVisitRescheduleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class CloserPipelineWorkflowTest extends TestCase
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

        Storage::fake('public');
        $this->createSchema();
        Carbon::setTestNow(Carbon::parse('2026-04-19 18:40:00'));

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldIgnoreMissing();
        $this->app->instance(NotificationService::class, $notificationService);

        $asmService = Mockery::mock(AsmCnpAutomationService::class);
        $asmService->shouldIgnoreMissing();
        $this->app->instance(AsmCnpAutomationService::class, $asmService);

        $rescheduleService = Mockery::mock(SiteVisitRescheduleService::class);
        $rescheduleService->shouldIgnoreMissing();
        $this->app->instance(SiteVisitRescheduleService::class, $rescheduleService);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_closer_pipeline_happy_path_and_correction_cycle_work_end_to_end(): void
    {
        $seniorManagerRole = $this->createRole(Role::SENIOR_MANAGER);
        $crmRole = $this->createRole(Role::CRM);
        $seniorManager = $this->createUser($seniorManagerRole, [
            'name' => 'Senior Manager',
            'email' => 'senior-manager@example.test',
        ]);

        $crm = $this->createUser($crmRole, [
            'name' => 'CRM User',
            'email' => 'crm-user@example.test',
        ]);

        $lead = $this->createLead([
            'name' => 'Closer Lead',
            'created_by' => $seniorManager->id,
            'status' => 'qualified',
        ]);

        $siteVisit = $this->createVerifiedVisit($lead, $seniorManager);

        Sanctum::actingAs($seniorManager);

        $this->getJson('/api/sales-manager/site-visits/closer-pipeline?bucket=visited_clients')
            ->assertOk()
            ->assertJsonPath('counts.visited_clients', 1)
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/sales-manager/site-visits/{$siteVisit->id}/move-to-closer")
            ->assertOk()
            ->assertJsonPath('data.closer_status', 'draft');

        $draftPayload = $this->validKycPayload([
            'actual_closer_date' => '2026-04-19',
            'customer_name' => 'Closer Lead',
            'nominee_name' => 'Nominee One',
            'customer_dob' => '1991-02-10',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card_no' => '123412341234',
            'primary_applicant_name' => 'Closer Lead',
            'primary_applicant_date_of_birth' => '1991-02-10',
            'primary_applicant_pan_no' => 'ABCDE1234F',
            'primary_applicant_aadhaar_no' => '123412341234',
        ]);

        $this->postJson("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/draft", $draftPayload)
            ->assertOk()
            ->assertJsonPath('data.closer_status', 'draft');

        $submitPayload = array_merge($draftPayload, [
            'kyc_documents' => [
                UploadedFile::fake()->create('pan-card.pdf', 100, 'application/pdf'),
            ],
            'proof_photos' => [
                UploadedFile::fake()->create('customer-proof.jpg', 120, 'image/jpeg'),
            ],
        ]);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/submit", $submitPayload, [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'pending_crm');

        $siteVisit->refresh();
        $this->assertSame('pending_crm', $siteVisit->closer_status);
        $this->assertTrue($siteVisit->hasCompleteKyc());

        Sanctum::actingAs($crm);

        $this->postJson("/api/crm/site-visits/{$siteVisit->id}/send-back-closer", [
            'remark' => 'Upload clearer proof photo',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'correction_required');

        $siteVisit->refresh();
        $this->assertSame('correction_required', $siteVisit->closer_status);
        $this->assertSame('Upload clearer proof photo', $siteVisit->closer_review_remark);
        $originalKycDocuments = $siteVisit->kyc_documents;

        Sanctum::actingAs($seniorManager);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/closer/resubmit", $this->validKycPayload([
            'actual_closer_date' => '2026-04-19',
            'customer_name' => 'Closer Lead',
            'nominee_name' => 'Nominee One',
            'customer_dob' => '1991-02-10',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card_no' => '123412341234',
            'primary_applicant_name' => 'Closer Lead',
            'primary_applicant_date_of_birth' => '1991-02-10',
            'primary_applicant_pan_no' => 'ABCDE1234F',
            'primary_applicant_aadhaar_no' => '123412341234',
            'existing_kyc_documents' => $originalKycDocuments,
            'proof_photos' => [
                UploadedFile::fake()->create('customer-proof-2.jpg', 120, 'image/jpeg'),
            ],
        ]), [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'pending_crm');

        $siteVisit->refresh();
        $this->assertSame('pending_crm', $siteVisit->closer_status);
        $this->assertSame(1, (int) $siteVisit->closer_resubmission_count);

        Sanctum::actingAs($crm);

        $this->postJson("/api/crm/site-visits/{$siteVisit->id}/verify-closing", [
            'notes' => 'Approved after correction',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'approved');

        $siteVisit->refresh();
        $lead->refresh();

        $this->assertSame('approved', $siteVisit->closer_status);
        $this->assertSame('2026-04-19', $siteVisit->actual_closer_date->toDateString());
        $this->assertNotNull($siteVisit->actual_closer_date_approved_at);
        $this->assertSame($crm->id, (int) $siteVisit->actual_closer_date_approved_by);
        $this->assertSame('verified', $siteVisit->closing_verification_status);
        $this->assertSame('closed', $lead->status);

        Sanctum::actingAs($seniorManager);

        $this->postJson("/api/sales-manager/site-visits/{$siteVisit->id}/request-incentive", [
            'type' => 'closer',
            'amount' => 25000,
        ])->assertOk()
            ->assertJsonPath('data.type', 'closer')
            ->assertJsonPath('data.status', 'pending_finance_manager');

        $this->getJson('/api/sales-manager/site-visits/closer-pipeline?bucket=approved_closers')
            ->assertOk()
            ->assertJsonPath('counts.approved_closers', 1);

        $this->getJson('/api/sales-manager/site-visits/closer-pipeline?bucket=incentives')
            ->assertOk()
            ->assertJsonPath('counts.incentives', 1)
            ->assertJsonPath('data.0.incentive.status', 'pending_finance_manager');
    }

    public function test_rejected_closer_can_be_resubmitted_with_prefilled_data_and_replaced_proof_photo(): void
    {
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'name' => 'Senior Manager',
            'email' => 'senior-manager-reject@example.test',
        ]);
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'name' => 'CRM User',
            'email' => 'crm-user-reject@example.test',
        ]);

        $lead = $this->createLead([
            'name' => 'Rejected Closer Lead',
            'created_by' => $seniorManager->id,
            'status' => 'qualified',
        ]);

        $siteVisit = $this->createVerifiedVisit($lead, $seniorManager);

        Sanctum::actingAs($seniorManager);

        $this->postJson("/api/sales-manager/site-visits/{$siteVisit->id}/move-to-closer")->assertOk();

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/submit", $this->validKycPayload([
            'actual_closer_date' => '2026-04-18',
            'customer_name' => 'Rejected Closer Lead',
            'nominee_name' => 'Nominee Two',
            'customer_dob' => '1993-08-11',
            'pan_card' => 'ABCDE9876F',
            'aadhaar_card_no' => '999988887777',
            'primary_applicant_name' => 'Rejected Closer Lead',
            'primary_applicant_date_of_birth' => '1993-08-11',
            'primary_applicant_pan_no' => 'ABCDE9876F',
            'primary_applicant_aadhaar_no' => '999988887777',
            'kyc_documents' => [
                UploadedFile::fake()->create('customer-pan.pdf', 100, 'application/pdf'),
            ],
            'proof_photos' => [
                UploadedFile::fake()->create('old-proof.jpg', 120, 'image/jpeg'),
            ],
        ]), [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'pending_crm');

        $siteVisit->refresh();
        $originalKycDocuments = $siteVisit->kyc_documents;

        Sanctum::actingAs($crm);

        $this->postJson("/api/crm/site-visits/{$siteVisit->id}/reject-closing", [
            'reason' => 'Need a fresh customer proof photo',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'rejected');

        $siteVisit->refresh();
        $this->assertSame('rejected', $siteVisit->closer_status);
        $this->assertSame('Need a fresh customer proof photo', $siteVisit->closer_rejection_reason);

        Sanctum::actingAs($seniorManager);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/closer/resubmit", $this->validKycPayload([
            'actual_closer_date' => '2026-04-18',
            'customer_name' => 'Rejected Closer Lead',
            'nominee_name' => 'Nominee Two',
            'customer_dob' => '1993-08-11',
            'pan_card' => 'ABCDE9876F',
            'aadhaar_card_no' => '999988887777',
            'primary_applicant_name' => 'Rejected Closer Lead',
            'primary_applicant_date_of_birth' => '1993-08-11',
            'primary_applicant_pan_no' => 'ABCDE9876F',
            'primary_applicant_aadhaar_no' => '999988887777',
            'existing_kyc_documents' => $originalKycDocuments,
            'existing_proof_photos' => [],
            'proof_photos' => [
                UploadedFile::fake()->create('new-proof.jpg', 120, 'image/jpeg'),
            ],
        ]), [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('data.closer_status', 'pending_crm');

        $siteVisit->refresh();
        $this->assertSame('pending_crm', $siteVisit->closer_status);
        $this->assertSame(1, (int) $siteVisit->closer_resubmission_count);
        $this->assertSame($originalKycDocuments, $siteVisit->kyc_documents);
        $this->assertCount(1, $siteVisit->closer_request_proof_photos ?? []);
        $this->assertNotSame('old-proof.jpg', basename((string) $siteVisit->closer_request_proof_photos[0]));
        $this->assertNull($siteVisit->closer_rejection_reason);
    }

    public function test_actual_closer_date_rejects_future_and_requires_reason_for_old_backdate(): void
    {
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'name' => 'Senior Manager',
            'email' => 'senior-manager-date@example.test',
        ]);

        $lead = $this->createLead([
            'name' => 'Date Guard Lead',
            'created_by' => $seniorManager->id,
            'status' => 'qualified',
        ]);

        $siteVisit = $this->createVerifiedVisit($lead, $seniorManager);

        Sanctum::actingAs($seniorManager);
        $this->postJson("/api/sales-manager/site-visits/{$siteVisit->id}/move-to-closer")->assertOk();

        $payload = $this->validKycPayload([
            'customer_name' => 'Date Guard Lead',
            'nominee_name' => 'Nominee Three',
            'customer_dob' => '1994-01-12',
            'pan_card' => 'ABCDE1111F',
            'aadhaar_card_no' => '111122223333',
            'primary_applicant_name' => 'Date Guard Lead',
            'primary_applicant_date_of_birth' => '1994-01-12',
            'primary_applicant_pan_no' => 'ABCDE1111F',
            'primary_applicant_aadhaar_no' => '111122223333',
            'kyc_documents' => [
                UploadedFile::fake()->create('customer-pan.pdf', 100, 'application/pdf'),
            ],
            'proof_photos' => [
                UploadedFile::fake()->create('proof.jpg', 120, 'image/jpeg'),
            ],
        ]);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/submit", array_merge($payload, [
            'actual_closer_date' => '2026-04-20',
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_closer_date'], 'errors');

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/submit", array_merge($payload, [
            'actual_closer_date' => '2026-04-10',
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_closer_backdate_reason'], 'errors');

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/kyc/submit", array_merge($payload, [
            'actual_closer_date' => '2026-04-10',
            'actual_closer_backdate_reason' => 'Customer signed booking form earlier, documents were collected later.',
        ]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.closer_status', 'pending_crm');

        $siteVisit->refresh();
        $this->assertSame('2026-04-10', $siteVisit->actual_closer_date->toDateString());
        $this->assertSame('Customer signed booking form earlier, documents were collected later.', $siteVisit->actual_closer_backdate_reason);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
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

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->string('preferred_location')->nullable();
            $table->string('budget')->nullable();
            $table->text('requirements')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('new');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_form_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('lead_form_field_id')->nullable();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location_path')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dynamic_form_id');
            $table->string('field_key');
            $table->string('field_type')->default('text');
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('order')->default(0);
            $table->string('section')->nullable();
            $table->json('styles')->nullable();
            $table->string('default_value')->nullable();
            $table->boolean('is_system')->default(false);
            $table->string('system_binding')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('verification_routing_settings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type', 50)->unique();
            $table->string('mode', 50)->default('reporting_senior');
            $table->json('fixed_role_ids')->nullable();
            $table->unsignedBigInteger('fixed_user_id')->nullable();
            $table->json('fallback_role_ids')->nullable();
            $table->unsignedBigInteger('fallback_user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_routing_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('workflow_type', 50);
            $table->string('source_type', 30);
            $table->unsignedBigInteger('source_user_id')->nullable();
            $table->unsignedBigInteger('source_role_id')->nullable();
            $table->unsignedBigInteger('source_team_user_id')->nullable();
            $table->string('verifier_type', 30);
            $table->unsignedBigInteger('verifier_user_id')->nullable();
            $table->json('verifier_role_ids')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('type')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('telecaller_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('task_type')->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('first_reminder_sent_at')->nullable();
            $table->dateTime('final_reminder_sent_at')->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('closer_status')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->string('property_name')->nullable();
            $table->string('property_address')->nullable();
            $table->string('budget_range')->nullable();
            $table->text('visit_notes')->nullable();
            $table->string('project')->nullable();
            $table->string('property_type')->nullable();
            $table->string('lead_type')->nullable();
            $table->decimal('incentive_amount', 12, 2)->nullable();
            $table->text('closer_review_remark')->nullable();
            $table->text('closer_rejection_reason')->nullable();
            $table->text('closing_rejection_reason')->nullable();
            $table->timestamp('closer_submitted_at')->nullable();
            $table->unsignedBigInteger('closer_submitted_by')->nullable();
            $table->date('actual_closer_date')->nullable();
            $table->text('actual_closer_backdate_reason')->nullable();
            $table->timestamp('actual_closer_date_approved_at')->nullable();
            $table->unsignedBigInteger('actual_closer_date_approved_by')->nullable();
            $table->timestamp('closer_reviewed_at')->nullable();
            $table->unsignedBigInteger('closer_reviewed_by')->nullable();
            $table->string('finance_handover_status')->nullable();
            $table->timestamp('finance_transferred_at')->nullable();
            $table->unsignedBigInteger('finance_transferred_by')->nullable();
            $table->timestamp('finance_reviewed_at')->nullable();
            $table->unsignedBigInteger('finance_reviewed_by')->nullable();
            $table->unsignedInteger('closer_resubmission_count')->default(0);
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_last_corrected_at')->nullable();
            $table->date('customer_dob')->nullable();
            $table->string('nominee_name')->nullable();
            $table->string('second_customer_name')->nullable();
            $table->text('pan_card')->nullable();
            $table->text('aadhaar_card_no')->nullable();
            $table->json('kyc_documents')->nullable();
            $table->json('closer_request_proof_photos')->nullable();
            $table->json('primary_applicant_details')->nullable();
            $table->json('joint_applicant_details')->nullable();
            $table->json('unit_details')->nullable();
            $table->unsignedBigInteger('kyc_dynamic_form_id')->nullable();
            $table->string('booking_form_version')->nullable();
            $table->string('booking_lifecycle_status')->nullable();
            $table->json('booking_payment_proofs')->nullable();
            $table->json('booking_activity_log')->nullable();
            $table->json('booking_document_reviews')->nullable();
            $table->json('kyc_custom_fields')->nullable();
            $table->json('kyc_section_remarks')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->timestamp('converted_to_closer_at')->nullable();
            $table->unsignedBigInteger('closer_verified_by')->nullable();
            $table->timestamp('closer_verified_at')->nullable();
            $table->unsignedBigInteger('closing_verified_by')->nullable();
            $table->timestamp('closing_verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('queue_hidden_at')->nullable();
            $table->string('queue_hidden_reason')->nullable();
            $table->softDeletes();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending_finance_manager');
            $table->unsignedBigInteger('sales_head_verified_by')->nullable();
            $table->timestamp('sales_head_verified_at')->nullable();
            $table->unsignedBigInteger('crm_verified_by')->nullable();
            $table->timestamp('crm_verified_at')->nullable();
            $table->unsignedBigInteger('finance_manager_verified_by')->nullable();
            $table->timestamp('finance_manager_verified_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    private function validKycPayload(array $overrides = []): array
    {
        return array_merge([
            'actual_closer_date' => '2026-04-19',
            'customer_name' => 'Pipeline Lead',
            'nominee_name' => 'Nominee',
            'customer_dob' => '1991-02-10',
            'pan_card' => 'ABCDE1234F',
            'aadhaar_card_no' => '123412341234',
            'primary_applicant_name' => 'Pipeline Lead',
            'primary_applicant_relation_name' => 'Parent Name',
            'primary_applicant_date_of_birth' => '1991-02-10',
            'primary_applicant_nationality' => 'Indian',
            'primary_applicant_pan_no' => 'ABCDE1234F',
            'primary_applicant_aadhaar_no' => '123412341234',
            'primary_applicant_address' => 'Test address',
            'primary_applicant_communication_address' => 'Test address',
            'primary_applicant_city' => 'Indore',
            'primary_applicant_state' => 'Madhya Pradesh',
            'primary_applicant_pin' => '452001',
            'primary_applicant_email' => 'customer@example.test',
            'primary_applicant_mobile_no' => '9999999999',
            'booking_type' => 'Plot',
            'booking_project_name' => 'Closer Project',
            'booking_date' => '2026-04-19',
            'booking_deal_type' => 'Fresh',
            'unit_details_unit_no' => 'A-101',
            'unit_details_super_area_sq_ft' => '1000',
            'unit_details_basic_sale_price' => '5000',
        ], $overrides);
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucwords(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => $role->name . ' User',
            'email' => uniqid($role->slug . '-', true) . '@example.test',
            'password' => 'secret',
            'role_id' => $role->id,
            'is_active' => true,
        ], $overrides));
    }

    private function createLead(array $overrides = []): Lead
    {
        return Lead::create(array_merge([
            'name' => 'Pipeline Lead',
            'phone' => '9999999999',
            'email' => 'lead@example.test',
            'source' => 'manual',
            'status' => 'new',
        ], $overrides));
    }

    private function createVerifiedVisit(Lead $lead, User $owner): SiteVisit
    {
        DB::table('lead_assignments')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $owner->id,
            'assigned_by' => $owner->id,
            'is_active' => true,
            'assigned_at' => Carbon::now()->subHours(3),
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHours(3),
        ]);

        return SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
            'customer_name' => $lead->name,
            'phone' => $lead->phone,
            'project' => 'Closer Project',
            'property_name' => 'Tower A',
            'property_address' => 'Sector 99',
            'budget_range' => '1 Cr - 2 Cr',
            'status' => 'completed',
            'verification_status' => 'verified',
            'completed_at' => Carbon::now()->subHour(),
            'scheduled_at' => Carbon::now()->subHours(2),
            'created_at' => Carbon::now()->subHours(3),
            'updated_at' => Carbon::now()->subHour(),
        ]);
    }
}
