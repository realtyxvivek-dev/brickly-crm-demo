<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\Prospect;
use App\Models\Task;
use App\Models\User;
use App\Services\AsmCnpAutomationService;
use App\Services\LeadOutcomeService;
use App\Services\MetaReviewAutoStageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class LeadOutcomeOwnerRetentionTest extends TestCase
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
    }

    public function test_junk_and_not_interested_outcomes_keep_the_current_owner(): void
    {
        foreach (['junk', 'not_interested'] as $status) {
            $user = User::create([
                'name' => 'Owner ' . $status,
                'email' => $status . '@example.com',
                'password' => 'secret',
            ]);
            $lead = Lead::withoutEvents(fn () => Lead::create([
                'name' => 'Customer ' . $status,
                'phone' => $status === 'junk' ? '9000000001' : '9000000002',
                'status' => 'connected',
                'status_auto_update_enabled' => true,
                'next_followup_at' => now()->addDay(),
            ]));
            $assignment = LeadAssignment::create([
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'assigned_by' => $user->id,
                'assigned_at' => now(),
                'is_active' => true,
            ]);
            $task = Task::withoutEvents(fn () => Task::create([
                'lead_id' => $lead->id,
                'assigned_to' => $user->id,
                'type' => 'phone_call',
                'title' => 'Call customer',
                'status' => 'pending',
                'created_by' => $user->id,
            ]));
            $prospect = Prospect::create([
                'lead_id' => $lead->id,
                'customer_name' => $lead->name,
                'verification_status' => 'pending',
            ]);

            $cnpAutomation = Mockery::mock(AsmCnpAutomationService::class);
            $cnpAutomation->shouldReceive('cancelLeadAutomation')->once();
            $metaStage = Mockery::mock(MetaReviewAutoStageService::class);
            $metaStage->shouldReceive('applyForOutcome')->once()->with(Mockery::type(Lead::class), $status);
            $this->app->instance(MetaReviewAutoStageService::class, $metaStage);

            Lead::withoutEvents(function () use ($cnpAutomation, $task, $lead, $prospect, $user, $status): void {
                (new LeadOutcomeService($cnpAutomation))->markAsOtherLead(
                    $task,
                    $lead,
                    $prospect,
                    $user,
                    $status,
                    'Outcome remark'
                );
            });

            $this->assertSame($status, $lead->fresh()->status);
            $this->assertFalse($lead->fresh()->status_auto_update_enabled);
            $this->assertNull($lead->fresh()->next_followup_at);
            $this->assertTrue($assignment->fresh()->is_active);
            $this->assertNull($assignment->fresh()->unassigned_at);
            $this->assertSame('completed', $task->fresh()->status);
        }
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('new');
            $table->text('notes')->nullable();
            $table->boolean('is_dead')->default(false);
            $table->boolean('status_auto_update_enabled')->default(true);
            $table->timestamp('next_followup_at')->nullable();
            $table->unsignedBigInteger('other_lead_marked_by')->nullable();
            $table->timestamp('other_lead_marked_at')->nullable();
            $table->text('other_lead_reason')->nullable();
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

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id');
            $table->unsignedBigInteger('assigned_to');
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('outcome')->nullable();
            $table->text('outcome_remark')->nullable();
            $table->timestamp('outcome_recorded_at')->nullable();
            $table->timestamp('next_action_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prospects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('verification_status')->nullable();
            $table->string('lead_status')->nullable();
            $table->text('manager_remark')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->timestamps();
        });
    }
}
