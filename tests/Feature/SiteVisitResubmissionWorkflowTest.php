<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use App\Services\AsmCnpAutomationService;
use App\Services\NotificationService;
use App\Services\SiteVisitRescheduleService;
use App\Services\WhatsAppAutomationTriggerService;
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

class SiteVisitResubmissionWorkflowTest extends TestCase
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
        Carbon::setTestNow(Carbon::parse('2026-04-27 16:10:00'));

        $notificationService = Mockery::mock(NotificationService::class);
        $notificationService->shouldIgnoreMissing();
        $this->app->instance(NotificationService::class, $notificationService);

        $asmService = Mockery::mock(AsmCnpAutomationService::class);
        $asmService->shouldIgnoreMissing();
        $this->app->instance(AsmCnpAutomationService::class, $asmService);

        $rescheduleService = Mockery::mock(SiteVisitRescheduleService::class);
        $rescheduleService->shouldIgnoreMissing();
        $this->app->instance(SiteVisitRescheduleService::class, $rescheduleService);

        $whatsAppTriggerService = Mockery::mock(WhatsAppAutomationTriggerService::class);
        $whatsAppTriggerService->shouldIgnoreMissing();
        $this->app->instance(WhatsAppAutomationTriggerService::class, $whatsAppTriggerService);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rejected_site_visit_can_repeat_resubmit_reject_and_verify_cycle(): void
    {
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER), [
            'name' => 'Senior Manager',
            'email' => 'senior-resubmit@example.test',
        ]);
        $crm = $this->createUser($this->createRole(Role::CRM), [
            'name' => 'CRM Reviewer',
            'email' => 'crm-resubmit@example.test',
        ]);
        $lead = $this->createLead([
            'name' => 'Rejected Visit Lead',
            'created_by' => $seniorManager->id,
            'status' => 'visit_done',
        ]);

        $siteVisit = $this->createRejectedVisit($lead, $seniorManager);

        Sanctum::actingAs($seniorManager);

        $firstResubmit = $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/resubmit", [
            'customer_name' => 'Rejected Visit Lead',
            'phone' => '9999999999',
            'employee' => 'Senior Manager',
            'occupation' => 'Business',
            'date_of_visit' => '2026-04-26',
            'project' => 'Updated Project',
            'property_name' => 'Tower B',
            'property_address' => 'Sector 120',
            'budget_range' => '1 Cr - 2 Cr',
            'team_leader' => 'Akash',
            'property_type' => 'Flat',
            'payment_mode' => 'Loan',
            'tentative_period' => 'Within 3 Months',
            'lead_type' => 'New Visit',
            'visit_sequence' => '2nd_visit',
            'scheduled_at' => '2026-04-26 13:30:00',
            'visit_notes' => 'Corrected remarks for first resubmission',
            'feedback' => 'Customer interested after clarification',
            'rating' => 4,
            'existing_completion_proof_photos' => ['site-visits/proof/original-proof.jpg'],
            'proof_photos' => [
                $this->fakeImageUpload('new-proof.jpg'),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $firstResubmit->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.resubmission_count', 1);

        $siteVisit->refresh();
        $this->assertSame('pending', $siteVisit->verification_status);
        $this->assertNull($siteVisit->rejection_reason);
        $this->assertSame('Updated Project', $siteVisit->project);
        $this->assertCount(2, $siteVisit->completion_proof_photos ?? []);
        $this->assertNotNull($siteVisit->resubmitted_at);

        Sanctum::actingAs($crm);

        $this->postJson("/api/crm/site-visits/{$siteVisit->id}/reject", [
            'reason' => 'Need clearer customer confirmation',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'rejected');

        $siteVisit->refresh();
        $this->assertSame('rejected', $siteVisit->verification_status);
        $this->assertSame('Need clearer customer confirmation', $siteVisit->rejection_reason);
        $this->assertNotNull($siteVisit->latest_rejected_at);

        Sanctum::actingAs($seniorManager);

        $secondResubmit = $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/resubmit", [
            'customer_name' => 'Rejected Visit Lead',
            'phone' => '9999999999',
            'employee' => 'Senior Manager',
            'occupation' => 'Business',
            'date_of_visit' => '2026-04-26',
            'project' => 'Updated Project',
            'property_name' => 'Tower B',
            'property_address' => 'Sector 120',
            'budget_range' => '1 Cr - 2 Cr',
            'team_leader' => 'Akash',
            'property_type' => 'Flat',
            'payment_mode' => 'Loan',
            'tentative_period' => 'Within 3 Months',
            'lead_type' => 'New Visit',
            'visit_sequence' => '2nd_visit',
            'scheduled_at' => '2026-04-26 13:30:00',
            'visit_notes' => 'Second corrected submission',
            'feedback' => 'All objections handled',
            'rating' => 5,
            'existing_completion_proof_photos' => $siteVisit->completion_proof_photos,
        ], [
            'Accept' => 'application/json',
        ]);

        $secondResubmit->assertOk()
            ->assertJsonPath('data.verification_status', 'pending')
            ->assertJsonPath('data.resubmission_count', 2);

        Sanctum::actingAs($crm);

        $this->postJson("/api/crm/site-visits/{$siteVisit->id}/verify", [
            'notes' => 'Verified after second resubmission',
        ])->assertOk()
            ->assertJsonPath('data.verification_status', 'verified');

        $siteVisit->refresh();
        $this->assertSame('verified', $siteVisit->verification_status);
        $this->assertNull($siteVisit->rejection_reason);
        $this->assertSame(2, (int) $siteVisit->resubmission_count);
    }

    public function test_resubmit_rejects_when_all_proof_photos_are_removed(): void
    {
        $seniorManager = $this->createUser($this->createRole(Role::SENIOR_MANAGER));
        $lead = $this->createLead(['created_by' => $seniorManager->id]);
        $siteVisit = $this->createRejectedVisit($lead, $seniorManager);

        Sanctum::actingAs($seniorManager);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/resubmit", [
            'customer_name' => 'Rejected Visit Lead',
            'phone' => '9999999999',
            'project' => 'Updated Project',
            'budget_range' => '1 Cr - 2 Cr',
            'team_leader' => 'Akash',
            'property_type' => 'Flat',
            'payment_mode' => 'Loan',
            'tentative_period' => 'Within 3 Months',
            'lead_type' => 'New Visit',
            'scheduled_at' => '2026-04-26 13:30:00',
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(422)
            ->assertJsonPath('errors.proof_photos.0', 'At least one proof photo is required before resubmitting.');
    }

    public function test_unauthorized_user_cannot_resubmit_other_users_rejected_visit(): void
    {
        $owner = $this->createUser($this->createRole(Role::SENIOR_MANAGER), ['email' => 'owner@example.test']);
        $otherUser = $this->createUser($this->createRole(Role::SENIOR_MANAGER), ['email' => 'other@example.test']);
        $lead = $this->createLead(['created_by' => $owner->id]);
        $siteVisit = $this->createRejectedVisit($lead, $owner);

        Sanctum::actingAs($otherUser);

        $this->post("/api/sales-manager/site-visits/{$siteVisit->id}/resubmit", [
            'customer_name' => 'Rejected Visit Lead',
            'phone' => '9999999999',
            'project' => 'Updated Project',
            'budget_range' => '1 Cr - 2 Cr',
            'team_leader' => 'Akash',
            'property_type' => 'Flat',
            'payment_mode' => 'Loan',
            'tentative_period' => 'Within 3 Months',
            'lead_type' => 'New Visit',
            'scheduled_at' => '2026-04-26 13:30:00',
            'existing_completion_proof_photos' => ['site-visits/proof/original-proof.jpg'],
        ], [
            'Accept' => 'application/json',
        ])->assertStatus(403);
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

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('telecaller_id')->nullable();
            $table->timestamps();
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('employee')->nullable();
            $table->string('occupation')->nullable();
            $table->date('date_of_visit')->nullable();
            $table->string('project')->nullable();
            $table->string('budget_range')->nullable();
            $table->string('team_leader')->nullable();
            $table->string('property_type')->nullable();
            $table->string('payment_mode')->nullable();
            $table->string('tentative_period')->nullable();
            $table->string('lead_type')->nullable();
            $table->string('visit_sequence')->nullable();
            $table->json('photos')->nullable();
            $table->json('completion_proof_photos')->nullable();
            $table->string('property_name')->nullable();
            $table->text('property_address')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->nullable();
            $table->string('verification_status')->default('pending');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('resubmission_count')->default(0);
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamp('latest_rejected_at')->nullable();
            $table->text('visit_notes')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('closer_status')->nullable();
            $table->string('closing_verification_status')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->timestamp('first_reminder_sent_at')->nullable();
            $table->timestamp('final_reminder_sent_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_visit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'is_active' => true,
            ]
        );
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
            'name' => 'Rejected Visit Lead',
            'phone' => '9999999999',
            'email' => 'rejected-visit@example.test',
            'source' => 'manual',
            'status' => 'new',
        ], $overrides));
    }

    private function createRejectedVisit(Lead $lead, User $owner): SiteVisit
    {
        DB::table('lead_assignments')->insert([
            'lead_id' => $lead->id,
            'assigned_to' => $owner->id,
            'assigned_by' => $owner->id,
            'is_active' => true,
            'assigned_at' => Carbon::now()->subHours(5),
            'created_at' => Carbon::now()->subHours(5),
            'updated_at' => Carbon::now()->subHours(5),
        ]);

        return SiteVisit::create([
            'lead_id' => $lead->id,
            'created_by' => $owner->id,
            'assigned_to' => $owner->id,
            'customer_name' => $lead->name,
            'phone' => $lead->phone,
            'employee' => $owner->name,
            'occupation' => 'Service',
            'date_of_visit' => '2026-04-26',
            'project' => 'Original Project',
            'property_name' => 'Tower A',
            'property_address' => 'Sector 119',
            'budget_range' => '1 Cr - 2 Cr',
            'team_leader' => 'Akash',
            'property_type' => 'Flat',
            'payment_mode' => 'Self Fund',
            'tentative_period' => 'Within 3 Months',
            'lead_type' => 'New Visit',
            'scheduled_at' => Carbon::parse('2026-04-26 13:30:00'),
            'completed_at' => Carbon::parse('2026-04-26 15:00:00'),
            'status' => 'completed',
            'verification_status' => 'rejected',
            'verified_by' => $owner->id,
            'verified_at' => Carbon::parse('2026-04-26 18:00:00'),
            'rejection_reason' => 'Need corrected details',
            'latest_rejected_at' => Carbon::parse('2026-04-26 18:00:00'),
            'visit_notes' => 'Original rejected notes',
            'feedback' => 'Initial feedback',
            'rating' => 3,
            'completion_proof_photos' => ['site-visits/proof/original-proof.jpg'],
            'created_at' => Carbon::now()->subDays(1),
            'updated_at' => Carbon::now()->subHours(2),
        ]);
    }

    private function fakeImageUpload(string $name): UploadedFile
    {
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9l8wAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $pngBytes);
    }
}
