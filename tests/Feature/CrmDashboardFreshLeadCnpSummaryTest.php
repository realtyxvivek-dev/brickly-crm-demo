<?php

namespace Tests\Feature;

use App\Models\AsmCnpAutomationAudit;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmDashboardFreshLeadCnpSummaryTest extends TestCase
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

        $this->createSchema();
        Carbon::setTestNow(Carbon::parse('2026-03-31 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_returns_user_wise_fresh_lead_cnp_totals_and_latest_lead_state(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $salesExecutiveRole = $this->createRole(Role::SALES_EXECUTIVE);
        $executiveOne = $this->createUser($salesExecutiveRole, 'Exec One');
        $executiveTwo = $this->createUser($salesExecutiveRole, 'Exec Two');

        $leadOne = $this->createLead('Lead One', '9999991111');
        $leadTwo = $this->createLead('Lead Two', '9999992222');

        $this->assignLead($leadOne, $executiveOne->id, Carbon::parse('2026-03-31 09:00:00'));
        $oldAssignment = $this->assignLead($leadTwo, $executiveOne->id, Carbon::parse('2026-03-31 09:15:00'));
        $oldAssignment->update(['is_active' => false]);
        $this->assignLead($leadTwo, $executiveTwo->id, Carbon::parse('2026-03-31 11:00:00'));

        $this->createAudit($leadOne->id, $executiveOne->id, 1, Carbon::parse('2026-03-31 09:30:00'));
        $this->createAudit($leadOne->id, $executiveOne->id, 2, Carbon::parse('2026-03-31 10:00:00'));
        $this->createAudit($leadTwo->id, $executiveOne->id, 4, Carbon::parse('2026-03-31 11:30:00'));

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/fresh-lead-cnp-summary?date_range=all_time');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_name', 'Exec One');
        $response->assertJsonPath('data.0.cnp_total', 3);
        $response->assertJsonPath('data.0.leads.0.lead_name', 'Lead Two');
        $response->assertJsonPath('data.0.leads.0.cnp_number', 4);
        $response->assertJsonPath('data.0.leads.0.current_owner_name', 'Exec Two');
        $response->assertJsonPath('data.0.leads.0.cnp_marked_by_name', 'Exec One');
        $response->assertJsonPath('data.0.leads.1.lead_name', 'Lead One');
        $response->assertJsonPath('data.0.leads.1.cnp_number', 2);
    }

    public function test_it_applies_date_and_role_filters_to_fresh_lead_cnp_summary(): void
    {
        $crm = $this->createUser($this->createRole(Role::CRM), 'CRM User');
        $executive = $this->createUser($this->createRole(Role::SALES_EXECUTIVE), 'Exec User');
        $manager = $this->createUser($this->createRole(Role::SALES_MANAGER), 'Manager User');

        $leadOne = $this->createLead('Scoped Lead', '9999993333');
        $leadTwo = $this->createLead('Manager Lead', '9999994444');

        $this->assignLead($leadOne, $executive->id, Carbon::parse('2026-03-29 09:00:00'));
        $this->assignLead($leadTwo, $manager->id, Carbon::parse('2026-03-29 09:30:00'));

        $this->createAudit($leadOne->id, $executive->id, 1, Carbon::parse('2026-03-29 10:00:00'));
        $this->createAudit($leadOne->id, $executive->id, 2, Carbon::parse('2026-03-31 10:00:00'));
        $this->createAudit($leadTwo->id, $manager->id, 1, Carbon::parse('2026-03-31 11:00:00'));

        Sanctum::actingAs($crm);

        $response = $this->getJson('/api/crm/dashboard/fresh-lead-cnp-summary?date_range=custom&start_date=2026-03-31&end_date=2026-03-31&role_slug=sales_executive');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.user_name', 'Exec User');
        $response->assertJsonPath('data.0.cnp_total', 1);
        $response->assertJsonPath('data.0.leads.0.lead_name', 'Scoped Lead');
        $response->assertJsonMissing(['user_name' => 'Manager User']);
    }

    private function createSchema(): void
    {
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
            $table->string('status')->default('new');
            $table->string('source')->nullable();
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->unsignedBigInteger('assigned_by')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('assignment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('unassigned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('asm_cnp_automation_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('state_id')->nullable();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('config_id')->nullable();
            $table->unsignedBigInteger('from_user_id')->nullable();
            $table->unsignedBigInteger('to_user_id')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedInteger('cnp_count')->default(0);
            $table->string('action');
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });
    }

    private function createRole(string $slug): Role
    {
        return Role::create([
            'name' => ucfirst(str_replace('_', ' ', $slug)),
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function createUser(Role $role, string $name): User
    {
        static $counter = 1;

        return User::create([
            'name' => $name,
            'email' => 'crm-cnp-' . $counter++ . '@example.test',
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'manager_id' => in_array($role->slug, [Role::SALES_MANAGER, Role::SENIOR_MANAGER], true) ? 77 : null,
            'is_active' => true,
        ]);
    }

    private function createLead(string $name, string $phone): Lead
    {
        return Lead::create([
            'name' => $name,
            'phone' => $phone,
            'status' => 'new',
            'source' => 'meta',
        ]);
    }

    private function assignLead(Lead $lead, int $userId, Carbon $assignedAt): LeadAssignment
    {
        return LeadAssignment::create([
            'lead_id' => $lead->id,
            'assigned_to' => $userId,
            'assigned_at' => $assignedAt,
            'is_active' => true,
        ]);
    }

    private function createAudit(int $leadId, int $fromUserId, int $cnpCount, Carbon $actedAt): AsmCnpAutomationAudit
    {
        return AsmCnpAutomationAudit::create([
            'lead_id' => $leadId,
            'from_user_id' => $fromUserId,
            'cnp_count' => $cnpCount,
            'action' => 'retry_created',
            'message' => 'Auto retry task created after CNP #' . $cnpCount,
            'acted_at' => $actedAt,
            'created_at' => $actedAt,
            'updated_at' => $actedAt,
        ]);
    }
}
